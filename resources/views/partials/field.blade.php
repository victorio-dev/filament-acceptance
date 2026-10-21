{{--
    One labelled form field. $bag is the form's error bag; the error is tied to the field with
    aria-describedby (with the help text, if any), and the first invalid field of the page gets
    autofocus (no JavaScript). $value, when given, wins over the old input.
--}}
@php
    $id = 'fa-'.str_replace('_', '-', $name);
    $error = $bag->first($name);
    $invalid = $error !== '';
    $current = $value ?? old($name);
    $describedBy = trim((isset($help) ? $id.'-help ' : '').($invalid ? $id.'-error' : ''));
@endphp
<div class="fa-field">
    <label for="{{ $id }}">{{ $label }}</label>
    @if (($type ?? 'text') === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" maxlength="{{ $maxlength }}"
            @if (isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
            @if ($invalid) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($autofocus) autofocus @endif>{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ $current }}" maxlength="{{ $maxlength }}"
            @if (isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
            @if ($required ?? false) required @endif
            @if ($invalid) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($autofocus) autofocus @endif>
    @endif
    @if (isset($help))
        <p id="{{ $id }}-help" class="fa-help">{{ $help }}</p>
    @endif
    @if ($invalid)
        <p id="{{ $id }}-error" class="fa-error">{{ $error }}</p>
    @endif
</div>
