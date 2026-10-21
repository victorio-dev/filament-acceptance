# filament-acceptance — design

Status: **approved** 2026-10-21; steps 1-5 done (sealed versions; decisions and evidence files;
audit log and retention; public decision pages; Filament and approval without code). The short version lives in [../CLAUDE.md](../CLAUDE.md); this
file is the detailed plan. No code exists yet. Decided in [ADR-0010](../../docs/decisions.md).

## 0. Purpose and boundaries

The common layer of the paid packages that ask someone outside the panel to agree to a document:
`filament-quotes` (Árajánlat Pro) now, `filament-contracts` (Szerződés Pro) later. It owns:

1. **Sealed versions** — an immutable snapshot of a document's public content, in canonical JSON,
   with its SHA-256 hash.
2. **Decisions** — accept or decline of one sealed version, recorded race-free, bound to the hash
   of exactly that content, with time, HMAC-hashed IP and user agent.
3. **The public decision flow** on top of `filament-public-links`: an abstract link handler with
   the accept and decline actions, and the Blade components for the forms and the confirmation.
4. **An audit log** per document (the "napló"), prunable.
5. **Evidence files** — a file (the PDF of the accepted version) stored once next to the decision,
   with its SHA-256 hash.

It does **not** know what a quote or a contract is: no pricing, no numbering, no statuses, no PDF
rendering, no Filament resource. The consuming package supplies the content, the rendering and
its own status machine; this package guarantees that the content cannot change after sealing and
that a decision provably refers to it.

Free and open source (MIT, public repo, public Packagist), like public-links (ADR-0007, ADR-0010):
both paid packages depend on it, nobody pays twice, and the evidence code is auditable by the
people who rely on it. Dependencies: `support` ← `filament-public-links` ← `filament-acceptance`
← `filament-quotes`, `filament-contracts`.

**No legally binding e-signature is promised** (ADR-0009): a decision is a simple electronic
signature under eIDAS. The README, the default consent text and the form say so.

## a) Data model

Table names from `filament-acceptance.tables.*`, models from `filament-acceptance.models.*` (D5).
Ids that cross a morph are strings (`string(36)`), as in public-links, so int, ULID and UUID
subjects coexist; pgsql refuses `varchar = integer`, `make check-db` covers it (F7).

### `victorio_sealed_versions` (`tables.versions`)

