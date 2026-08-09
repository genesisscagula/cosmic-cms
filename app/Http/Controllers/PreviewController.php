<?php

namespace App\Http\Controllers;

use App\Services\PreviewDeploymentService;
use Illuminate\Http\Response;

class PreviewController extends Controller
{
    public function local(PreviewDeploymentService $previews, string $slug, ?string $path = null): Response
    {
        return $this->serve($previews, $slug, $path);
    }

    public function subdomain(PreviewDeploymentService $previews, string $preview, ?string $path = null): Response
    {
        return $this->serve($previews, $preview, $path);
    }

    private function serve(PreviewDeploymentService $previews, string $slug, ?string $path): Response
    {
        $html = $previews->resolveFile($slug, $path);
        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
