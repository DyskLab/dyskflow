<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\DataTable;
use App\Support\MediaUploader;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use RuntimeException;

class MediaController extends Controller
{
    public function index()
    {
        return view('admin.table', [
            'title' => __('Media'),
            'actions' => [['label' => __('Upload'), 'icon' => 'upload', 'sheet' => route('admin.media.upload')]],
            'table' => DataTable::make([
                'id' => 'media',
                'source' => Media::query(),
                'columns' => [
                    ['key' => 'preview', 'label' => __('Preview'), 'render' => fn ($m) => new HtmlString($m->isImage()
                        ? '<img class="thumb" src="'.e($m->url()).'" alt="'.e((string) $m->alt).'" loading="lazy">'
                        : '<span class="thumb">'.icon('page').'</span>')],
                    ['key' => 'title', 'label' => __('Title'), 'sort' => true, 'render' => fn ($m) => new HtmlString('<div style="font-weight:500">'.e((string) $m->title).'</div><div class="muted small">'.e($m->path).'</div>')],
                    ['key' => 'mime', 'label' => __('Type'), 'sort' => true],
                    ['key' => 'size', 'label' => __('Size'), 'sort' => true, 'render' => fn ($m) => Number::fileSize($m->size)],
                    ['key' => 'created_at', 'label' => __('Uploaded'), 'sort' => true, 'render' => fn ($m) => cms_date($m->created_at)],
                ],
                'search' => ['title', 'filename', 'alt'],
                'filters' => ['kind' => [
                    'label' => __('Type'),
                    'options' => ['image' => __('Images'), 'other' => __('Other files')],
                    'apply' => fn ($q, $value) => $value === 'image' ? $q->where('mime', 'like', 'image/%') : $q->where('mime', 'not like', 'image/%'),
                ]],
                'default_sort' => ['created_at', 'desc'],
                'bulk_url' => route('admin.media.bulk'),
                'bulk' => ['delete' => ['label' => __('Delete'), 'danger' => true, 'confirm' => __('Selected files will be deleted from the server.')]],
                'row_actions' => fn ($m) => [
                    ['label' => __('Edit'), 'icon' => 'pencil', 'sheet' => route('admin.media.edit', $m)],
                    ['label' => __('Open'), 'icon' => 'external', 'url' => $m->url(), 'blank' => true],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => route('admin.media.bulk'), 'fields' => ['ids[]' => [$m->id]], 'danger' => true, 'confirm' => __('Delete ":title"? This cannot be undone.', ['title' => $m->title])],
                ],
            ]),
        ]);
    }

    public function uploadForm()
    {
        return view('admin.media-upload', ['allowed' => MediaUploader::allowedExtensions()]);
    }

    public function picker(Request $request)
    {
        $items = Media::query()->where('mime', 'like', 'image/%')->latest('id')->limit(120)->get();

        return view('admin.media-picker', ['items' => $items, 'allowed' => MediaUploader::allowedExtensions()]);
    }

    public function store(Request $request)
    {
        $request->validate(['files' => 'required|array', 'files.*' => 'file']);
        $saved = [];
        try {
            foreach ($request->file('files') as $file) {
                $saved[] = MediaUploader::store($file);
                activity('media.uploaded', end($saved)->filename);
            }
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            $first = $saved[0];

            return response()->json(['path' => $first->path, 'url' => $first->url(), 'alt' => (string) $first->alt]);
        }

        return back()->with('success', __('Uploaded :count file(s).', ['count' => count($saved)]));
    }

    public function edit(Media $media)
    {
        return view('admin.partials.sheet-form', [
            'title' => __('Edit media'),
            'description' => $media->path,
            'action' => route('admin.media.update', $media),
            'values' => $media->toArray(),
            'fields' => [
                ['name' => 'title', 'label' => __('Title')],
                ['name' => 'alt', 'label' => __('Alt text'), 'help' => __('Describes the image for screen readers and search engines.')],
            ],
        ]);
    }

    public function update(Request $request, Media $media)
    {
        $media->update($request->validate(['title' => 'nullable|string|max:190', 'alt' => 'nullable|string|max:190']));

        return back()->with('success', __('Saved.'));
    }

    public function bulk(Request $request)
    {
        $items = Media::whereIn('id', $this->ids($request))->get();
        foreach ($items as $item) {
            if (str_starts_with($item->path, 'uploads/') && ! str_contains($item->path, '..')) {
                @unlink(public_path($item->path));
            }
            $item->delete();
            activity('media.deleted', $item->filename);
        }

        return back()->with('success', __('Done: :count item(s).', ['count' => $items->count()]));
    }
}
