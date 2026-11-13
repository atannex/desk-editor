<?php

use Atannex\DeskEditor\Http\Controllers\AssetController;
use Atannex\DeskEditor\Http\Controllers\DraftController;
use Atannex\DeskEditor\Http\Controllers\MentionController;
use Atannex\DeskEditor\Http\Controllers\ThreadController;
use Atannex\DeskEditor\Http\Controllers\UnfurlController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('desk-editor.route_prefix', 'desk-editor'), '/');

Route::prefix($prefix)->name('desk-editor.')->group(function () {
    Route::get('assets/{path}', AssetController::class)
        ->where('path', '.*')
        ->name('asset');

    Route::get('mentions', MentionController::class)
        ->middleware((array) config('desk-editor.mentions.middleware', ['web', 'auth']))
        ->name('mentions');

    Route::get('unfurl', UnfurlController::class)
        ->middleware(['web', 'auth', 'throttle:30,1'])
        ->name('unfurl');

    if (config('desk-editor.drafts.enabled', true)) {
        Route::middleware((array) config('desk-editor.drafts.middleware', ['web', 'auth']))
            ->where(['key' => '[A-Za-z0-9:_.-]{1,191}'])
            ->controller(DraftController::class)
            ->group(function () {
                Route::get('drafts/{key}', 'show')->name('drafts.show');
                Route::put('drafts/{key}', 'update')->name('drafts.update');
                Route::delete('drafts/{key}', 'destroy')->name('drafts.destroy');
            });
    }

    if (config('desk-editor.comments.enabled', true)) {
        Route::middleware((array) config('desk-editor.comments.middleware', ['web', 'auth']))
            ->controller(ThreadController::class)
            ->group(function () {
                Route::get('threads', 'index')->name('threads.index');
                Route::post('threads', 'store')->name('threads.store');
                Route::post('threads/{thread}/messages', 'reply')->name('threads.reply');
                Route::patch('threads/{thread}', 'resolve')->name('threads.resolve');
                Route::delete('threads/{thread}', 'destroy')->name('threads.destroy');
                Route::delete('thread-messages/{message}', 'destroyMessage')->name('threads.messages.destroy');
            });
    }
});

// Module route files (each owned by its module's service provider).
foreach (['media', 'articles', 'blog'] as $module) {
    if (is_file($file = __DIR__."/{$module}.php")) {
        require $file;
    }
}
