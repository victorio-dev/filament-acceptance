# filament-acceptance — design

Status: **approved** 2026-10-21; step 1 (sealed versions) done. The short version lives in [../CLAUDE.md](../CLAUDE.md); this
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

Written once. The model throws `SealedVersionIsImmutable` on update and `EvidenceIsPermanent` on
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
| model `save()` / `saveQuietly()` of a change, `update()`, `increment()` | `SealedVersionIsImmutable` (model event, and the builder for the quiet paths) |
| model `delete()` / `deleteQuietly()` / `forceDelete()`, `SealedVersion::destroy()` | `EvidenceIsPermanent` |
| builder `update`, `upsert`, `touch`, `increment`, `decrement`, `incrementEach`, `decrementEach` — on `SealedVersion::query()` and on `$subject->sealedVersions()` | `SealedVersionIsImmutable` |
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
| `tenant_id` | `string(36)`, nullable | |
| `decision` | `string(16)` | `accepted` or `declined`. |
| `name`, `email` | `string(255)`, nullable | Required to accept, optional to decline. |
| `comment` | `text`, nullable | Optional remark when accepting. |
| `reason` | `text`, nullable | Optional reason when declining — feedback for the seller. |
| `consent_text` | `text`, nullable | The checkbox label exactly as shown (accept only). |
| `locale` | `string(8)` | Language the form was shown in. |
| `content_hash` | `char(64)` | Hash of the version's `content`, recomputed at the moment of the decision and equal to `sealed_versions.content_hash` (otherwise the decision is refused). |
| `ip_hash` | `char(64)` | `hash_hmac('sha256', $ip, key)`, key `evidence.ip_hash_key` (default derived from `app.key`). Never the raw IP. |
| `user_agent` | `string(255)`, nullable | Truncated to `evidence.user_agent_max_length`. |
| `public_link_id` | `ulid`, nullable | The link used. No FK: links may be pruned, the decision stays. |
| `evidence_disk`, `evidence_path` | `string`, nullable | The stored evidence file (b, "Evidence files"). |
| `evidence_hash` | `char(64)`, nullable | SHA-256 of that file's bytes. |
| `decided_at` | `timestamp` | |

Written once; the evidence columns are written once more, only while null. A business record,
not a log: personal data (name, e-mail) the host is responsible for, documented in the README.

### `victorio_audit_entries` (`tables.audit`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id()` | Never exposed publicly. |
| `tenant_id` | `string(36)`, nullable | |
| `subject_type`, `subject_id` | `string` | |
| `sealed_version_id` | `ulid`, nullable | |
| `event` | `string(64)` | Free, consumer-defined: `created`, `sealed`, `sent`, `viewed`, `accepted`, … |
| `causer_type`, `causer_id` | `string`, nullable | The admin; null for the public visitor. |
| `ip_hash` | `char(64)`, nullable | Public events only, same HMAC. |
| `properties` | `json`, nullable | Never tokens or personal data in plain text (E5). JSON column: F7. |
| `occurred_at` | `timestamp`, indexed | The `prunable()` column. |

`AuditEntry` uses `MassPrunable`: older than `audit.prune_after_days` (default **90**, rule E6;
`null` = never pruned — allowed and documented). The host schedules `model:prune`.

### Retention — evidence is never deleted automatically

