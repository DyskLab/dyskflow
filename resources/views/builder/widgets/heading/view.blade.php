@if ($w['text'] !== '')
@if ($w['kicker'] !== '')<p class="b-kicker">{{ $w['kicker'] }}</p>@endif
<{{ $w['tag'] }} class="b-heading {{ \App\Support\Builder\Tokens::classes('size', $w['size'], ['sm', 'md', 'lg', 'xl', '2xl']) }}">{{ $w['text'] }}</{{ $w['tag'] }}>
@endif
