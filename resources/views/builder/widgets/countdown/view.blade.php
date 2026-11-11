@php
    try { $end = $w['date'] !== '' ? \Illuminate\Support\Carbon::parse($w['date'], setting('timezone', 'UTC')) : null; } catch (\Throwable) { $end = null; }
    $left = $end ? max(0, $end->getTimestamp() - time()) : 0;
    $units = ['days' => [intdiv($left, 86400), __('Days')], 'hours' => [intdiv($left % 86400, 3600), __('Hours')], 'minutes' => [intdiv($left % 3600, 60), __('Minutes')]];
    if ($w['seconds']) $units['seconds'] = [$left % 60, __('Seconds')];
@endphp
@if ($end)
<div class="b-countdown b-countdown-{{ $w['variant'] }}" data-b-countdown="{{ $end->getTimestamp() }}" role="timer" aria-label="{{ __('Time left until :date', ['date' => cms_date($end, 'd M Y H:i')]) }}">
    <div class="b-countdown-units" @if ($left === 0 && $w['ended'] !== '') hidden @endif>
        @foreach ($units as $key => [$value, $label])
            <div class="b-countdown-unit"><span data-b-unit="{{ $key }}">{{ str_pad((string) $value, 2, '0', STR_PAD_LEFT) }}</span>@if ($w['labels'])<small>{{ $label }}</small>@endif</div>
        @endforeach
    </div>
    @if ($w['ended'] !== '')<p class="b-countdown-ended" @if ($left > 0) hidden @endif>{{ $w['ended'] }}</p>@endif
</div>
@elseif ($preview)
<p class="b-note">{{ __('Enter a date such as 2026-12-31 19:00.') }}</p>
@endif
