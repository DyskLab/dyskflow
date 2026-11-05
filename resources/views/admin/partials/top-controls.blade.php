<form method="post" action="{{ route('locale', app()->getLocale() === 'id' ? 'en' : 'id') }}">
    <button class="btn btn-ghost btn-sm" type="submit" title="{{ __('Change language') }}">
        {{ icon('languages') }} {{ app()->getLocale() === 'id' ? 'EN' : 'ID' }}
    </button>
</form>
@include('admin.partials.theme-toggle')
