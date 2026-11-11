<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use App\Support\Builder\Builder;
use App\Support\Builder\Templates;
use App\Support\Builder\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Theme Builder templates (Design > Templates): builder layouts for the article, lists, header and footer. */
class TemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Post $article;

    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');
        Widgets::flush();
        Templates::flush();

        $this->admin = User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'id', 'site_title' => 'Test Site', 'site_url' => 'http://localhost', 'home_type' => 'posts']);

        $category = Term::create(['taxonomy' => 'category', 'name' => 'Berita', 'slug' => 'berita']);
        $this->article = Post::create(['user_id' => $this->admin->id, 'type' => 'article', 'title' => 'Artikel utama', 'slug' => 'artikel-utama', 'content' => '<p>Isi artikel</p>', 'status' => 'published', 'published_at' => now()->subDay(), 'allow_comments' => true]);
        $related = Post::create(['user_id' => $this->admin->id, 'type' => 'article', 'title' => 'Artikel kedua', 'slug' => 'artikel-kedua', 'content' => '<p>Lain</p>', 'status' => 'published', 'published_at' => now()->subDays(2)]);
        $this->article->terms()->attach($category->id);
        $related->terms()->attach($category->id);
    }

    protected function tearDown(): void
    {
        Widgets::flush();
        Templates::flush();
        parent::tearDown();
    }

    /** Creates a template from its starter, then publishes it the way the builder does. */
    private function activate(string $kind, ?array $spec = null, bool $another = false): Post
    {
        $this->actingAs($this->admin);
        if ($another || ! Templates::find($kind)) {
            $this->post(route('admin.design.templates.store'), ['kind' => $kind])->assertRedirect();
        }
        $post = $another ? Post::where('type', 'template')->latest('id')->first() : Templates::find($kind);
        if ($spec) {
            $post->forceFill(['builder_draft' => Builder::compact($spec)])->save();
        }
        $this->postJson(route('admin.design.templates.builder.publish', $post), [])->assertOk();
        Templates::flush();

        return $post->fresh();
    }

    public function test_a_single_article_template_replaces_the_theme_view_once_published(): void
    {
        $this->actingAs($this->admin)->post(route('admin.design.templates.store'), ['kind' => 'single'])->assertRedirect();
        $template = Templates::find('single');
        $this->assertSame('draft', $template->status);
        // A draft template changes nothing on the site.
        $this->get('/blog/artikel-utama')->assertOk()->assertDontSee('b-con', false);

        $this->activate('single');
        $html = $this->get('/blog/artikel-utama')->assertOk()->getContent();
        $this->assertStringContainsString('css/builder.css', $html);
        $this->assertMatchesRegularExpression('#<h1 class="b-heading[^"]*">Artikel utama</h1>#', $html);
        $this->assertStringContainsString('<div class="prose"><p>Isi artikel</p></div>', $html);
        $this->assertStringContainsString('/category/berita">Berita</a>', $html);
        $this->assertStringContainsString('Artikel kedua', $html, 'related articles');
        $this->assertStringContainsString('name="_token"', $html, 'the comment form');
        $this->assertMatchesRegularExpression('#rel="canonical" href="[^"]+/blog/artikel-utama"#', $html);

        // Turned off, the theme's own view is back; the template is kept.
        $this->post(route('admin.design.templates.toggle', $template))->assertRedirect();
        Templates::flush();
        $this->get('/blog/artikel-utama')->assertOk()->assertDontSee('b-con', false)->assertSee('Isi artikel');
        $this->assertNotNull(Templates::find('single'));
    }

    public function test_list_templates_show_the_articles_and_categories_fall_back_to_the_list_template(): void
    {
        $this->activate('archive');

        foreach (['/blog' => 'Artikel', '/category/berita' => 'Berita', '/' => 'Test Site'] as $url => $heading) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('b-post-list', $html, $url);
            $this->assertStringContainsString('Artikel utama', $html, $url);
            $this->assertStringContainsString('>'.$heading, $html, $url);
        }

        // Its own template wins for categories and tags.
        $this->activate('taxonomy', ['version' => 1, 'sections' => [['type' => 'container', 'children' => [
            ['type' => 'heading', 'text' => 'Arsip khusus'], ['type' => 'archive-title'], ['type' => 'archive-posts'],
        ]]]]);
        $this->get('/category/berita')->assertOk()->assertSee('Arsip khusus');
        $this->get('/blog')->assertOk()->assertDontSee('Arsip khusus');
    }

    public function test_header_and_footer_templates_replace_the_theme_parts_everywhere(): void
    {
        $this->activate('header', ['version' => 1, 'sections' => [['type' => 'container', 'style' => ['direction' => 'row'], 'children' => [
            ['type' => 'site-logo'], ['type' => 'heading', 'text' => 'Header kustom'], ['type' => 'site-menu'],
        ]]]]);
        $this->activate('footer', ['version' => 1, 'sections' => [['type' => 'container', 'children' => [['type' => 'text', 'html' => '<p>Footer kustom</p>']]]]]);

        foreach (['/blog/artikel-utama', '/blog'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<header class="b-site-header">', $html, $url);
            $this->assertStringContainsString('Header kustom', $html, $url);
            $this->assertStringContainsString('Footer kustom', $html, $url);
            $this->assertStringNotContainsString('class="site-header"', $html, $url);
            $this->assertStringContainsString('flex-direction:row', $html, $url);
            // Once only: a second link after the header's <style> would override the header's own values.
            $this->assertSame(1, substr_count($html, 'css/builder.css'), $url);
        }
    }

    public function test_the_editor_previews_a_template_with_an_example_and_offers_its_widgets(): void
    {
        $template = $this->activate('single');
        $this->actingAs($this->admin);

        $state = $this->getJson(route('admin.design.templates.builder.spec', $template))->assertOk()->json();
        $this->assertSame('single', $state['templateContext']);
        $this->assertNull($state['viewUrl']);

        $preview = $this->post(route('admin.design.templates.builder.render', $template), ['spec' => $template->builderSpec()])->assertOk()->getContent();
        $this->assertStringContainsString('Artikel utama', $preview, 'the newest article is the example');
        $this->assertStringContainsString('noindex', $preview);
        $this->assertStringNotContainsString('name="_token"', $preview, 'no comment form in the preview');

        $this->get(route('admin.design.templates.builder.edit', $template))->assertOk()->assertSee(route('admin.design.templates.index'), false);
        $this->get(route('admin.design.templates.index'))->assertOk()->assertSee(__('Single article'))->assertSee(__('Active'));
    }

    public function test_conditions_pick_a_template_for_a_category_a_page_or_the_homepage(): void
    {
        $this->activate('single', ['version' => 1, 'sections' => [['type' => 'container', 'children' => [['type' => 'heading', 'text' => 'Umum'], ['type' => 'post-title']]]]]);
        $news = $this->activate('single', ['version' => 1, 'sections' => [['type' => 'container', 'children' => [['type' => 'heading', 'text' => 'Khusus berita'], ['type' => 'post-title']]]]], true);
        $this->assertStringStartsWith('single--', $news->slug);
        $other = Post::create(['user_id' => $this->admin->id, 'type' => 'article', 'title' => 'Tanpa kategori', 'slug' => 'tanpa-kategori', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        // Without conditions the main template wins; the second one is not used anywhere yet.
        $this->get('/blog/artikel-utama')->assertSee('Umum')->assertDontSee('Khusus berita');

        $category = Term::where('slug', 'berita')->first();
        $this->get(route('admin.design.templates.conditions', $news))->assertOk()->assertSee('Berita');
        $this->post(route('admin.design.templates.conditions.save', $news), ['title' => 'Berita', 'conditions' => ['', 'category:'.$category->id]])->assertRedirect();
        $this->assertSame(['category:'.$category->id], $news->fresh()->conditions);
        $this->post(route('admin.design.templates.conditions.save', $news), ['title' => 'Berita', 'conditions' => ['page:999999']])->assertSessionHasErrors('conditions.0');

        Templates::flush();
        $this->get('/blog/artikel-utama')->assertSee('Khusus berita')->assertDontSee('Umum');
        $this->get('/blog/'.$other->slug)->assertSee('Umum')->assertDontSee('Khusus berita');

        // A header only on one page; the theme header elsewhere.
        $page = Post::create(['user_id' => $this->admin->id, 'type' => 'page', 'title' => 'Tentang', 'slug' => 'tentang', 'content' => '<p>Tentang kami</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        $header = $this->activate('header', ['version' => 1, 'sections' => [['type' => 'container', 'children' => [['type' => 'heading', 'text' => 'Header tentang']]]]]);
        $header->forceFill(['conditions' => ['page:'.$page->id]])->save();
        Templates::flush();
        $this->get('/tentang')->assertSee('Header tentang');
        $this->get('/blog')->assertDontSee('Header tentang')->assertSee('class="site-header"', false);

        $this->get(route('admin.design.templates.index'))->assertOk()->assertSee('Kategori: Berita')->assertSee(__('Everywhere'));
    }

    public function test_a_container_saved_as_a_block_can_be_inserted_elsewhere(): void
    {
        $this->actingAs($this->admin);
        $node = ['id' => 'xhero', 'type' => 'container', 'children' => [['id' => 'wtitle', 'type' => 'heading', 'text' => 'Hero tersimpan']]];
        $response = $this->postJson(route('admin.design.blocks.store'), ['title' => 'Hero', 'node' => $node]);
        $this->assertSame(200, $response->status(), (string) $response->getContent());
        $saved = $response->json('block');
        $this->assertNotSame('xhero', $saved['node']['id'], 'a block gets new ids');

        $library = $this->getJson(route('admin.design.blocks.library'))->assertOk()->json('blocks');
        $this->assertSame('Hero', $library[0]['title']);
        $this->assertSame('Hero tersimpan', $library[0]['node']['children'][0]['text']);

        $this->postJson(route('admin.design.blocks.store'), ['title' => 'Widget', 'node' => ['type' => 'heading']])->assertStatus(422);

        $block = Post::where('type', 'block')->first();
        $this->get(route('admin.design.blocks.index'))->assertOk()->assertSee('Hero');
        $this->get(route('admin.design.blocks.builder.edit', $block))->assertOk();
        $this->post(route('admin.design.blocks.builder.render', $block), ['spec' => $block->builderSpec()])->assertOk()->assertSee('Hero tersimpan');
        $this->get('/'.$block->slug)->assertNotFound();

        // An editor of pages may use blocks but not manage them.
        $editor = User::create(['role_id' => Role::create(['name' => 'Editor', 'slug' => 'editor', 'capabilities' => ['pages.manage']])->id, 'name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password123']);
        $this->actingAs($editor)->getJson(route('admin.design.blocks.library'))->assertOk();
        $this->get(route('admin.design.blocks.index'))->assertForbidden();

        // "New block" in the list: an empty container, opened in the builder.
        $this->actingAs($this->admin)->get(route('admin.design.blocks.create'))->assertOk()->assertSee(__('New block'));
        $this->get(route('admin.design.blocks.index'))->assertSee(route('admin.design.blocks.create'), false);
        $this->post(route('admin.design.blocks.create.save'), ['title' => 'CTA'])->assertRedirect();
        $new = Post::where('type', 'block')->where('title', 'CTA')->firstOrFail();
        $this->assertSame('container', $new->builderSpec()['sections'][0]['type']);
        $this->get(route('admin.design.blocks.builder.edit', $new))->assertOk();
        $new->forceDelete();

        $this->post(route('admin.design.blocks.delete', $block))->assertRedirect();
        $this->assertSame(0, Post::where('type', 'block')->count());
    }

    public function test_templates_need_appearance_permission_and_stay_apart_from_pages(): void
    {
        $template = $this->activate('single');
        $editor = User::create(['role_id' => Role::create(['name' => 'Editor', 'slug' => 'editor', 'capabilities' => ['pages.manage']])->id, 'name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password123']);

        $this->actingAs($editor)->get(route('admin.design.templates.builder.edit', $template))->assertForbidden();
        $this->get(route('admin.pages.builder.edit', $template))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.design.templates.builder.classic', $template))->assertNotFound();
        $this->get('/'.$template->slug)->assertNotFound();

        $this->post(route('admin.design.templates.delete', $template))->assertRedirect();
        $this->assertNull(Templates::find('single'));
    }
}
