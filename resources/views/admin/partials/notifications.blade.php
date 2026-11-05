@php
    $notifications = \App\Support\Notifier::forHeader(auth()->user());
    $unread = $notifications['unread'];
@endphp
<div class="notif" data-notifications @if (\App\Support\Notifier::updateCheckDue(auth()->user())) data-check-url="{{ route('admin.notifications.check') }}" @endif>
    <button class="btn btn-ghost btn-icon btn-sm notif-toggle" type="button" aria-expanded="false" aria-controls="notif-panel" aria-label="{{ $unread ? __('Notifications, :count unread', ['count' => $unread]) : __('Notifications') }}" title="{{ __('Notifications') }}">
        {{ icon('bell') }}
        @if ($unread)<span class="notif-count" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
    </button>
    <div class="notif-panel" id="notif-panel" hidden>
        <div class="notif-head">
            <strong>{{ __('Notifications') }}</strong>
            @if ($unread)
                <form method="post" action="{{ route('admin.notifications.read-all') }}">@csrf
                    <button class="btn btn-ghost btn-sm" type="submit">{{ __('Mark all as read') }}</button>
                </form>
            @endif
        </div>
        <div class="notif-list">
            @forelse ($notifications['items'] as $notification)
                <a class="notif-item {{ $notification->is_read ? '' : 'is-unread' }} notif-{{ $notification->type }}" href="{{ route('admin.notifications.open', $notification->id) }}">
                    <span class="notif-icon">{{ icon($notification->iconName()) }}</span>
                    <span class="notif-text">
                        <span class="notif-title">{{ $notification->titleText() }}@if ($notification->count > 1) ×{{ $notification->count }}@endif</span>
                        @if ($notification->body)<span class="notif-body">{{ $notification->body }}</span>@endif
                        <span class="notif-time">{{ cms_date($notification->updated_at) }}</span>
                    </span>
                </a>
            @empty
                <div class="notif-empty">{{ __('No notifications yet.') }}</div>
            @endforelse
        </div>
        <a class="notif-all" href="{{ route('admin.notifications.index') }}">{{ __('All notifications') }}</a>
    </div>
</div>
