<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/** Stores uploads in public/uploads/Y/m, which works on hosting without symlinks. */
class MediaUploader
{
    public static function allowedExtensions(): array
    {
        $configured = array_filter(explode(',', (string) setting('media_allowed', 'jpg,jpeg,png,webp,gif,pdf')));

        return array_values(array_diff($configured, config('cms.upload_blocked')));
    }

    public static function store(UploadedFile $file): Media
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $maxBytes = (int) setting('media_max_mb', 10) * 1024 * 1024;

        if (! in_array($extension, self::allowedExtensions(), true)) {
            throw new RuntimeException(__('File type :type is not allowed.', ['type' => $extension ?: '?']));
        }
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException(__('File is larger than the :mb MB limit.', ['mb' => setting('media_max_mb', 10)]));
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
        $file->move(public_path($folder), $name);
        $path = public_path($folder.'/'.$name);

        if ($isImageExtension) {
            self::shrink($path, $extension, (int) setting('media_image_max_width', 2000));
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
