<?php

use Atannex\DeskEditor\Articles\Http\Controllers\AuthorController;
use Atannex\DeskEditor\Articles\Http\Controllers\CategoryController;
use Atannex\DeskEditor\Articles\Http\Controllers\LockController;
use Atannex\DeskEditor\Articles\Http\Controllers\ReviewController;
use Atannex\DeskEditor\Articles\Http\Controllers\RevisionController;
use Atannex\DeskEditor\Articles\Http\Controllers\TagController;
use Atannex\DeskEditor\Articles\Http\Controllers\TranslationController;
use Atannex\DeskEditor\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

if (config('desk-editor.articles.enabled')) {
    $prefix = trim((string) config('desk-editor.articles.prefix', 'desk/articles'), '/');
    $middleware = (array) config('desk-editor.articles.middleware', ['web', 'auth', 'can:manage-desk-articles']);
    // Categories and tags live next to the articles: desk/articles → desk/categories, desk/tags.
    $base = str_contains($prefix, '/') ? substr($prefix, 0, (int) strrpos($prefix, '/')) : $prefix;

    Route::prefix($prefix)
        ->middleware($middleware)
        ->name('desk-editor.articles.')
        ->group(function () {
            Route::controller(ArticleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::post('bulk', 'bulk')->name('bulk');
                Route::get('{article}/edit', 'edit')->name('edit');
                Route::get('{article}/preview', 'preview')->name('preview');
                Route::put('{article}', 'update')->name('update');
                Route::post('{article}/duplicate', 'duplicate')->name('duplicate');
                Route::post('{article}/revisions/{revision}/restore', 'restoreRevision')->name('revisions.restore');
                Route::delete('{article}', 'destroy')->name('destroy');
                Route::post('{article}/restore', 'restore')->name('restore')->withTrashed();
                Route::delete('{article}/force', 'forceDelete')->name('force-delete')->withTrashed();
            });

            Route::get('authors', AuthorController::class)->name('authors');
            Route::get('{article}/revisions/compare', [RevisionController::class, 'compare'])->name('revisions.compare');
            Route::post('{article}/review', ReviewController::class)->name('review');
            Route::post('{article}/translations', [TranslationController::class, 'store'])->name('translations.store');
            Route::post('{article}/lock', [LockController::class, 'heartbeat'])->name('lock')->middleware('throttle:120,1');
            Route::post('{article}/lock/release', [LockController::class, 'release'])->name('lock.release');
        });

    Route::prefix($base.'/categories')
        ->middleware($middleware)
        ->name('desk-editor.categories.')
        ->controller(CategoryController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('reorder', 'reorder')->name('reorder');
            Route::put('{category}', 'update')->name('update');
            Route::delete('{category}', 'destroy')->name('destroy');
        });

    Route::prefix($base.'/tags')
        ->middleware($middleware)
        ->name('desk-editor.tags.')
        ->controller(TagController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('suggest', 'suggest')->name('suggest');
            Route::post('merge', 'merge')->name('merge');
            Route::post('bulk-delete', 'bulkDestroy')->name('bulk-destroy');
            Route::put('{tag}', 'update')->name('update');
            Route::delete('{tag}', 'destroy')->name('destroy');
        });
}
