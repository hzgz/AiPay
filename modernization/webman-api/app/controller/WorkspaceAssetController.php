<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\UploadWorkspace;
use Webman\Http\Request;
use Webman\Http\Response;

class WorkspaceAssetController
{
    public function upload(Request $request): Response
    {
        $resolvedPath = UploadWorkspace::resolveAssetPath($this->assetPathFromRequest($request));
        if ($resolvedPath === null || is_dir($resolvedPath)) {
            return $this->notFound();
        }

        return $this->serveFile($resolvedPath);
    }

    private function assetPathFromRequest(Request $request): string
    {
        return rawurldecode(trim((string)($request->route ? $request->route->param('path', '') : '')));
    }

    private function serveFile(string $absolutePath): Response
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->notFound();
        }

        $contents = @file_get_contents($absolutePath);
        if (!is_string($contents)) {
            return response('资源读取失败', 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $mtime = @filemtime($absolutePath) ?: time();

        return response($contents, 200, [
            'Content-Type' => $this->contentType($absolutePath),
            'Cache-Control' => 'public, max-age=300',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
        ]);
    }

    private function contentType(string $path): string
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=utf-8',
            'js', 'mjs' => 'application/javascript; charset=utf-8',
            'html', 'htm' => 'text/html; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'txt' => 'text/plain; charset=utf-8',
            default => (mime_content_type($path) ?: 'application/octet-stream'),
        };
    }

    private function notFound(): Response
    {
        return response('资源不存在', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
