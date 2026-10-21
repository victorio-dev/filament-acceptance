Global rules: ../CLAUDE.md

# victorio/filament-acceptance

Package-level notes. The global rules are not repeated here — read them first, then this file.
Keep this file current: it is the context for every task in this package. The detailed plan is
[docs/design.md](docs/design.md); status: **steps 1-5 of 5 done** (sealed versions, decisions, evidence files, audit log,
retention, public decision pages, Filament UI and approval without code), ADR-0010.

## Purpose

The common layer for documents that someone outside the panel accepts or declines: sealed,
immutable versions (canonical JSON + SHA-256), race-free decisions bound to that hash, the
public accept/decline flow on public links, evidence files, an audit log. Used by
`filament-quotes` now and `filament-contracts` later. Free and open source (MIT, public Packagist).
Out of scope: anything domain-specific (pricing, numbering, statuses, PDF rendering, a Filament
resource), a legally binding e-signature (simple electronic signature under eIDAS only).

## Dependencies

- `victorio/support` — shared helpers, as they appear.
- `victorio/filament-public-links` `^0.2` — links to sealed versions, handler contracts, layout,
  `IpHasher` (decisions store the access log's IP HMAC). Stable releases only
  (`"minimum-stability": "stable"`; `make release` refuses anything else).
- Never a paid package: `quotes` and `contracts` depend on this one, not the other way round.

## Models and tables

| Model | Table (config key) | Tenant-owned | Notes |
| --- | --- | --- | --- |
| `SealedVersion` | `filament-acceptance.tables.versions` | current Filament tenant, or `seal(tenant:)` | immutable; content in a text column, never `json`; never deleted automatically |
| `Decision` | `filament-acceptance.tables.decisions` | copied from the version | unique per version; public-links' IP HMAC and access row; evidence file + hash; never deleted automatically |
| `AuditEntry` | `filament-acceptance.tables.audit` | copied | immutable; `MassPrunable` (own `pruneAll`), 90 days, null = never; the evidence chain and erasure records are never pruned |

## Public API

Everything listed here follows semver; everything else in `src/` is `@internal`.

- `Support\CanonicalJson::encode()` (documented canonical form, RFC 8785-compatible subset).
- `Concerns\HasSealedVersions`: `seal()`, `sealedVersions()`, `latestSealedVersion()`, `auditEntries()`,
  `hasAnySealedVersion()`; refuses deleting a sealed subject for good.
- `Models\SealedVersion` (`content()`, `internal()`, `verify()`, `forCurrentTenant()`), `Models\Decision`
  (`storeEvidence()`, `evidenceContents()`), `Models\AuditEntry`.
- `Facades\Decisions::record()`, `Data\DecisionInput`, `Enums\DecisionKind`, `Facades\AuditLog::record()`,
  `Facades\Erasure::anonymize()|purge()`, `Data\ErasureReport`, command `filament-acceptance:erase`.
- `Http\DecisionLinkHandler` (abstract: `renderContent()`, `title()`, optional `isActionable()`,
  `consentText()`, `locale()`, `timezone()`, `transition()`), Blade components `styles`,
  `decision-forms`, `decision-summary`, `fingerprint`.
- Approval without code: `Approval\ApprovalField`, `approvalFields()` / `approvalTitle()` /
  `approvalConsentText()`, `SendForApprovalAction::purpose()`,
  `AcceptancePlugin::approvable()`, `Http\ApprovalLinkHandler`; Filament
  `Actions\{SendForApproval,ViewActivity,DownloadEvidence,VerifySealedVersion}Action`,
  `RelationManagers\DecisionsRelationManager`; plugin `rowActions()`,
  `modify{Row,Header,Toolbar}ActionsUsing()`.
- `Events\VersionSealed`, `Events\Decision{Recorded,Accepted,Declined}`, `Exceptions\*`; config and
  translation keys.

## Public routes and tokens

No own routes: consumers register a `DecisionLinkHandler` subclass with public-links, which owns
tokens, rate limits, headers and the access log (E1-E5). Its actions: `accept`, `decline` (asks to
confirm), `decline-confirm`, `back`, `evidence` (POST, CSRF). Decision links are issued without a
usage limit (design.md b). Decisions and audit entries store the IP only as public-links' HMAC
(`IpHasher`), never their own (E4, E5).

## Conventions specific to this package

- MIT, public repository, like public-links; its API is public from 0.1, so semver matters.
- Eight languages; the consent text and legal note are legal in nature (README: have them reviewed).
- Evidence (versions, decisions, evidence files) is never deleted by the package; tests pin it.
  The only way out is `Erasure` (explicit, logged); pruning spares the evidence chain.
  Immutability is enforced in the model and its builder; below Eloquent only `verify()` detects
  changes (design.md a, "What immutability covers").
- `StringKeyedMorphMany`, `ModelKey` and the pseudo-locale test helpers come from
  `victorio/support` `^0.3` (no copies).
- Canonical content contains integers only, never floats; any change to the canonical form is a
  breaking change and needs an ADR.

## Commands

Run from the workspace root, in Docker — never on the host (rule I1):

```bash
make check PKG=filament-acceptance     # git-check + F1-F3
make check-db PKG=filament-acceptance  # tests on sqlite, mysql, pgsql (F7)
make check-lowest PKG=filament-acceptance  # lowest stable dependencies on PHP 8.3
```

## Open questions

- None; drawn signatures and multiple signers are revisited with contracts (design.md f).
