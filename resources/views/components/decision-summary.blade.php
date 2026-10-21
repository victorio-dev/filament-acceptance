@props(['decision', 'decidedAt', 'evidenceAction' => null])
{{-- The read-only confirmation after a decision; the evidence file is offered when there is one. --}}
@php
    $accepted = $decision->isAccepted();
    $key = ($accepted ? 'accepted' : 'declined').($decision->name !== null ? '_by' : '');
    $note = $accepted ? $decision->comment : $decision->reason;
@endphp
<section class="fa-panel fa-summary" aria-labelledby="fa-summary-heading">
    <h2 id="fa-summary-heading">{{ __('filament-acceptance::filament-acceptance.summary.'.$key, ['name' => $decision->name ?? '', 'date' => $decidedAt]) }}</h2>

    @if ($note !== null)
        <dl>
            <dt>{{ __('filament-acceptance::filament-acceptance.summary.'.($accepted ? 'comment' : 'reason')) }}</dt>
            <dd>{{ $note }}</dd>
        </dl>
    @endif

    @if ($evidenceAction !== null)
        <form method="post" action="{{ $evidenceAction }}">
            @csrf
            <button type="submit" class="fa-button fa-button-secondary">{{ __('filament-acceptance::filament-acceptance.summary.download') }}</button>
        </form>
    @endif
</section>
