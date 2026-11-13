<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Facades;

use Atannex\DeskEditor\DeskEditorManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string sanitize(?string $html)
 * @method static \Atannex\DeskEditor\Support\DeskContent content(?string $html, bool $sanitize = true)
 * @method static array<string, mixed> clientConfig(array<string, mixed> $overrides = [])
 * @method static list<string> toolbar(string|array<int, string> $toolbar)
 * @method static \Illuminate\Support\HtmlString styles()
 * @method static \Illuminate\Support\HtmlString scripts()
 * @method static \Illuminate\Support\HtmlString assets()
 * @method static string asset(string $file)
 * @method static bool uploadsEnabled()
 * @method static bool mentionsEnabled()
 *
 * @see DeskEditorManager
 */
class DeskEditor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DeskEditorManager::class;
    }
}
