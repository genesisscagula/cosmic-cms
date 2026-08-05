<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedRequest
{
    public function handle(Request $request, Closure $next, int $kilobytes = 256): Response
    {
        $limit = max(1, $kilobytes) * 1024;
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > $limit || strlen($request->getContent()) > $limit) {
            return response()->json([
                'message' => 'The request payload is too large.',
            ], 413);
        }

        return $next($request);
    }
}
