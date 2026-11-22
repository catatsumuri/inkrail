<?php

namespace App\Http\Controllers;

use App\Actions\StoreArticleImage;
use App\Http\Requests\StoreArticleImageRequest;
use App\Models\ArticleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleImageController extends Controller
{
    public function store(StoreArticleImageRequest $request, StoreArticleImage $storeImage): JsonResponse
    {
        $image = $storeImage($request->user(), $request->file('image'));

        return response()->json(['url' => $image->url()], 201);
    }

    /**
     * Serve an image by its content-hash path. The route only matches that shape.
     */
    public function show(string $path): StreamedResponse
    {
        $storagePath = ArticleImage::DIRECTORY.'/'.$path;
        $disk = Storage::disk(ArticleImage::disk());

        abort_unless($disk->exists($storagePath), 404);

        return $disk->response($storagePath, null, [
            'Content-Type' => array_search(pathinfo($path, PATHINFO_EXTENSION), StoreArticleImage::EXTENSIONS, true),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
