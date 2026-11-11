<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/** Stores uploads in public/uploads/Y/m, which works on hosting without symlinks. */
class MediaUploader
{
    public static function allowedExtensions(): array
    {
        $configured = array_filter(explode(',', (string) setting('media_allowed', 'jpg,jpeg,png,webp,gif,pdf')));

        return array_values(array_diff($configured, config('cms.upload_blocked')));
    }

    /** The largest file that can be uploaded: the setting, unless PHP on this host allows less. */
    public static function maxBytes(): int
    {
        $limits = array_filter([
            (int) setting('media_max_mb', 10) * 1024 * 1024,
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
        ]);

        return $limits ? min($limits) : 10 * 1024 * 1024;
    }

    /** True when the host's PHP, not the setting, is what limits the size. */
    public static function limitedByHost(): bool
    {
        return self::maxBytes() < (int) setting('media_max_mb', 10) * 1024 * 1024;
    }

    /** "8M" => 8388608; 0 or empty means no limit. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }
        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Why PHP rejected an upload, in words the person can act on. */
    public static function uploadError(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('This file is larger than this hosting accepts (:max). Upload a smaller file, or ask your host to raise upload_max_filesize and post_max_size.', ['max' => Number::fileSize(self::maxBytes())]),
            UPLOAD_ERR_PARTIAL => __('The upload was interrupted. Check your connection and try again.'),
            UPLOAD_ERR_NO_FILE => __('No file was received.'),
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => __('The server could not save the uploaded file: its temporary folder is missing or full. Ask your host to check it.'),
            default => __('The server refused this file (a PHP extension stopped the upload).'),
        };
    }

    public static function store(UploadedFile $file): Media
    {
        if (! $file->isValid()) {
            throw new RuntimeException(self::uploadError($file));
        }
        $extension = strtolower($file->getClientOriginalExtension());
        $maxBytes = self::maxBytes();

        if (! in_array($extension, self::allowedExtensions(), true)) {
            throw new RuntimeException(__('File type :type is not allowed. Allowed: :types.', ['type' => $extension ?: '?', 'types' => implode(', ', self::allowedExtensions())]));
        }
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException(__('This file is :size, larger than the :max limit.', ['size' => Number::fileSize((int) $file->getSize()), 'max' => Number::fileSize($maxBytes)]));
        }

        $mime = (string) $file->getMimeType();
        $isImageExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true);
        if ($isImageExtension && ! str_starts_with($mime, 'image/')) {
            throw new RuntimeException(__('This file is not a valid image.'));
        }
        // The extension is allow-listed, but the content must not be something a browser would run.
        if (preg_match('#(php|html|svg|javascript)#i', $mime)) {
            throw new RuntimeException(__('File type :type is not allowed.', ['type' => $mime]));
        }

        $folder = 'uploads/'.now()->format('Y/m');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $name = $base.'.'.$extension;
        for ($i = 1; file_exists(public_path($folder.'/'.$name)); $i++) {
            $name = $base.'-'.$i.'.'.$extension;
        }
        try {
            $file->move(public_path($folder), $name);
        } catch (FileException) {
            throw new RuntimeException(__('Cannot save the file in :folder. Check that public/uploads is writable.', ['folder' => $folder.'/']));
        }
        $path = public_path($folder.'/'.$name);

        if ($isImageExtension) {
            self::shrink($path, $extension, (int) setting('media_image_max_width', 2000));
            if (setting('media_webp') === '1' && ($webp = self::toWebp($path, $extension, (int) setting('media_webp_quality', 82)))) {
                [$path, $name, $mime] = [$webp, basename($webp), 'image/webp'];
            }
        }
        $size = @getimagesize($path) ?: [null, null];
        clearstatcache(true, $path);

        return Media::create([
            'path' => $folder.'/'.$name,
            'filename' => $name,
            'mime' => $mime,
            'size' => filesize($path),
            'width' => $size[0],
            'height' => $size[1],
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'user_id' => auth()->id(),
        ]);
    }

    /** Whether this server can write WebP (GD built with WebP support). */
    public static function canWebp(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatetruecolor');
    }

    /**
     * Converts a JPEG or PNG to WebP next to it and removes the original. Returns the new path, or
     * null when the server cannot convert it, the image would not fit in memory, or the WebP is
     * not smaller (the original is kept then). GIFs keep their animation; WebP stays as it is.
     */
    private static function toWebp(string $path, string $extension, int $quality): ?string
    {
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! self::canWebp() || ! self::fitsInMemory($path)) {
            return null;
        }
        $image = $extension === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if (! $image) {
            return null;
        }
        if ($extension === 'png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        $base = pathinfo($path, PATHINFO_FILENAME);
        $target = dirname($path).'/'.$base.'.webp';
        for ($i = 1; file_exists($target); $i++) {
            $target = dirname($path).'/'.$base.'-'.$i.'.webp';
        }
        $ok = @imagewebp($image, $target, max(1, min(100, $quality ?: 82)));
        clearstatcache();
        if (! $ok || ! is_file($target) || filesize($target) === 0 || filesize($target) >= filesize($path)) {
            @unlink($target);

            return null;
        }
        @unlink($path);

        return $target;
    }

    /** Decoding needs about 5 bytes per pixel; a photo too big for the memory limit is left alone. */
    private static function fitsInMemory(string $path): bool
    {
        [$width, $height] = @getimagesize($path) ?: [0, 0];
        $limit = self::iniBytes((string) ini_get('memory_limit'));

        return $width > 0 && ($limit <= 0 || $width * $height * 5 <= $limit - memory_get_usage());
    }

    /** Reduces oversized images when GD is available. Skips GIFs to keep animation. */
    private static function shrink(string $path, string $extension, int $maxWidth): void
    {
        if ($maxWidth <= 0 || ! function_exists('imagecreatetruecolor') || $extension === 'gif') {
            return;
        }
        [$width, $height] = @getimagesize($path) ?: [0, 0];
        if ($width <= $maxWidth) {
            return;
        }
        // Decoding needs about 5 bytes per pixel. A photo too big for the memory limit is kept as
        // it is instead of stopping PHP with a fatal error halfway through the upload.
        $limit = self::iniBytes((string) ini_get('memory_limit'));
        if ($limit > 0 && $width * $height * 5 > $limit - memory_get_usage()) {
            return;
        }
        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $image) {
            return;
        }
        $resized = imagescale($image, $maxWidth, (int) round($height * $maxWidth / $width));
        if ($extension === 'png' || $extension === 'webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($resized, $path, 85),
            'png' => imagepng($resized, $path),
            'webp' => imagewebp($resized, $path, 85),
        };
    }
}
