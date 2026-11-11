@php $items = array_filter($w['items'], fn ($i) => $i['value'] !== ''); @endphp
@if ($items)
<dl class="b-stats {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['2', '3', '4']) }}">
    @foreach ($items as $item)
        <div><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}</dd></div>
    @endforeach
</dl>
@endif
