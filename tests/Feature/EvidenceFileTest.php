<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Victorio\Acceptance\Exceptions\EvidenceAlreadyStored;
use Victorio\Acceptance\Exceptions\EvidenceFileUnavailable;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Support\EvidenceDownload;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('evidence');
    config()->set('filament-acceptance.evidence.disk', 'evidence');
});

it('stores the file on the configured disk with its SHA-256', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF-1.7 quote', 'Q-2026-0042 v1.pdf');

    $stored = Decision::query()->whereKey($decision->getKey())->sole();

    expect($stored->evidence_disk)->toBe('evidence')
        ->and($stored->evidence_hash)->toBe(hash('sha256', '%PDF-1.7 quote'))
        ->and((string) $stored->evidence_path)->toStartWith('victorio/evidence/'.$decision->getKey().'/')
        ->and((string) $stored->evidence_path)->toEndWith('-Q-2026-0042-v1.pdf')
        ->and(Storage::disk('evidence')->get((string) $stored->evidence_path))->toBe('%PDF-1.7 quote')
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($stored->evidenceContents())->toBe('%PDF-1.7 quote')
        ->and($stored->verifyEvidence())->toBeTrue()
        ->and($stored->verify())->toBeTrue();
});

it('keeps finding the file after the configured disk changed', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF', 'quote.pdf');
    config()->set('filament-acceptance.evidence.disk', 'local');

    expect(Decision::query()->whereKey($decision->getKey())->sole()->evidenceContents())->toBe('%PDF');
});

it('fails verification once the file was changed', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF-1.7 quote', 'quote.pdf');
    Storage::disk('evidence')->put((string) $decision->evidence_path, '%PDF-1.7 quotf');

    expect($decision->verifyEvidence())->toBeFalse()
        ->and($decision->verify())->toBeFalse()
        ->and(fn (): string => $decision->evidenceContents())->toThrow(EvidenceFileUnavailable::class, 'no longer matches');
});

it('fails verification once the file is gone', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF', 'quote.pdf');
    Storage::disk('evidence')->delete((string) $decision->evidence_path);

    expect($decision->verify())->toBeFalse()
        ->and(fn (): string => $decision->evidenceContents())->toThrow(EvidenceFileUnavailable::class, 'missing');
});

it('stores an evidence file only once', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF one', 'quote.pdf');

    expect(fn () => $decision->storeEvidence('%PDF two', 'quote.pdf'))->toThrow(EvidenceAlreadyStored::class);

    // A stale copy of the model that has not seen the first write loses at the database.
    $stale = Decision::query()->whereKey($decision->getKey())->sole();
    $stale->setRawAttributes([...$stale->getAttributes(), 'evidence_path' => null]);

    expect(fn () => $stale->storeEvidence('%PDF three', 'quote.pdf'))->toThrow(EvidenceAlreadyStored::class)
        ->and(Decision::query()->whereKey($decision->getKey())->sole()->evidenceContents())->toBe('%PDF one');
});

it('never deletes the file', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF', 'quote.pdf');

    Artisan::call('model:prune', ['--model' => [Decision::class]]);

    expect(fn (): ?bool => $decision->delete())->toThrow(EvidenceIsPermanent::class)
        ->and(Storage::disk('evidence')->exists((string) $decision->evidence_path))->toBeTrue();
});

it('has nothing to verify without a file', function (): void {
    $decision = decide();

    expect($decision->verifyEvidence())->toBeFalse()
        ->and($decision->verify())->toBeTrue()
        ->and(fn (): string => $decision->evidenceContents())->toThrow(EvidenceFileUnavailable::class, 'no evidence file');
});

it('keeps the file name safe', function (): void {
    $decision = decide();
    $decision->storeEvidence('%PDF', '../../etc/passwd árajánlat.pdf');

    expect((string) $decision->evidence_path)->not->toContain('..')
        ->and((string) $decision->evidence_path)->toMatch('#^victorio/evidence/[0-9a-z]{26}/[0-9a-f]{12}-[A-Za-z0-9._-]+$#');
});

it('tells the file\'s name and type', function (): void {
    $decision = decide();

    expect($decision->evidenceFilename())->toBeNull()
        ->and($decision->evidenceMimeType())->toBeNull();

    $decision->storeEvidence('%PDF-1.7 quote', 'Q-2026-0042 v1.pdf');

    expect($decision->evidenceFilename())->toBe('Q-2026-0042-v1.pdf')
        ->and($decision->evidenceMimeType())->toBe('application/pdf');
});

it('tells the type from the contents, not the name', function (): void {
    $decision = decide();
    $decision->storeEvidence('<!DOCTYPE html><html><body>hi</body></html>', 'quote.pdf');

    expect($decision->evidenceMimeType())->toBe('text/html');
});

it('sends a non-ASCII file name with an ASCII fallback', function (): void {
    // The package stores ASCII names; a host subclass or an older row may not.
    $decision = new Decision;
    $decision->setRawAttributes(['evidence_disk' => 'evidence', 'evidence_path' => 'victorio/evidence/x/0123456789ab-árajánlat 100%.pdf']);
    Storage::disk('evidence')->put('victorio/evidence/x/0123456789ab-árajánlat 100%.pdf', '%PDF-1.7');

    expect(EvidenceDownload::headers($decision))->toBe([
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"arajanlat 100-.pdf\"; filename*=utf-8''%C3%A1raj%C3%A1nlat%20100%25.pdf",
        'X-Content-Type-Options' => 'nosniff',
    ]);
});

it('serves only inert types as they are', function (string $type, string $served): void {
    expect(EvidenceDownload::servedType($type))->toBe($served);
})->with([
    ['application/pdf', 'application/pdf'],
    ['image/png', 'image/png'],
    ['text/plain; charset=utf-8', 'text/plain'],
    ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['text/html', 'application/octet-stream'],
    ['TEXT/HTML; charset=utf-8', 'application/octet-stream'],
    ['application/xhtml+xml', 'application/octet-stream'],
    ['image/svg+xml', 'application/octet-stream'],
    ['text/xml', 'application/octet-stream'],
    ['application/xml', 'application/octet-stream'],
    ['text/javascript', 'application/octet-stream'],
    ['application/x-shockwave-flash', 'application/octet-stream'],
    ['application/pdfx', 'application/octet-stream'],
    ['', 'application/octet-stream'],
]);
