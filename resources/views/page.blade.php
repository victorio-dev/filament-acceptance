{{--
    The public page of a sealed version (DecisionLinkHandler::show()), in the public-links layout:
    the consumer's content, the version fingerprint, and the forms or the decision. Every value is
    escaped; $content is the consumer's own Htmlable.
--}}
@php
    $notices = ['accepted', 'declined', 'already_decided', 'closed'];
@endphp
<x-filament-public-links::layout :link="$visit->link" :page-title="$title">
    <x-slot:title>{{ $title }}</x-slot:title>

    <x-filament-acceptance::styles />

    @if ($notice !== null && in_array($notice, $notices, true))
        <p class="fa-notice" role="status">{{ __('filament-acceptance::filament-acceptance.notice.'.$notice) }}</p>
    @endif

    <div class="fa-content">{{ $content }}</div>

    <x-filament-acceptance::fingerprint :version="$version" />

    @if ($decision !== null)
        <x-filament-acceptance::decision-summary
            :decision="$decision"
            :decided-at="$decidedAt"
            :evidence-action="$decision->evidence_path !== null ? $action.'/evidence' : null"
        />
    @elseif ($actionable)
        <x-filament-acceptance::decision-forms :action="$action" :consent-text="$consentText" :consent-hash="$consentHash" />
    @else
        @if ($notice !== 'closed')
            <p class="fa-notice" role="status">{{ __('filament-acceptance::filament-acceptance.notice.closed') }}</p>
        @endif
        @include('filament-acceptance::partials.contact', ['email' => $contactEmail])
    @endif
</x-filament-public-links::layout>
