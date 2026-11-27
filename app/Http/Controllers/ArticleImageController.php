<?php

namespace App\Http\Controllers;

use App\Actions\StoreArticleImage;
use App\Http\Requests\StoreArticleImageRequest;
use App\Models\ArticleImage;
use App\Support\RepositoryImageStore;
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
     * Serve uploaded and repository images publicly.
     */
    public function show(string $path, RepositoryImageStore $repositoryImages): StreamedResponse
    {
        $storagePath = ArticleImage::DIRECTORY.'/'.$path;
        $disk = Storage::disk(ArticleImage::disk());

        if (preg_match('/\A[0-9a-f]{64}\.(png|jpg|gif|webp)\z/', $path) !== 1 || ! $disk->exists($storagePath)) {
            return $repositoryImages->response($path);
        }

        return $disk->response($storagePath, null, [
            'Content-Type' => array_search(pathinfo($path, PATHINFO_EXTENSION), StoreArticleImage::EXTENSIONS, true),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
