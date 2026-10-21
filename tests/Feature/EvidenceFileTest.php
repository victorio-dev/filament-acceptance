<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Victorio\Acceptance\Exceptions\EvidenceAlreadyStored;
use Victorio\Acceptance\Exceptions\EvidenceFileUnavailable;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\Decision;

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
