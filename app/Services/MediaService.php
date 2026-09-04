<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

/**
 * Secure uploads + responsive image variants (WebP) for Core Web Vitals.
 */
class MediaService
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    public const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
    public const MAX_IMAGE_MB = 10;
    public const MAX_VIDEO_MB = 512;

    /** Variants: name => max width */
    public const VARIANTS = ['thumb' => 400, 'medium' => 800, 'large' => 1400];

    public function upload(UploadedFile $file, ?int $userId = null, ?string $alt = null): Media
    {
        $this->assertSafe($file);
        $mime = (string) $file->getMimeType();
        $isImage = in_array($mime, self::IMAGE_MIMES, true);
        $isVideo = in_array($mime, self::VIDEO_MIMES, true);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $dir = ($isImage ? 'images' : ($isVideo ? 'videos' : 'files')).'/'.date('Y/m');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $filename = Str::limit($filename, 60, '').'-'.Str::lower(Str::random(8)).'.'.$ext;
        $disk = Storage::disk('public');
        $path = $file->storeAs($dir, $filename, 'public');

        $media = new Media([
            'user_id' => $userId, 'disk' => 'public', 'path' => $path, 'filename' => $filename,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime' => $mime, 'extension' => $ext,
            'type' => $isImage ? 'image' : ($isVideo ? 'video' : 'file'), 'size' => $file->getSize(), 'alt' => $alt,
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]);

        if ($isImage && $mime !== 'image/gif') {
            try {
                $manager = new ImageManager(new GdDriver);
                $img = $manager->read($disk->path($path));
                $media->width = $img->width();
                $media->height = $img->height();
                // strip EXIF / re-encode original (removes embedded payloads) when reasonable
                if ($img->width() > 2400) {
                    $img->scaleDown(width: 2400);
                    $img->save($disk->path($path), quality: 85);
                    $media->width = $img->width();
                    $media->height = $img->height();
                    $media->size = filesize($disk->path($path));
                }
                $variants = [];
                $useWebp = setting_bool('perf.webp', true) && function_exists('imagewebp');
                foreach (self::VARIANTS as $name => $w) {
                    if ($img->width() <= $w && $name !== 'thumb') {
                        continue;
                    }
                    $v = (clone $img)->scaleDown(width: $w);
                    $vext = $useWebp ? 'webp' : 'jpg';
                    $vpath = $dir.'/'.pathinfo($filename, PATHINFO_FILENAME)."-{$name}.{$vext}";
                    $useWebp ? $v->toWebp(82)->save($disk->path($vpath)) : $v->toJpeg(82)->save($disk->path($vpath));
                    $variants[$name] = $vpath;
                }
                if ($useWebp) {
                    $wpath = $dir.'/'.pathinfo($filename, PATHINFO_FILENAME).'.webp';
                    $img->toWebp(85)->save($disk->path($wpath));
                    $variants['webp'] = $wpath;
                }
                $media->variants = $variants;
            } catch (\Throwable $e) {
                // keep original if processing fails
            }
        }
        $media->save();
        return $media;
    }

    /** Validate mime by content (not extension), size, and reject double-extensions / executables. */
    public function assertSafe(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Upload failed: '.$file->getErrorMessage());
        }
        $mime = (string) $file->getMimeType(); // finfo-based (content sniffing)
        $name = strtolower($file->getClientOriginalName());
        if (preg_match('/\.(php|phtml|php\d|phar|cgi|pl|py|sh|bat|cmd|exe|dll|js|html?|svg|htaccess)(\.|$)/i', $name)) {
            app(AuditLogger::class)->security('upload_rejected', 'warning', ['name' => $name, 'reason' => 'dangerous extension']);
            throw new \InvalidArgumentException('This file type is not allowed.');
        }
        $allowed = array_merge(self::IMAGE_MIMES, self::VIDEO_MIMES, ['application/pdf']);
        if (! in_array($mime, $allowed, true)) {
            app(AuditLogger::class)->security('upload_rejected', 'warning', ['name' => $name, 'mime' => $mime]);
            throw new \InvalidArgumentException("File type {$mime} is not allowed.");
        }
        $mb = $file->getSize() / 1024 / 1024;
        if (in_array($mime, self::IMAGE_MIMES, true) && $mb > self::MAX_IMAGE_MB) {
            throw new \InvalidArgumentException('Images must be smaller than '.self::MAX_IMAGE_MB.' MB.');
        }
        if (in_array($mime, self::VIDEO_MIMES, true) && $mb > self::MAX_VIDEO_MB) {
            throw new \InvalidArgumentException('Videos must be smaller than '.self::MAX_VIDEO_MB.' MB.');
        }
        if (in_array($mime, self::IMAGE_MIMES, true) && $mime !== 'image/avif') {
            $info = @getimagesize($file->getRealPath());
            if ($info === false) {
                throw new \InvalidArgumentException('The image file appears to be corrupted.');
            }
        }
    }

    public function delete(Media $media): void
    {
        $media->deleteFiles();
        $media->delete();
    }

    /** Storage usage in bytes for the public disk. */
    public function storageUsage(): array
    {
        $disk = Storage::disk('public');
        $total = 0;
        $count = 0;
        foreach (['images', 'videos', 'files'] as $dir) {
            foreach ($disk->allFiles($dir) as $f) {
                $total += $disk->size($f);
                $count++;
            }
        }
        $free = @disk_free_space(storage_path());
        $totalDisk = @disk_total_space(storage_path());
        return ['media_bytes' => $total, 'files' => $count, 'disk_free' => $free ?: null, 'disk_total' => $totalDisk ?: null];
    }
}
