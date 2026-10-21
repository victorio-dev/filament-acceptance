# victorio/filament-acceptance

Sealed, tamper-evident document versions and provable accept or decline decisions for Filament,
on top of [victorio/filament-public-links](https://github.com/victorio-dev/filament-public-links).

> **Status: in development, not released**
> ([docs/design.md](https://github.com/victorio-dev/filament-acceptance/blob/main/docs/design.md)).

- Requires PHP `^8.3`, Laravel `^12.0|^13.0`, Filament `^4.0|^5.0`
- Free and open source (MIT), part of the Victorio suite of Filament plugins
- Depends on `victorio/support` and `victorio/filament-public-links`

A decision recorded by this package is a simple electronic signature under eIDAS, not an advanced
or qualified electronic signature.

## Quick start — no code to write

Send any record for approval from Filament: the customer opens a link without logging in,
reads the record's fields and accepts or declines. Three steps, no handler, no view:

**1. The model** says which fields the customer sees, with a label per language:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Victorio\Acceptance\Approval\ApprovalField;
use Victorio\Acceptance\Concerns\HasSealedVersions;

class Offer extends Model
{
    use HasSealedVersions;
    use SoftDeletes;   // recommended: a sealed record can be soft deleted, never force deleted

    public function approvalTitle(): ?string
    {
        return $this->title;
    }

    public function approvalFields(): array
    {
        return [
            ApprovalField::make('body')->multiline()->label(['en' => 'Offer', 'hu' => 'Ajánlat']),
            ApprovalField::make('price_eur')->integer()->label(['en' => 'Price (EUR)', 'hu' => 'Ár (EUR)']),
            ApprovalField::make('valid_until')->date()->label(['en' => 'Valid until', 'hu' => 'Érvényes']),
        ];
    }
}
```

Or keep the model untouched and say it on the panel plugin, which wins over the model:

```php
use Victorio\Acceptance\AcceptancePlugin;
use Victorio\Acceptance\Approval\ApprovalField;

$panel->plugins([
    AcceptancePlugin::make()->approvable(Offer::class, title: 'title', fields: [
        ApprovalField::make('body')->multiline()->label(['en' => 'Offer', 'hu' => 'Ajánlat']),
    ]),
]);
```

**2. The resource** gets the decisions table, which has a "Send for approval" button:

```php
use Victorio\Acceptance\Filament\RelationManagers\DecisionsRelationManager;

public static function getRelations(): array
{
    return [DecisionsRelationManager::class];
}
```

`SendForApprovalAction::make()` also works on its own, as a page or table row action.

**3. Send it.** The action asks for the expiry and the language, seals the record's fields in
that language and shows the link (no usage limit) to copy. The customer's page, the consent text
and the error messages are in that language, whatever their browser says; sending in another
language seals a new version. The table shows each version's status (awaiting decision,
accepted, declined, superseded), who decided when, the comment or reason, the fingerprint, the
stored document, a verify button and the activity. Seeing it needs `view` on the record, sending
needs `update` — the record's own policy, as for public links.

Values are text, multi-line text, integers, booleans or dates; a float is refused (sealed content
holds no floats — store money in minor units, or as a decimal string with `text()`).

Need more than fields — your own layout, a status change on acceptance? Write a handler
([below](#the-public-decision-page)).

## Sealed versions

```php
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Concerns\HasSealedVersions;

class Quote extends Model
{
    use HasSealedVersions;
}

$version = $quote->seal(
    kind: 'quote',
    content: ['number' => 'Q-2026-0042', 'total' => 2_057_400],   // public, hashed
    internal: ['cost' => 1_200_000],                              // admin only, encrypted
    by: auth()->user(),
);

$version->number;           // 1, 2, 3 … per quote; sealing a new one supersedes the previous
$version->content_hash;     // SHA-256 of the stored canonical JSON
$version->verify();         // true while the stored content is exactly what was sealed
$quote->sealedVersions;     // newest first
$quote->latestSealedVersion();
```

Every seal, decision and stored evidence file also lands in the document's timeline
(`$quote->auditEntries`); add your own events with `AuditLog::record($quote, 'sent', ...)`.
No personal data goes into it, and the IP only as the access log's HMAC.

Content must be integers, strings, booleans, null and arrays: a float anywhere throws
`NotCanonicalizable`, so money has to be in integer minor units. With Filament tenancy, a version
stores the current tenant (or `seal(tenant: $team)` in a queued job), and `sealedVersions` and
`SealedVersion::query()->forCurrentTenant()` show only that tenant's versions.

### The internal part and the app key

The internal part is encrypted with your `APP_KEY` and is not part of the hash. Rotating the key
without keeping the old one in `APP_PREVIOUS_KEYS` makes the internal part unreadable
(`internal()` throws `InternalPartUnreadable`), but it does not affect the content, its hash or
`verify()`.

### What "immutable" covers

Through Eloquent, a sealed version or a decision cannot be changed or deleted: `save()`, `update()`,
`increment()`, `delete()`, `forceDelete()`, `destroy()` and their quiet variants throw, and so do
`update()`, `upsert()`, `increment()`, `delete()`, `truncate()` and friends on
`SealedVersion::query()`, `Decision::query()` and their relations. Neither is ever pruned.

What no PHP code can stop: `DB::table()`, `->toBase()`, raw SQL and anyone with direct database
access. The package makes such changes **detectable** — `verify()` returns false after a single
changed byte — not impossible. To make them impossible, revoke `UPDATE` and `DELETE` on the
`victorio_sealed_versions` and `victorio_decisions` tables from the application's database user.

## Decisions

```php
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Facades\Decisions;

$decision = Decisions::record(
    $version,
    DecisionInput::accept(name: 'Anna Kovács', email: 'anna@example.test', consentText: $checkboxLabel),
    request: $request,                                    // hashed IP and user agent
    transition: fn (): bool => $quote->markAccepted(),    // your status change, same transaction
);

DecisionInput::decline(reason: 'Too expensive');          // name, e-mail and reason optional
$decision->verify();     // the version is intact and is the one that was decided on
```

A version has at most one decision: a second accept, or an accept racing a decline, throws
`DecisionAlreadyMade` and writes nothing (a unique index backs this up). A superseded version
(`VersionSuperseded`), a tampered one (`ContentHashMismatch`) or your transition returning false
(`DecisionRefused`) refuse the decision, and everything rolls back. `DecisionAccepted` and
`DecisionDeclined` fire after the commit.

Decisions are business records with personal data (name, e-mail) — your app is responsible for
them. The IP is never stored: the decision keeps the same HMAC that
[filament-public-links](https://github.com/victorio-dev/filament-public-links) writes into its
access log, plus the id of that request's access row, so the two can be matched. Decisions are
never pruned.

### Evidence files

```php
$decision->storeEvidence($pdfBytes, 'Q-2026-0042-v2.pdf');   // once, on evidence.disk, with its SHA-256
$decision->evidenceContents();                               // the bytes, after checking the hash
```

Use a private disk. The package never deletes an evidence file.

## The public decision page

Send a sealed version through a [public link](https://github.com/victorio-dev/filament-public-links)
and let the customer accept or decline it, without a login. You render your content; the
package does the rest — forms, validation, the confirmation, the superseded and closed states:

```php
use Illuminate\Contracts\Support\Htmlable;
use Victorio\Acceptance\Http\DecisionLinkHandler;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\PublicLinks\Data\PublicLinkVisit;
use Victorio\PublicLinks\Facades\PublicLinks;

final class QuoteDecision extends DecisionLinkHandler
{
    protected function renderContent(SealedVersion $version, PublicLinkVisit $visit): Htmlable
    {
        return view('quotes.public', ['quote' => $version->content()]);   // escape with {{ }}
    }

    protected function title(SealedVersion $version): string
    {
        return 'Quote '.$version->content()['number'];
    }

    // Optional: isActionable(), consentText(), locale(), timezone(), transition()
}

// In a service provider's boot():
PublicLinks::handle(SealedVersion::class, 'quote', QuoteDecision::class, 'Quote');

// Send it:
$url = (new PendingPublicLink($version, 'quote'))->expiresIn(days: 30)->issue()->url;
```

- The page shows your content, the version's fingerprint and an accept form (name, e-mail,
  consent checkbox, comment) and a decline form (optional name, e-mail, reason). After the
  decision the link shows a read-only confirmation, with a download of the evidence file if you
  stored one.
- **Issue decision links without a usage limit.** A recorded decision spends one use; with
  `maxUses(1)` the confirmation page would answer 410. Two decisions are impossible anyway.
- The consent text the customer saw is exactly what is stored with the acceptance; if it changes
  while the page is open, the customer is asked to confirm again.
- A superseded version explains that a newer one was prepared, with your contact address
  (public-links' `branding.contact_email`).
- Eight languages (below); labels, error messages tied to their fields, 44 px buttons, no
  JavaScript, right-to-left languages supported. Restyle through public-links' branding; the views are publishable
  (`filament-acceptance-views`) as a last resort.

## Languages

The pages ship in English and Hungarian, checked by hand, and in German, Spanish, French,
Italian, Dutch and Brazilian Portuguese, machine-assisted — corrections are welcome. They are
shown in the link's language (`->locale()` when issuing it), else the visitor's browser
language, else your app's; `locale()` on your handler overrides it.

> **Have the consent text and the legal note reviewed in your own language.** The checkbox
> label ("I accept this document and its terms.") and the note that the confirmation is a
> simple electronic declaration, not a qualified electronic signature, are legal in nature. The
> machine-assisted translations are a starting point, not legal wording: override
> `consentText()` or publish the translations
> (`php artisan vendor:publish --tag="filament-acceptance-translations"`) and have them checked.

## Retention and erasure

What the package deletes on its own — only once you schedule `model:prune` (rule of thumb:
daily, `Schedule::command('model:prune', ['--model' => [AuditEntry::class]])->daily()`):

| | Deleted automatically? |
| --- | --- |
| Sealed versions, decisions, evidence files | **Never** |
| Audit entries of a decided version, or of a version that can still be decided | **Never** |
| Erasure records (see below) | **Never** |
| Other audit entries | After `audit.prune_after_days` (90 days; `null` keeps them forever) |

A model with sealed versions cannot be deleted for good: a force delete (or, without
`SoftDeletes`, any delete) throws `EvidenceIsPermanent`. Use `SoftDeletes` on your subjects; a
soft delete works as usual. Mass deletes that bypass model events (`Quote::query()->delete()`,
a `MassPrunable` subject) cannot be stopped, but the sealed versions survive them.

**Why keep it?** Proof of what was accepted is the point of this package. Under the GDPR, the
right to erasure does not apply where processing is necessary for the establishment, exercise or
defence of legal claims (Article 17(3)(e)); keeping acceptance evidence may rest on that. Whether
it does in your case, and for how long, is your decision — **this is not legal advice**.

**If your assessment requires erasure**, there is one deliberate, logged way; nothing in the
package ever calls it:

```php
use Victorio\Acceptance\Facades\Erasure;

// Removes name, e-mail, comment, reason, user agent and IP hash from the decisions,
// and the IP hashes from the audit log. Hashes, sealed content and evidence files stay,
// so verify() still holds.
Erasure::anonymize($quote, reason: 'GDPR request #123', by: auth()->user());

// Removes every sealed version, decision, evidence file and audit entry of the quote.
Erasure::purge($quote, reason: 'GDPR request #123', by: auth()->user());
```

```bash
php artisan filament-acceptance:erase "App\Models\Quote" 42 --reason="GDPR request #123" [--purge]
```

> **`anonymize` does not touch the sealed content.** If the snapshot itself holds personal data
> — the customer's name and address in a quote — that data stays, and so does the evidence file
> (a PDF of the quote). Only `purge` removes it.

Both need a reason, write an `erasure` record into the audit log (mode, reason, counts; for a
purge also the removed versions' numbers and hashes, never their content) and log a notice with
ids only. The command works by type and id, so it also reaches the evidence of a quote that no
longer exists.

### The canonical form

Anyone can re-check a version without this package: `content_hash` is the SHA-256 of the stored
`content`, which is canonical JSON — object keys sorted by UTF-16 code units, lists in order, no
whitespace, integers within ±(2^53 − 1), no floats, strings escaping only `"`, `\` and control
characters (`\b \t \n \f \r`, others as `\u00xx`), everything else literal UTF-8. For such data
this is exactly [RFC 8785](https://www.rfc-editor.org/rfc/rfc8785) (JCS), so any JCS
implementation reproduces the bytes. Strings are not Unicode-normalized.

## License

MIT — see [LICENSE.md](https://github.com/victorio-dev/filament-acceptance/blob/main/LICENSE.md). Please report vulnerabilities privately, as described in
[SECURITY.md](https://github.com/victorio-dev/filament-acceptance/blob/main/SECURITY.md).
