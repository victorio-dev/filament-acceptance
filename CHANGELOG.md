# Changelog

All notable changes to `victorio/filament-acceptance` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html): the public API is everything not
marked `@internal`.

## [Unreleased]

### Added

First release.

- **Send for approval without code.** Say which fields of a model the customer sees — on the
  model (`approvalFields()`) or on the panel plugin (`AcceptancePlugin::approvable()`) — with a
  label per language. `SendForApprovalAction` seals the record in the chosen language and shows
  a link to copy; the customer opens it without logging in and accepts or declines.
- **A public decision page.** One primary accept form (name, e-mail, consent, comment), declining
  on demand below it, a read-only confirmation afterwards with the stored document, and clear
  pages for a superseded or closed version. No JavaScript, labels and errors tied to their
  fields, 44 px touch targets, light and dark mode, right-to-left languages.
- **The approvals table.** `DecisionsRelationManager` shows each version's status (awaiting
  decision, accepted, declined, superseded), who decided when, the comment or reason, the
  fingerprint; download the stored document, verify a version, see the activity. Permissions
  follow the record's `view` / `update` policy, tenancy is respected.
- **Proof of what was accepted.** Every version is sealed as canonical JSON with its SHA-256
  (RFC 8785-compatible, re-checkable without this package); a decision is bound to exactly that
  hash and records the consent text the customer saw; a stored document keeps its own hash.
  Eloquent refuses to change or delete any of it, and `verify()` detects changes made below it.
- **Race-free decisions.** One decision per version, even with two tabs open or a double click.
- **Your own page when you need it.** Extend `DecisionLinkHandler` for your own content (a quote
  with its lines), a status change on acceptance, or a check before deciding.
- **Activity log** of every seal, send, decision and stored document, pruned after 90 days except
  for the evidence chain; events for sealed, recorded, accepted and declined.
- **Retention and erasure.** Evidence is never deleted automatically; `Erasure::anonymize()` and
  `Erasure::purge()` (or `php artisan filament-acceptance:erase`) are the deliberate, logged way
  when your legal assessment requires it.
- **Eight languages.** English and Hungarian checked by hand; German, Spanish, French, Italian,
  Dutch and Brazilian Portuguese machine-assisted. Have the consent text reviewed in your
  language: it is legal in nature.
- A decision is a simple electronic signature under eIDAS, not an advanced or qualified one.
- Requires `victorio/filament-public-links` `^0.2` and `victorio/support` `^0.3`.
