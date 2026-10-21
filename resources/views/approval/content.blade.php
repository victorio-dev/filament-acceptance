{{--
    The sealed fields of a record sent for approval (ApprovalLinkHandler), as a definition list.
    Every value is escaped; multi-line text keeps its paragraphs and line breaks.
--}}
@php
    $t = static fn (string $key): string => (string) __('filament-acceptance::filament-acceptance.approval.'.$key);
@endphp
<dl class="fa-approval">
    @foreach ($fields as $field)
        @php
            $value = $field['value'] ?? null;
            $type = $field['type'] ?? 'text';
        @endphp
        <dt>{{ $field['label'] ?? '' }}</dt>
        <dd>
            @if ($value === null || $value === '')
                —
            @elseif ($type === 'boolean')
                {{ $value ? $t('yes') : $t('no') }}
            @elseif ($type === 'multiline')
                @foreach (preg_split('/\R{2,}/', (string) $value) ?: [] as $paragraph)
                    <p>{!! nl2br(e($paragraph)) !!}</p>
                @endforeach
            @else
                {{ is_scalar($value) ? $value : '' }}
            @endif
        </dd>
    @endforeach
</dl>
