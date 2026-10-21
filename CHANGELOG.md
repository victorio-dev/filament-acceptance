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
  `VersionSealed` event; Eloquent refuses to change or delete a sealed version.
