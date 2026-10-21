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
- Requires `victorio/filament-public-links` `^0.2`.
