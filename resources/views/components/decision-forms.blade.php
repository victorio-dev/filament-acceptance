@props(['action', 'consentText', 'consentHash'])
{{--
    The decision form: one form, two buttons, no JavaScript. "Accept" submits it to the accept
    action, with the browser checking the required fields; "Decline" sends it to the decline
    action instead (formaction) without that check (formnovalidate), which asks for a
    confirmation before anything is recorded. Labels for every field, errors tied to their
    fields, the first invalid field focused. The consent hash proves which text was shown.
--}}
@php
    $t = static fn (string $key): string => (string) __('filament-acceptance::filament-acceptance.'.$key);
    $bag = $errors->getBag(\Victorio\Acceptance\Http\DecisionLinkHandler::ERRORS);
    $firstInvalid = collect(['name', 'email', 'comment', 'consent'])->first(fn (string $field): bool => $bag->has($field));
    $maxComment = max(\Victorio\Acceptance\Support\AcceptanceConfig::maxCommentLength(), \Victorio\Acceptance\Support\AcceptanceConfig::maxReasonLength());
@endphp
<section class="fa-panel" aria-labelledby="fa-decision-heading">
    <form method="post" action="{{ $action }}/accept">
        @csrf
        <input type="hidden" name="consent_hash" value="{{ $consentHash }}">
        <h2 id="fa-decision-heading">{{ $t('form.heading') }}</h2>

        @if ($bag->any())
            <p class="fa-error-summary" role="alert">{{ $t('form.errors') }}</p>
        @endif

        @include('filament-acceptance::partials.field', ['bag' => $bag, 'name' => 'name', 'label' => $t('form.name'), 'autocomplete' => 'name', 'required' => true, 'maxlength' => 255, 'autofocus' => $firstInvalid === 'name'])
        @include('filament-acceptance::partials.field', ['bag' => $bag, 'name' => 'email', 'type' => 'email', 'label' => $t('form.email'), 'autocomplete' => 'email', 'required' => true, 'maxlength' => 255, 'autofocus' => $firstInvalid === 'email'])
        @include('filament-acceptance::partials.field', ['bag' => $bag, 'name' => 'comment', 'type' => 'textarea', 'label' => $t('form.comment'), 'help' => $t('form.comment_help'), 'autocomplete' => 'off', 'maxlength' => $maxComment, 'autofocus' => $firstInvalid === 'comment'])

        <div class="fa-check">
            <input id="fa-consent" name="consent" type="checkbox" value="1" required @checked(old('consent'))
                @if ($bag->has('consent')) aria-invalid="true" aria-describedby="fa-consent-error" @endif
                @if ($firstInvalid === 'consent') autofocus @endif>
            <label for="fa-consent">{{ $consentText }}</label>
        </div>
        @if ($bag->has('consent'))
            <p id="fa-consent-error" class="fa-error">{{ $bag->first('consent') }}</p>
        @endif

        <div class="fa-buttons">
            <button type="submit" class="fa-button fa-button-primary">{{ $t('form.accept_button') }}</button>
            <button type="submit" class="fa-button fa-button-secondary" formaction="{{ $action }}/decline" formnovalidate>{{ $t('form.decline_button') }}</button>
        </div>
        <p class="fa-legal">{{ $t('form.legal_note') }}</p>
    </form>
</section>
