{{--
    The second step of declining (DecisionLinkHandler, action "decline"): nothing is recorded yet.
    "Yes, decline" posts to "decline-confirm"; "Back" posts to "back", which returns to the form
    with what was typed. The values are editable here.
--}}
@php
    $t = static fn (string $key): string => (string) __('filament-acceptance::filament-acceptance.'.$key);
    $noErrors = new \Illuminate\Support\MessageBag;
@endphp
<x-filament-public-links::layout :link="$visit->link" :page-title="$t('decline_confirm.title')">
    <x-slot:title>{{ $t('decline_confirm.title') }}</x-slot:title>

    <x-filament-acceptance::styles />

    <p class="fa-subject">{{ $title }}</p>
    <x-filament-acceptance::fingerprint :version="$version" />

    <section class="fa-panel" aria-labelledby="fa-decline-warning">
        <form method="post" action="{{ $action }}/decline-confirm">
            @csrf
            <p id="fa-decline-warning" class="fa-warning">{{ $t('decline_confirm.warning') }}</p>

            @include('filament-acceptance::partials.field', ['bag' => $noErrors, 'name' => 'name', 'value' => $values['name'] ?? '', 'label' => $t('form.decline_name'), 'autocomplete' => 'name', 'maxlength' => 255, 'autofocus' => false])
            @include('filament-acceptance::partials.field', ['bag' => $noErrors, 'name' => 'email', 'value' => $values['email'] ?? '', 'type' => 'email', 'label' => $t('form.decline_email'), 'autocomplete' => 'email', 'maxlength' => 255, 'autofocus' => false])
            @include('filament-acceptance::partials.field', ['bag' => $noErrors, 'name' => 'comment', 'value' => $values['comment'] ?? '', 'type' => 'textarea', 'label' => $t('decline_confirm.reason'), 'autocomplete' => 'off', 'maxlength' => \Victorio\Acceptance\Support\AcceptanceConfig::maxReasonLength(), 'autofocus' => false])

            <div class="fa-buttons">
                <button type="submit" class="fa-button fa-button-decline">{{ $t('decline_confirm.submit') }}</button>
                <button type="submit" class="fa-button fa-button-secondary" formaction="{{ $action }}/back" formnovalidate>{{ $t('decline_confirm.back') }}</button>
            </div>
        </form>
    </section>
</x-filament-public-links::layout>
