<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\SiteLocale;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/* Install wizard */
Route::prefix('install')->controller(InstallController::class)->group(function () {
    Route::get('/', 'requirements')->name('install');
    Route::get('database', 'database')->name('install.database');
    Route::post('database', 'saveDatabase');
    Route::get('site', 'site')->name('install.site');
    Route::post('site', 'finish');
    Route::get('done', 'done')->name('install.done');
});

/* Language switch */
// The builder's Form widget. No CSRF token, so public pages stay cookie-free: see App\Support\Forms.
Route::post('form', [FormController::class, 'submit'])->middleware('throttle:20,1')->name('form.submit');
Route::post('locale/{locale}', [Admin\AccountController::class, 'locale'])->whereIn('locale', ['en', 'id'])->name('locale');

/* Auth */
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AccountController::class, 'loginForm'])->name('login');
        Route::post('login', [Admin\AccountController::class, 'login'])->middleware('throttle:10,1');
        Route::get('login/totp', [Admin\AccountController::class, 'totpForm'])->name('login.totp');
        Route::post('login/totp', [Admin\AccountController::class, 'totp'])->middleware('throttle:10,1');
    });
    Route::post('logout', [Admin\AccountController::class, 'logout'])->middleware('auth')->name('logout');
});

/* Admin */
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('search', Admin\SearchController::class)->name('search');

    Route::prefix('account/security')->name('account.security.')->controller(Admin\SecurityController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('setup', 'setup')->name('setup');
        Route::post('setup', 'confirm')->middleware('throttle:10,1')->name('confirm');
        Route::get('disable', 'disableForm')->name('disable.form');
        Route::post('disable', 'disable')->middleware('throttle:10,1')->name('disable');
        Route::get('codes', 'codesForm')->name('codes.form');
        Route::post('codes', 'codes')->middleware('throttle:10,1')->name('codes');
    });

    Route::prefix('notifications')->name('notifications.')->controller(Admin\NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('read-all', 'readAll')->name('read-all');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::get('check', 'check')->name('check');
        Route::get('{id}/open', 'open')->whereNumber('id')->name('open');
    });

    foreach (['articles' => ['article', 'articles.write'], 'pages' => ['page', 'pages.manage']] as $path => [$type, $capability]) {
        Route::prefix($path)->name("$path.")->middleware("can:$capability")->controller(Admin\PostController::class)->group(function () use ($type) {
            Route::get('/', 'index')->name('index')->defaults('type', $type);
            Route::get('create', 'create')->name('create')->defaults('type', $type);
            Route::post('/', 'store')->name('store')->defaults('type', $type);
            Route::post('bulk', 'bulk')->name('bulk')->defaults('type', $type);
            Route::get('{post}/edit', 'edit')->whereNumber('post')->name('edit')->defaults('type', $type);
            Route::post('{post}', 'update')->whereNumber('post')->name('update')->defaults('type', $type);
        });
        Route::prefix("$path/{post}/translation")->name("$path.translation")->middleware("can:$capability")->whereNumber('post')->controller(Admin\PostTranslationController::class)->group(function () use ($type) {
            Route::get('/', 'edit')->defaults('type', $type);
            Route::post('/', 'save')->name('.save')->defaults('type', $type);
            Route::post('delete', 'destroy')->name('.delete')->defaults('type', $type);
        });
    }

    // The visual builder edits the JSON layout of a page (posts.builder), like Elementor, and of a
    // Theme Builder template (Design > Templates) with the same actions under its own address.
    $builderRoutes = function () {
        Route::prefix('{post}/builder')->whereNumber('post')->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::post('start', 'start')->name('start');
            Route::post('classic', 'classic')->name('classic');
            Route::get('spec', 'spec')->name('spec');
            Route::post('draft', 'saveDraft')->middleware('throttle:120,1')->name('draft');
            Route::post('render', 'render')->middleware('throttle:240,1')->name('render');
            Route::post('publish', 'publish')->middleware('throttle:30,1')->name('publish');
            Route::post('upload', 'upload')->middleware(['can:media.manage', 'throttle:30,1'])->name('upload');
            Route::post('discard', 'discard')->name('discard');
            Route::get('revisions', 'revisions')->name('revisions');
            Route::get('revisions/{revision}', 'revision')->whereNumber('revision')->name('revision');
            Route::post('revisions/{revision}/restore', 'restore')->whereNumber('revision')->name('restore');
        });
    };
    Route::prefix('pages')->name('pages.builder.')->middleware('can:pages.manage')->controller(Admin\BuilderController::class)->group(function () use ($builderRoutes) {
        Route::get('builder/create', 'createForm')->name('create');
        Route::post('builder/create', 'store')->name('store');
        Route::get('builder/starter', 'starter')->name('starter');
        $builderRoutes();
    });
    Route::prefix('design/templates')->name('design.templates.')->middleware('can:appearance.manage')->group(function () use ($builderRoutes) {
        Route::controller(Admin\TemplateController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'createForm')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{template}/conditions', 'conditionsForm')->whereNumber('template')->name('conditions');
            Route::post('{template}/conditions', 'saveConditions')->whereNumber('template')->name('conditions.save');
            Route::post('{template}/toggle', 'toggle')->whereNumber('template')->name('toggle');
            Route::post('{template}/delete', 'destroy')->whereNumber('template')->name('delete');
        });
        Route::name('builder.')->controller(Admin\BuilderController::class)->group($builderRoutes);
    });
    // Saved blocks: containers kept to be inserted into other layouts. The list is under Design;
    // the editor's own calls need either permission, since pages and templates both use blocks.
    Route::prefix('design/blocks')->name('design.blocks.')->group(function () use ($builderRoutes) {
        Route::controller(Admin\BlockController::class)->group(function () {
            Route::get('/', 'index')->middleware('can:appearance.manage')->name('index');
            Route::get('create', 'createForm')->middleware('can:appearance.manage')->name('create');
            Route::post('create', 'create')->middleware('can:appearance.manage')->name('create.save');
            Route::post('{block}/delete', 'destroy')->whereNumber('block')->middleware('can:appearance.manage')->name('delete');
            Route::get('library', 'library')->name('library');
            Route::post('library', 'store')->name('store');
        });
        Route::name('builder.')->middleware('can:appearance.manage')->controller(Admin\BuilderController::class)->group($builderRoutes);
    });

    foreach (['categories' => 'category', 'tags' => 'tag'] as $path => $taxonomy) {
        Route::prefix($path)->name("$path.")->middleware('can:terms.manage')->controller(Admin\TermController::class)->group(function () use ($taxonomy) {
            Route::get('/', 'index')->name('index')->defaults('taxonomy', $taxonomy);
            Route::get('create', 'form')->name('create')->defaults('taxonomy', $taxonomy);
            Route::post('/', 'save')->name('store')->defaults('taxonomy', $taxonomy);
            Route::post('bulk', 'bulk')->name('bulk')->defaults('taxonomy', $taxonomy);
            Route::get('{id}/edit', 'form')->whereNumber('id')->name('edit')->defaults('taxonomy', $taxonomy);
            Route::post('{id}', 'save')->whereNumber('id')->name('update')->defaults('taxonomy', $taxonomy);
        });
    }

    Route::prefix('forms')->name('forms.')->middleware('can:forms.manage')->controller(Admin\FormSubmissionController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->name('export');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::get('{submission}', 'show')->whereNumber('submission')->name('show');
    });
    Route::prefix('comments')->name('comments.')->middleware('can:comments.manage')->controller(Admin\CommentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::get('{comment}/edit', 'edit')->name('edit');
        Route::post('{comment}', 'update')->name('update');
    });

    Route::prefix('media')->name('media.')->middleware('can:media.manage')->controller(Admin\MediaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('picker', 'picker')->name('picker');
        Route::get('upload', 'uploadForm')->name('upload');
        Route::post('/', 'store')->name('store');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::get('{media}/edit', 'edit')->name('edit');
        Route::post('{media}', 'update')->name('update');
    });

    foreach (['users' => Admin\UserController::class, 'roles' => Admin\RoleController::class] as $path => $controller) {
        Route::prefix($path)->name("$path.")->middleware('can:users.manage')->controller($controller)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'form')->name('create');
            Route::post('/', 'save')->name('store');
            Route::post('bulk', 'bulk')->name('bulk');
            Route::get('{id}/edit', 'form')->whereNumber('id')->name('edit');
            Route::post('{id}', 'save')->whereNumber('id')->name('update');
        });
    }

    Route::prefix('design')->name('design.')->middleware('can:appearance.manage')->controller(Admin\FileController::class)->group(function () {
        Route::get('files', 'index')->name('files.index');
        Route::get('files/edit', 'edit')->name('files.edit');
        Route::get('files/custom/{kind}', 'custom')->whereIn('kind', ['css', 'js'])->name('files.custom');
        Route::post('files/save', 'save')->middleware('throttle:60,1')->name('files.save');
        Route::get('files/create', 'createForm')->name('files.create');
        Route::post('files/create', 'store')->name('files.store');
        Route::get('files/rename', 'renameForm')->name('files.rename');
        Route::post('files/rename', 'rename')->name('files.rename.save');
        Route::post('files/delete', 'delete')->name('files.delete');
        Route::get('files/upload', 'uploadForm')->name('files.upload');
        Route::post('files/upload', 'upload')->name('files.upload.save');
        Route::get('files/revisions', 'revisions')->name('files.revisions');
        Route::get('files/revisions/{id}', 'revision')->whereNumber('id')->name('files.revision');
        Route::post('files/revisions/{id}/restore', 'restore')->whereNumber('id')->name('files.restore');
        Route::get('confirm', 'confirmForm')->name('confirm');
        Route::post('confirm', 'confirm')->middleware('throttle:10,1');
    });

    Route::prefix('design')->name('design.')->middleware('can:appearance.manage')->controller(Admin\DesignController::class)->group(function () {
        Route::get('theme', 'theme')->name('theme.index');
        Route::post('theme/override', 'overrideFile')->name('theme.override');
        Route::post('theme/bulk', 'themeBulk')->name('theme.bulk');
        // Landing pages are a folder of the Explorer now; the old address still works.
        Route::get('landing', fn () => redirect()->route('admin.design.files.index', ['dir' => 'landing'], 301))->name('landing.index');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [Admin\SettingController::class, 'index'])->name('index');

        Route::prefix('logs')->name('logs.')->middleware('can:logs.view')->controller(Admin\ActivityController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('bulk', 'bulk')->name('bulk');
        });

        Route::prefix('backups')->name('backups.')->middleware('can:backups.manage')->controller(Admin\BackupController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('bulk', 'bulk')->name('bulk');
            Route::get('{name}/download', 'download')->name('download');
            Route::post('{name}/restore', 'restore')->name('restore');
        });

        Route::prefix('tools')->name('tools.')->middleware('can:tools.manage')->controller(Admin\ToolController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('export', 'export')->name('export');
            Route::post('import', 'import')->name('import');
        });

        Route::prefix('update')->name('update.')->middleware('can:updates.manage')->controller(Admin\UpdateController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('repo', 'saveRepo')->name('repo');
            Route::post('run', 'run')->name('run');
            Route::get('finish', 'finish')->name('finish');
        });

        Route::prefix('plugins')->name('plugins.')->middleware('can:plugins.manage')->controller(Admin\PluginController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('bulk', 'bulk')->name('bulk');
            Route::get('{slug}/settings', 'settings')->name('settings');
            Route::post('{slug}/settings', 'saveSettings');
        });

        Route::middleware('can:settings.manage')->group(function () {
            Route::post('email/test', [Admin\SettingController::class, 'testEmail'])->name('email.test');
            Route::get('privacy', [Admin\SiteToolsController::class, 'privacy'])->name('privacy.index');
            Route::post('privacy/draft', [Admin\SiteToolsController::class, 'privacyDraft'])->name('privacy.draft');
            Route::get('health', [Admin\SiteToolsController::class, 'health'])->name('health.index');
            Route::get('{section}', [Admin\SettingController::class, 'edit'])->name('edit');
            Route::post('{section}', [Admin\SettingController::class, 'update'])->name('update');
        });
    });
});

