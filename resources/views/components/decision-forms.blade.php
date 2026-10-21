@props(['action', 'consentText', 'consentHash'])
{{--
    One primary accept form, and below it declining on demand, in a <details> that opens by
    itself when the decline form has errors. Labels for every field, errors tied to their fields,
    the first invalid field focused, real submit buttons. The consent hash proves which text was
    shown.
--}}
@php
    $t = static fn (string $key): string => (string) __('filament-acceptance::filament-acceptance.'.$key);
    $accept = $errors->getBag('accept');
    $decline = $errors->getBag('decline');
    $firstInvalid = collect(['name', 'email', 'comment', 'consent'])->first(fn (string $field): bool => $accept->has($field))
        ?? collect(['decline_name', 'decline_email', 'reason'])->first(fn (string $field): bool => $decline->has($field));
    $maxComment = \Victorio\Acceptance\Support\AcceptanceConfig::maxCommentLength();
    $maxReason = \Victorio\Acceptance\Support\AcceptanceConfig::maxReasonLength();
@endphp
<section class="fa-panel" aria-labelledby="fa-accept-heading">
    <form method="post" action="{{ $action }}/accept">
        @csrf
        <input type="hidden" name="consent_hash" value="{{ $consentHash }}">
        <h2 id="fa-accept-heading">{{ $t('form.accept_heading') }}</h2>

        @if ($accept->any())
            <p class="fa-error-summary" role="alert">{{ $t('form.errors') }}</p>
        @endif

        @include('filament-acceptance::partials.field', ['bag' => $accept, 'name' => 'name', 'label' => $t('form.name'), 'autocomplete' => 'name', 'required' => true, 'maxlength' => 255, 'autofocus' => $firstInvalid === 'name'])
        @include('filament-acceptance::partials.field', ['bag' => $accept, 'name' => 'email', 'type' => 'email', 'label' => $t('form.email'), 'autocomplete' => 'email', 'required' => true, 'maxlength' => 255, 'autofocus' => $firstInvalid === 'email'])
        @include('filament-acceptance::partials.field', ['bag' => $accept, 'name' => 'comment', 'type' => 'textarea', 'label' => $t('form.comment'), 'autocomplete' => 'off', 'maxlength' => $maxComment, 'autofocus' => $firstInvalid === 'comment'])

        <div class="fa-check">
            <input id="fa-consent" name="consent" type="checkbox" value="1" required @checked(old('consent'))
                @if ($accept->has('consent')) aria-invalid="true" aria-describedby="fa-consent-error" @endif
                @if ($firstInvalid === 'consent') autofocus @endif>
            <label for="fa-consent">{{ $consentText }}</label>
        </div>
        @if ($accept->has('consent'))
            <p id="fa-consent-error" class="fa-error">{{ $accept->first('consent') }}</p>
        @endif

        <button type="submit" class="fa-button fa-button-primary">{{ $t('form.accept_button') }}</button>
        <p class="fa-legal">{{ $t('form.legal_note') }}</p>
    </form>
</section>

{{-- Declining is on demand: closed unless its own form came back with errors. No JavaScript. --}}
<details class="fa-decline" @if ($decline->any()) open @endif>
    <summary>{{ $t('form.decline_summary') }}</summary>

    <form method="post" action="{{ $action }}/decline">
        @csrf

        @if ($decline->any())
            <p class="fa-error-summary" role="alert">{{ $t('form.errors') }}</p>
        @endif

        @include('filament-acceptance::partials.field', ['bag' => $decline, 'name' => 'decline_name', 'label' => $t('form.decline_name'), 'autocomplete' => 'name', 'maxlength' => 255, 'autofocus' => $firstInvalid === 'decline_name'])
        @include('filament-acceptance::partials.field', ['bag' => $decline, 'name' => 'decline_email', 'type' => 'email', 'label' => $t('form.decline_email'), 'autocomplete' => 'email', 'maxlength' => 255, 'autofocus' => $firstInvalid === 'decline_email'])
        @include('filament-acceptance::partials.field', ['bag' => $decline, 'name' => 'reason', 'type' => 'textarea', 'label' => $t('form.reason'), 'autocomplete' => 'off', 'maxlength' => $maxReason, 'autofocus' => $firstInvalid === 'reason'])

        <button type="submit" class="fa-button fa-button-secondary">{{ $t('form.decline_button') }}</button>
    </form>
</details>
