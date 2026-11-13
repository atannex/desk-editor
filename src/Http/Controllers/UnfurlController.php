<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Atannex\DeskEditor\Support\Unfurl\Unfurler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UnfurlController extends Controller
{
    public function __invoke(Request $request, Unfurler $unfurler): JsonResponse
    {
        $url = (string) $request->validate(['url' => ['required', 'string', 'max:2048', 'url:http,https']])['url'];
        $data = $unfurler->unfurl($url);

        return $data
            ? response()->json(['data' => $data])
            : response()->json(['message' => __('desk-editor::editor.previewUnavailable')], 422);
    }
}
