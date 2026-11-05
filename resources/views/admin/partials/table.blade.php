@php
    $config = $t->config;
    $rows = $t->rows;
    $hasFilters = request()->filled('q') || collect(request('f', []))->filter()->isNotEmpty();
    $columnCount = count($config['columns']) + 1 + (isset($config['row_actions']) ? 1 : 0);
@endphp
<div class="dt" data-table>
    <form class="dt-toolbar" method="get" action="{{ url()->current() }}">
        @if (request('sort'))
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="dir" value="{{ request('dir') }}">
        @endif
        @if (! empty($config['search']))
            <input class="input dt-search" type="search" name="q" autocomplete="off" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
        @endif
        @foreach ($config['filters'] ?? [] as $key => $filter)
            <select class="select" name="f[{{ $key }}]" aria-label="{{ $filter['label'] }}" data-auto-submit>
                <option value="">{{ $filter['label'] }}: {{ __('All') }}</option>
                @foreach ($filter['options'] as $value => $label)
                    <option value="{{ $value }}" @selected($t->filterValue($key) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        @endforeach
        <button class="btn btn-outline" type="submit">{{ __('Filter') }}</button>
        @if ($hasFilters)<a class="btn btn-ghost" href="{{ url()->current() }}">{{ __('Reset') }}</a>@endif
    </form>

    <form method="post" action="{{ $config['bulk_url'] }}" id="bulk-{{ $config['id'] }}">
        @csrf
        <div class="dt-bulk" data-bulk-bar hidden style="margin-bottom:12px">
            <span><strong data-bulk-count>0</strong> {{ __('selected') }}</span>
            @foreach ($config['bulk'] ?? [] as $action => $bulk)
                <button class="btn btn-sm {{ ! empty($bulk['danger']) ? 'btn-danger' : 'btn-outline' }}" type="submit" name="action" value="{{ $action }}"
                    @if (! empty($bulk['danger']) || ! empty($bulk['confirm'])) data-confirm="{{ $bulk['confirm'] ?? __('This action cannot be undone.') }}" @endif>{{ $bulk['label'] }}</button>
            @endforeach
        </div>

        <div class="dt-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="col-check"><input class="checkbox" type="checkbox" data-check-all aria-label="{{ __('Select all') }}"></th>
                        @foreach ($config['columns'] as $column)
                            <th class="{{ $column['class'] ?? '' }}" @if ($t->sort === $column['key']) aria-sort="{{ $t->dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                @if (! empty($column['sort']))
                                    <a class="sort-link" href="{{ $t->sortUrl($column['key']) }}">
                                        {{ $column['label'] }}
                                        @if ($t->sort === $column['key'])
                                            {{ icon($t->dir === 'asc' ? 'up' : 'down', 14) }}
                                        @else
                                            <span class="sort-idle">{{ icon('sort', 14) }}</span>
                                        @endif
                                    </a>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                        @isset($config['row_actions'])<th class="col-actions"><span class="sr-only">{{ __('Actions') }}</span></th>@endisset
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="col-check"><input class="checkbox" type="checkbox" name="ids[]" value="{{ $t->rowId($row) }}" data-row-check aria-label="{{ __('Select row') }}"></td>
                            @foreach ($config['columns'] as $column)
                                <td class="{{ $column['class'] ?? '' }}">{{ $t->cell($column, $row) }}</td>
                            @endforeach
                            @isset($config['row_actions'])
                                <td class="col-actions">@include('admin.partials.row-actions', ['actions' => $config['row_actions']($row)])</td>
                            @endisset
                        </tr>
                    @empty
                        <tr><td class="dt-empty" colspan="{{ $columnCount }}">{{ $config['empty'] ?? __('No data yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="dt-footer">
        <div class="row">
            <span class="muted small">{{ __('Showing :from–:to of :total', ['from' => $rows->firstItem() ?? 0, 'to' => $rows->lastItem() ?? 0, 'total' => $rows->total()]) }}</span>
            <form method="get" action="{{ url()->current() }}" class="row">
                @foreach (request()->except(['per_page', 'page']) as $name => $value)
                    @if (is_array($value))
                        @foreach ($value as $subKey => $subValue)
                            @if (is_string($subValue))<input type="hidden" name="{{ $name }}[{{ $subKey }}]" value="{{ $subValue }}">@endif
                        @endforeach
                    @elseif (is_string($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <select class="select" style="width:auto;min-height:32px;padding:4px 8px" name="per_page" aria-label="{{ __('Rows per page') }}" data-auto-submit>
                    @foreach (\App\Support\DataTable::PER_PAGE as $size)
                        <option value="{{ $size }}" @selected($rows->perPage() === $size)>{{ $size }} / {{ __('page') }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if ($rows->lastPage() > 1)
            <nav class="pagination" aria-label="{{ __('Page navigation') }}">
                <a class="btn btn-outline btn-icon btn-sm" href="{{ $rows->previousPageUrl() ?? '#' }}" @if ($rows->onFirstPage()) aria-disabled="true" style="opacity:.5;pointer-events:none" @endif aria-label="{{ __('Previous') }}">{{ icon('left') }}</a>
                @foreach (range(max(1, $rows->currentPage() - 2), min($rows->lastPage(), $rows->currentPage() + 2)) as $page)
                    <a class="btn btn-sm btn-icon {{ $page === $rows->currentPage() ? 'btn-outline' : 'btn-ghost' }}" href="{{ $rows->url($page) }}" @if ($page === $rows->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                @endforeach
                <a class="btn btn-outline btn-icon btn-sm" href="{{ $rows->nextPageUrl() ?? '#' }}" @if (! $rows->hasMorePages()) aria-disabled="true" style="opacity:.5;pointer-events:none" @endif aria-label="{{ __('Next') }}">{{ icon('right') }}</a>
            </nav>
        @endif
    </div>
</div>
