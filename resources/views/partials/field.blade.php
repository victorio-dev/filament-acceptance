{{--
    One labelled form field. $bag is the form's error bag; the error is tied to the field with
    aria-describedby, and the first invalid field of the page gets autofocus (no JavaScript).
--}}
@php
    $id = 'fa-'.str_replace('_', '-', $name);
    $error = $bag->first($name);
    $invalid = $error !== '';
@endphp
<div class="fa-field">
    <label for="{{ $id }}">{{ $label }}</label>
    @if (($type ?? 'text') === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" maxlength="{{ $maxlength }}"
            @if (isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if ($autofocus) autofocus @endif>{{ old($name) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ old($name) }}" maxlength="{{ $maxlength }}"
            @if (isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
            @if ($required ?? false) required @endif
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if ($autofocus) autofocus @endif>
    @endif
    @if ($invalid)
        <p id="{{ $id }}-error" class="fa-error">{{ $error }}</p>
    @endif
</div>
