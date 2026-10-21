@props(['version'])
{{-- Version number and the first 12 hex digits of the content hash, for the page and the PDF. --}}
<p class="fa-fingerprint">
    {{ __('filament-acceptance::filament-acceptance.page.version', ['number' => $version->number]) }}
    · {{ __('filament-acceptance::filament-acceptance.page.fingerprint') }}
    <code>{{ substr($version->content_hash, 0, 12) }}</code>
</p>
