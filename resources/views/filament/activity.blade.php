{{--
    The activity of a record in a slide-over: the last 50 audit entries. No personal data and no
    IP addresses are kept in the log, so none can show here.
--}}
@php
    $t = static fn (string $key, array $replace = []): string => (string) __('filament-acceptance::filament-acceptance.filament.'.$key, $replace);
    $eventLabel = static function (string $event) use ($t): string {
        $key = 'filament-acceptance::filament-acceptance.filament.events.'.$event;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $event;
    };
@endphp
@if ($entries->isEmpty())
    <p>{{ $t('actions.activity.empty') }}</p>
@else
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: start;">
                <th scope="col" style="padding: .5rem; text-align: start;">{{ $t('actions.activity.time') }}</th>
                <th scope="col" style="padding: .5rem; text-align: start;">{{ $t('actions.activity.event') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td style="padding: .5rem; white-space: nowrap;">{{ $date($entry->occurred_at) }}</td>
                    <td style="padding: .5rem;">{{ $eventLabel($entry->event) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
