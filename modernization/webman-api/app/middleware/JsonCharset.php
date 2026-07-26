<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class JsonCharset implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        return $this->withUtf8Charset($handler($request));
    }

    private function withUtf8Charset(Response $response): Response
    {
        $contentType = $response->getHeader('Content-Type');
        if (is_array($contentType)) {
            $contentType = implode(';', $contentType);
        }

        $normalized = strtolower(trim((string)$contentType));
        if ($normalized === '' || !str_starts_with($normalized, 'application/json')) {
            return $response;
        }

        if (str_contains($normalized, 'charset=')) {
            return $response;
        }

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
