<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Fixtures;

use Atannex\DeskEditor\Contracts\MentionResolver;
use Illuminate\Http\Request;

class UserMentions implements MentionResolver
{
    public function search(string $query, Request $request): iterable
    {
        return User::query()
            ->where('name', 'like', "%{$query}%")
            ->get()
            ->map(fn (User $user) => ['id' => $user->id, 'label' => $user->name, 'description' => $user->email]);
    }
}
