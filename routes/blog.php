<?php

use Atannex\DeskEditor\Blog\Http\Controllers\AnalyticsController;
use Atannex\DeskEditor\Blog\Http\Controllers\BlogController;
use Atannex\DeskEditor\Blog\Http\Controllers\CommentController;
use Atannex\DeskEditor\Blog\Http\Controllers\CommentModerationController;
use Atannex\DeskEditor\Blog\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

// Public, reader-facing blog.
if (config('desk-editor.blog.enabled')) {
    Route::prefix(trim((string) config('desk-editor.blog.prefix', 'blog'), '/'))
        ->middleware((array) config('desk-editor.blog.middleware', ['web']))
        ->name('desk-editor.blog.')
        ->group(function () {
            Route::get('/', [BlogController::class, 'home'])->name('home');
            Route::get('feed', [FeedController::class, 'rss'])->name('feed');
            Route::get('atom', [FeedController::class, 'atom'])->name('atom');
            Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
            Route::get('search', [BlogController::class, 'search'])->name('search');
            Route::get('category/{slug}', [BlogController::class, 'category'])->name('category');
            Route::get('tag/{slug}', [BlogController::class, 'tag'])->name('tag');
            Route::get('author/{id}/{name?}', [BlogController::class, 'author'])->where('id', '[A-Za-z0-9-]+')->name('author');
            Route::get('{slug}', [BlogController::class, 'show'])->name('show');
        });

    // Comment submissions use their own (throttled) middleware stack.
    Route::prefix(trim((string) config('desk-editor.blog.prefix', 'blog'), '/'))
        ->middleware((array) config('desk-editor.blog.comments.middleware', ['web', 'throttle:10,1']))
        ->name('desk-editor.blog.')
        ->group(function () {
            Route::post('{slug}/comments', [CommentController::class, 'store'])->name('comments.store');
        });
}

// Admin: comment moderation and analytics.
if (config('desk-editor.articles.enabled')) {
    Route::prefix('desk')
        ->middleware((array) config('desk-editor.articles.middleware', ['web', 'auth', 'can:manage-desk-articles']))
        ->name('desk-editor.')
        ->group(function () {
            Route::controller(CommentModerationController::class)->prefix('comments')->name('comments.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('bulk', 'bulk')->name('bulk');
                Route::post('{comment}/approve', 'approve')->name('approve');
                Route::post('{comment}/spam', 'spam')->name('spam');
                Route::post('{comment}/trash', 'trash')->name('trash');
                Route::post('{comment}/restore', 'restore')->name('restore');
                Route::post('{comment}/reply', 'reply')->name('reply');
                Route::delete('{comment}', 'destroy')->name('destroy');
            });

            Route::get('analytics', AnalyticsController::class)->name('analytics');
        });
}
