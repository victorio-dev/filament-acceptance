# Changelog

All notable changes to `victorio/filament-acceptance` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html): the public API is everything not
marked `@internal`.

## [Unreleased]

### Added

- `CanonicalJson::encode()`: the canonical JSON sealed versions are hashed over (RFC 8785 for
  integers, strings, booleans, null and arrays); floats, objects and integers beyond ±(2^53 − 1)
  are refused.
- `HasSealedVersions` with `seal()`, `sealedVersions()` and `latestSealedVersion()`: immutable,
  numbered versions with the SHA-256 of their content and an encrypted internal part.
- `SealedVersion::verify()`, `content()`, `internal()`, `forCurrentTenant()`; the
  `VersionSealed` event; Eloquent refuses to change or delete a sealed version
  (`EvidenceIsImmutable`, `EvidenceIsPermanent`).
- `Decisions::record()` with `DecisionInput::accept()` / `decline()`: one decision per sealed
  version, race-free, bound to the version's content hash, refused on a superseded or tampered
  version or by the consumer's transition. `Decision::verify()`; `DecisionRecorded`,
  `DecisionAccepted`, `DecisionDeclined` after commit.
- Decisions store the client IP as the same HMAC as the filament-public-links access log, and
  the access row of the request.
- Evidence files: `Decision::storeEvidence()`, `evidenceContents()`, `verifyEvidence()` — stored
  once on the configured disk with their SHA-256, never deleted.
- Audit log: `AuditLog::record()` and `$subject->auditEntries()`; the package records `sealed`,
  `accepted`, `declined` and `evidence_stored` itself, in the same transaction. Personal data and
  secrets are refused as property keys; IPs only as the public-links HMAC.
- Audit pruning (`audit.prune_after_days`, 90 by default, `null` = never) spares the evidence
  chain: entries of decided or still decidable versions, and erasure records.
- A subject with sealed versions cannot be deleted for good (`EvidenceIsPermanent`); soft
  deletes keep working.
- `Erasure::anonymize()` / `Erasure::purge()` and `php artisan filament-acceptance:erase`: the
  deliberate, logged way to remove evidence.
- Public decision pages: `Http\DecisionLinkHandler` (abstract; `renderContent()`, `title()`,
  optional `isActionable()`, `consentText()`, `locale()`, `timezone()`, `transition()`) with
  accept and decline forms, post/redirect/get, a read-only confirmation with the evidence
  download, a page for superseded versions, and the Blade components `styles`,
  `decision-forms`, `decision-summary`, `fingerprint`.
- Eight languages: English and Hungarian (checked by hand), German, Spanish, French, Italian,
  Dutch and Brazilian Portuguese (machine-assisted), including the default consent text. The
  pages follow the link's language (filament-public-links 0.2) and support right-to-left
  languages with logical CSS.
- Approval without code: `ApprovalField` (text, multi-line, integer, boolean, date; a label per
  language), `approvalFields()` / `approvalTitle()` on the model or
  `AcceptancePlugin::approvable()`, and the built-in `ApprovalLinkHandler` (purpose
  `approval.purpose`), which shows the sealed fields in the language they were sent in.
- `Filament\Actions\SendForApprovalAction`: seals a record in the chosen language and issues a
  decision link through public-links' `IssuePublicLinkAction` (expiry and language, no usage
  limit), showing its URL.
- `Filament\RelationManagers\DecisionsRelationManager`: versions, status, decision, fingerprint,
  document download, verification, activity; permissions from the owner record's policy.
- Requires `victorio/filament-public-links` `^0.2` and `victorio/support` `^0.3` (its
  `StringKeyedMorphMany`, `ModelKey` and pseudo-locale test helpers).