/* Public site: one address for the whole site */
Route::controller(SiteController::class)->group(function () {
    Route::get('sitemap.xml', 'sitemap');
    Route::get('robots.txt', 'robots');
    Route::get('_landing/{slug}/{path}', 'landingAsset')->where('path', '.*');
    Route::get('_theme/{path}', 'themeAsset')->where('path', '.*');
});

/* Public site: the same pages in each language. In views, link with site_route() to stay in the current language. */
$publicRoutes = function () {
    Route::controller(SiteController::class)->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('feed', 'feed')->name('feed');
        Route::get('search', 'search')->name('search');
        Route::get('blog', 'blog')->name('blog');
        Route::get('blog/{slug}', 'article')->name('article');
        Route::post('blog/{slug}/comments', 'comment')->middleware('throttle:5,1')->name('comment');
        Route::get('category/{slug}', 'term')->defaults('taxonomy', 'category')->name('category');
        Route::get('tag/{slug}', 'term')->defaults('taxonomy', 'tag')->name('tag');
        Route::get('{slug}', 'page')->name('page');
    });
};
foreach (array_keys(Locales::ALL) as $locale) {
    Route::prefix($locale)->name("$locale.")->middleware(SiteLocale::class.":$locale")->group($publicRoutes);
}
Route::middleware(SiteLocale::class)->group($publicRoutes);
