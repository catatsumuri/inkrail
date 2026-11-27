<?php

use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\ArticleBackupController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleFeedController;
use App\Http\Controllers\ArticleHistoryController;
use App\Http\Controllers\ArticleImageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeleteNotificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkNotificationAsReadController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\SandboxImportController;
use App\Http\Controllers\ZennImportController;
use App\Http\Controllers\ZennPushController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('sandbox', SandboxController::class)
    ->middleware('throttle:6,1')
    ->block(10, 10)
    ->name('sandbox.start');

Route::post('locale', LocaleController::class)->name('locale.update');

Route::get('images/{path}', [ArticleImageController::class, 'show'])
    ->where('path', '.+\.(png|jpg|jpeg|gif|webp)')
    ->name('images.show');

Route::get('rss.xml', ArticleFeedController::class)->name('public.articles.feed');

Route::get('p', [PublicArticleController::class, 'index'])
    ->name('public.articles.index');

Route::get('p/{article:slug}', [PublicArticleController::class, 'show'])
    ->name('public.articles.show');

Route::patch('notifications/{notification}/read', MarkNotificationAsReadController::class)
    ->middleware('auth')
    ->name('notifications.read');

Route::delete('notifications/{notification}', DeleteNotificationController::class)
    ->middleware('auth')
    ->name('notifications.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('sandbox/import', SandboxImportController::class)->middleware(['can:import-from-zenn', 'throttle:6,1'])->name('sandbox.import');
    Route::get('articles/{article}/history', [ArticleHistoryController::class, 'index'])->name('articles.history');
    Route::post('articles/{article}/translation-review', [ArticleHistoryController::class, 'confirm'])->name('articles.review');
    Route::post('articles/{article}/revisions/{revision}/restore', [ArticleHistoryController::class, 'restore'])->name('articles.restore');
    Route::delete('articles/{article}/translations/{translationLocale}', [ArticleHistoryController::class, 'destroy'])->name('articles.translations.destroy');
    Route::post('article-images', [ArticleImageController::class, 'store'])->middleware('throttle:30,1')->name('article-images.store');
    Route::post('articles/{article}/zenn', ZennPushController::class)->name('articles.zenn.push');
    Route::resource('articles', ArticleController::class);

    Route::get('dashboard', DashboardController::class)->withoutMiddleware('verified')->name('dashboard');
    Route::post('zenn/import', ZennImportController::class)->middleware('throttle:6,1')->name('zenn.import');

    Route::prefix('backups')->name('backups.')->middleware('throttle:10,1')->where(['filename' => 'inkrail-[0-9]{8}-[0-9]{6}(-[a-z0-9]{4})?\.zip'])->group(function () {
        Route::post('/', [ArticleBackupController::class, 'store'])->withoutMiddleware('verified')->name('store');
        Route::post('upload', [ArticleBackupController::class, 'upload'])->withoutMiddleware('verified')->name('upload');
        Route::get('{filename}', [ArticleBackupController::class, 'download'])->withoutMiddleware('verified')->name('download');
        Route::post('{filename}/restore', [ArticleBackupController::class, 'restore'])->withoutMiddleware('verified')->name('restore');
        Route::delete('{filename}', [ArticleBackupController::class, 'destroy'])->withoutMiddleware('verified')->name('destroy');
    });

    Route::get('admin/security-events', SecurityEventController::class)
        ->middleware('can:view-security-events')
        ->name('admin.security-events.index');
});

require __DIR__.'/settings.php';
