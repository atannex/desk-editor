<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Articles\Support\Authors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Searchable list of people articles can be attributed to.
 */
class AuthorController extends Controller
{
    use ResolvesArticles;

    public function __invoke(Request $request, Authors $authors): JsonResponse
    {
        $this->authorizeAbility('view');

        return response()->json(['data' => $authors->search((string) $request->query('q', ''), 30)]);
    }
}
