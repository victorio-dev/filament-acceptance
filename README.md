# victorio/filament-acceptance

Sealed, tamper-evident document versions and provable accept or decline decisions for Filament,
on top of [victorio/filament-public-links](https://github.com/victorio-dev/filament-public-links).

> **Status: in development, not released.** Sealed versions and the canonical JSON exist;
> decisions, the public decision flow, evidence files and the audit log follow
> ([docs/design.md](docs/design.md)).

- Requires PHP `^8.3`, Laravel `^12.0|^13.0`, Filament `^4.0|^5.0`
- Free and open source (MIT), part of the Victorio suite of Filament plugins
- Depends on `victorio/support` and `victorio/filament-public-links`

A decision recorded by this package is a simple electronic signature under eIDAS, not an advanced
or qualified electronic signature.

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

Through Eloquent, a sealed version cannot be changed or deleted: `save()`, `update()`,
`increment()`, `delete()`, `forceDelete()`, `destroy()` and their quiet variants throw, and so do
`update()`, `upsert()`, `increment()`, `delete()`, `truncate()` and friends on
`SealedVersion::query()` and on `$quote->sealedVersions()`. Sealed versions are never pruned.

What no PHP code can stop: `DB::table()`, `->toBase()`, raw SQL and anyone with direct database
access. The package makes such changes **detectable** — `verify()` returns false after a single
changed byte — not impossible. To make them impossible, revoke `UPDATE` and `DELETE` on the
`victorio_sealed_versions` table from the application's database user.

### The canonical form

Anyone can re-check a version without this package: `content_hash` is the SHA-256 of the stored
`content`, which is canonical JSON — object keys sorted by UTF-16 code units, lists in order, no
whitespace, integers within ±(2^53 − 1), no floats, strings escaping only `"`, `\` and control
characters (`\b \t \n \f \r`, others as `\u00xx`), everything else literal UTF-8. For such data
this is exactly [RFC 8785](https://www.rfc-editor.org/rfc/rfc8785) (JCS), so any JCS
implementation reproduces the bytes. Strings are not Unicode-normalized.

## License

MIT — see [LICENSE.md](LICENSE.md). Please report vulnerabilities privately, as described in
[SECURITY.md](SECURITY.md).
