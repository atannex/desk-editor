<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests;

use Atannex\DeskEditor\DeskEditorServiceProvider;
use Atannex\DeskEditor\Tests\Fixtures\User;
use Atannex\DeskEditor\View\Components\Editor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [DeskEditorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Editor::$assetsRendered = false;
    }

    protected function user(): User
    {
        return User::query()->create(['name' => 'Ada Lovelace', 'email' => uniqid().'@example.com']);
    }
}
