@if ($w['items'])
<dl class="b-counter b-stats {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3', '4']) }}" data-b-counter="{{ (int) $w['duration'] }}">
    @foreach ($w['items'] as $item)
        <div>
            <dt>{{ $item['label'] }}</dt>
            <dd>{{ $item['prefix'] }}<span data-b-count="{{ (int) $item['number'] }}">{{ number_format((int) $item['number'], 0, ',', '.') }}</span>{{ $item['suffix'] }}</dd>
        </div>
    @endforeach
</dl>
@endif
