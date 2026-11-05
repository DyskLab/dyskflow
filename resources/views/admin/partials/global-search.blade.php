<div class="global-search" role="search" data-global-search data-url="{{ route('admin.search') }}">
    {{ icon('search') }}
    <input class="input" type="search" role="combobox" aria-expanded="false" aria-controls="global-search-results" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="{{ __('Search menus, articles, pages…') }}" aria-label="{{ __('Search') }}">
    <kbd aria-hidden="true">⌘K</kbd>
    <div class="global-search-results" id="global-search-results" role="listbox" aria-label="{{ __('Search results') }}" hidden></div>
</div>
