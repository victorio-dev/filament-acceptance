{{--
    A decision page that cannot show the version: superseded (410) or failing verification (409).
--}}
@php
    $heading = __('filament-acceptance::filament-acceptance.status.'.$kind.'.title');
@endphp
<x-filament-public-links::layout :link="$visit->link" :page-title="$heading">
    <x-slot:title>{{ $heading }}</x-slot:title>

    <p>{{ __('filament-acceptance::filament-acceptance.status.'.$kind.'.body') }}</p>

    @include('filament-acceptance::partials.contact', ['email' => $contactEmail])
</x-filament-public-links::layout>
