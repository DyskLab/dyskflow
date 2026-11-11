<?php

use App\Http\Controllers\Admin\BuilderController;
use App\Models\Post;
use App\Models\User;
use App\Support\Builder\Builder;
use App\Support\Builder\Schema;
use App\Support\Builder\Validator;
use App\Support\Builder\Widgets;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cms:totp-reset {email}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error('No user with that email.');

        return 1;
    }

    $user->disableTotp();
    activity('user.totp_disabled', $user->email);
    $this->info('Two-step verification is off for '.$user->email.'.');

    return 0;
})->purpose('Turn off two-step verification for a locked-out user');

/*
 * Builder pages are pages with their layout as JSON in posts.builder (draft in posts.builder_draft).
 * These commands are how AI tools and scripts read and write them. See docs/BUILDER.md.
 */
$builderPage = function (string $page): ?Post {
    $query = Post::where('type', 'page')->whereNotNull('builder');

    return ctype_digit($page) ? $query->find((int) $page) : $query->where('slug', $page)->first();
};

Artisan::command('builder:schema {--all : Include theme and plugin widgets and print to the screen instead of writing docs/builder.schema.json}', function () {
    foreach (Widgets::problems() as $problem) {
        $this->warn($problem);
    }
    if ($this->option('all')) {
        $this->line(rtrim(Schema::encode(Schema::build(true))));

        return 0;
    }
    file_put_contents(base_path('docs/builder.schema.json'), Schema::encode(Schema::build()));
    $this->info('Wrote docs/builder.schema.json.');

    return 0;
})->purpose('Write the JSON Schema for builder layouts from the widget definitions');

Artisan::command('builder:list', function () {
    $pages = Post::where('type', 'page')->whereNotNull('builder')->orderBy('id')->get(['id', 'slug', 'title', 'status', 'builder_draft']);
    if ($pages->isEmpty()) {
        $this->info('No builder pages yet. Create one with builder:import <slug> <file> --create="Title".');

        return 0;
    }
    $this->table(['id', 'slug', 'title', 'status', 'draft'], $pages->map(fn ($p) => [$p->id, $p->slug, $p->title, $p->status, $p->builder_draft !== null ? 'yes' : 'no'])->all());

    return 0;
})->purpose('List the pages made with the builder');

Artisan::command('builder:export {page : Id or slug} {--published : The published layout, even when there is a draft}', function (string $page) use ($builderPage) {
    $post = $builderPage($page);
    if (! $post) {
        $this->error("There is no builder page \"{$page}\". See builder:list.");

        return 1;
    }
    $spec = $post->builderSpec(! $this->option('published')) ?? Builder::blank();
    $this->line(rtrim(Builder::encode(['$schema' => base_path('docs/builder.schema.json')] + $spec)));

    return 0;
})->purpose('Print the layout of a builder page as JSON (the draft when there is one)');

Artisan::command('builder:import {page : Id or slug} {file : A JSON file, or - to read from stdin} {--publish : Publish it instead of saving a draft} {--create= : Create the page with this title when it does not exist; the slug is "page"}', function (string $page, string $file) use ($builderPage) {
    $json = $file === '-' ? stream_get_contents(STDIN) : (is_file($file) ? file_get_contents($file) : false);
    if ($json === false) {
        $this->error("Cannot read {$file}.");

        return 1;
    }
    $spec = json_decode((string) $json, true);
    if (! is_array($spec)) {
        $this->error('Not valid JSON ('.json_last_error_msg().').');

        return 1;
    }
    unset($spec['$schema']);
    $result = Validator::check($spec);
    foreach ($result['warnings'] as $warning) {
        $this->warn($warning);
    }
    foreach ($result['errors'] as $error) {
        $this->error($error);
    }
    if ($result['errors']) {
        $this->error('Nothing was saved.');

        return 1;
    }

    $post = $builderPage($page);
    if (! $post && ($title = (string) $this->option('create')) !== '') {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $page) || in_array($page, (array) config('cms.reserved_slugs'), true) || Post::withTrashed()->where('type', 'page')->where('slug', $page)->exists()) {
            $this->error("The slug \"{$page}\" is not valid, is reserved, or is used by another page.");

            return 1;
        }
        $post = Post::create(['type' => 'page', 'title' => $title, 'slug' => $page, 'content' => '', 'status' => 'draft', 'allow_comments' => false, 'builder' => Builder::compact(Builder::blank())]);
        $this->info("Created page #{$post->id} /{$page} as a draft.");
    }
    if (! $post) {
        $this->error("There is no builder page \"{$page}\". Add --create=\"Title\" to create it, or see builder:list.");

        return 1;
    }

    if ($this->option('publish')) {
        BuilderController::publishLayout($post, $result['spec']);
        $this->info("Published page #{$post->id} at ".$post->url().'.');
    } else {
        $post->forceFill(['builder_draft' => Builder::compact($result['spec'])])->save();
        $this->info("Saved as the draft of page #{$post->id}. Check it in the builder, or run again with --publish.");
    }

    return 0;
})->purpose('Save a JSON layout into a builder page, as a draft or published');

Artisan::command('builder:validate {page? : Id or slug. Leave out to check every builder page} {--published : Check the published layout instead of the draft}', function (?string $page = null) use ($builderPage) {
    $pages = $page ? array_filter([$builderPage($page)]) : Post::where('type', 'page')->whereNotNull('builder')->orderBy('id')->get()->all();
    if ($page && ! $pages) {
        $this->error("There is no builder page \"{$page}\". See builder:list.");

        return 1;
    }
    if (! $pages) {
        $this->info('No builder pages found.');

        return 0;
    }
    foreach (Widgets::problems() as $problem) {
        $this->warn($problem);
    }

    $failed = false;
    foreach ($pages as $post) {
        $label = "#{$post->id} /{$post->slug}";
        $result = Validator::check($post->builderSpec(! $this->option('published')));
        foreach ($result['errors'] as $error) {
            $this->error("{$label}: {$error}");
        }
        foreach ($result['warnings'] as $warning) {
            $this->warn("{$label}: {$warning}");
        }
        $failed = $failed || $result['errors'];
        if (! $result['errors']) {
            $this->info("{$label}: OK".($result['warnings'] ? ' with '.count($result['warnings']).' warning(s)' : '').'.');
        }
    }

    return $failed ? 1 : 0;
})->purpose('Check builder pages for errors');
