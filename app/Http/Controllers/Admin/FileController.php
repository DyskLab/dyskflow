<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileRevision;
use App\Support\DataTable;
use App\Support\FileConflictException;
use App\Support\Landing;
use App\Support\Locales;
use App\Support\SafeFiles;
use App\Support\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Developer > Explorer: one place for all code. Browse, edit, create, rename, delete and upload
 * inside config('cms.design_roots') (landing/, theme/, plugins/). Landing folders show their
 * title and status, theme/ links to the default theme files (DesignController::theme), and the
 * top level offers the site's custom CSS and JavaScript (Theme::CUSTOM).
 * PHP, Blade and SVG need code.manage, CMS_CODE_EDITOR on, and a password or TOTP check in the last 15 minutes.
 */
class FileController extends Controller
{
    public const CONFIRM_MINUTES = 15;

    private const CONFIRM_KEY = 'design.code_confirmed_at';

    public function index(Request $request)
    {
        $dir = trim((string) $request->query('dir', ''), '/');
        $folder = $dir === '' ? null : SafeFiles::resolve($dir);
        abort_if($folder && ! $folder['is_dir'], 404);

        $entries = $dir === '' ? $this->topLevel() : collect(SafeFiles::entries($dir));
        if ($dir === 'landing') {
            $entries = $this->withLandingInfo($entries);
        }
        $canCode = $this->canCode();

        $actions = $dir === '' ? [] : [
            ...(explode('/', $dir)[0] === 'theme' && Theme::enabled() ? [['label' => __('Default theme files'), 'icon' => 'code', 'secondary' => true, 'url' => route('admin.design.theme.index')]] : []),
            ['label' => __('Upload'), 'icon' => 'upload', 'secondary' => true, 'sheet' => route('admin.design.files.upload', ['dir' => $dir])],
            ['label' => __('New folder'), 'icon' => 'folder', 'secondary' => true, 'sheet' => route('admin.design.files.create', ['dir' => $dir, 'kind' => 'folder'])],
            ['label' => __('New file'), 'icon' => 'plus', 'sheet' => route('admin.design.files.create', ['dir' => $dir, 'kind' => 'file'])],
        ];

        $roots = collect(SafeFiles::roots())->map(fn (string $path, string $key) => [
            'key' => $key,
            'writable' => is_dir($path) ? is_writable($path) : is_writable(dirname($path)),
        ]);

        return view('admin.design.files', [
            'title' => __('Explorer'),
            'dir' => $dir,
            'crumbs' => $this->crumbs($dir),
            'actions' => $actions,
            'unwritable' => $roots->where('writable', false)->pluck('key'),
            'canCode' => $canCode,
            'table' => DataTable::make([
                'id' => 'files',
                'source' => $entries->map(fn ($entry) => $entry + ['kind' => $entry['type'] === 'dir' ? 'dir' : ($entry['requires_code'] ? 'code' : ($entry['editable'] ? 'text' : 'asset'))]),
                'row_id' => fn ($entry) => $entry['path'],
                'columns' => [
                    ['key' => 'name', 'label' => __('Name'), 'sort' => true, 'render' => fn ($entry) => $this->nameCell($entry)],
                    ['key' => 'size', 'label' => __('Size'), 'sort' => true, 'class' => 'nowrap', 'render' => fn ($entry) => $entry['type'] === 'dir' ? '—' : Number::fileSize($entry['size'])],
                    ['key' => 'modified', 'label' => __('Changed'), 'sort' => true, 'class' => 'nowrap', 'render' => fn ($entry) => $entry['modified'] ? cms_date('@'.$entry['modified']) : '—'],
                ],
                'search' => ['name'],
                'filters' => ['kind' => ['label' => __('Type'), 'options' => ['dir' => __('Folder'), 'text' => __('Text file'), 'code' => __('Code'), 'asset' => __('Image or font')]]],
                'default_sort' => ['name', 'asc'],
                'bulk_url' => route('admin.design.files.index'),
                'row_actions' => fn ($entry) => $dir === '' ? [] : [
                    isset($entry['view_url']) ? ['label' => __('View'), 'icon' => 'external', 'blank' => true, 'url' => $entry['view_url']] : null,
                    $entry['editable'] && (! $entry['requires_code'] || $canCode) ? ['label' => __('Edit'), 'icon' => 'pencil', 'url' => route('admin.design.files.edit', ['path' => $entry['path']])] : null,
                    $entry['editable'] ? ['label' => __('Revisions'), 'icon' => 'restore', 'url' => route('admin.design.files.revisions', ['path' => $entry['path']])] : null,
                    ! $entry['requires_code'] || $canCode ? ['label' => __('Rename'), 'icon' => 'tag', 'sheet' => route('admin.design.files.rename', ['path' => $entry['path']])] : null,
                    ! $entry['requires_code'] || $canCode ? ['label' => __('Delete'), 'icon' => 'trash', 'post' => route('admin.design.files.delete'), 'fields' => ['path' => $entry['path']], 'danger' => true,
                        'confirm' => $entry['type'] === 'dir' ? __('Delete the folder ":title"? Only an empty folder can be deleted.', ['title' => $entry['name']]) : __('Delete ":title"? Its content is kept as a revision.', ['title' => $entry['name']])] : null,
                ],
                'empty' => __('This folder is empty.'),
            ]),
        ]);
    }

