<?php

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\SystemConfig;
use app\support\UploadWorkspace;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;

class CommonUploadController
{
    private const WANG_EDITOR_UPLOAD_URL = '/api/common/upload/wangeditor';

    public function wangEditor(Request $request): Response
    {
        $configuredFileType = SystemConfig::int('file-type', 1);
        if ($configuredFileType !== 1) {
            return ApiResponse::error(
                'local rich-text upload is only available when file-type is set to local storage',
                422,
                [
                    'configured_file_type' => $configuredFileType,
                ],
                422
            );
        }

        try {
            $path = $this->normalizeUploadDirectory((string)$request->get('path', 'editor'));
            $authorizationError = $this->authorizeUpload($request, $path);
            if ($authorizationError instanceof Response) {
                return $authorizationError;
            }
            $file = $this->normalizeUploadFile($request);
            $prepared = $this->prepareUploadedImage($file, max(1, SystemConfig::int('imageSize', 2000)) * 1024);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $absolutePath = '';
        $legacyPath = '';
        try {
            [$photoId, $href] = Db::transaction(function () use ($file, $path, $prepared, &$absolutePath, &$legacyPath): array {
                $dateSegment = date('Ymd');
                $relativeChild = $dateSegment . '/' . date('His') . '_' . bin2hex(random_bytes(8)) . '.' . $prepared['ext'];
                $absolutePath = UploadWorkspace::directoryPath($path)
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relativeChild);
                $directory = dirname($absolutePath);
                if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \RuntimeException('上传目录初始化失败');
                }

                $href = UploadWorkspace::publicHref($path, $relativeChild);

                $file->move($absolutePath);
                $legacyPath = UploadWorkspace::mirrorFileToLegacyPublic($absolutePath, $path, $relativeChild);

                $photoId = (int)Db::table('admin_photo')->insertGetId([
                    'name' => $prepared['name'],
                    'href' => $href,
                    'path' => $path,
                    'type' => 1,
                    'ext' => $prepared['ext'],
                    'mime' => $prepared['mime'],
                    'size' => $prepared['size_bytes'],
                    'create_time' => date('Y-m-d H:i:s'),
                ]);

                return [$photoId, $href];
            });
        } catch (\Throwable $exception) {
            if ($absolutePath !== '' && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            if ($legacyPath !== '' && is_file($legacyPath)) {
                @unlink($legacyPath);
            }

            return ApiResponse::error('富文本图片上传失败：' . $exception->getMessage(), 500, null, 500);
        }

        $this->recordAdminUpload($request, $path, $photoId, $prepared, $href);

        return ApiResponse::success([
            'url' => $href,
            'alt' => $prepared['name'],
            'href' => $href,
            'photo_id' => $photoId,
            'path' => $path,
        ], 'rich-text image uploaded');
    }

    private function normalizeUploadDirectory(string $path): string
    {
        $normalized = strtolower(trim($path));
        $allowed = ['editor', 'news', 'plugins'];

        if ($normalized === '' || !in_array($normalized, $allowed, true)) {
            throw new \InvalidArgumentException('upload path is not allowed');
        }

        return $normalized;
    }

    private function authorizeUpload(Request $request, string $path): ?Response
    {
        return match ($path) {
            'news' => (new AdminRouteAuthorization())->authorizeAny($request, 'ContentNews', ['add', 'edit']),
            'plugins' => (new AdminRouteAuthorization())->authorizeAny($request, 'ContentPluginDownloads', ['add', 'edit']),
            default => null,
        };
    }

    private function normalizeUploadFile(Request $request): UploadFile
    {
        $file = $request->file('file');
        if (!$file instanceof UploadFile) {
            throw new \InvalidArgumentException('one image file is required');
        }

        return $file;
    }

    /**
     * @return array{name: string, ext: string, mime: string, size_bytes: int}
     */
    private function prepareUploadedImage(UploadFile $file, int $maxSizeBytes): array
    {
        $uploadName = trim((string)($file->getUploadName() ?? ''));
        $displayName = $uploadName !== '' ? basename($uploadName) : 'unnamed-file';

        if (!$file->isValid()) {
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" is invalid', $displayName));
        }

        $sizeBytes = max(0, (int)$file->getSize());
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" is empty', $displayName));
        }

        if ($sizeBytes > $maxSizeBytes) {
            throw new \InvalidArgumentException(sprintf(
                'uploaded file "%s" exceeds the configured limit of %d KB',
                $displayName,
                (int)ceil($maxSizeBytes / 1024)
            ));
        }

        $uploadExtension = strtolower(trim((string)$file->getUploadExtension()));
        if ($uploadExtension !== '' && !in_array($uploadExtension, ['jpg', 'jpeg', 'png', 'bmp', 'gif'], true)) {
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" has an unsupported extension', $displayName));
        }

        $imageInfo = @getimagesize($file->getPathname());
        $mime = is_array($imageInfo) ? trim((string)($imageInfo['mime'] ?? '')) : '';
        $allowedMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
        ];
        if ($mime === '' || !isset($allowedMimeMap[$mime])) {
            throw new \InvalidArgumentException(sprintf('uploaded file "%s" is not a supported image', $displayName));
        }

        return [
            'name' => mb_substr($displayName, 0, 50),
            'ext' => $allowedMimeMap[$mime],
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * @param array{name: string, ext: string, mime: string, size_bytes: int} $prepared
     */
    private function recordAdminUpload(Request $request, string $path, int $photoId, array $prepared, string $href): void
    {
        $adminId = (int)(((array)($request->admin ?? []))['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => self::WANG_EDITOR_UPLOAD_URL,
            'desc' => sprintf(
                'rich-text upload path="%s" photo_id=%d size=%d mime=%s href="%s" name="%s"',
                $this->truncateLogText($path, 32),
                $photoId,
                (int)($prepared['size_bytes'] ?? 0),
                $this->truncateLogText((string)($prepared['mime'] ?? ''), 60),
                $this->truncateLogText($href, 255),
                $this->truncateLogText((string)($prepared['name'] ?? ''), 80)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if ($value === '') {
            return '';
        }

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 3) . '...' : $value;
    }
}
