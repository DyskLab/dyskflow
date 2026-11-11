<div class="b-readbar b-readbar-{{ $w['position'] }} b-readbar-{{ $w['height'] }}" style="--b-bar: var(--{{ $w['color'] }})" data-b-readbar aria-hidden="true"><span></span></div>
@if ($preview)<p class="b-note">{{ __('A bar at the :position of the screen fills as visitors scroll.', ['position' => $w['position'] === 'top' ? __('top') : __('bottom')]) }}</p>@endif
