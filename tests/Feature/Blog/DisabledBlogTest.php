<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class DisabledBlogTest extends TestCase
{
    public function test_public_blog_is_not_registered_by_default(): void
    {
        $this->assertFalse(Route::has('desk-editor.blog.home'));
        $this->get('/blog')->assertNotFound();

        // Admin pages still exist with the article manager.
        $this->assertTrue(Route::has('desk-editor.comments.index'));
        $this->assertTrue(Route::has('desk-editor.analytics'));
    }
}