    public function edit(Request $request)
    {
        $path = (string) $request->query('path');
        $file = SafeFiles::resolve($path);
        abort_unless($file['is_file'] && SafeFiles::editable($path), 404);
        if ($redirect = $this->guard($request, $path)) {
            return $redirect;
        }

        try {
            $content = SafeFiles::read($path);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return redirect()->route('admin.design.files.index', ['dir' => dirname($path)])->with('error', $e->getMessage());
        }

        $code = SafeFiles::requiresCode($path);

        return view('admin.design.editor', [
            'title' => basename($path),
            'path' => $path,
            'crumbs' => $this->crumbs(dirname($path)),
            'content' => $content,
            'hash' => hash('sha256', $content),
            'language' => SafeFiles::language($path),
            'size' => strlen($content),
            'viewUrl' => SafeFiles::publicUrl($path),
            'unchecked' => $code && SafeFiles::extension($path) !== 'svg' && ! SafeFiles::canLint(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => 'required|string|max:500',
            'content' => 'present|nullable|string',
            'hash' => 'nullable|string|size:64',
            'force' => 'boolean',
        ]);
        $path = $data['path'];
        if ($this->guard($request, $path)) {
            return response()->json(['message' => __('Confirm your password to keep editing code.'), 'confirm' => route('admin.design.confirm', ['return' => route('admin.design.files.edit', ['path' => $path])])], 423);
        }

        try {
            $hash = SafeFiles::write($path, (string) ($data['content'] ?? ''), ! empty($data['force']) ? null : ($data['hash'] ?? null));
        } catch (FileConflictException $e) {
            return response()->json(['message' => $e->getMessage(), 'conflict' => true], 409);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        activity('file.saved', $path);

        return response()->json(['message' => __('Saved.'), 'hash' => $hash, 'size' => Number::fileSize(strlen((string) ($data['content'] ?? '')))]);
    }

    public function createForm(Request $request)
    {
        $dir = (string) $request->query('dir');
        $folder = $request->query('kind') === 'folder';
        abort_unless(SafeFiles::resolve($dir)['is_dir'], 404);

        return view('admin.partials.sheet-form', [
            'title' => $folder ? __('New folder') : __('New file'),
            'description' => __('In :folder', ['folder' => $dir.'/']),
            'action' => route('admin.design.files.store', ['dir' => $dir, 'kind' => $folder ? 'folder' : 'file']),
            'values' => [],
            'fields' => [[
                'name' => 'name', 'label' => __('Name'), 'required' => true, 'maxlength' => 190,
                'placeholder' => $folder ? 'partials' : 'style.css',
                'help' => $folder ? null : __('Allowed: :types', ['types' => implode(', ', $this->creatableTypes())]),
            ]],
            'submit' => __('Create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dir = (string) $request->query('dir');
        $kind = $request->query('kind') === 'folder' ? 'folder' : 'file';
        $name = (string) $request->validate(['name' => 'required|string|max:190'])['name'];
        if ($kind === 'file' && ($redirect = $this->guard($request, $name))) {
            return $redirect;
        }
        $this->throttle('design-files');

        try {
            $path = SafeFiles::create($dir, $name, $kind);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }
        activity($kind === 'folder' ? 'folder.created' : 'file.created', $path);

        return $kind === 'folder'
            ? redirect()->route('admin.design.files.index', ['dir' => $path])->with('success', __('Folder created.'))
            : redirect()->route('admin.design.files.edit', ['path' => $path])->with('success', __('File created.'));
    }

    public function renameForm(Request $request)
    {
        $path = (string) $request->query('path');
        $file = SafeFiles::resolve($path);
        abort_if($file['relative'] === '', 403);

        return view('admin.partials.sheet-form', [
            'title' => __('Rename'),
            'description' => $path,
            'action' => route('admin.design.files.rename.save', ['path' => $path]),
            'values' => ['name' => basename($path)],
            'fields' => [['name' => 'name', 'label' => __('New name'), 'required' => true, 'maxlength' => 190]],
            'submit' => __('Rename'),
        ]);
    }

    public function rename(Request $request): RedirectResponse
    {
        $path = (string) $request->query('path');
        $name = (string) $request->validate(['name' => 'required|string|max:190'])['name'];
        if (($redirect = $this->guard($request, $path)) || ($redirect = $this->guard($request, $name))) {
            return $redirect;
        }

        try {
            $newPath = SafeFiles::rename($path, $name);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }
        activity('file.renamed', $path.' → '.$newPath);

        return redirect()->route('admin.design.files.index', ['dir' => dirname($newPath)])->with('success', __('Renamed to :name.', ['name' => basename($newPath)]));
    }

    public function delete(Request $request): RedirectResponse
    {
        $path = (string) $request->input('path');
        if ($redirect = $this->guard($request, $path)) {
            return $redirect;
        }

        try {
            SafeFiles::delete($path);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        activity('file.deleted', $path);

        return back()->with('success', __('Deleted: :name', ['name' => basename($path)]));
    }

    public function uploadForm(Request $request)
    {
        $dir = (string) $request->query('dir');
        abort_unless(SafeFiles::resolve($dir)['is_dir'], 404);

        return view('admin.partials.sheet-form', [
            'title' => __('Upload'),
            'description' => __('In :folder', ['folder' => $dir.'/']),
            'action' => route('admin.design.files.upload.save', ['dir' => $dir]),
            'multipart' => true,
            'values' => [],
            'fields' => [[
                'name' => 'file', 'label' => __('File'), 'type' => 'file', 'required' => true,
                'help' => __('Allowed: :types. Up to :mb MB.', ['types' => implode(', ', $this->uploadTypes()), 'mb' => setting('media_max_mb', 10)]),
            ]],
            'submit' => __('Upload'),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $dir = (string) $request->query('dir');
        $request->validate(['file' => 'required|file']);
        $this->throttle('design-files');
        // SVG can carry script, so it needs the same check as code.
        $svg = SafeFiles::extension((string) $request->file('file')->getClientOriginalName()) === 'svg';
        if ($svg && ($redirect = $this->guard($request, 'file.svg'))) {
            return $redirect;
        }

        try {
            $path = SafeFiles::upload($dir, $request->file('file'), $svg);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
        activity('file.uploaded', $path);

        return redirect()->route('admin.design.files.index', ['dir' => $dir])->with('success', __('Uploaded: :name', ['name' => basename($path)]));
    }

    public function revisions(Request $request)
    {
        $path = (string) $request->query('path');
        abort_unless(SafeFiles::editable($path), 404);
        SafeFiles::resolve($path, false);
        $code = SafeFiles::requiresCode($path);
        $canRestore = ! $code || $this->canCode();

        return view('admin.table', [
            'title' => __('Revisions: :name', ['name' => basename($path)]),
            'description' => __('The last :count versions of :path before each change. Restoring keeps the current version as a revision too.', ['count' => 20, 'path' => $path]),
            'actions' => is_file(SafeFiles::resolve($path, false)['absolute']) && $canRestore
                ? [['label' => __('Edit'), 'icon' => 'pencil', 'url' => route('admin.design.files.edit', ['path' => $path])]]
                : [],
            'table' => DataTable::make([
                'id' => 'revisions',
                'source' => FileRevision::with('user')->where('path', $path),
                'columns' => [
                    ['key' => 'created_at', 'label' => __('Saved before'), 'sort' => true, 'render' => fn ($revision) => cms_date($revision->created_at)],
                    ['key' => 'user_id', 'label' => __('Changed by'), 'render' => fn ($revision) => $revision->user?->name ?? '—'],
                    ['key' => 'size', 'label' => __('Size'), 'sort' => true, 'render' => fn ($revision) => Number::fileSize($revision->size)],
                ],
                'default_sort' => ['created_at', 'desc'],
                'bulk_url' => route('admin.design.files.revisions', ['path' => $path]),
                'row_actions' => fn ($revision) => [
                    ['label' => __('View'), 'icon' => 'page', 'sheet' => route('admin.design.files.revision', $revision->id), 'wide' => true],
                    $canRestore ? ['label' => __('Restore'), 'icon' => 'restore', 'post' => route('admin.design.files.restore', $revision->id), 'confirm' => __('Replace the current file with this version?')] : null,
                ],
                'empty' => __('No revisions yet. One is kept each time the file is saved.'),
            ]),
        ]);
    }

    public function revision(Request $request, int $id)
    {
        $revision = FileRevision::with('user')->findOrFail($id);
        // Builder layouts share the table under "page:<id>/…", which is not a design folder.
        abort_unless(SafeFiles::editable($revision->path), 404);
        SafeFiles::resolve($revision->path, false);

        return view('admin.design.revision', ['revision' => $revision]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $revision = FileRevision::findOrFail($id);
        if ($redirect = $this->guard($request, $revision->path)) {
            return $redirect;
        }

        try {
            $path = SafeFiles::restore($id);
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        activity('file.restored', $path);

        return redirect()->route('admin.design.files.edit', ['path' => $path])->with('success', __('Version restored.'));
    }

    public function confirmForm(Request $request)
    {
        abort_unless($this->canCode(), 403);

        return view('admin.design.confirm', [
            'title' => __('Confirm to edit code'),
            'totp' => $request->user()->hasTotp(),
            'return' => $this->safeReturn((string) $request->query('return')),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        abort_unless($this->canCode(), 403);
        $user = $request->user();
        $data = $request->validate(['secret' => 'required|string|max:255', 'return' => 'nullable|string']);

        $key = 'design-confirm:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['secret' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        $passed = $user->hasTotp() ? $user->passesTotp($data['secret']) : Hash::check($data['secret'], (string) $user->password);
        if (! $passed) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['secret' => $user->hasTotp() ? __('The code is not valid.') : __('The password is not correct.')]);
        }

        RateLimiter::clear($key);
        $request->session()->put(self::CONFIRM_KEY, now()->getTimestamp());
        activity('code.confirmed');

        return redirect()->to($this->safeReturn((string) ($data['return'] ?? '')));
    }

    /**
     * Null when the path may be changed now. Otherwise 403, or a redirect to the confirmation page.
     * Plain text files, styles and scripts pass without a check.
     */
    private function guard(Request $request, string $path): ?RedirectResponse
    {
        if (! SafeFiles::requiresCode($path)) {
            return null;
        }
        abort_unless($this->canCode(), 403, __('Editing PHP, Blade and SVG needs the Edit code permission.'));

        $confirmedAt = (int) $request->session()->get(self::CONFIRM_KEY, 0);
        if ($confirmedAt > now()->getTimestamp() - self::CONFIRM_MINUTES * 60) {
            return null;
        }

        $return = $request->isMethod('get') ? $request->fullUrl() : (url()->previous() ?: route('admin.design.files.index'));

        return redirect()->route('admin.design.confirm', ['return' => $return]);
    }

    private function canCode(): bool
    {
        return (bool) config('cms.code_editor') && (bool) auth()->user()?->can('code.manage');
    }

    private function throttle(string $name): void
    {
        $key = $name.':'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);
    }

    /** Only addresses inside the admin, so the form cannot send anyone to another site. */
    private function safeReturn(string $url): string
    {
        $admin = route('admin.dashboard');

        return $url !== '' && str_starts_with($url, $admin) ? $url : route('admin.design.files.index');
    }

    /** @return list<array{label:string,url:string}> */
    private function crumbs(string $dir): array
    {
        $crumbs = [['label' => __('All folders'), 'url' => route('admin.design.files.index')]];
        $path = '';
        foreach ($dir === '' || $dir === '.' ? [] : explode('/', $dir) as $segment) {
            $path = ltrim($path.'/'.$segment, '/');
            $crumbs[] = ['label' => $segment, 'url' => route('admin.design.files.index', ['dir' => $path])];
        }

        return $crumbs;
    }

    /**
     * The top level: the three folders with what they are for, then the custom CSS and JavaScript.
     *
     * @return Collection<int, array>
     */
    private function topLevel()
    {
        $about = [
            'landing' => [__('Landing pages'), __('Pages coded in Blade or HTML, by hand or with AI. One folder per page.')],
            'theme' => [__('Theme'), __('Your changes to the default theme: copies of its views, styles and scripts, and your own builder widgets.')],
            'plugins' => [__('Plugins'), __('Features added without changing the core. One folder per plugin.')],
        ];
        $folders = collect(SafeFiles::roots())->map(fn ($absolute, $key) => [
            'name' => $key, 'path' => $key, 'type' => 'dir', 'size' => 0, 'modified' => is_dir($absolute) ? filemtime($absolute) : 0,
            'editable' => false, 'requires_code' => false, 'label' => $about[$key][0] ?? null, 'about' => $about[$key][1] ?? null,
        ])->values();
        if (! isset(SafeFiles::roots()['theme'])) {
            return $folders;
        }
        $custom = collect(['css' => [__('Custom CSS'), __('Styles for every public page, loaded after the theme.')], 'js' => [__('Custom JavaScript'), __('A script for every public page, loaded at the end.')]])
            ->map(function (array $about, string $kind) {
                $path = Theme::customPath($kind);
                $file = Theme::assetsPath().'/'.Theme::CUSTOM[$kind];

                return [
                    // "~" sorts after letters, so the folders come first when sorted by name.
                    'name' => '~'.basename($path), 'path' => $path, 'type' => 'custom', 'custom' => $kind,
                    'size' => is_file($file) ? filesize($file) : 0, 'modified' => is_file($file) ? filemtime($file) : 0,
                    'editable' => true, 'requires_code' => false, 'label' => $about[0], 'about' => $about[1],
                ];
            })->values();

        return $folders->concat($custom);
    }

    /** Folders in landing/ show the page's title and whether it is public or the homepage. */
    private function withLandingInfo($entries)
    {
        $landings = Landing::all()->keyBy('slug');
        $home = Landing::home();

        return $entries->map(function (array $entry) use ($landings, $home) {
            $landing = $entry['type'] === 'dir' ? $landings->get($entry['name']) : null;
            if (! $landing) {
                return $entry;
            }
            $status = $landing['slug'] === $home ? __('Homepage') : ($landing['public'] ? __('Published') : __('Hidden'));

            return $entry + [
                'label' => __($landing['title']),
                'badge' => $status,
                'badge_class' => $landing['public'] || $landing['slug'] === $home ? 'badge-success' : 'badge-muted',
                'view_url' => $landing['slug'] === $home ? Locales::url('', Locales::default()) : ($landing['public'] ? Locales::url($landing['slug'], Locales::default()) : null),
            ];
        });
    }

    /** Opens the custom CSS or JavaScript, creating the empty file the first time. */
    public function custom(string $kind): RedirectResponse
    {
        $path = Theme::customPath($kind);
        if (! is_file(Theme::assetsPath().'/'.Theme::CUSTOM[$kind])) {
            try {
                SafeFiles::put($path, '', null, false);
            } catch (RuntimeException $e) {
                return redirect()->route('admin.design.files.index')->with('error', $e->getMessage());
            }
            activity('file.created', $path);
        }

        return redirect()->route('admin.design.files.edit', ['path' => $path]);
    }

    private function nameCell(array $entry): HtmlString
    {
        if (isset($entry['label'])) {
            $url = $entry['type'] === 'custom'
                ? route('admin.design.files.custom', $entry['custom'])
                : route('admin.design.files.index', ['dir' => $entry['path']]);
            $icon = icon($entry['type'] === 'custom' ? 'code' : 'folder', 16);
            $badge = isset($entry['badge']) ? ' <span class="badge '.e($entry['badge_class']).'">'.e($entry['badge']).'</span>' : '';

            return new HtmlString('<span class="file-name">'.$icon.'<span><a href="'.e($url).'"><strong>'.e($entry['label']).'</strong></a>'.$badge
                .' <span class="muted small mono">'.e($entry['type'] === 'custom' ? $entry['path'] : $entry['path'].'/').'</span>'
                .(isset($entry['about']) ? '<span class="muted small" style="display:block">'.e($entry['about']).'</span>' : '').'</span></span>');
        }

        $canOpen = $entry['type'] === 'dir' || ($entry['editable'] && (! $entry['requires_code'] || $this->canCode()));
        $icon = icon($entry['type'] === 'dir' ? 'folder' : ($entry['requires_code'] ? 'code' : ($entry['editable'] ? 'page' : 'image')), 16);
        $name = e($entry['name']).($entry['type'] === 'dir' ? '/' : '');
        $url = $entry['type'] === 'dir'
            ? route('admin.design.files.index', ['dir' => $entry['path']])
            : route('admin.design.files.edit', ['path' => $entry['path']]);
        $badge = $entry['requires_code'] ? ' <span class="badge badge-muted">'.e(__('Code')).'</span>' : '';

        return new HtmlString('<span class="file-name">'.$icon.($canOpen ? '<a class="mono" href="'.e($url).'">'.$name.'</a>' : '<span class="mono">'.$name.'</span>').$badge.'</span>');
    }

    /** @return list<string> */
    private function creatableTypes(): array
    {
        return array_values(array_filter(SafeFiles::EDITABLE, fn ($type) => $this->canCode() || ! in_array($type, SafeFiles::CODE, true)));
    }

    /** @return list<string> */
    private function uploadTypes(): array
    {
        return $this->canCode() ? [...SafeFiles::UPLOADABLE, 'svg'] : SafeFiles::UPLOADABLE;
    }
}
