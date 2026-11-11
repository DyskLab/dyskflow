<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BuilderController;
use App\Models\FileRevision;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Builder\Builder;
use App\Support\Builder\Schema;
use App\Support\Builder\Tokens;
use App\Support\Builder\Validator;
use App\Support\Builder\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Builder pages are pages whose layout is JSON in posts.builder, edited in the visual builder or with builder:import. */
class BuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $slug;

    private string $theme;

    private ?Post $post = null;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');

        $this->slug = 'zz-builder-'.bin2hex(random_bytes(3));
        $this->theme = sys_get_temp_dir().'/cms-theme-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->theme);
        config(['cms.design_roots' => ['theme' => $this->theme, 'landing' => base_path('landing'), 'plugins' => base_path('plugins')]]);
        Widgets::flush();

        User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'id', 'site_title' => 'Test Site', 'site_url' => 'http://localhost', 'home_type' => 'posts']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->theme);
        Widgets::flush();
        parent::tearDown();
    }

    /** A published builder page with these sections, in Indonesian and English. */
    private function page(array $sections, array $extra = []): Post
    {
        $spec = Builder::compact(['version' => 1, 'layout' => 'site', 'sections' => $sections] + $extra);
        if ($this->post) {
            $this->post->forceFill(['builder' => $spec, 'builder_draft' => null])->save();

            return $this->post;
        }
        $this->post = Post::create(['type' => 'page', 'title' => 'Builder test', 'slug' => $this->slug, 'content' => '', 'status' => 'published', 'published_at' => now()->subMinute(), 'builder' => $spec]);
        PostTranslation::create(['post_id' => $this->post->id, 'locale' => 'en', 'type' => 'page', 'title' => 'Builder test', 'slug' => $this->slug, 'status' => 'published']);

        return $this->post;
    }

    private function draft(array $sections): void
    {
        $this->post->forceFill(['builder_draft' => Builder::compact(['version' => 1, 'sections' => $sections])])->save();
    }

    private function hero(array $widgets, array $column = [], array $section = []): array
    {
        return ['id' => 'shero', 'type' => 'section', 'columns' => [['id' => 'chero', 'widgets' => $widgets] + $column]] + $section;
    }

    private function role(string $slug, array $capabilities): User
    {
        return User::create(['role_id' => Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'capabilities' => $capabilities])->id, 'name' => ucfirst($slug), 'email' => "$slug@example.test", 'password' => 'password123']);
    }

    public function test_a_builder_page_renders_at_its_address_in_each_language(): void
    {
        $this->page([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'tag' => 'h1', 'text' => ['id' => 'Halo dunia', 'en' => 'Hello world']]])]);

        $this->get('/'.$this->slug)->assertOk()
            ->assertSee('<h1 class="b-heading b-size-lg">Halo dunia</h1>', false)
            ->assertSee('css/builder.css')
            ->assertSee('site-header', false)
            ->assertSee('<link rel="canonical" href="'.url('/'.$this->slug).'">', false)
            ->assertSee('hreflang="en"', false)
            ->assertDontSee('data-b-id');
        $this->get('/en/'.$this->slug)->assertOk()->assertSee('Hello world')->assertDontSee('Halo dunia');

        // The page is a page: drafts are hidden, and it can be the homepage.
        $this->post->update(['status' => 'draft']);
        $this->get('/'.$this->slug)->assertNotFound();
        $this->post->update(['status' => 'published']);
        Setting::write(['home_type' => 'page', 'home_page_id' => (string) $this->post->id]);
        $this->get('/')->assertOk()->assertSee('Halo dunia');
    }

    public function test_a_missing_translation_is_empty_and_the_widget_disappears(): void
    {
        $this->page([$this->hero([
            ['id' => 'wonly', 'type' => 'heading', 'text' => ['id' => 'Hanya Indonesia']],
            ['id' => 'wboth', 'type' => 'heading', 'text' => 'Same in both'],
        ])]);

        $this->get('/en/'.$this->slug)->assertOk()->assertDontSee('Hanya Indonesia')->assertDontSee('b-w-heading" data', false)->assertSee('Same in both');
        $result = Validator::check($this->post->builderSpec());
        $this->assertSame([], $result['errors']);
        $this->assertStringContainsString('has no en text', implode("\n", $result['warnings']));
    }

    public function test_layout_tokens_become_classes_and_free_css_does_not(): void
    {
        $this->page([$this->hero(
            [['id' => 'wtext', 'type' => 'text', 'html' => '<p>Hi</p>', 'margin' => ['base' => 'lg', 'mobile' => 'sm']]],
            ['width' => ['base' => 6, 'mobile' => 12], 'class' => 'promo bad"><script>'],
            ['style' => ['padding' => 'xl', 'background' => 'muted', 'color' => 'red; background: url(x)'], 'hide' => ['mobile' => true], 'anchor' => 'top'],
        )]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<section class="b-section b-pad-xl b-bg-muted bm-hide" id="top">', $html);
        $this->assertStringContainsString('class="b-col b-col-6 bm-col-12 promo"', $html);
        $this->assertStringContainsString('class="b-w b-w-text b-mt-lg bm-mt-sm"', $html);
        $this->assertStringNotContainsString('bad"', $html);
        $this->assertStringNotContainsString('red;', $html);
    }

    public function test_unsafe_links_html_and_images_never_reach_the_page(): void
    {
        $this->page([$this->hero([
            ['id' => 'wbtn', 'type' => 'button', 'buttons' => [['label' => 'Bad', 'url' => 'javascript:alert(1)'], ['label' => 'Good', 'url' => 'https://example.com']]],
            ['id' => 'wtext', 'type' => 'text', 'html' => '<p onclick="x()">Hi<script>alert(1)</script></p>'],
            ['id' => 'wimg', 'type' => 'image', 'src' => 'javascript:alert(1)'],
            ['id' => 'wimg2', 'type' => 'image', 'src' => 'uploads/../../.env'],
        ])]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('.env', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringNotContainsString('>Bad<', $html);

        $errors = Validator::check($this->post->builderSpec())['errors'];
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('sections[0].columns[0].widgets[0] (wbtn): field "buttons.0.url"', $errors[0]);
    }

    public function test_the_validator_names_the_path_and_id_of_each_problem(): void
    {
        $result = Validator::check(['version' => 1, 'layout' => 'custom', 'sections' => [[
            'id' => 'sone', 'style' => ['padding' => 'huge', 'gap' => ['base' => 'md', 'phone' => 'sm']],
            'columns' => [['id' => 'cone', 'width' => 13, 'widgets' => [
                ['id' => 'wone', 'type' => 'heading'],
                ['id' => 'wone', 'type' => 'spacer'],
                ['type' => 'nope'],
                ['id' => 'inner', 'type' => 'inner-section', 'columns' => [['id' => 'cin', 'widgets' => [['id' => 'deep', 'type' => 'inner-section', 'columns' => []]]]]],
            ]]],
        ]]]);

        $errors = implode("\n", $result['errors']);
        $this->assertStringContainsString('"layout" must be one of: site, blank', $errors);
        $this->assertStringContainsString('sections[0] (sone): "padding" must be one of', $errors);
        $this->assertStringContainsString('sections[0] (sone): "gap" has unknown screens: phone', $errors);
        $this->assertStringContainsString('sections[0].columns[0] (cone): "width" (base) must be a whole number from 1 to 12', $errors);
        $this->assertStringContainsString('widgets[0] (wone): field "text" is required', $errors);
        $this->assertStringContainsString('widgets[1] (wone): the id is also used at sections[0].columns[0].widgets[0]', $errors);
        $this->assertStringContainsString('(deep): an inner section cannot hold another inner section', $errors);
        $this->assertStringContainsString('there is no widget "nope"', implode("\n", $result['warnings']));
        $this->assertMatchesRegularExpression(Builder::ID_PATTERN, $result['spec']['sections'][0]['columns'][0]['widgets'][2]['id']);
        $this->assertSame('inner-section', $result['spec']['sections'][0]['columns'][0]['widgets'][3]['type']);
    }

    public function test_inner_sections_render_one_level_deep(): void
    {
        $this->page([$this->hero([
            ['id' => 'inner', 'type' => 'inner-section', 'columns' => [
                ['id' => 'cleft', 'width' => 6, 'widgets' => [['id' => 'wleft', 'type' => 'heading', 'text' => 'LEFT']]],
                ['id' => 'cright', 'width' => 6, 'widgets' => [['id' => 'wright', 'type' => 'heading', 'text' => 'RIGHT'],
                    ['id' => 'deep', 'type' => 'inner-section', 'columns' => [['id' => 'cdeep', 'widgets' => [['id' => 'wdeep', 'type' => 'heading', 'text' => 'TOO DEEP']]]]],
                ]],
            ]],
        ])]);

        $this->get('/'.$this->slug)->assertOk()->assertSee('b-inner-section', false)->assertSee('LEFT')->assertSee('RIGHT')->assertDontSee('TOO DEEP');
    }

    public function test_a_theme_widget_renders_and_cannot_replace_a_core_one(): void
    {
        File::ensureDirectoryExists($this->theme.'/widgets/price');
        File::put($this->theme.'/widgets/price/widget.json', json_encode(['label' => 'Price', 'fields' => [
            ['key' => 'amount', 'type' => 'number', 'min' => 0, 'default' => 10],
            ['key' => 'name', 'type' => 'text', 'translatable' => true],
        ]]));
        File::put($this->theme.'/widgets/price/view.blade.php', '<p class="price">{{ $w[\'name\'] }}: {{ $w[\'amount\'] }}</p>');
        File::ensureDirectoryExists($this->theme.'/widgets/heading');
        File::put($this->theme.'/widgets/heading/widget.json', '{"fields": []}');
        File::put($this->theme.'/widgets/heading/view.blade.php', 'HIJACKED');

        $this->page([$this->hero([
            ['id' => 'wprice', 'type' => 'price', 'amount' => -5, 'name' => ['id' => 'Paket', 'en' => 'Plan']],
            ['id' => 'wtitle', 'type' => 'heading', 'text' => 'Real heading'],
        ])]);

        $this->get('/'.$this->slug)->assertOk()->assertSee('<p class="price">Paket: 0</p>', false)->assertSee('Real heading')->assertDontSee('HIJACKED');
        $this->assertSame('theme', Widgets::get('price')['source']);
        $this->assertStringContainsString('has the name of a core widget', implode("\n", Widgets::problems()));
    }

    public function test_a_broken_widget_is_hidden_on_the_site_and_shown_in_preview(): void
    {
        File::ensureDirectoryExists($this->theme.'/widgets/broken');
        File::put($this->theme.'/widgets/broken/widget.json', '{"fields": []}');
        File::put($this->theme.'/widgets/broken/view.blade.php', '{{ $undefinedVariable->boom }}');
        $this->page([$this->hero([['id' => 'wbroke', 'type' => 'broken'], ['id' => 'wtitle', 'type' => 'heading', 'text' => 'Still here']])]);

        $this->get('/'.$this->slug)->assertOk()->assertSee('Still here')->assertDontSee('b-problem');

        $html = Builder::render($this->post->builderSpec(), '', [], true);
        $this->assertStringContainsString('class="b-w b-problem" data-b-id="wbroke"', $html);
        $this->assertStringContainsString('data-b-id="wtitle"', $html);
    }

    public function test_the_latest_articles_widget_uses_the_page_language(): void
    {
        $this->page([$this->hero([['id' => 'wposts', 'type' => 'posts', 'count' => 2]])]);
        $this->get('/'.$this->slug)->assertOk()->assertDontSee('b-w-posts');
        $this->assertStringContainsString('Belum ada artikel yang terbit.', Builder::render($this->post->builderSpec(), '', [], true));

        Post::create(['user_id' => User::first()->id, 'type' => 'article', 'title' => 'Artikel Indonesia', 'slug' => 'artikel-indonesia', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->get('/'.$this->slug)->assertOk()->assertSee('Artikel Indonesia');
        $this->get('/en/'.$this->slug)->assertOk()->assertDontSee('Artikel Indonesia');
    }

    public function test_encode_keeps_a_stable_key_order_and_two_space_indent(): void
    {
        $spec = ['sections' => [['columns' => [], 'type' => 'section', 'id' => 'sone']], 'version' => 1, 'layout' => 'site'];

        $this->assertSame("{\n  \"version\": 1,\n  \"layout\": \"site\",\n  \"sections\": [\n    {\n      \"id\": \"sone\",\n      \"type\": \"section\",\n      \"columns\": []\n    }\n  ]\n}\n", Builder::encode($spec));
        $this->assertSame('{"version":1,"layout":"site","sections":[{"id":"sone","type":"section","columns":[]}]}', Builder::compact($spec));
    }

    public function test_tokens_drop_unknown_values(): void
    {
        $this->assertSame('b-pad-lg bm-pad-sm', Tokens::classes('pad', ['base' => 'lg', 'mobile' => 'sm', 'phone' => 'xl'], Tokens::SPACE));
        $this->assertSame('', Tokens::classes('pad', 'lg;color:red', Tokens::SPACE));
        $this->assertSame('a b_c', Tokens::customClasses('a "x" b_c 1bad'));
    }

    public function test_ai_tools_read_and_write_pages_with_commands(): void
    {
        $folder = sys_get_temp_dir().'/cms-builder-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($folder);
        try {
            $good = ['$schema' => 'x', 'version' => 1, 'sections' => [['type' => 'section', 'columns' => [['widgets' => [['type' => 'heading', 'text' => ['id' => 'Dari AI', 'en' => 'From AI']]]]]]]];
            File::put($folder.'/good.json', json_encode($good));
            File::put($folder.'/bad.json', json_encode(['version' => 1, 'sections' => [$this->hero([['id' => 'wtitle', 'type' => 'heading']])]]));

            $this->artisan('builder:list')->expectsOutputToContain('No builder pages yet')->assertExitCode(0);
            $this->artisan('builder:import', ['page' => 'ai-page', 'file' => $folder.'/good.json'])->expectsOutputToContain('--create')->assertExitCode(1);
            $this->artisan('builder:import', ['page' => 'ai-page', 'file' => $folder.'/good.json', '--create' => 'From AI'])->expectsOutputToContain('Saved as the draft')->assertExitCode(0);

            $page = Post::where('slug', 'ai-page')->firstOrFail();
            $this->assertSame('draft', $page->status);
            $this->assertMatchesRegularExpression(Builder::ID_PATTERN, $page->builderSpec(true)['sections'][0]['columns'][0]['widgets'][0]['id']);
            $this->assertArrayNotHasKey('$schema', $page->builderSpec(true));

            $this->artisan('builder:export', ['page' => 'ai-page'])->expectsOutputToContain('"Dari AI"')->assertExitCode(0);
            $this->artisan('builder:export', ['page' => (string) $page->id, '--published' => true])->doesntExpectOutputToContain('Dari AI')->assertExitCode(0);
            $this->artisan('builder:validate', ['page' => 'ai-page'])->expectsOutputToContain('OK')->assertExitCode(0);

            // Errors stop an import: nothing is saved.
            $this->artisan('builder:import', ['page' => 'ai-page', 'file' => $folder.'/bad.json'])->expectsOutputToContain('field "text" is required')->assertExitCode(1);
            $this->assertStringContainsString('Dari AI', $page->fresh()->builder_draft);

            $this->artisan('builder:import', ['page' => 'ai-page', 'file' => $folder.'/good.json', '--publish' => true])->assertExitCode(0);
            $page->refresh();
            $this->assertSame('published', $page->status);
            $this->assertNull($page->builder_draft);
            $this->get('/ai-page')->assertOk()->assertSee('Dari AI');
            $this->assertStringContainsString('Dari AI', $page->content, 'content holds the text for search and feeds');

            $this->artisan('builder:list')->expectsOutputToContain('ai-page')->assertExitCode(0);
            $this->artisan('builder:import', ['page' => 'admin', 'file' => $folder.'/good.json', '--create' => 'X'])->assertExitCode(1);
            $this->artisan('builder:export', ['page' => 'nope'])->assertExitCode(1);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_a_classic_page_moves_to_the_builder_and_back(): void
    {
        $admin = User::first();
        $page = Post::create(['type' => 'page', 'title' => 'About', 'slug' => 'about', 'content' => '<p>Old text</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->actingAs($admin)->get(route('admin.pages.edit', $page))->assertOk()->assertSee(route('admin.pages.builder.start', $page), false);
        $this->get(route('admin.pages.builder.edit', $page))->assertNotFound();

        $this->post(route('admin.pages.builder.start', $page))->assertRedirect(route('admin.pages.builder.edit', $page));
        $page->refresh();
        $this->assertTrue($page->isBuilder());
        $this->assertSame('<p>Old text</p>', $page->builderSpec()['sections'][0]['columns'][0]['widgets'][0]['html']);
        $this->get('/about')->assertOk()->assertSee('Old text')->assertSee('b-w-text', false);

        // The page form keeps the title, SEO and status; it never touches the layout or its text.
        $this->get(route('admin.pages.edit', $page))->assertOk()->assertSee(route('admin.pages.builder.edit', $page), false)->assertDontSee('name="content"', false);
        $this->post(route('admin.pages.update', $page), ['title' => 'About us', 'slug' => 'about', 'status' => 'published', 'meta_description' => 'Who we are'])->assertRedirect();
        $page->refresh();
        $this->assertSame('About us', $page->title);
        $this->assertTrue($page->isBuilder());
        $this->get('/about')->assertSee('Old text')->assertSee('Who we are');

        $this->get(route('admin.pages.index'))->assertOk()->assertSee('Builder')->assertSee(route('admin.pages.builder.edit', $page), false);

        $this->post(route('admin.pages.builder.classic', $page))->assertRedirect(route('admin.pages.edit', $page));
        $page->refresh();
        $this->assertFalse($page->isBuilder());
        $this->assertSame(1, FileRevision::where('path', 'page:'.$page->id.'/builder.json')->count());
    }

    public function test_the_editor_saves_a_draft_and_publishes_it(): void
    {
        $admin = User::first();
        $this->page([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'text' => 'LIVE']])]);
        $state = $this->actingAs($admin)->getJson(route('admin.pages.builder.spec', $this->post))->assertOk()->assertJsonPath('draft', false)->assertJsonPath('live', true)->json();
        $this->assertContains('heading', array_column($state['widgets'], 'name'));
        $this->assertArrayNotHasKey('view', $state['widgets'][0]);

        $this->get(route('admin.pages.builder.edit', $this->post))->assertOk()->assertSee('js/builder-editor.js')->assertSee('data-bld-frame', false);

        $draft = $state['spec'];
        $draft['sections'][0]['columns'][0]['widgets'][0]['text'] = ['id' => '  Draf  ', 'en' => 'Draft EN'];
        $saved = $this->postJson(route('admin.pages.builder.draft', $this->post), ['spec' => $draft, 'hash' => $state['hash']])->assertOk()->json();
        $this->assertSame('  Draf  ', $this->post->fresh()->builderSpec(true)['sections'][0]['columns'][0]['widgets'][0]['text']['id']);
        $this->get('/'.$this->slug)->assertSee('LIVE');

        // A stale hash is refused: something else changed the draft.
        $this->postJson(route('admin.pages.builder.draft', $this->post), ['spec' => $draft, 'hash' => $state['hash']])->assertStatus(409)->assertJsonPath('conflict', true);

        $this->post(route('admin.pages.builder.render', $this->post), ['spec' => $draft, 'locale' => 'en'])->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')->assertSee('Draft EN')->assertSee('data-b-id="wtitle"', false)->assertSee('<meta name="robots" content="noindex">', false);

        $this->postJson(route('admin.pages.builder.publish', $this->post), ['hash' => $saved['hash']])->assertOk()->assertJsonPath('draft', false);
        $this->post->refresh();
        $this->assertNull($this->post->builder_draft);
        $this->get('/'.$this->slug)->assertSee('Draf')->assertDontSee('LIVE');
        $this->assertSame(1, FileRevision::where('path', 'page:'.$this->post->id.'/builder.json')->count());
        $this->assertStringContainsString('Draft EN', $this->post->translations()->first()->content);
        $this->postJson(route('admin.pages.builder.publish', $this->post))->assertStatus(422);
    }

    public function test_publishing_refuses_a_draft_with_errors_and_publishes_a_draft_page(): void
    {
        $this->page([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'text' => 'LIVE']])]);
        $this->draft([$this->hero([['id' => 'wtitle', 'type' => 'heading']])]);

        $this->actingAs(User::first())->postJson(route('admin.pages.builder.publish', $this->post))
            ->assertStatus(422)->assertJsonPath('errors.0', 'sections[0].columns[0].widgets[0] (wtitle): field "text" is required.');
        $this->assertNotNull($this->post->fresh()->builder_draft);
        $this->get('/'.$this->slug)->assertSee('LIVE');

        // Publish in the builder also publishes a page that is still a draft.
        $this->post->update(['status' => 'draft', 'builder_draft' => null]);
        $this->postJson(route('admin.pages.builder.publish', $this->post))->assertOk()->assertJsonPath('live', true);
        $this->get('/'.$this->slug)->assertOk()->assertSee('LIVE');
    }

    public function test_discarded_and_published_layouts_are_revisions_that_restore_into_the_draft(): void
    {
        $this->page([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'text' => 'FIRST']])]);
        $this->draft([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'text' => 'THROWN AWAY']])]);
        $this->actingAs(User::first());

        $this->post(route('admin.pages.builder.discard', $this->post))->assertRedirect(route('admin.pages.builder.edit', $this->post));
        $this->assertNull($this->post->fresh()->builder_draft);
        $revision = FileRevision::where('path', 'page:'.$this->post->id.'/builder.json')->firstOrFail();

        $this->get(route('admin.pages.builder.revisions', $this->post))->assertOk()->assertSee(route('admin.pages.builder.revision', [$this->post, $revision->id]), false);
        $this->get(route('admin.pages.builder.revision', [$this->post, $revision->id]))->assertOk()->assertSee('THROWN AWAY');
        $this->post(route('admin.pages.builder.restore', [$this->post, $revision->id]))->assertRedirect(route('admin.pages.builder.edit', $this->post));
        $this->assertStringContainsString('THROWN AWAY', $this->post->fresh()->builder_draft);
        $this->get('/'.$this->slug)->assertSee('FIRST');

        // A revision belongs to its page.
        $other = Post::create(['type' => 'page', 'title' => 'Other', 'slug' => 'other', 'status' => 'draft', 'builder' => Builder::compact(Builder::blank())]);
        $this->get(route('admin.pages.builder.revision', [$other, $revision->id]))->assertNotFound();
        $this->get(route('admin.design.files.revision', $revision->id))->assertNotFound();
    }

    public function test_builder_pages_are_created_from_a_template_or_a_copy(): void
    {
        $this->assertSame(['about', 'coming-soon', 'contact', 'event', 'portfolio', 'product', 'restaurant', 'service'], array_keys(Builder::templates()));
        foreach (array_keys(Builder::templates()) as $name) {
            $result = Validator::check(Builder::template($name));
            $this->assertSame([], $result['errors'], $name);
            $this->assertSame([], $result['warnings'], $name);
        }

        $this->actingAs(User::first())->get(route('admin.pages.index'))->assertSee(route('admin.pages.builder.create'), false);
        $this->get(route('admin.pages.builder.create'))->assertOk()->assertSee('template:product', false);
        // Each starting point has a live thumbnail, drawn without saving anything.
        $this->get(route('admin.pages.builder.create'))->assertSee(route('admin.pages.builder.starter', ['from' => 'template:event']), false);
        $count = Post::count();
        $this->get(route('admin.pages.builder.starter', ['from' => 'template:event']))->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('<h1', false);
        $this->get(route('admin.pages.builder.starter', ['from' => 'blank']))->assertOk();
        $this->get(route('admin.pages.builder.starter', ['from' => 'template:../../etc']))->assertNotFound();
        $this->assertSame($count, Post::count());
        $this->post(route('admin.pages.builder.store'), ['title' => 'Launch', 'template' => 'template:product'])->assertRedirect();
        $made = Post::where('slug', 'launch')->firstOrFail();
        $this->assertSame('draft', $made->status);
        $this->assertTrue($made->isBuilder());
        $this->assertArrayNotHasKey('template', $made->builderSpec(true));
        $this->assertSame(count(Builder::template('product')['sections']), count($made->builderSpec(true)['sections']));

        $this->get(route('admin.pages.builder.create'))->assertSee('page:'.$made->id, false);
        $this->post(route('admin.pages.builder.store'), ['title' => 'Launch', 'template' => 'page:'.$made->id])->assertRedirect();
        $copy = Post::where('slug', 'launch-2')->firstOrFail();
        $this->assertSame(count($made->builderSpec(true)['sections']), count($copy->builderSpec(true)['sections']));
        $this->assertNotSame($made->builderSpec(true)['sections'][0]['id'], $copy->builderSpec(true)['sections'][0]['id']);

        $this->post(route('admin.pages.builder.store'), ['title' => 'X', 'template' => 'template:../../etc'])->assertSessionHasErrors('template');
        $this->post(route('admin.pages.builder.store'), ['title' => 'Admin', 'template' => 'blank'])->assertRedirect();
        $this->assertTrue(Post::where('slug', 'admin-2')->exists(), 'a reserved address gets a number');
    }

    public function test_only_page_managers_open_the_builder_and_only_on_builder_pages(): void
    {
        $this->page([]);
        $article = Post::create(['type' => 'article', 'title' => 'A', 'slug' => 'a', 'status' => 'draft', 'builder' => Builder::compact(Builder::blank())]);
        $this->actingAs(User::first())->get(route('admin.pages.builder.edit', $article))->assertNotFound();
        $this->post(route('admin.pages.builder.start', $article))->assertNotFound();

        $writer = $this->role('writer', ['articles.write']);
        $this->actingAs($writer)->get(route('admin.pages.builder.edit', $this->post))->assertForbidden();
        $this->actingAs($writer)->postJson(route('admin.pages.builder.draft', $this->post), ['spec' => ['version' => 1, 'sections' => []]])->assertForbidden();
        $this->actingAs($writer)->post(route('admin.pages.builder.publish', $this->post))->assertForbidden();
        $this->actingAs($writer)->post(route('admin.pages.builder.render', $this->post), ['spec' => ['version' => 1, 'sections' => []]])->assertForbidden();

        $this->actingAs(User::first())->postJson(route('admin.pages.builder.draft', $this->post), ['spec' => 'not an object'])->assertStatus(422);
        $this->get('/admin/pages/abc/builder')->assertNotFound();
    }

    public function test_the_content_widgets_render(): void
    {
        Setting::write(['contact_whatsapp' => '+62 812-3456', 'contact_email' => 'hi@example.test', 'contact_address' => "Jl. Mawar 1\nBandung"]);
        $this->page([$this->hero([
            ['id' => 'wfaq', 'type' => 'faq', 'schema' => true, 'open_first' => true, 'items' => [['question' => ['id' => 'Apa itu?', 'en' => 'What is it?'], 'answer' => 'Sebuah CMS </script><b>x</b>']]],
            ['id' => 'wquote', 'type' => 'testimonials', 'items' => [['quote' => 'Mantap', 'name' => 'Budi', 'role' => 'CEO']]],
            ['id' => 'wprice', 'type' => 'pricing', 'plans' => [['name' => 'Pro', 'price' => 'Rp 99.000', 'period' => '/bulan', 'features' => "Satu\nDua\n", 'button_label' => 'Pilih', 'url' => '#beli', 'featured' => true, 'badge' => 'Populer']]],
            ['id' => 'wstats', 'type' => 'stats', 'items' => [['value' => '10+', 'label' => 'Tahun']]],
            ['id' => 'wcontact', 'type' => 'contact', 'show_email' => false],
        ])]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<details class="b-faq-item" open>', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('</script>', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('<blockquote>Mantap</blockquote>', $html);
        $this->assertStringContainsString('b-plan b-plan-featured', $html);
        $this->assertSame(2, substr_count($html, '<li><svg'), 'two features with a check each');
        $this->assertStringContainsString('<dd>10+</dd>', $html);
        $this->assertStringContainsString('href="https://wa.me/628123456"', $html);
        $contact = substr($html, strpos($html, 'class="b-contact'), 2000);
        $this->assertStringNotContainsString('hi@example.test', substr($contact, 0, strpos($contact, '</ul>')));
        $this->assertStringContainsString("Jl. Mawar 1\nBandung", $html);

        $this->assertSame([], Validator::check($this->post->builderSpec())['errors']);
        $this->get('/en/'.$this->slug)->assertSee('What is it?');
    }

    public function test_encoding_is_stable_and_unknown_nodes_survive_a_save(): void
    {
        $spec = Builder::template('product');
        $spec['sections'][0]['columns'][0]['widgets'][] = ['id' => 'wfuture', 'type' => 'from-a-future-version', 'x' => 1];
        $once = Builder::encode(Validator::check($spec)['spec']);
        $twice = Builder::encode(Validator::check(json_decode($once, true))['spec']);

        $this->assertSame($once, $twice);
        $this->assertStringContainsString('"from-a-future-version"', $twice);
    }

    public function test_only_code_editors_can_add_or_change_html_widgets(): void
    {
        $html = ['id' => 'wraw', 'type' => 'html', 'html' => '<p>Reviewed</p>'];
        $this->page([$this->hero([$html, ['id' => 'wtitle', 'type' => 'heading', 'text' => 'Hi']])]);
        $designer = $this->role('designer', ['pages.manage']);
        $this->actingAs($designer);
        $draft = route('admin.pages.builder.draft', $this->post);
        $with = fn (array $widgets) => ['spec' => ['version' => 1, 'layout' => 'site', 'sections' => [$this->hero($widgets)]]];

        // Moving, copying and deleting the existing HTML is fine.
        $this->postJson($draft, $with([['id' => 'wtitle', 'type' => 'heading', 'text' => 'Hi'], $html, ['type' => 'html', 'html' => '<p>Reviewed</p>']]))->assertOk();
        $this->postJson($draft, $with([['id' => 'wtitle', 'type' => 'heading', 'text' => 'Hi']]))->assertOk();
        // Changing it, or adding new HTML, is not: the draft keeps the last allowed version.
        $this->postJson($draft, $with([['id' => 'wraw', 'type' => 'html', 'html' => '<p>Changed</p>']]))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'HTML'));
        $this->postJson($draft, $with([['type' => 'html', 'html' => ['id' => 'Baru', 'en' => 'New']]]))->assertStatus(422);
        $this->assertStringNotContainsString('Changed', $this->post->fresh()->builder_draft);
        $this->get(route('admin.pages.builder.edit', $this->post))->assertSee('"code":false', false);

        // A copy of a page with HTML is allowed (the values are already there); a template with new HTML would not be.
        $this->post(route('admin.pages.builder.store'), ['title' => 'Copy', 'template' => 'page:'.$this->post->id])->assertRedirect();

        // Someone who may edit code can do all of it.
        $this->actingAs(User::first());
        $this->postJson($draft, $with([['id' => 'wraw', 'type' => 'html', 'html' => '<p>Changed</p>']]))->assertOk();
        $this->assertStringContainsString('Changed', $this->post->fresh()->builder_draft);
        $this->get(route('admin.pages.builder.edit', $this->post))->assertSee('"code":true', false);
    }

    public function test_a_page_has_its_own_css_and_javascript(): void
    {
        $this->page([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'text' => 'Hi', 'class' => 'promo']])], [
            'css' => '.promo { color: red; } a::after { content: "</style><script>alert(1)</script>"; }',
            'js' => 'console.log("</script><img src=x onerror=alert(1)>");',
        ]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<style data-b-page-css>.promo { color: red; }', $html);
        $this->assertStringNotContainsString('</style><script>alert(1)', $html);
        $this->assertStringContainsString('<script data-b-page-js>console.log("<\\/script><img', $html);
        $this->assertSame(1, substr_count($html, '<img src=x'), 'only inside the script string');

        // The preview gets the CSS but never runs the page's script in the admin.
        $preview = $this->actingAs(User::first())->post(route('admin.pages.builder.render', $this->post), ['spec' => $this->post->builderSpec()])->getContent();
        $this->assertStringContainsString('data-b-page-css', $preview);
        $this->assertStringNotContainsString('data-b-page-js', $preview);

        $this->assertStringContainsString('"css" must be text', implode("\n", Validator::check(['version' => 1, 'css' => ['x'], 'sections' => []])['errors']));
        $this->assertStringContainsString('"js" is longer than', implode("\n", Validator::check(['version' => 1, 'js' => str_repeat('a', Validator::MAX_CODE + 1), 'sections' => []])['errors']));

        // CSS is for anyone who edits the page; JavaScript needs code.manage, like the HTML widget.
        $this->actingAs($this->role('designer', ['pages.manage']));
        $draft = route('admin.pages.builder.draft', $this->post);
        $spec = $this->post->builderSpec();
        $this->postJson($draft, ['spec' => ['css' => '.promo { color: blue; }'] + $spec])->assertOk();
        $this->postJson($draft, ['spec' => ['js' => 'alert(2)'] + $spec])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'JavaScript'));
        $this->postJson($draft, ['spec' => array_diff_key($spec, ['js' => 1])])->assertOk();
    }

    public function test_containers_lay_out_widgets_with_free_values_per_screen(): void
    {
        $this->page([[
            'id' => 'xtop', 'type' => 'container',
            'style' => ['direction' => ['base' => 'row', 'mobile' => 'column'], 'gap' => '32px', 'min_height' => '80vh', 'padding' => '48px 24px', 'background_color' => 'var(--muted)', 'radius' => '24px'],
            'children' => [
                ['id' => 'xleft', 'type' => 'container', 'style' => ['width' => ['base' => '40%', 'mobile' => 'auto'], 'justify' => 'center'], 'children' => [
                    ['id' => 'wtitle', 'type' => 'heading', 'text' => 'Kiri', 'style' => ['margin' => '0 auto', 'width' => '320px']],
                ]],
                ['id' => 'xright', 'type' => 'container', 'children' => [['id' => 'wbody', 'type' => 'text', 'html' => '<p>Kanan</p>']]],
            ],
        ]]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<section class="b-con b-con-top b-boxed b-n-xtop"><div class="b-con-inner">', $html);
        $this->assertStringContainsString('<div class="b-con b-n-xleft b-sized">', $html);
        $this->assertStringContainsString('<div class="b-con b-n-xright">', $html);
        $this->assertStringContainsString('.b-n-xtop>.b-con-inner{flex-direction:row}', $html);
        $this->assertStringContainsString('.b-n-xtop>.b-con-inner>.b-con:not(.b-sized){flex:1 1 0}', $html);
        $this->assertStringContainsString('.b-n-xtop>.b-con-inner{gap:32px}', $html);
        $this->assertStringContainsString('.b-n-xtop{min-height:80vh}', $html);
        $this->assertStringContainsString('.b-n-xtop{background-color:var(--muted);border-radius:24px}', $html);
        $this->assertStringContainsString('.b-n-xleft{justify-content:center}', $html);
        $this->assertStringContainsString('.b-n-wtitle{width:320px;flex:0 1 auto}', $html);
        $this->assertStringContainsString('@media (max-width: 767px){.b-n-xtop>.b-con-inner{flex-direction:column}', $html);
        $this->assertStringContainsString('.b-n-xleft{width:auto}', $html);
        $this->assertStringContainsString('class="b-w b-w-heading b-n-wtitle"', $html);

        // The text of a container page still reaches "content" for search and feeds.
        $this->assertStringContainsString('Kanan', BuilderController::text($this->post->builderSpec(), 'id'));
    }

    public function test_container_values_are_checked_and_never_printed_when_wrong(): void
    {
        $check = fn (array $node) => implode("\n", Validator::check(['version' => 1, 'sections' => [$node]])['errors']);
        $container = fn (array $style, array $children = []) => ['id' => 'xbad', 'type' => 'container', 'style' => $style, 'children' => $children];

        $this->assertStringContainsString('"style.width" must be a size', $check($container(['width' => 'calc(100% - 1px)'])));
        $this->assertStringContainsString('"style.padding" must be one to four sizes', $check($container(['padding' => '1px 2px 3px 4px 5px'])));
        $this->assertStringContainsString('"style.padding" must be one to four sizes', $check($container(['padding' => '-10px'])));
        $this->assertSame('', $check($container(['margin' => '-10px auto'])));
        $this->assertStringContainsString('"style.color" must be a color', $check($container(['color' => 'red;}body{display:none'])));
        $this->assertStringContainsString('"style.background_color" must be a color', $check($container(['background_color' => 'var(--evil)'])));
        $this->assertStringContainsString('"style.gap" has unknown screens: phone', $check($container(['gap' => ['phone' => '1px']])));
        $this->assertStringContainsString('"style.float" is not known', $check($container(['float' => 'left'])));
        $this->assertStringContainsString('"style.direction" (mobile) must be one of', $check($container(['direction' => ['mobile' => 'diagonal']])));
        $this->assertStringContainsString('"children" must be a list', $check(['id' => 'xbad', 'type' => 'container']));
        $this->assertStringContainsString('Use a container with direction "row"', $check($container([], [['id' => 'sinner', 'type' => 'inner-section', 'columns' => []]])));

        $deep = ['id' => 'xdeep6', 'type' => 'container', 'children' => [['id' => 'xdeep7', 'type' => 'container', 'children' => []]]];
        foreach ([5, 4, 3, 2, 1] as $n) {
            $deep = ['id' => 'xdeep'.$n, 'type' => 'container', 'children' => [$deep]];
        }
        $this->assertStringContainsString('at most 6 levels', $check($deep));

        // A value that slipped past the validator (an old draft, a hand-made layout) is left out of the CSS.
        $this->page([$container(['width' => '10px}</style><script>alert(1)</script>', 'gap' => '12px'])]);
        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('.b-n-xbad>.b-con-inner{gap:12px}', $html);
    }

    public function test_the_schema_follows_the_widget_definitions(): void
    {
        $this->assertSame(Schema::encode(Schema::build()), File::get(base_path('docs/builder.schema.json')), 'Run php artisan builder:schema after changing a core widget.');

        File::ensureDirectoryExists($this->theme.'/widgets/team');
        File::put($this->theme.'/widgets/team/widget.json', json_encode(['label' => 'Team', 'fields' => [['key' => 'people', 'type' => 'repeater', 'fields' => [['key' => 'name', 'type' => 'text', 'required' => true]]], ['key' => 'cols', 'type' => 'select', 'options' => ['2', '3'], 'responsive' => true]]]));
        File::put($this->theme.'/widgets/team/view.blade.php', '');
        Widgets::flush();

        $team = collect(Schema::build(true)['$defs']['widget']['allOf'])->firstWhere('if.properties.type.const', 'team')['then'];
        $this->assertSame('string', $team['properties']['people']['items']['properties']['name']['type']);
        $this->assertSame(['2', '3'], $team['properties']['cols']['oneOf'][0]['enum']);
        $this->assertNull(collect(Schema::build()['$defs']['widget']['allOf'])->firstWhere('if.properties.type.const', 'team'));
        $this->artisan('builder:schema', ['--all' => true])->expectsOutputToContain('"team"')->assertSuccessful();
    }

    public function test_images_upload_into_the_media_library(): void
    {
        $public = sys_get_temp_dir().'/cms-public-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($public);
        $this->app->usePublicPath($public);
        try {
            $this->page([]);
            $this->actingAs(User::first());
            $url = route('admin.pages.builder.upload', $this->post);

            $path = $this->post($url, ['file' => UploadedFile::fake()->image('Hero Photo.JPG', 20, 20)], ['Accept' => 'application/json'])->assertOk()->json('path');
            $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/hero-photo\.jpg$#', $path);
            $this->assertFileExists($public.'/'.$path);
            $this->assertTrue(Media::where('path', $path)->exists());

            $this->post($url, ['file' => UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertStatus(422);
            $this->post($url, ['file' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4')], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'mp4'));

            // Someone without the media library cannot upload, and sees no upload button.
            $this->actingAs($this->role('pager', ['pages.manage']));
            $this->post($url, ['file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertForbidden();
            $this->get(route('admin.pages.builder.edit', $this->post))->assertSee('"mediaPicker":false', false);

            // The image works in a widget right away, and a missing upload is a warning.
            $this->page([$this->hero([['id' => 'wimg', 'type' => 'image', 'src' => $path, 'alt' => 'x'], ['id' => 'wgone', 'type' => 'image', 'src' => 'uploads/gone.jpg', 'alt' => 'y']])]);
            $this->get('/'.$this->slug)->assertSee($path, false);
            $this->assertStringContainsString('uploads/gone.jpg, which does not exist', implode("\n", Validator::check($this->post->builderSpec())['warnings']));
        } finally {
            File::deleteDirectory($public);
        }
    }

    public function test_the_gallery_and_map_widgets_render(): void
    {
        Setting::write(['contact_address' => "Jl. Asia Afrika 8\nBandung"]);
        $this->page([$this->hero([
            ['id' => 'wgal', 'type' => 'gallery', 'open' => true, 'columns' => ['base' => '4', 'mobile' => '2'], 'images' => [['src' => 'uploads/a.jpg', 'alt' => 'Satu', 'caption' => ['id' => 'Pertama', 'en' => 'First']], ['src' => 'javascript:alert(1)']]],
            ['id' => 'wmap', 'type' => 'map', 'zoom' => 99],
            ['id' => 'wmap2', 'type' => 'map', 'address' => 'Monas & "Jakarta"', 'height' => 'lg'],
        ])]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertStringContainsString('b-gallery b-ratio-1-1 b-cards-4 bm-cards-2', $html);
        $this->assertSame(1, substr_count($html, '<figure>'), 'the bad image is left out');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<figcaption>Pertama</figcaption>', $html);
        // The map is a button until pressed: no Google iframe, and the address comes from Settings.
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('data-b-video="https://www.google.com/maps?q=Jl.%20Asia%20Afrika%208%2C%20Bandung&amp;z=20&amp;output=embed"', $html);
        $this->assertStringContainsString('q=Monas%20%26%20%22Jakarta%22', $html);
        $this->assertStringContainsString('b-map b-map-lg', $html);

        Setting::write(['contact_address' => '']);
        $this->page([$this->hero([['id' => 'wmap', 'type' => 'map']])]);
        $this->assertStringNotContainsString('b-map', $this->get('/'.$this->slug)->getContent());
    }

    public function test_a_video_can_be_a_local_file(): void
    {
        $this->page([$this->hero([
            ['id' => 'wclip', 'type' => 'video', 'file' => 'uploads/2026/11/clip.webm', 'loop' => true, 'title' => 'Demo'],
            ['id' => 'wplay', 'type' => 'video', 'file' => 'uploads/2026/11/a.mp4'],
            ['id' => 'wboth', 'type' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'file' => 'uploads/a.mp4'],
        ])]);

        $html = $this->get('/'.$this->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<video\s+autoplay muted loop playsinline\s+aria-label="Demo">\s*<source src="[^"]+/uploads/2026/11/clip\.webm" type="video/webm">#', $html);
        $this->assertMatchesRegularExpression('#<video\s+controls preload="none"\s*>\s*<source src="[^"]+/uploads/2026/11/a\.mp4" type="video/mp4">#', $html);
        $this->assertSame(2, substr_count($html, '<video'), 'a link wins over a file');
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);

        // Only the extensions the field accepts.
        $bad = Validator::check(['version' => 1, 'sections' => [$this->hero([['id' => 'wbad', 'type' => 'video', 'file' => 'uploads/clip.png']])]]);
        $this->assertStringContainsString('mp4 or webm', implode("\n", $bad['errors']));

        // A file field needs "accept".
        File::ensureDirectoryExists($this->theme.'/widgets/doc');
        File::put($this->theme.'/widgets/doc/widget.json', json_encode(['label' => 'Doc', 'fields' => [['key' => 'pdf', 'type' => 'file']]]));
        File::put($this->theme.'/widgets/doc/view.blade.php', '');
        Widgets::flush();
        $this->assertStringContainsString('needs "accept"', implode("\n", Widgets::problems()));
    }

    public function test_the_editor_fills_the_screen_and_exits_to_the_page(): void
    {
        $this->page([]);
        $this->actingAs(User::first());

        $this->get(route('admin.pages.builder.edit', $this->post))->assertOk()
            ->assertSee('<body class="bld-full">', false)
            ->assertSee('href="'.route('admin.pages.edit', $this->post).'" data-bld-exit', false)
            ->assertSee(route('admin.pages.builder.revisions', $this->post), false);
        $this->get(route('admin.pages.edit', $this->post))->assertSee('<body class="">', false);

        // The translation form points to the builder instead of a content editor.
        $this->get(route('admin.pages.translation', $this->post))->assertOk()->assertSee(route('admin.pages.builder.edit', $this->post), false)->assertDontSee('name="content"', false);
    }

    public function test_export_and_import_carry_the_layout(): void
    {
        $this->page([$this->hero([['id' => 'wtitle', 'type' => 'heading', 'text' => 'Exported']])]);
        $this->actingAs(User::first());

        $json = $this->post(route('admin.settings.tools.export'), ['include' => ['pages']])->assertOk()->streamedContent();
        $data = json_decode($json, true);
        $this->assertSame('Exported', collect($data['posts'])->firstWhere('slug', $this->slug)['builder']['sections'][0]['columns'][0]['widgets'][0]['text']);

        $this->post->forceDelete();
        $file = UploadedFile::fake()->createWithContent('export.json', $json);
        $this->post(route('admin.settings.tools.import'), ['file' => $file])->assertRedirect();
        $page = Post::where('slug', $this->slug)->firstOrFail();
        $this->assertTrue($page->isBuilder());
        $this->get('/'.$this->slug)->assertSee('Exported');
    }
}
