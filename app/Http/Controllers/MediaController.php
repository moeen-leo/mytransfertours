<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves images from the "uploads" disk at /media/{path}, with long browser caching. */
class MediaController extends Controller
{
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

    public function __invoke(string $path): StreamedResponse
    {
        $disk = Storage::disk('uploads');

        abort_if(
            str_contains($path, '..')
            || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED, true)
            || ! $disk->exists($path),
            404
        );

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=2592000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
