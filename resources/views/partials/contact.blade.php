@if ($email !== null)
    {{-- The sentence is escaped first; only the address becomes a link, itself escaped. --}}
    <p>{!! str_replace(
        '%email%',
        '<a href="mailto:'.e($email).'">'.e($email).'</a>',
        e(__('filament-acceptance::filament-acceptance.contact', ['email' => '%email%'])),
    ) !!}</p>
@endif
