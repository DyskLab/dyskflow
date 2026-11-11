@php $items = array_values(array_filter($w['items'], fn ($i) => $i['title'] !== '')); $group = 'acc-'.substr(md5(json_encode($items)), 0, 8); @endphp
@if ($items)
<div class="b-accordion b-accordion-{{ $w['variant'] }}">
    @foreach ($items as $i => $item)
        <details class="b-accordion-item"@if ($w['one_open']) name="{{ $group }}"@endif{{ $i === 0 && $w['open_first'] ? ' open' : '' }}>
            <summary>{{ $item['title'] }}</summary>
            <div class="b-accordion-body b-text">{{ $item['body'] }}</div>
        </details>
    @endforeach
</div>
@endif
