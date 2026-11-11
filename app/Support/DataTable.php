<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * The only way admin tables are built, so every table gets checkboxes,
 * pagination, sorting and filtering. Works with a query or a collection.
 *
 * Config: source, columns[key,label,sort,render,class], search[], filters[key=>[label,options,apply]],
 * default_sort[key,dir], bulk_url, bulk[action=>[label,danger]], row_actions, row_id, empty,
 * grid (fn ($row) => HtmlString: the tile of a row in gallery view), default_view (list|grid).
 */
class DataTable
{
    public const PER_PAGE = [10, 25, 50, 100];

    public LengthAwarePaginator $rows;

    public string $sort = '';

    public string $dir = 'asc';

    /** "list" (the table) or "grid" (tiles, for tables with a "grid" renderer such as Media). */
    public string $view = 'list';

    public function __construct(public array $config)
    {
        if (isset($config['grid'])) {
            $requested = request()->query('view');
            $this->view = in_array($requested, ['list', 'grid'], true) ? $requested : (($config['default_view'] ?? 'list') === 'grid' ? 'grid' : 'list');
        }
        $this->rows = $this->build();
    }

    public function viewUrl(string $view): string
    {
        return request()->fullUrlWithQuery(['view' => $view]);
    }

    /** @return list<array> the columns that can be sorted, for the sort menu of the gallery view */
    public function sortable(): array
    {
        return array_values(array_filter($this->config['columns'], fn ($c) => ! empty($c['sort'])));
    }

    public static function make(array $config): self
    {
        return new self($config);
    }

    public function render(): HtmlString
    {
        return new HtmlString(view('admin.partials.table', ['t' => $this])->render());
    }

    public function rowId(mixed $row): string
    {
        $resolver = $this->config['row_id'] ?? null;

        return (string) ($resolver instanceof Closure ? $resolver($row) : data_get($row, 'id'));
    }

    public function cell(array $column, mixed $row): mixed
    {
        return isset($column['render']) ? $column['render']($row) : data_get($row, $column['key']);
    }

    public function sortUrl(string $key): string
    {
        $dir = $this->sort === $key && $this->dir === 'asc' ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir, 'page' => null]);
    }

    public function filterValue(string $key): string
    {
        $value = request()->input("f.$key");

        return is_string($value) ? $value : '';
    }

    private function build(): LengthAwarePaginator
    {
        $perPage = (int) request('per_page', 25);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 25;
        $this->resolveSort();
        $source = $this->config['source'];

        return ($source instanceof Collection ? $this->fromCollection($source, $perPage) : $this->fromQuery($source, $perPage))
            ->withQueryString();
    }

    /** Only keys declared as sortable are accepted, so the request cannot pick arbitrary columns. */
    private function resolveSort(): void
    {
        $sortable = collect($this->config['columns'])->filter(fn ($c) => ! empty($c['sort']))->keyBy('key');
        [$defaultKey, $defaultDir] = $this->config['default_sort'] ?? [null, 'desc'];
        $requested = request('sort');

        $this->sort = is_string($requested) && $sortable->has($requested) ? $requested : (string) $defaultKey;
        $this->dir = $requested && $sortable->has($requested)
            ? (request('dir') === 'desc' ? 'desc' : 'asc')
            : $defaultDir;
    }

    private function fromQuery(BuilderContract $query, int $perPage): LengthAwarePaginator
    {
        $term = trim((string) (is_string(request('q')) ? request('q') : ''));
        if ($term !== '' && ! empty($this->config['search'])) {
            $query->where(function ($q) use ($term) {
                foreach ($this->config['search'] as $column) {
                    $q->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        foreach ($this->config['filters'] ?? [] as $key => $filter) {
            $value = $this->filterValue($key);
            if ($value === '' || ! array_key_exists($value, $filter['options'])) {
                continue;
            }
            isset($filter['apply']) ? $filter['apply']($query, $value) : $query->where($filter['column'] ?? $key, $value);
        }

        if ($this->sort !== '') {
            $column = collect($this->config['columns'])->firstWhere('key', $this->sort);
            $sortColumn = is_string($column['sort'] ?? null) ? $column['sort'] : $this->sort;
            $query->orderBy($sortColumn, $this->dir);
        }

        return $query->paginate($perPage);
    }

    private function fromCollection(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $term = trim((string) (is_string(request('q')) ? request('q') : ''));
        if ($term !== '') {
            $rows = $rows->filter(function ($row) use ($term) {
                foreach ($this->config['search'] ?? [] as $key) {
                    if (stripos((string) data_get($row, $key), $term) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        foreach ($this->config['filters'] ?? [] as $key => $filter) {
            $value = $this->filterValue($key);
            if ($value !== '' && array_key_exists($value, $filter['options'])) {
                $rows = $rows->filter(fn ($row) => (string) data_get($row, $filter['column'] ?? $key) === $value);
            }
        }

        if ($this->sort !== '') {
            $rows = $rows->sortBy(fn ($row) => data_get($row, $this->sort), SORT_NATURAL | SORT_FLAG_CASE, $this->dir === 'desc');
        }

        $page = max(1, (int) request('page', 1));

        return new LengthAwarePaginator(
            $rows->values()->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url()],
        );
    }
}
