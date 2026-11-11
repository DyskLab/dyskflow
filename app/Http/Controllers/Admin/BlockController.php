<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FileRevision;
use App\Models\Post;
use App\Support\Builder\Builder;
use App\Support\Builder\Validator;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Saved blocks (Design > Blocks), like Elementor's saved templates: a container or section kept
 * to be inserted into any page or template. Each is a posts row of type "block" whose layout holds
 * that one node. Inserting copies it: changing a block later does not change the pages that used it.
 */
class BlockController extends Controller
{
    /** At most this many blocks are offered in the editor. */
    public const LIMIT = 200;

    public function index()
    {
        return view('admin.table', [
            'title' => __('Blocks'),
            'actions' => [['label' => __('New block'), 'icon' => 'plus', 'sheet' => route('admin.design.blocks.create')]],
            'description' => __('Containers and sections saved from the builder with "Save as block", to insert into other pages and templates. Inserting makes a copy: editing a block later does not change pages that already use it.'),
            'table' => DataTable::make([
                'id' => 'blocks',
                'source' => Post::where('type', 'block')->with('author'),
                'columns' => [
                    ['key' => 'title', 'label' => __('Block'), 'sort' => true, 'render' => fn ($b) => new HtmlString('<strong>'.e($b->title).'</strong>'.($b->builder_draft !== null ? ' <span class="badge badge-warning">'.e(__('Unpublished changes')).'</span>' : ''))],
                    ['key' => 'user_id', 'label' => __('Author'), 'render' => fn ($b) => $b->author?->name ?? '—'],
                    ['key' => 'updated_at', 'label' => __('Updated'), 'sort' => true, 'render' => fn ($b) => cms_date($b->updated_at)],
                ],
                'search' => ['title'],
                'default_sort' => ['updated_at', 'desc'],
                'bulk_url' => route('admin.design.blocks.index'),
                'row_actions' => fn ($b) => [
                    ['label' => __('Edit with the builder'), 'icon' => 'landing', 'url' => route('admin.design.blocks.builder.edit', $b)],
                    ['label' => __('Delete'), 'icon' => 'trash', 'danger' => true, 'post' => route('admin.design.blocks.delete', $b), 'confirm' => __('Delete this block? Pages that already use it keep their copy.')],
                ],
                'empty' => __('No blocks yet. In the builder, select a container and press "Save as block".'),
            ]),
        ]);
    }

    public function createForm()
    {
        return view('admin.partials.sheet-form', [
            'title' => __('New block'),
            'description' => __('A block starts as an empty container. Design it in the builder, then insert it into any page or template from Saved blocks.'),
            'action' => route('admin.design.blocks.create.save'),
            'values' => [],
            'fields' => [['name' => 'title', 'label' => __('Name'), 'required' => true, 'maxlength' => 255, 'placeholder' => __('For example: Call to action')]],
            'submit' => __('Create'),
        ]);
    }

    /** A new block from the Blocks list: one empty container, opened in the builder. */
    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:255']);
        $spec = ['version' => Builder::VERSION, 'layout' => 'blank', 'sections' => [['id' => Builder::newId('x'), 'type' => 'container', 'children' => []]]];
        $post = Post::create([
            'type' => 'block',
            'slug' => self::newSlug(),
            'title' => $data['title'],
            'content' => '',
            'status' => 'published',
            'published_at' => now(),
            'user_id' => auth()->id(),
            'allow_comments' => false,
            'builder' => Builder::compact($spec),
        ]);
        activity('block.created', $post->title);

        return redirect()->route('admin.design.blocks.builder.edit', $post)->with('success', __('Block created. Add widgets to the container, then press Publish.'));
    }

    public function destroy(int $block): RedirectResponse
    {
        $post = Post::where('type', 'block')->findOrFail($block);
        FileRevision::where('path', BuilderController::revisionPath($post))->delete();
        $post->forceDelete();
        activity('block.deleted', $post->title);

        return redirect()->route('admin.design.blocks.index')->with('success', __('Block deleted.'));
    }

    /** The blocks for the editor's palette, with their node ready to insert. */
    public function library(): JsonResponse
    {
        $this->allowed();

        return response()->json(['blocks' => Post::where('type', 'block')->latest('updated_at')->limit(self::LIMIT)->get()
            ->map(fn (Post $b) => ['id' => $b->id, 'title' => $b->title, 'node' => $b->builderSpec()['sections'][0] ?? null])
            ->filter(fn ($b) => is_array($b['node']))->values()]);
    }

    /** "Save as block" in the editor: one container or section, with new ids. */
    public function store(Request $request): JsonResponse
    {
        $this->allowed();
        $data = $request->validate(['title' => 'required|string|max:255', 'node' => 'required|array', 'node.type' => 'required|string|in:container,section']);
        $key = 'builder:'.auth()->id();
        abort_if(RateLimiter::tooManyAttempts($key, 30), 429);
        RateLimiter::hit($key, 60);

        // validate() keeps only node.type of the node, so the whole node comes from the input.
        $result = Validator::check(['version' => Builder::VERSION, 'layout' => 'blank', 'sections' => [BuilderController::freshIds((array) $request->input('node'))]]);
        if ($result['errors'] || count($result['spec']['sections']) !== 1) {
            return response()->json(['message' => implode(' ', $result['errors']) ?: __('This cannot be saved as a block.')], 422);
        }
        try {
            Builder::guardCode(null, $result['spec'], self::specs());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $post = Post::create([
            'type' => 'block',
            'slug' => self::newSlug(),
            'title' => $data['title'],
            'content' => '',
            'status' => 'published',
            'published_at' => now(),
            'user_id' => auth()->id(),
            'allow_comments' => false,
            'builder' => Builder::compact($result['spec']),
        ]);
        activity('block.created', $post->title);

        return response()->json(['message' => __('Saved as a block. Find it in the widget list under Saved blocks.'), 'block' => ['id' => $post->id, 'title' => $post->title, 'node' => $result['spec']['sections'][0]]]);
    }

    /**
     * The layouts of all blocks. A block's code was allowed when it was saved, so inserting it is
     * not adding new code (see Builder::guardCode).
     *
     * @return list<array>
     */
    public static function specs(): array
    {
        return rescue(fn () => Post::where('type', 'block')->latest('updated_at')->limit(self::LIMIT)->get()
            ->map(fn (Post $b) => $b->builderSpec())->filter()->values()->all(), [], false);
    }

    private static function newSlug(): string
    {
        do {
            $slug = 'block-'.substr(bin2hex(random_bytes(4)), 0, 8);
        } while (Post::withTrashed()->where('type', 'block')->where('slug', $slug)->exists());

        return $slug;
    }

    /** Pages and templates both insert blocks, so either permission is enough. */
    private function allowed(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->can('pages.manage') || $user->can('appearance.manage')), 403);
    }
}
