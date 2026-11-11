@php $items = array_values(array_filter($w['items'], fn ($i) => $i['title'] !== '')); $uid = 'tab-'.substr(md5(json_encode($items)), 0, 8); @endphp
@if ($items)
<div class="b-tabs b-tabs-{{ $w['layout'] }}" data-b-tabs>
    <div class="b-tabs-list" role="tablist">
        @foreach ($items as $i => $item)
            <button type="button" role="tab" id="{{ $uid }}-t{{ $i }}" aria-controls="{{ $uid }}-p{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}">{{ $item['title'] }}</button>
        @endforeach
    </div>
    @foreach ($items as $i => $item)
        <div class="b-tabs-panel b-text" role="tabpanel" id="{{ $uid }}-p{{ $i }}" aria-labelledby="{{ $uid }}-t{{ $i }}" tabindex="0" @if ($i > 0) hidden @endif>{{ $item['body'] }}</div>
    @endforeach
</div>
@endif