Sealed versions and decisions are **never** removed by the package: they are not `Prunable`, the
decisions FK is `restrictOnDelete`, both models refuse `delete()`, and `HasSealedVersions` refuses
to force-delete a subject that has sealed versions (soft deletes keep working). Evidence files are
never deleted by the package either. Removing evidence is a deliberate host decision (e.g. a GDPR
erasure) through `Evidence::purge($subject)`, which is explicit, logged and documented. Tests pin
all of this (h).

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
    transition: fn (): bool => $quoteTransitions->accept($quote),   // the consumer's status change
);
```

In one transaction: recompute `hash('sha256', content)` and compare with `content_hash` in
constant time (mismatch → `ContentHashMismatch`, logged as an alert, nothing written); call the
consumer's `transition` (a conditional update returning whether it won); insert the decision
(unique per version). If the transition returns false or the insert hits the unique index, the
transaction rolls back and `DecisionAlreadyMade` is thrown with the existing decision, so two
simultaneous accepts — or an accept racing a decline — leave exactly one decision and one
status change. `DecisionRecorded` fires after commit. `DecisionInput::decline(name: null,
email: null, reason: null, locale: …)` for the other case.

### Evidence files

```php
$decision->storeEvidence(contents: $pdfBytes, filename: 'Q-2026-0042-v2.pdf');
// writes to evidence.disk under evidence.path, sets evidence_path and evidence_hash once
$decision->evidenceContents();    // bytes, after checking evidence_hash; throws on mismatch
```

Quotes uses it for the PDF of the accepted version (rendered by quotes, after the decision is
committed, so the PDF can show the decision); contracts will store the signed PDF the same way.

### Audit log

```php
use Victorio\Acceptance\Facades\AuditLog;

AuditLog::record($quote, 'sent', causer: auth()->user(), version: $version,
    properties: ['recipients' => 1]);
$quote->auditEntries;             // via HasSealedVersions, newest first
```

Public-request entries (`viewed`, `accepted`, …) get the HMAC IP from the request; properties are
checked against a small denylist (`token`, `email`, `ip`, `password`) and refused in tests.

### Public decision flow

```php
use Victorio\Acceptance\Http\DecisionLinkHandler;
use Victorio\PublicLinks\Data\PublicLinkVisit;

final class QuoteLinkHandler extends DecisionLinkHandler
{
    protected function render(PublicLinkVisit $visit, SealedVersion $version, ?Decision $decision): Response { … }
    protected function isActionable(SealedVersion $version): bool { … }   // current, not expired
    protected function transition(SealedVersion $version, string $decision): bool { … }
    protected function consentText(SealedVersion $version): string { … }
}
```

`DecisionLinkHandler` implements public-links' `PublicLinkHandler` and `HandlesPublicLinkActions`:
`countsOpensAsUses()` is false; actions `accept` and `decline` (a subclass may add more, quotes
adds `pdf`); it validates (name and e-mail required for accept, checkbox required, lengths),
calls `Decisions::record()`, spends a use with `recordUse()` only on success, writes the audit
entry, and redirects (303) to the link's GET, which renders the confirmation (PRG). A lost race or
a double submit lands on the confirmation of the existing decision, not on an error. Validation
errors go back with old input; 419 shows a translated "page expired" message.

Blade components (no JavaScript, styled to sit inside `<x-filament-public-links::layout>`):

- `<x-filament-acceptance::decision-forms :visit :version />` — the accept form (name, e-mail,
  consent checkbox, optional comment) and the decline form (optional name, e-mail, reason), with
  the "simple electronic declaration, not a qualified electronic signature" note.
- `<x-filament-acceptance::decision-summary :decision />` — "Accepted by … on …", comment or
  reason.
- `<x-filament-acceptance::fingerprint :version />` — version number and the first 12 hex digits
  of the hash, for the page and the PDF footer.

All copy in `resources/lang/{en,hu}/filament-acceptance.php`; the consumer can override the
consent text.

### Filament building blocks

- `Filament\Actions\VerifySealedVersionAction` — runs `verify()` (content hash, decision hash,
  evidence file hash) and shows the result.
- `Filament\RelationManagers\AuditEntriesRelationManager` — the timeline of a subject using
  `HasSealedVersions` (time, event, causer, version); tenant-scoped.
- A decision infolist entry for the version views of the consumers.

### Events

`VersionSealed`, `DecisionRecorded` (with the decision, accepted or declined). Consumers translate
them into their own (`QuoteAccepted`, …).

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
        'ip_hash_key' => env('ACCEPTANCE_IP_HASH_KEY'),   // null = derived from app.key
        'user_agent_max_length' => 255,
        'disk' => 'local',                 // evidence files; a private disk
        'path' => 'victorio/evidence',
    ],

    'audit' => [
        'prune_after_days' => 90,          // MassPrunable (E6); null = never pruned
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
