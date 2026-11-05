<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Throwable;

class InstallController extends Controller
{
    public function requirements()
    {
        $checks = [
            ['PHP 8.3+', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION],
            ['PDO MySQL / SQLite', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), ''],
            ['mbstring', extension_loaded('mbstring'), ''],
            ['openssl', extension_loaded('openssl'), ''],
            ['fileinfo', extension_loaded('fileinfo'), ''],
            ['dom', extension_loaded('dom'), ''],
            ['zip', class_exists('ZipArchive'), ''],
            ['.env '.__('writable'), is_writable(base_path('.env')), ''],
            ['storage/ '.__('writable'), is_writable(storage_path()), ''],
            ['bootstrap/cache/ '.__('writable'), is_writable(base_path('bootstrap/cache')), ''],
            ['public/uploads/ '.__('writable'), is_writable(public_path('uploads')), ''],
        ];
        $ok = collect($checks)->every(fn ($check) => $check[1]);

        return view('install.requirements', [
            'checks' => $checks,
            'ok' => $ok,
            'gd' => extension_loaded('gd'),
            'title' => __('Check requirements'),
            'step' => 1,
        ]);
    }

    public function database()
    {
        return view('install.database', ['title' => __('Database'), 'step' => 2, 'db' => session('install.db', [])]);
    }

    public function saveDatabase(Request $request)
    {
        $data = $request->validate([
            'driver' => 'required|in:mysql,sqlite',
            'host' => 'required_if:driver,mysql|nullable|string|max:190',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required_if:driver,mysql|nullable|string|max:190',
            'username' => 'required_if:driver,mysql|nullable|string|max:190',
            'password' => 'nullable|string|max:190',
        ]);

        try {
            $this->connect($data);
            if (Schema::hasTable('posts') || Schema::hasTable('users')) {
                return back()->withInput($request->except('password'))->with('error', __('This database already has CMS tables. Use an empty database.'));
            }
        } catch (Throwable $e) {
            return back()->withInput($request->except('password'))->with('error', __('Cannot connect to the database: :message', ['message' => $e->getMessage()]));
        }

        session(['install.db' => $data]);

        return redirect()->route('install.site');
    }

    public function site(Request $request)
    {
        if (! session('install.db')) {
            return redirect()->route('install.database');
        }

        return view('install.site', [
            'title' => __('Site and administrator'),
            'step' => 3,
            'siteUrl' => $request->getSchemeAndHttpHost().$request->getBasePath(),
        ]);
    }

    public function finish(Request $request)
    {
        $db = session('install.db');
        if (! $db) {
            return redirect()->route('install.database');
        }

        $data = $request->validate([
            'site_title' => 'required|string|max:190',
            'tagline' => 'nullable|string|max:190',
            'site_url' => 'required|url:http,https|max:190',
            'timezone' => 'required|timezone',
            'locale' => 'required|in:id,en',
            'name' => 'required|string|max:190',
            'email' => 'required|email|max:190',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        try {
            $this->connect($db);
            Artisan::call('migrate', ['--force' => true]);

            DB::transaction(function () use ($data) {
                foreach (config('cms.default_roles') as $slug => $role) {
                    Role::create([
                        'slug' => $slug,
                        'name' => $role['name'],
                        'capabilities' => $role['capabilities'] === '*' ? ['*'] : $role['capabilities'],
                        'is_system' => $slug === 'administrator',
                    ]);
                }

                $user = User::create([
                    'role_id' => Role::where('slug', 'administrator')->value('id'),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'locale' => $data['locale'],
                ]);

                Setting::write([
                    'site_title' => $data['site_title'],
                    'tagline' => $data['tagline'] ?? '',
                    'site_url' => rtrim($data['site_url'], '/'),
                    'timezone' => $data['timezone'],
                    'locale' => $data['locale'],
                ]);

                Auth::login($user);
            });

            $this->writeEnv($db, rtrim($data['site_url'], '/'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', __('Installation failed: :message', ['message' => $e->getMessage()]));
        }

        file_put_contents(storage_path('app/.installed'), now()->toIso8601String());
        session()->forget('install.db');
        session(['locale' => $data['locale']]);

        return redirect()->route('install.done');
    }

    public function done()
    {
        abort_unless(cms_installed() && Auth::check(), 404);

        return view('install.done', ['title' => __('Installation complete'), 'step' => 4]);
    }

    /** Points the running app at the chosen database and verifies the connection. */
    private function connect(array $db): void
    {
        if ($db['driver'] === 'sqlite') {
            $path = database_path('database.sqlite');
            if (! file_exists($path)) {
                touch($path);
            }
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $db['host'],
                'database.connections.mysql.port' => $db['port'] ?: 3306,
                'database.connections.mysql.database' => $db['database'],
                'database.connections.mysql.username' => $db['username'],
                'database.connections.mysql.password' => $db['password'] ?? '',
            ]);
        }
        DB::purge();
        DB::connection()->getPdo();
    }

    private function writeEnv(array $db, string $url): void
    {
        // APP_NAME stays untouched: Laravel derives the session cookie name from it,
        // so changing it would log the new administrator out. The title lives in settings.
        $values = ['APP_URL' => $url, 'DB_CONNECTION' => $db['driver']];
        if ($db['driver'] === 'mysql') {
            $values += [
                'DB_HOST' => $db['host'],
                'DB_PORT' => $db['port'] ?: 3306,
                'DB_DATABASE' => $db['database'],
                'DB_USERNAME' => $db['username'],
                'DB_PASSWORD' => $db['password'] ?? '',
            ];
        }

        $path = base_path('.env');
        $env = (string) file_get_contents($path);
        foreach ($values as $key => $value) {
            $safe = str_replace(['\\', '"', '$', "\n", "\r"], ['\\\\', '\\"', '\\$', '', ''], (string) $value);
            $line = $key.'="'.$safe.'"';
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
            $env = preg_match($pattern, $env)
                ? preg_replace_callback($pattern, fn () => $line, $env, 1)
                : rtrim($env)."\n".$line."\n";
        }
        file_put_contents($path, $env);
    }
}
