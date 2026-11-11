@php $buttons = array_filter($w['buttons'], fn ($b) => $b['label'] !== '' && $b['url'] !== ''); @endphp
@if ($buttons)
<div class="b-buttons">
    @foreach ($buttons as $b)
        <a class="btn b-btn b-btn-{{ $w['size'] }}{{ $b['variant'] === 'primary' ? '' : ' btn-'.$b['variant'] }}" href="{{ $b['url'] }}"@if ($b['new_tab']) target="_blank" rel="noopener"@endif>{{ $b['label'] }}</a>
    @endforeach
</div>
@endif
