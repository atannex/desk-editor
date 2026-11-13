<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Contracts;

use Illuminate\Http\Request;

interface MentionResolver
{
    /**
     * Return up to ~8 matches for the query typed after the trigger character.
     *
     * Each item: ['id' => string|int, 'label' => string, 'description' => ?string, 'avatar' => ?string]
     *
     * @return iterable<int, array{id: string|int, label: string, description?: string|null, avatar?: string|null}>
     */
    public function search(string $query, Request $request): iterable;
}
