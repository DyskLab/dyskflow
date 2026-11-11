{{-- Loaded into the right sheet: one saved version, read-only. --}}
<div class="sheet-header">
    <h2 class="sheet-title">{{ $name ?? basename($revision->path) }}</h2>
    <p class="muted small">{{ __('Saved before the change on :date by :user.', ['date' => cms_date($revision->created_at), 'user' => $revision->user?->name ?? '—']) }}</p>
</div>
<div class="sheet-content">
    <pre class="revision-content mono">{{ $revision->content }}</pre>
</div>
<div class="sheet-footer">
    <button class="btn btn-outline" type="button" data-sheet-close>{{ __('Close') }}</button>
</div>