Written once. The model throws `EvidenceIsImmutable` on update and `EvidenceIsPermanent` on
delete; the only later write is `superseded_at`, set once through a query.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `ulid` primary | The public link's `linkable`. |
| `tenant_id` | `string(36)`, nullable | The current Filament tenant at sealing time, or the one passed to `seal(tenant:)` (a queued job has no current tenant). Null with tenancy off. |
| `subject_type`, `subject_id` | `string` | Morph to the document (a quote, a contract). No FK. |
| `kind` | `string(32)` | `quote`, `contract`: which consumer owns it. |
| `number` | `unsignedInteger` | 1, 2, 3 … per subject. `unique(subject_type, subject_id, number)`. |
| `content` | `longText` | Canonical JSON of the public content. A **text** column on purpose: a MySQL `json` column re-orders keys and re-formats numbers, which would change the bytes the hash is taken over. |
| `content_hash` | `char(64)` | `hash('sha256', content)`, hex. |
| `internal` | `longText`, nullable | Encrypted JSON (`Crypt`), in `$hidden`: data only the admin side may see (a quote's costs). Never hashed, never passed to a public view. |
| `sealed_by_type`, `sealed_by_id` | `string`, nullable | |
| `sealed_at` | `timestamp` | |
| `superseded_at` | `timestamp`, nullable | A newer version of the same subject was sealed. |

Indexes: the unique one above, which also serves "versions of this document"; `(tenant_id)`.

### What immutability covers (learned in step 1)

| Path | Result |
| --- | --- |
| model `save()` / `saveQuietly()` of a change, `update()`, `increment()` | `EvidenceIsImmutable` (model event, and the builder for the quiet paths) |
| model `delete()` / `deleteQuietly()` / `forceDelete()`, `SealedVersion::destroy()` | `EvidenceIsPermanent` |
| builder `update`, `upsert`, `touch`, `increment`, `decrement`, `incrementEach`, `decrementEach` — on `SealedVersion::query()` and on `$subject->sealedVersions()` | `EvidenceIsImmutable` |
| builder `delete`, `forceDelete`, `truncate` | `EvidenceIsPermanent` |
| `->toBase()`, `DB::table()`, raw SQL, direct database access | **not prevented**; `verify()` detects every change, database permissions are the only prevention (README) |
| `model:prune` of the versions or of a `Prunable` / `MassPrunable` subject | versions untouched (not prunable, no FK, no cascade) |

Sealer's own write of `superseded_at` goes through `toBase()` on purpose: it is not sealed
content. `verify()` is true only when the SHA-256 matches **and** the stored text is canonical,
so a re-encoded copy with a fixed-up hash fails too.

### `victorio_decisions` (`tables.decisions`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `ulid` primary | |
| `sealed_version_id` | `ulid`, FK → versions, `restrictOnDelete` | **Unique**: one decision per version, enforced by the database. |
| `tenant_id` | `string(36)`, nullable | Copied from the version, so a decision recorded later (a queued job) keeps the tenant. |
| `decision` | `string(16)` | `DecisionKind`: `accepted` or `declined`. |
| `name`, `email` | `string(255)`, nullable | Required to accept, optional to decline. |
| `comment` | `text`, nullable | Optional remark when accepting. |
| `reason` | `text`, nullable | Optional reason when declining — feedback for the seller. |
| `consent_text` | `text`, nullable | The checkbox label exactly as shown (accept only). |
| `locale` | `string(8)` | Language the form was shown in. |
| `content_hash` | `char(64)` | Hash of the version's `content`, recomputed at the moment of the decision and equal to `sealed_versions.content_hash` (otherwise the decision is refused). |
| `ip_hash` | `char(64)`, nullable | `Victorio\PublicLinks\Support\IpHasher::forRequest()`: byte for byte the `ip_hash` of the public-links access row of the same request (same HMAC, same key). Never the raw IP. Null for a decision recorded outside a request. |
| `user_agent` | `string(255)`, nullable | Truncated to `evidence.user_agent_max_length`. |
| `public_link_id` | `string(26)`, nullable | The link used. No FK: links may be pruned, the decision stays. |
| `public_link_access_id` | `unsignedBigInteger`, nullable | The access row of the request (public-links writes it before the handler runs and passes it as `$visit->access`). No FK: access rows are pruned after 90 days; the `ip_hash` still matches afterwards. |
| `evidence_disk`, `evidence_path` | `string`, nullable | The stored evidence file (b, "Evidence files"). |
| `evidence_hash` | `char(64)`, nullable | SHA-256 of that file's bytes. |
| `decided_at` | `timestamp` | |

Written once; the evidence columns are written once more, only while null. A business record,
not a log: personal data (name, e-mail) the host is responsible for, documented in the README.

### `victorio_audit_entries` (`tables.audit`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id()` | Never exposed publicly; orders entries written within the same second. |
| `tenant_id` | `string(36)`, nullable | The version's tenant; otherwise the given or current Filament tenant. |
| `subject_type`, `subject_id` | `string` | |
| `sealed_version_id` | `string(26)`, nullable, indexed | No FK: pruning decides per entry, an erasure removes versions and entries together. |
| `event` | `string(64)` | The package writes `sealed`, `accepted`, `declined`, `evidence_stored`, `erasure`; consumers add theirs (`sent`, `viewed`, …). |
| `causer_type`, `causer_id` | `string`, nullable | The admin; null for the public visitor. |
| `ip_hash` | `char(64)`, nullable | Public events only: public-links' `IpHasher`, the access log's HMAC. |
| `properties` | `json`, nullable | Ids, hashes, counts. A denylist of keys (`email`, `name`, `phone`, `ip`, `ip_address`, `token`, `password`, `secret`, at any depth) throws `AuditPropertyNotAllowed`. JSON column: F7. |
| `occurred_at` | `timestamp`, indexed | The `prunable()` column. |

The package's own entries are written in the same transaction as the operation, so a rolled-back
seal or decision leaves no entry. Names and e-mails never go into the log; they live in the
decision only.

**Pruning** (`MassPrunable`, `audit.prune_after_days`, default **90**, rule E6; `null` = never):
an entry older than that is pruned only if it is not part of the evidence chain, that is if it
has no version, or its version is superseded **and** has no decision. Entries of a decided
version, of a version that can still be decided (not superseded — so a "sealed" entry older than
90 days is still there when the customer finally accepts), and `erasure` records are kept.
`AuditEntry::pruneAll()` deletes below Eloquent in chunks, because the evidence builder refuses
deletes; it is the entry's only way out besides an erasure.

### Retention — evidence is never deleted automatically

| | Deleted automatically? |
| --- | --- |
| Sealed versions, decisions, evidence files | Never (not prunable; models and builders refuse deletes; decisions FK `restrictOnDelete`) |
| Audit entries of a decided or still decidable version, erasure records | Never |
| Other audit entries | After `audit.prune_after_days` (90; `null` = never) |

**Subjects.** `HasSealedVersions` refuses to delete a subject for good while it has sealed
versions (`EvidenceIsPermanent`, checked across all tenants): with `SoftDeletes` a soft delete
works and a force delete throws; without `SoftDeletes` the model cannot be deleted at all once
sealed. A `Prunable` subject is therefore kept by `model:prune` (Laravel reports the exception and
goes on). Mass deletes below the model events (`query()->delete()`, a `MassPrunable` subject,
`DB::table()`) are not covered; the versions survive them (no FK) as orphans that still verify.
The README recommends `SoftDeletes` on subjects.

**Erasure** — the one deliberate way out, never called by the package itself
(`Facades\Erasure`, `filament-acceptance:erase {type} {id} --reason= [--purge] [--force]`):

- `anonymize`: nulls name, e-mail, comment, reason, user agent and IP hash of the subject's
  decisions and the IP hashes of its audit entries. Content, hashes and evidence files stay, so
  `verify()` keeps its result — and personal data **inside the sealed content** (a customer name
  in a quote snapshot) stays too.
- `purge`: removes the subject's versions, decisions, evidence files (after commit) and audit
  entries; earlier erasure records stay.
- Both need a non-empty reason, write an `erasure` entry (mode, reason, counts; for a purge the
  removed versions' numbers and content hashes), log a notice with ids only, and go below
  Eloquent on purpose. The command works by morph type and id, so it reaches orphans too.

## b) Public PHP API

Namespace `Victorio\Acceptance`. Everything in this section is public API (semver), the rest is
`@internal` (D4).

### Canonical JSON and the hash

```php
use Victorio\Acceptance\Support\CanonicalJson;

$json = CanonicalJson::encode($content);   // string; throws NotCanonicalizable on a float,
                                           // a resource, an object, an int beyond ±2^53
hash('sha256', $json);
```

The canonical form, documented in the README so anyone can re-verify a version without this
package: objects with keys sorted by byte order, lists in order, no insignificant whitespace,
UTF-8 unescaped, `/` unescaped, integers only (no floats; amounts are minor units), `true`,
`false`, `null`. For such documents the output equals **RFC 8785 (JCS)**, so off-the-shelf JCS
tools reproduce the hash. Empty arrays are encoded as `[]`; a consumer that needs an empty object
uses a non-empty one or `null` (documented — PHP cannot tell the two apart).

### Sealing

```php
use Victorio\Acceptance\Concerns\HasSealedVersions;

final class Quote extends Model
{
    use HasSealedVersions;
}

$version = $quote->seal(
    kind: 'quote',
    content: $publicContent,       // array, canonicalised and hashed
    internal: ['costs' => $costs], // optional, encrypted, never hashed
    by: auth()->user(),
);   // SealedVersion; number = previous + 1, the previous one gets superseded_at

$quote->sealedVersions;            // newest first, current tenant only
$quote->latestSealedVersion();
$version->content();               // decoded public content (array)
$version->internal();              // decrypted internal part — admin side only; throws
                                   // InternalPartUnreadable after an APP_KEY change without
                                   // previous_keys (content, hash and verify() unaffected)
$version->verify();                // bool: hash(content) === content_hash and content canonical
                                   // (step 2 adds the decision's hash, step 3 the evidence file)
```

Numbering is race-free: in one transaction the subject row is locked (`lockForUpdate`), the next
number computed, the row inserted; the unique index is the backstop. `VersionSealed` fires after
commit.

### Decisions

```php
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Facades\Decisions;

$decision = Decisions::record(
    version: $version,
    input: DecisionInput::accept(name: 'Anna Kovács', email: 'anna@example.test',
        consentText: $label, comment: null, locale: 'hu'),
    request: $request,                        // IP (HMAC) and user agent
    link: $visit->link,                       // optional
    access: $visit->access,                   // optional: the access row of this request
    transition: fn (): bool => $quoteTransitions->accept($quote),   // the consumer's status change
);
```

First the input is validated (`DecisionInput::rules()`, `ValidationException`: accept needs
name, a valid e-mail and the consent text; decline takes an optional name, e-mail and reason;
comment and reason lengths from config). Then, in one transaction:

1. lock the version row (`lockForUpdate`) — this also orders a decision against the sealing of a
   newer version, which writes `superseded_at` on the same row;
2. superseded → `VersionSuperseded`;
3. `verify()` fails → `ContentHashMismatch`, logged as an alert with the version id only;
4. a decision exists → `DecisionAlreadyMade` (with `->decision`);
5. the consumer's `transition` returns anything but true → `DecisionRefused`;
6. insert; the unique index on `sealed_version_id` turns a lost race into `DecisionAlreadyMade`.

Any exception rolls back everything, including what the transition wrote. Then
`DecisionRecorded` and `DecisionAccepted` or `DecisionDeclined`, after commit.
`Decision::verify()` is true when the version verifies, its hash equals the decision's
`content_hash`, and the evidence file (if any) matches its hash.

### Evidence files

```php
$decision->storeEvidence(contents: $pdfBytes, filename: 'Q-2026-0042-v2.pdf');
// writes to evidence.disk under evidence.path, sets evidence_path and evidence_hash once
$decision->evidenceContents();    // bytes, after checking evidence_hash
$decision->verifyEvidence();      // bool
```

The path is `{evidence.path}/{decision id}/{first 12 hex of the SHA-256}-{sanitised filename}`;
the disk name is stored with it, so changing `evidence.disk` later still finds old files. Written
once: the file is put first, then a conditional update (`whereNull('evidence_path')`) claims the
columns; a loser gets `EvidenceAlreadyStored` and its file stays (never deleted). Missing,
changed or never stored → `EvidenceFileUnavailable`.

Quotes uses it for the PDF of the accepted version (rendered by quotes, after the decision is
committed, so the PDF can show the decision); contracts will store the signed PDF the same way.

### Audit log

```php
use Victorio\Acceptance\Facades\AuditLog;

AuditLog::record($quote, 'sent', causer: auth()->user(), version: $version,
    properties: ['recipients' => 1]);
$quote->auditEntries;             // via HasSealedVersions, newest first
```

Pass the request for public events: the entry gets public-links' IP HMAC. Properties are checked
against the denylist above. `AuditLog::record(..., tenant: $team)` for entries without a version
written outside a Filament request.

### Public decision flow (step 4)

```php
use Illuminate\Contracts\Support\Htmlable;
use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Http\DecisionLinkHandler;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Data\PublicLinkVisit;

final class QuoteLinkHandler extends DecisionLinkHandler
{
    protected function renderContent(SealedVersion $version, PublicLinkVisit $visit): Htmlable
    {
        return view('filament-quotes::public.lines', ['quote' => $version->content()]);
    }

    protected function title(SealedVersion $version): string { … }               // "Quote Q-2026-0042"
    protected function isActionable(SealedVersion $version): bool { … }          // optional: not expired
    protected function consentText(SealedVersion $version): string { … }         // optional
    protected function locale(SealedVersion $version): string { … }              // optional
    protected function timezone(SealedVersion $version): string { … }            // optional
    protected function transition(SealedVersion $version, DecisionKind $kind): bool { … } // optional
}

PublicLinks::handle(SealedVersion::class, 'quote', QuoteLinkHandler::class, 'Quote');
```

**Decided (2026-10-21):**

- **Rendering:** the consumer renders only its content (`renderContent()` returns an
  `Htmlable`, usually a view) and the title; the package owns the page, every state, the forms
  and the post/redirect/get flow, so they are built and tested once. A consumer that wants full
  control overrides `show()` and composes the public Blade components itself.
- **After the decision** the link shows a read-only confirmation (200): the content, "Accepted
  by … on …" (or declined), the comment or reason, the fingerprint and — when there is an
  evidence file — a download button (`POST …/evidence`, hash-checked, 404 if missing or changed).
  Not 410: the customer keeps a record of what they agreed to.
- **Usage limit:** opening never spends a use; a recorded decision spends one (`recordUse()`).
  Decision links are issued **without a usage limit**: with `maxUses(1)` the decision exhausts
  the link, and the confirmation after the redirect answers 410 (pinned by a test; README and
  docblock say so). A second decision is impossible anyway.
- **Consent text:** `consentText()` (default: the translation `form.consent`). The page sends
  its SHA-256 with the form; the POST recomputes the text on the server and records it only when
  the hashes match, so the decision stores exactly the text the visitor saw — never text from
  the client. If it changed in between, the form comes back with an error.
- **Language:** `locale()` (default: the app locale) is set for the page, its validation
  messages (the package's own, in the eight shipped languages, independent of the host's
  validation translations) and the
  stored decision.

| Situation | Answer |
| --- | --- |
| GET, decidable | content, fingerprint, one form (name, e-mail, comment, consent) with Accept (primary) and Decline (`formaction` to `decline`, `formnovalidate`) |
| GET, decided | content, fingerprint, confirmation, evidence download |
| GET, `isActionable()` false | content, "can no longer be accepted or declined", contact |
| GET, superseded version | 410 "A newer version was prepared", contact address |
| POST decline, valid | 200 confirmation page: subject, fingerprint, "cannot be undone", name/e-mail/reason prefilled; nothing recorded |
| POST back | 303 to the page with the values as old input |
| POST accept / decline-confirm, success | `recordUse()`, 303 to the page (PRG), notice |
| POST, invalid | 303 back, errors in the `decision` bag, old input (accept needs name, e-mail, consent; decline nothing) |
| POST, already decided (two tabs, double click) | 303 to the page, "already recorded" notice — never 500 |
| POST, superseded / refused / not actionable | 303 to the page, which explains it |
| POST, tampered version | 409 page, contact address; nothing recorded, alert logged |

The redirect target is the request URL without its action segment: the pages send
`Referrer-Policy: no-referrer`, so `back()` has nothing to go on. The token appears in the HTML
only in form actions. The contact address comes from public-links' branding (its configured
`BrandingResolver`, so per-tenant brands work).

Languages: en and hu (checked by hand), de, es, fr, it, nl, pt_BR (machine-assisted, the
consent text and legal note included; the README asks buyers to have those reviewed). The
language is public-links' (link locale → Accept-Language → app) unless `locale()` says
otherwise; `dir="rtl"` comes from the public-links layout, the decision styles use logical
properties. `TranslationsTest` and `PseudoLocaleTest` guard keys and hard-coded text.

Accessibility: a label for every field; errors tied with `aria-invalid` + `aria-describedby`, a
`role="alert"` summary, `autofocus` on the first invalid field (no JavaScript); real submit
buttons, at least 44 px high; the accept button uses the brand colour pair public-links picks for
contrast; 16 px inputs (no zoom on iOS).

Blade components (anonymous, `filament-acceptance::components.*`, publishable with the views):
`<x-filament-acceptance::styles />`, `<x-filament-acceptance::decision-forms :action
:consent-text :consent-hash />`, `<x-filament-acceptance::decision-summary :decision :decided-at
:evidence-action />`, `<x-filament-acceptance::fingerprint :version />`.

### Filament building blocks and approval without code (step 5)

- **Approval fields.** `Approval\ApprovalField::make($attribute)` with a type (`text`,
  `multiline`, `integer`, `boolean`, `date`) and a label per locale (array, English fallback) or
  a translation key. A model lists them in `approvalFields()` (and its heading in
  `approvalTitle()`), or `AcceptancePlugin::approvable($model, fields, title)` sets them and wins.
- **Sealed content** (`Approval\ApprovalContent`, kind `approval`): `locale`, `title`, and per
  field `key`, `type`, `value` and the label **in that locale only** (decided 2026-10-21). The
  page needs no configuration, and a later config change never alters a sent version. Another
  language = another sealed version.
- **`Http\ApprovalLinkHandler`** (registered for the version model under `approval.purpose`)
  renders those fields (escaped, multi-line text in paragraphs) and returns the snapshot's
  `locale` from `locale()`, so page, consent text and errors are in that language regardless of
  `Accept-Language`. Its `consentText()` asks the record (looked up without global scopes, so a
  soft-deleted one too) for `approvalConsentText()` in that locale; null or blank keeps the
  translation. The text is the record's current one, not sealed: the consent hash check catches
  a change while the page is open (decided 2026-10-21).
- **`Filament\Actions\SendForApprovalAction`** extends public-links' `IssuePublicLinkAction`
  (not final from 0.2): `fields(['expiry', 'locale'])`, `issueUsing()` seals the record
  (`ApprovalContent::for($record, $locale)`) and issues a link to the version (no usage limit,
  the chosen expiry and locale), `linkOwnedUsing()` accepts only links to this record's versions.
  Audit entry `sent`. Permission: `update` on the record (public-links' rule). `purpose()`
  (string, or a closure given the record) picks the link's purpose per record, so one button can
  send to the built-in page or to a consumer's own `DecisionLinkHandler`; default
  `approval.purpose`.
- **`Filament\RelationManagers\DecisionsRelationManager`** on `sealedVersions` (tenant-scoped):
  version, sealed at, status badge (pending, accepted, declined, superseded), name, decided at,
  comment or reason, fingerprint; row actions download (hash-checked, else a notification) and
  verify; header actions send for approval and activity (slide-over, last 50 audit entries);
  refreshes on public-links' `LINKS_CHANGED`. `view` to see, `update` to send; no policy → allowed
  unless `strictAuthorization()`.
- Tests: `ApprovalTest` (content, labels, fallback, plugin, floats, page language), `FilamentTest`
  (action, URL modal, crafted requests, table, download, verify, activity, tenancy, policy, strict
  authorization, int/ULID/UUID owners), `PseudoLocaleTest` (table, send modal, activity).

### Events

`VersionSealed`, `DecisionRecorded`, `DecisionAccepted`, `DecisionDeclined` (with the
decision), all `ShouldDispatchAfterCommit`. Consumers translate them into their own
(`QuoteAccepted`, …).

## c) Config — `config/filament-acceptance.php`

```php
return [
    'models' => [
        'version' => \Victorio\Acceptance\Models\SealedVersion::class,
        'decision' => \Victorio\Acceptance\Models\Decision::class,
        'audit' => \Victorio\Acceptance\Models\AuditEntry::class,
    ],

    'tables' => [
        'versions' => 'victorio_sealed_versions',
        'decisions' => 'victorio_decisions',
        'audit' => 'victorio_audit_entries',
    ],

    'tenancy' => [
        'enabled' => true,
        'column' => 'tenant_id',
    ],

    'evidence' => [
        // no IP key of its own: decisions use filament-public-links' access_log.ip_hash_key
        'user_agent_max_length' => 255,
        'disk' => 'local',                 // evidence files; a private disk
        'path' => 'victorio/evidence',
    ],

    'audit' => [
        'prune_after_days' => 90,          // MassPrunable (E6); null = never pruned; the evidence
                                           // chain and erasure records are never pruned
    ],

    'decisions' => [
        'max_comment_length' => 2000,
        'max_reason_length' => 2000,
    ],
];
```

Install command `filament-acceptance:install`; publish tags `filament-acceptance-config`,
`-migrations`, `-views`, `-translations` (D11). Consumers' install commands call it.

## d) Test plan

Pest + Testbench; fixture subjects with int, ULID and UUID keys; all data tests in
`make check-db`.

- **Canonical JSON**: key order, nested lists, unicode (`Árvíztűrő`), slashes, integers at ±2^53,
  floats / objects / out-of-range ints refused; fixed vectors whose hashes are asserted literally
  (and match an RFC 8785 reference output).
- **Sealing**: content stored byte-exact and hash stable on sqlite, mysql and pgsql; numbering
  1..n per subject, concurrent seals give distinct numbers; update and delete of a version throw;
  `superseded_at` set on the previous one; internal part encrypted, hidden from `toArray()` and
  `toJson()`.
- **Decisions**: accept and decline inputs validated; hash mismatch after tampering refuses and
  writes nothing; two concurrent accepts and accept vs decline (second connection) leave exactly
  one decision, one transition, one event; transition returning false rolls back.
- **Retention**: `model:prune` removes old audit entries only; with `prune_after_days = null`
  nothing is removed; versions and decisions survive `model:prune`, `delete()` throws,
  force-deleting a subject with versions throws; evidence files remain.
- **Evidence files**: stored once, hash matches, a changed file is detected.
- **Public flow** (with a fixture handler): GET renders, POSTs decide, PRG, opens spend no use,
  a decision spends one, double submit shows the confirmation, CSRF 419, rate limit from
  public-links, audit entries written without personal data.
- **Tenant isolation** of the relation manager and of every query.
- **Filament**: verify action, audit relation manager, on Filament 4 and 5.

## e) Implementation order

1. Canonical JSON, `SealedVersion` with sealing, immutability, `verify()`.
2. `Decision`, `Decisions::record()` with the race-safe transaction, IP hasher, evidence files.
3. `AuditEntry`, `AuditLog`, pruning and the retention guarantees.
4. `DecisionLinkHandler`, the Blade components, translations, the public flow tests.
5. Filament building blocks, install command, README (the canonical-form spec), 0.1.0 on request.

Quotes' implementation starts once this package has a release it can require (`^0.1`).

## f) Open questions

- None at the moment; to be revisited when contracts needs drawn signatures (an image as an
  evidence file plus its hash, probably a second evidence slot) and multiple signers.
