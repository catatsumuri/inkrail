<?php

namespace App\Actions;

use App\Models\ArticleImage;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Re-encode an uploaded image and store it under its content hash.
 *
 * Re-encoding drops metadata such as GPS EXIF and rejects files that are not
 * decodable images. Animated GIFs are stored as uploaded so they keep moving.
 */
class StoreArticleImage
{
    /** @var array<string, string> */
    public const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    public function __invoke(User $user, UploadedFile $file): ArticleImage
    {
        [$bytes, $mime] = $this->encode($file);
        $path = hash('sha256', $bytes).'.'.self::EXTENSIONS[$mime];

        $image = ArticleImage::query()->firstOrNew(['path' => $path]);
        $disk = Storage::disk(ArticleImage::disk());

        if (! $image->exists || ! $disk->exists($image->storagePath())) {
            $disk->put($image->storagePath(), $bytes);
        }

        if ($image->exists) {
            $image->touch();

            return $image;
        }

        $image->fill(['user_id' => $user->id, 'mime' => $mime, 'size' => strlen($bytes)])->save();

        return $image;
    }

    /**
     * @return array{0: string, 1: string} The encoded bytes and their MIME type.
     */
    private function encode(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $raw = (string) file_get_contents($file->getRealPath());

        if (! isset(self::EXTENSIONS[$mime])) {
            throw new RuntimeException("Unsupported image type [{$mime}].");
        }

        if ($mime === 'image/gif') {
            return [$raw, $mime];
        }

        $image = imagecreatefromstring($raw);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The uploaded file is not a decodable image.');
        }

        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $raw);
        }

        $image = $this->scaleDown($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();

        match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 85),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
        };

        return [(string) ob_get_clean(), $mime];
    }

    private function orient(GdImage $image, string $raw): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function scaleDown(GdImage $image): GdImage
    {
        $max = (int) config('articles.image_max_dimension');
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $max && $height <= $max) {
            return $image;
        }

        $scaled = $width >= $height
            ? imagescale($image, $max, (int) round($height * $max / $width))
            : imagescale($image, (int) round($width * $max / $height), $max);

        return $scaled ?: $image;
    }
}
