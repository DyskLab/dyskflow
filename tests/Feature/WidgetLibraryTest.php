<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Builder\Builder;
use App\Support\Builder\Widgets;
use App\Support\Forms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** The widgets added after the first builder release, and the Form widget's messages. */
class WidgetLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $storage = sys_get_temp_dir().'/cms-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/views');
        $this->app->useStoragePath($storage);
        file_put_contents($storage.'/app/.installed', 'test');
        Widgets::flush();

        $this->admin = User::create(['role_id' => Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'capabilities' => ['*'], 'is_system' => true])->id, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123']);
        Setting::write(['locale' => 'id', 'site_title' => 'Test Site', 'site_url' => 'http://localhost', 'home_type' => 'posts', 'contact_email' => 'owner@example.test', 'social_instagram' => 'https://instagram.com/dysk']);
    }

    private function page(array $widgets, string $slug = 'zz-widgets'): Post
    {
        return Post::create(['type' => 'page', 'title' => 'Widgets', 'slug' => $slug, 'content' => '', 'status' => 'published', 'published_at' => now()->subMinute(),
            'builder' => Builder::compact(['version' => 1, 'layout' => 'site', 'sections' => [['id' => 's1', 'type' => 'section', 'columns' => [['id' => 'c1', 'widgets' => $widgets]]]]])]);
    }

    public function test_every_widget_renders_with_its_defaults_without_errors(): void
    {
        $widgets = [];
        foreach (array_keys(Widgets::all()) as $i => $type) {
            $widgets[] = ['id' => 'w'.$i, 'type' => $type];
        }
        // Required fields are still empty, so this layout could not be published; it still renders.
        $page = $this->page($widgets);
        $this->get('/zz-widgets')->assertOk();

        // The preview shows a hint or the widget for each of them, never an error box.
        $html = Builder::render($page->builderSpec(), '', ['title' => 'x'], true);
        $this->assertStringNotContainsString('b-problem', $html);
    }

    public function test_the_new_content_widgets_render_their_content(): void
    {
        $this->page([
            ['id' => 'wib', 'type' => 'icon-box', 'items' => [['icon' => 'rocket', 'title' => 'Cepat', 'text' => 'Tanpa build', 'url' => '/x']]],
            ['id' => 'wal', 'type' => 'alert', 'kind' => 'warning', 'title' => 'Perhatian', 'dismissible' => true],
            ['id' => 'wst', 'type' => 'star-rating', 'rating' => '4.5', 'label' => '120 ulasan'],
            ['id' => 'wso', 'type' => 'social-icons'],
            ['id' => 'wah', 'type' => 'animated-headline', 'before' => 'Situs yang', 'words' => "cepat\naman\nmudah"],
            ['id' => 'wac', 'type' => 'accordion', 'items' => [['title' => 'Satu', 'body' => '<p>Isi <script>x</script></p>'], ['title' => 'Dua', 'body' => '<p>B</p>']]],
            ['id' => 'wtb', 'type' => 'tabs', 'items' => [['title' => 'A', 'body' => '<p>Panel A</p>'], ['title' => 'B', 'body' => '<p>Panel B</p>']]],
            ['id' => 'wco', 'type' => 'counter', 'items' => [['number' => 1200, 'suffix' => '+', 'label' => 'Klien']]],
            ['id' => 'wpr', 'type' => 'progress', 'items' => [['label' => 'PHP', 'percent' => 150]]],
            ['id' => 'wcd', 'type' => 'countdown', 'date' => now()->addDays(3)->format('Y-m-d H:i'), 'ended' => 'Selesai'],
            ['id' => 'wrv', 'type' => 'reviews', 'items' => [['text' => 'Bagus sekali', 'name' => 'Sari', 'rating' => '5']], 'autoplay' => 4],
            ['id' => 'wcode', 'type' => 'code-highlight', 'code' => "<?php echo 1;\n\$a = '<b>';", 'language' => 'PHP'],
            ['id' => 'wtoc', 'type' => 'table-of-contents'],
            ['id' => 'wsh', 'type' => 'share-buttons'],
            ['id' => 'wvp', 'type' => 'video-playlist', 'items' => [['title' => 'Intro', 'url' => 'https://youtu.be/dQw4w9WgXcQ'], ['title' => 'Bad', 'url' => 'https://evil.test/v']]],
        ]);

        $html = $this->get('/zz-widgets')->assertOk()->getContent();
        $this->assertStringContainsString('<a href="/x">Cepat</a>', $html);
        $this->assertStringContainsString('b-alert-warning" role="alert"', $html);
        $this->assertStringContainsString('--b-rating: 4.5', $html);
        $this->assertStringContainsString('href="https://instagram.com/dysk"', $html);
        $this->assertStringContainsString('href="mailto:owner@example.test"', $html);
        $this->assertSame(3, preg_match_all('/class="b-animhead-word( is-on)?"/', $html));
        $this->assertStringContainsString('<details class="b-accordion-item" name="acc-', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertMatchesRegularExpression('/id="tab-[a-f0-9]+-p1"[^>]*hidden/', $html);
        $this->assertStringContainsString('data-b-count="1200"', $html);
        $this->assertStringContainsString('aria-valuenow="100"', $html, 'percent is capped');
        $this->assertMatchesRegularExpression('/data-b-countdown="\d+"/', $html);
        $this->assertStringContainsString('data-autoplay="4"', $html);
        $this->assertStringContainsString('&lt;?php echo 1;', $html, 'code is shown, never run');
        $this->assertStringContainsString('data-b-toc="h2-h3"', $html);
        $this->assertStringContainsString('https://wa.me/?text=', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringNotContainsString('evil.test', $html);
    }

    public function test_a_form_keeps_messages_without_cookies_and_notifies_the_owner(): void
    {
        Mail::fake();
        $this->page([['id' => 'wform', 'type' => 'form', 'name' => 'Kontak', 'email_to' => '', 'success' => 'Terima kasih!', 'fields' => [
            ['label' => 'Nama', 'kind' => 'text', 'required' => true],
            ['label' => 'Email', 'kind' => 'email', 'required' => true],
            ['label' => 'Paket', 'kind' => 'select', 'options' => "Basic\nPro"],
            ['label' => 'Pesan', 'kind' => 'textarea'],
            ['label' => 'Setuju', 'kind' => 'checkbox', 'required' => true],
        ]]]);

        $response = $this->get('/zz-widgets')->assertOk();
        $this->assertSame([], $response->headers->getCookies(), 'the page stays cookie-free');
        $html = $response->getContent();
        $this->assertStringNotContainsString('name="_token"', $html);
        preg_match('/name="_form" value="([^"]+)"/', $html, $m);
        $sealed = html_entity_decode($m[1]);
        $this->assertSame('Kontak', Forms::open($sealed)['name']);
        $this->assertStringNotContainsString('owner@example.test', $sealed, 'settings are encrypted');

        // Errors per field, as JSON for the script.
        $this->postJson(route('form.submit'), ['_form' => $sealed, 'f0' => '', 'f1' => 'bukan-email', 'f2' => 'Gratis'])
            ->assertStatus(422)->assertJsonValidationErrors(['f0', 'f1', 'f2', 'f4']);

        $this->postJson(route('form.submit'), ['_form' => $sealed, 'f0' => 'Budi', 'f1' => 'budi@example.test', 'f2' => 'Pro', 'f3' => "Halo\nSaya tertarik", 'f4' => '1'])
            ->assertOk()->assertJson(['message' => 'Terima kasih!']);
        $message = FormSubmission::sole();
        $this->assertSame('Kontak', $message->form);
        $this->assertSame('budi@example.test', $message->email);
        $this->assertSame(['Nama', 'Email', 'Paket', 'Pesan', 'Setuju'], array_column($message->data, 'label'));
        $this->assertSame('Ya', $message->data[4]['value']);
        Mail::assertSentCount(0);
        $this->assertDatabaseHas('admin_notifications', ['type' => 'form']);

        // Without JavaScript: back to the page with the thank-you message.
        RateLimiter::clear('form:'.sha1('127.0.0.1'));
        $this->post(route('form.submit'), ['_form' => $sealed, 'f0' => 'Ani', 'f1' => 'ani@example.test', 'f4' => '1'])
            ->assertRedirect(url('/zz-widgets').'?sent=wform#form-wform');
        $this->get('/zz-widgets?sent=wform')->assertSee('Terima kasih!');

        // A bot that fills the hidden field is told it worked, and nothing is kept.
        $this->postJson(route('form.submit'), ['_form' => $sealed, Forms::HONEYPOT => 'http://spam', 'f0' => 'Bot', 'f1' => 'bot@example.test', 'f4' => '1'])->assertOk();
        // A forged or edited form is refused.
        $this->postJson(route('form.submit'), ['_form' => 'not-sealed', 'f0' => 'x'])->assertStatus(422);
        $this->assertSame(2, FormSubmission::count());
    }

    public function test_messages_are_read_exported_and_deleted_in_the_admin(): void
    {
        $one = FormSubmission::create(['form' => 'Kontak', 'email' => 'a@example.test', 'data' => [['label' => 'Nama', 'value' => 'Ani'], ['label' => 'Pesan', 'value' => '=SUM(A1)']], 'page_url' => 'http://localhost/kontak']);
        FormSubmission::create(['form' => 'Daftar', 'data' => [['label' => 'Nama', 'value' => 'Budi']]]);

        $this->actingAs($this->admin)->get(route('admin.forms.index'))->assertOk()->assertSee('Ani')->assertSee('Daftar');
        $this->get(route('admin.dashboard'))->assertSee(route('admin.forms.index'), false);
        $this->get(route('admin.forms.show', $one))->assertOk()->assertSee('=SUM(A1)')->assertSee('mailto:a@example.test', false);
        $this->assertTrue($one->fresh()->is_read);

        $csv = $this->get(route('admin.forms.export', ['form' => 'Kontak']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Nama,Pesan', $csv);
        $this->assertStringContainsString("'=SUM(A1)", $csv, 'no formula runs in a spreadsheet');
        $this->assertStringNotContainsString('Budi', $csv);

        $this->post(route('admin.forms.bulk'), ['action' => 'delete', 'ids' => [(string) $one->id]])->assertRedirect();
        $this->assertSame(1, FormSubmission::count());

        // Only people with forms.manage see them.
        $author = User::create(['role_id' => Role::create(['name' => 'Author', 'slug' => 'author', 'capabilities' => ['articles.write']])->id, 'name' => 'Au', 'email' => 'au@example.test', 'password' => 'password123']);
        $this->actingAs($author)->get(route('admin.forms.index'))->assertForbidden();
    }

    public function test_the_widget_list_is_complete_and_valid(): void
    {
        foreach (['icon', 'icon-box', 'image-box', 'alert', 'blockquote', 'star-rating', 'social-icons', 'animated-headline', 'accordion', 'tabs', 'counter', 'progress',
            'call-to-action', 'flip-box', 'countdown', 'price-list', 'reviews', 'slides', 'image-carousel', 'hotspot', 'table-of-contents', 'share-buttons',
            'code-highlight', 'portfolio', 'video-playlist', 'audio', 'search-form', 'site-title', 'breadcrumbs', 'sitemap', 'reading-progress',
            'post-navigation', 'post-excerpt', 'form'] as $type) {
            $this->assertNotNull(Widgets::get($type), $type);
        }
        $this->assertSame([], Widgets::problems());
    }
}
