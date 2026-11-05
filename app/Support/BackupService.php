<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/** Backups are zip files holding database.json (portable across MySQL and SQLite) plus uploads. */
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
        $zip->close();
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
