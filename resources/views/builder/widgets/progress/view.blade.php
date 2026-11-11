@php $items = array_values(array_filter($w['items'], fn ($i) => $i['label'] !== '')); @endphp
@if ($items)
<div class="b-progress b-progress-{{ $w['height'] }}" data-b-progress>
    @foreach ($items as $item)
        @php $p = max(0, min(100, (int) $item['percent'])); @endphp
        <div class="b-progress-item">
            <div class="b-progress-head"><span>{{ $item['label'] }}</span>@if ($w['show_percent'])<span>{{ $p }}%</span>@endif</div>
            <div class="b-progress-track" role="progressbar" aria-label="{{ $item['label'] }}" aria-valuenow="{{ $p }}" aria-valuemin="0" aria-valuemax="100"><span style="--b-p: {{ $p }}%; --b-bar: var(--{{ $item['color'] }})"></span></div>
        </div>
    @endforeach
</div>
@endif
