@php $plans = array_filter($w['plans'], fn ($p) => $p['name'] !== ''); @endphp
@if ($plans)
<div class="b-cards b-pricing {{ \App\Support\Builder\Tokens::classes('cards', $w['columns'], ['1', '2', '3', '4']) }}">
    @foreach ($plans as $plan)
        <article class="b-card b-plan{{ $plan['featured'] ? ' b-plan-featured' : '' }}">
            <div class="b-plan-head">
                <h3>{{ $plan['name'] }}</h3>
                @if ($plan['badge'] !== '')<span class="b-plan-badge">{{ $plan['badge'] }}</span>@endif
            </div>
            @if ($plan['price'] !== '')<p class="b-plan-price"><strong>{{ $plan['price'] }}</strong>@if ($plan['period'] !== '')<span>{{ $plan['period'] }}</span>@endif</p>@endif
            @if ($plan['description'] !== '')<p>{{ $plan['description'] }}</p>@endif
            @php $features = array_filter(array_map('trim', preg_split('/\R/', $plan['features']) ?: [])); @endphp
            @if ($features)
                <ul class="b-list b-list-check">
                    @foreach ($features as $feature)<li>{{ icon('check', 18) }}<div><p>{{ $feature }}</p></div></li>@endforeach
                </ul>
            @endif
            @if ($plan['button_label'] !== '' && $plan['url'] !== '')
                <a class="btn b-btn b-btn-lg{{ $plan['featured'] ? '' : ' btn-outline' }}" href="{{ $plan['url'] }}">{{ $plan['button_label'] }}</a>
            @endif
        </article>
    @endforeach
</div>
@endif
