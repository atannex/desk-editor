<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Casts;

use Atannex\DeskEditor\DeskEditorManager;
use Atannex\DeskEditor\Support\DeskContent;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitises HTML on write and hydrates it as DeskContent on read:
 *
 *     protected function casts(): array { return ['body' => AsDeskContent::class]; }
 *
 * @implements CastsAttributes<DeskContent|null, DeskContent|string|null>
 */
class AsDeskContent implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?DeskContent
    {
        return $value === null ? null : DeskContent::make((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DeskContent) {
            return $value->html();
        }

        return app(DeskEditorManager::class)->sanitize((string) $value);
    }
}
