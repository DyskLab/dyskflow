<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Backups are zip files holding database.json (portable across MySQL and SQLite).
 * Full backups also hold uploads/ and the design folders theme/ and landing/ under design/.
 */
class BackupService
{
    public static function directory(): string
    {
        $directory = storage_path('app/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    public static function all(): Collection
    {
        return collect(glob(self::directory().'/*.zip') ?: [])
            ->map(fn ($path) => [
                'id' => basename($path),
                'name' => basename($path),
                'size' => filesize($path),
                'created' => filemtime($path),
                'kind' => str_contains(basename($path), '-full-') ? 'full' : 'database',
            ])
            ->values();
    }

    /** Only names listed by all() resolve, so a request cannot reach other files. */
    public static function path(string $name): ?string
    {
        return self::all()->contains('name', $name) ? self::directory().'/'.$name : null;
    }

    public static function create(bool $withUploads = true): string
    {
        @set_time_limit(300);
        $name = 'backup-'.($withUploads ? 'full' : 'db').'-'.now()->format('Ymd-His').'.zip';
        $zip = new ZipArchive;
        if ($zip->open(self::directory().'/'.$name, ZipArchive::CREATE) !== true) {
            throw new RuntimeException(__('Cannot create the backup file.'));
        }

        $data = ['version' => config('cms.version'), 'created_at' => now()->toIso8601String(), 'tables' => []];
        foreach (config('cms.backup_tables') as $table) {
            $data['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        $zip->addFromString('database.json', json_encode($data, JSON_UNESCAPED_UNICODE));

        if ($withUploads && is_dir(public_path('uploads'))) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(public_path('uploads'), RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile() && $file->getFilename() !== '.htaccess') {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(public_path()) + 1));
                    $zip->addFile($file->getPathname(), $relative);
                }
            }
        }
        if ($withUploads) {
            self::addDesignFiles($zip);
        }
        $zip->close();

        return $name;
    }

    public static function restore(string $path): void
    {
        @set_time_limit(300);
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('Cannot open the backup file.'));
        }
        $data = json_decode((string) $zip->getFromName('database.json'), true);
        if (! is_array($data['tables'] ?? null)) {
            $zip->close();
            throw new RuntimeException(__('The backup has no valid database.json.'));
        }

        $tables = config('cms.backup_tables');
        Schema::withoutForeignKeyConstraints(function () use ($data, $tables) {
            DB::transaction(function () use ($data, $tables) {
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }
                foreach ($tables as $table) {
                    $columns = array_flip(Schema::getColumnListing($table));
                    foreach (array_chunk($data['tables'][$table] ?? [], 200) as $chunk) {
                        DB::table($table)->insert(array_map(fn ($row) => array_intersect_key($row, $columns), $chunk));
                    }
                }
            });
        });

        self::restoreUploads($zip);
        self::restoreDesignFiles($zip);
        $zip->close();
    }

    /** Folders from config('cms.design_roots') that a full backup keeps. Plugins come from their own source. */
    public const DESIGN_FOLDERS = ['theme', 'landing'];

    /** Larger files, such as long videos, are left out so a backup stays small enough to download. */
    public const DESIGN_MAX_FILE = 20 * 1024 * 1024;

    private static function addDesignFiles(ZipArchive $zip): void
    {
        foreach (self::DESIGN_FOLDERS as $folder) {
            $root = config('cms.design_roots.'.$folder);
            if (! $root || ! is_dir($root)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($file->isFile() && ! $file->isLink() && self::designFileAllowed($relative) && $file->getSize() <= self::DESIGN_MAX_FILE) {
                    $zip->addFile($file->getPathname(), 'design/'.$folder.'/'.$relative);
                }
            }
        }
    }

    /** Writes design/theme/* and design/landing/* back. Existing files are replaced; files not in the backup stay. */
    private static function restoreDesignFiles(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (! preg_match('#^design/('.implode('|', self::DESIGN_FOLDERS).')/(.+)$#', $name, $match) || str_ends_with($name, '/')) {
                continue;
            }
            [, $folder, $relative] = $match;
            $root = config('cms.design_roots.'.$folder);
            if (! $root || ! self::designFileAllowed($relative)) {
                continue;
            }
            $target = $root.'/'.$relative;
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            $realRoot = realpath($root);
            $realParent = realpath(dirname($target));
            if ($realRoot === false || $realParent === false || ! str_starts_with($realParent.'/', $realRoot.'/') || is_link($target)) {
                continue;
            }
            copy('zip://'.$zip->filename.'#'.$name, $target);
        }
    }

    private static function designFileAllowed(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '..' || str_starts_with($segment, '.') || in_array(strtolower($segment), ['vendor', 'node_modules'], true)) {
                return false;
            }
        }
        $extension = SafeFiles::extension($relative);

        return in_array($extension, SafeFiles::EDITABLE, true) || in_array($extension, SafeFiles::UPLOADABLE, true);
    }

    private static function restoreUploads(ZipArchive $zip): void
    {
        $blocked = config('cms.upload_blocked');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! str_starts_with($name, 'uploads/') || str_ends_with($name, '/') || str_contains($name, '..') || in_array($extension, $blocked, true)) {
                continue;
            }
            $target = public_path($name);
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy('zip://'.$zip->filename.'#'.$name, $target);
        }
    }
}
