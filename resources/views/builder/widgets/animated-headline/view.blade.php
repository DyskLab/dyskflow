@php $words = array_values(array_filter(array_map('trim', preg_split('/\R/', $w['words'])))); @endphp
@if ($words)
<{{ $w['tag'] }} class="b-heading b-animhead b-animhead-{{ $w['effect'] }} {{ \App\Support\Builder\Tokens::classes('size', $w['size'], ['md', 'lg', 'xl', '2xl']) }}" @if ($w['effect'] !== 'highlight' && count($words) > 1) data-b-rotate="{{ $w['effect'] }}" @endif>
    @if ($w['before'] !== '')<span>{{ $w['before'] }}</span>@endif
    @if ($w['effect'] === 'highlight')
        <span class="b-animhead-word is-on">{{ $words[0] }}<svg viewBox="0 0 300 20" preserveAspectRatio="none" aria-hidden="true"><path d="M3 15c60-9 150-12 294-6"/></svg></span>
    @else
        <span class="b-animhead-words" aria-hidden="true">@foreach ($words as $i => $word)<span class="b-animhead-word{{ $i === 0 ? ' is-on' : '' }}">{{ $word }}</span>@endforeach</span>
        <span class="sr-only">{{ implode(', ', $words) }}</span>
    @endif
    @if ($w['after'] !== '')<span>{{ $w['after'] }}</span>@endif
</{{ $w['tag'] }}>
@endif
