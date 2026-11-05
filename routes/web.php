<?php

use App\Http\Controllers\Admin;
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
