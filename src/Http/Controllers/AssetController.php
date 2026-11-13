<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the compiled bundle (and KaTeX fonts) straight from the package so
 * the editor works without publishing anything.
 */
class AssetController extends Controller
{
    private const TYPES = [
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
    ];

    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        abort_unless((bool) preg_match('#^(desk-editor\.(js|css)|chunks/[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\.js|fonts/KaTeX_[A-Za-z0-9_-]+\.(woff2|woff|ttf))$#', $path), 404);

        $file = realpath(__DIR__.'/../../../dist/'.$path);
        abort_unless($file !== false && is_file($file), 404);

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => self::TYPES[$extension] ?? 'application/octet-stream',
            // Versioned entry files and content-hashed chunks/fonts never change.
            'Cache-Control' => $request->query('v') || ! str_starts_with($path, 'desk-editor.') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
        ]);
        $response->setAutoEtag()->setAutoLastModified()->isNotModified($request);

        return $response;
    }
}
