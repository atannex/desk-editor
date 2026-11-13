<?php

use Atannex\DeskEditor\Http\Controllers\UploadController;
use Atannex\DeskEditor\Media\Http\Controllers\MediaController;
use Atannex\DeskEditor\Media\Http\Controllers\MediaPageController;
use Illuminate\Support\Facades\Route;

Route::prefix(trim((string) config('desk-editor.route_prefix', 'desk-editor'), '/'))->name('desk-editor.')->group(function () {
    Route::post('upload', UploadController::class)
        ->middleware((array) config('desk-editor.uploads.middleware', ['web', 'auth']))
        ->name('upload');

    // JSON media library for the editor's picker and the admin page.
    Route::middleware((array) config('desk-editor.media.middleware', ['web', 'auth']))
        ->prefix('media')
        ->name('media.')
        ->controller(MediaController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{media}', 'show')->whereNumber('media')->name('show');
            Route::patch('{media}', 'update')->whereNumber('media')->name('update');
            Route::delete('{media}', 'destroy')->whereNumber('media')->name('destroy');
        });
});

// Admin media library page, guarded like the article manager.
Route::get(trim((string) config('desk-editor.media.prefix', 'desk/media'), '/'), MediaPageController::class)
    ->middleware((array) config('desk-editor.articles.middleware', ['web', 'auth']))
    ->name('desk-editor.media.manage');
