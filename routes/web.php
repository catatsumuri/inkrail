<?php

use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleHistoryController;
use App\Http\Controllers\DeleteNotificationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkNotificationAsReadController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\SandboxController;
use App\Http\Controllers\ZennPushController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome', [
    'publicIndexEnabled' => (bool) config('articles.public_index_enabled'),
]))->name('home');

Route::post('sandbox', SandboxController::class)
    ->middleware('throttle:6,1')
    ->block(10, 10)
    ->name('sandbox.start');

Route::post('locale', LocaleController::class)->name('locale.update');

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
    Route::get('articles/{article}/history', [ArticleHistoryController::class, 'index'])->name('articles.history');
    Route::post('articles/{article}/translation-review', [ArticleHistoryController::class, 'confirm'])->name('articles.review');
    Route::post('articles/{article}/revisions/{revision}/restore', [ArticleHistoryController::class, 'restore'])->name('articles.restore');
    Route::delete('articles/{article}/translations/{translationLocale}', [ArticleHistoryController::class, 'destroy'])->name('articles.translations.destroy');
    Route::post('articles/{article}/zenn', ZennPushController::class)->name('articles.zenn.push');
    Route::resource('articles', ArticleController::class);

    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('admin/security-events', SecurityEventController::class)
        ->middleware('can:view-security-events')
        ->name('admin.security-events.index');
});

require __DIR__.'/settings.php';
