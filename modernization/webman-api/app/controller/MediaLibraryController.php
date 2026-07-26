<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminMediaLibraryFormatter;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use app\support\SystemConfig;
use app\support\UploadWorkspace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;

class MediaLibraryController
{
    private const LEGACY_PAGE = '/admin.photo/index';
    private const CREATE_DIRECTORY_URL = '/api/admin/media-library/create-directory';
    private const UPLOAD_URL_TEMPLATE = '/api/admin/media-library/%s/upload';
    private const FILE_DELETE_AUDIT_URL = '/api/admin/media-library/file-delete-audit';
    private const FILE_DELETE_URL = '/api/admin/media-library/file-delete';
    private const BATCH_DELETE_AUDIT_URL = '/api/admin/media-library/batch-delete-audit';
    private const BATCH_DELETE_URL = '/api/admin/media-library/batch-delete';
    private const ADMIN_HIDDEN_DIRECTORIES = [
        'images',
        'news',
        'plugins',
        'qrcode',
        'pay_qr',
        'merchant_assets',
        'payment-accounts',
    ];

    public function index(Request $request): Response
    {
        $current = max(1, (int)$request->get('current', 1));
        $size = max(1, min((int)$request->get('size', 20), 100));

        $records = $this->directoryRecords($request);
        $filtered = $this->applyFilters($records, $request);
        $total = count($filtered);

        return ApiResponse::success([
            'records' => array_slice($filtered, ($current - 1) * $size, $size),
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'summary' => $this->summary($filtered),
        ]);
    }

    public function show(Request $request): Response
    {
        $path = $this->pathFromRequest($request);
        if ($path === null) {
            return ApiResponse::error('素材目录参数不正确', 422, null, 422);
        }

        $record = $this->findDirectoryRecord($request, $path);
        if ($record === null) {
            return ApiResponse::error('素材目录不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $record,
        ]);
    }

    public function createDirectory(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'add');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $path = $this->normalizeNewDirectoryName($payload['path'] ?? $payload['name'] ?? '');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $directoryMap = $this->directoryRecordMap($request);
        if (isset($directoryMap[$path])) {
            return ApiResponse::error('素材目录已存在', 422, null, 422);
        }

        $directoryPath = $this->directoryAbsolutePath($path);
        if (is_dir($directoryPath)) {
            return ApiResponse::error('磁盘中已存在同名素材目录', 422, null, 422);
        }

        if (!@mkdir($directoryPath, 0755, true) && !is_dir($directoryPath)) {
            return ApiResponse::error('创建素材目录失败', 500, null, 500);
        }

        $item = $this->findDirectoryRecord($request, $path);
        if ($item === null) {
            return ApiResponse::error('素材目录已创建，但重新加载失败', 500, null, 500);
        }

        $this->recordAdminCreateDirectory($request, $item);

        return ApiResponse::success([
            'created_path' => $path,
            'item' => $item,
        ], '素材目录已创建');
    }

    public function upload(Request $request): Response
    {
        $authorizationError = $this->authorizeAnyWrite($request, ['addPhoto', 'addPhotos']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $path = $this->pathFromRequest($request);
        if ($path === null) {
            return ApiResponse::error('素材目录参数不正确', 422, null, 422);
        }

        $record = $this->findDirectoryRecord($request, $path);
        if ($record === null) {
            return ApiResponse::error('素材目录不存在', 404, null, 404);
        }

        $configuredFileType = SystemConfig::int('file-type', 1);
        if ($configuredFileType !== 1) {
            return ApiResponse::error(
                '当前仅在本地存储模式下支持素材上传',
                422,
                [
                    'configured_file_type' => $configuredFileType,
                ],
                422
            );
        }

        try {
            $files = $this->normalizeUploadFiles($request);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $publicBase = $this->publicBaseUrl($request);
        $maxSizeKb = max(1, SystemConfig::int('imageSize', 2000));
        $maxSizeBytes = $maxSizeKb * 1024;
        $uploadedIds = [];
        $uploadedFiles = [];
        $createdPaths = [];

        try {
            Db::transaction(function () use (
                $files,
                $path,
                $publicBase,
                $maxSizeBytes,
                &$uploadedIds,
                &$uploadedFiles,
                &$createdPaths
            ): void {
                $dateSegment = date('Ymd');

                foreach ($files as $file) {
                    $prepared = $this->prepareUploadedImage($file, $maxSizeBytes);
                    $relativeChild = $dateSegment . '/' . $this->randomUploadFileName($prepared['ext']);
                    $absolutePath = $this->directoryAbsolutePath($path)
                        . DIRECTORY_SEPARATOR
                        . str_replace('/', DIRECTORY_SEPARATOR, $relativeChild);
                    $directory = dirname($absolutePath);
                    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new \RuntimeException('媒体库上传目录初始化失败');
                    }

                    $href = UploadWorkspace::publicHref($path, $relativeChild);

                    $file->move($absolutePath);
                    $createdPaths[] = $absolutePath;
                    $dbId = (int)Db::table('admin_photo')->insertGetId([
                        'name' => $prepared['name'],
                        'href' => $href,
                        'path' => $path,
                        'type' => 1,
                        'ext' => $prepared['ext'],
                        'mime' => $prepared['mime'],
                        'size' => $prepared['size_bytes'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);

                    $uploadedIds[] = $dbId;
                    $uploadedFiles[] = [
                        'db_id' => $dbId,
                        'name' => $prepared['name'],
                        'href' => $href,
                        'relative_path' => $href,
                        'preview_url' => rtrim($publicBase, '/') . $href,
                        'ext' => $prepared['ext'],
                        'mime' => $prepared['mime'],
                        'size_bytes' => $prepared['size_bytes'],
                    ];
                }
            });
        } catch (\InvalidArgumentException $exception) {
            $this->removeCreatedUploadFiles($createdPaths);

            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        } catch (\Throwable $exception) {
            $this->removeCreatedUploadFiles($createdPaths);

            return ApiResponse::error('素材上传失败：' . $exception->getMessage(), 500, null, 500);
        }

        $item = $this->findDirectoryRecord($request, $path);
        if ($item === null) {
            return ApiResponse::error('素材上传成功，但重新加载失败', 500, null, 500);
        }

        $this->recordAdminUpload($request, $path, $uploadedFiles);

        return ApiResponse::success([
            'path' => $path,
            'uploaded_count' => count($uploadedIds),
            'uploaded_db_ids' => $uploadedIds,
            'uploaded_files' => $uploadedFiles,
            'item' => $item,
        ], '素材上传完成');
    }

    public function fileDeleteAudit(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $selector = $this->normalizeFileSelector(
                RequestPayload::all($request)['file'] ?? RequestPayload::all($request)
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $resolved = $this->resolveFileSelectorFromMap(
            $this->directoryRecordMap($request),
            $selector
        );
        if ($resolved === null) {
            return ApiResponse::error('素材文件不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $resolved['file'],
            'audit' => $this->buildFileDeleteAudit($resolved),
        ]);
    }

    public function fileDelete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'remove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $selector = $this->normalizeFileSelector($payload['file'] ?? $payload);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $resolved = $this->resolveFileSelectorFromMap(
            $this->directoryRecordMap($request),
            $selector
        );
        if ($resolved === null) {
            return ApiResponse::error('素材文件不存在', 404, null, 404);
        }

        $audit = $this->buildFileDeleteAudit($resolved);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error('当前素材暂时不能删除', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        try {
            $this->deleteResolvedFile($resolved);
        } catch (\Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 500, ['audit' => $audit], 500);
        }

        $this->recordAdminFileDelete($request, $audit);

        return ApiResponse::success([
            'deleted_file_label' => (string)($audit['file_label'] ?? ''),
            'path' => (string)($audit['path'] ?? ''),
            'audit' => $audit,
        ], '素材已删除');
    }

    public function batchDeleteAudit(Request $request): Response{
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $selectors = $this->normalizeFileSelectors(
                RequestPayload::all($request)['files']
                    ?? RequestPayload::all($request)['items']
                    ?? []
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        return ApiResponse::success([
            'audit' => $this->prepareBatchFileDelete($request, $selectors)['audit'],
        ]);
    }

    public function batchDelete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'batchRemove');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);

        try {
            $selectors = $this->normalizeFileSelectors($payload['files'] ?? $payload['items'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        $prepared = $this->prepareBatchFileDelete($request, $selectors);
        $audit = (array)($prepared['audit'] ?? []);

        if (empty($audit['can_delete_all'])) {
            return ApiResponse::error('所选素材中存在不可删除项，请先处理后再重试', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)($payload['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        try {
            foreach ((array)($prepared['resolved'] ?? []) as $resolved) {
                $this->deleteResolvedFile((array)$resolved);
            }
        } catch (\Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 500, ['audit' => $audit], 500);
        }

        $this->recordAdminBatchDelete($request, $audit);

        return ApiResponse::success([
            'deleted_count' => (int)(($audit['summary'] ?? [])['deletable_count'] ?? 0),
            'deleted_selector_keys' => array_values(array_map(
                'strval',
                (array)($audit['deletable_selector_keys'] ?? [])
            )),
            'audit' => $audit,
        ], '素材已批量删除');
    }

    public function deleteAudit(Request $request): Response{
        $authorizationError = $this->authorizeWrite($request, 'del');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $path = $this->pathFromRequest($request);
        if ($path === null) {
            return ApiResponse::error('素材目录参数不正确', 422, null, 422);
        }

        $record = $this->findDirectoryRecord($request, $path);
        if ($record === null) {
            return ApiResponse::error('素材目录不存在', 404, null, 404);
        }

        return ApiResponse::success([
            'item' => $record,
            'audit' => $this->buildDirectoryDeleteAudit($record),
        ]);
    }

    public function delete(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'del');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $path = $this->pathFromRequest($request);
        if ($path === null) {
            return ApiResponse::error('素材目录参数不正确', 422, null, 422);
        }

        $record = $this->findDirectoryRecord($request, $path);
        if ($record === null) {
            return ApiResponse::error('素材目录不存在', 404, null, 404);
        }

        $audit = $this->buildDirectoryDeleteAudit($record);
        if (empty($audit['can_delete'])) {
            return ApiResponse::error('当前素材目录暂时不能删除', 422, ['audit' => $audit], 422);
        }

        $confirmationPhrase = trim((string)(RequestPayload::all($request)['confirmation_phrase'] ?? ''));
        if ($confirmationPhrase !== (string)($audit['confirmation_phrase'] ?? '')) {
            return ApiResponse::error('confirmation phrase mismatch', 422, ['audit' => $audit], 422);
        }

        try {
            $this->deleteDirectoryRecord($record);
        } catch (\Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 500, ['audit' => $audit], 500);
        }

        $this->recordAdminDirectoryDelete($request, $audit);

        return ApiResponse::success([
            'deleted_path' => $path,
            'audit' => $audit,
        ], '素材目录已删除');
    }

    /**
     * @return array<int, array<string, mixed>>     */
    private function directoryRecords(Request $request): array
    {
        $uploadRoot = $this->uploadRoot();
        $publicBase = $this->publicBaseUrl($request);
        $dbRows = array_map(
            static fn($row): array => (array)$row,
            Db::table('admin_photo')
                ->select('id', 'name', 'href', 'path', 'mime', 'size', 'type', 'ext', 'create_time')
                ->orderByDesc('create_time')
                ->orderByDesc('id')
                ->get()
                ->toArray()
        );

        $rowsByPath = [];
        foreach ($dbRows as $row) {
            $path = trim((string)($row['path'] ?? ''));
            if (
                $path === ''
                || !$this->isSafeDirectoryName($path)
                || !$this->isAdminVisibleDirectory($path)
            ) {
                continue;
            }

            $rowsByPath[$path][] = $row;
        }

        $paths = [];
        foreach ($this->filesystemDirectories($uploadRoot) as $path) {
            $paths[$path] = true;
        }
        foreach (array_keys($rowsByPath) as $path) {
            $paths[$path] = true;
        }

        $rawRecords = [];
        foreach (array_keys($paths) as $path) {
            $rawRecords[] = $this->buildDirectoryRecord(
                $path,
                $rowsByPath[$path] ?? [],
                $uploadRoot,
                $publicBase
            );
        }

        usort($rawRecords, static function (array $a, array $b): int {
            $priority = ((int)($a['sort_priority'] ?? 99)) <=> ((int)($b['sort_priority'] ?? 99));
            if ($priority !== 0) {
                return $priority;
            }

            $countCompare = (
                ((int)($b['disk_file_count'] ?? 0) + (int)($b['db_file_count'] ?? 0))
                <=>
                ((int)($a['disk_file_count'] ?? 0) + (int)($a['db_file_count'] ?? 0))
            );
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strnatcasecmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
        });

        return array_map(
            static fn(array $record): array => AdminMediaLibraryFormatter::formatDirectory($record),
            $rawRecords
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function directoryRecordMap(Request $request): array
    {
        $map = [];
        foreach ($this->directoryRecords($request) as $record) {
            $path = trim((string)($record['path'] ?? ''));
            if ($path !== '') {
                $map[$path] = $record;
            }
        }

        return $map;
    }

    private function findDirectoryRecord(Request $request, string $path): ?array
    {
        return $this->directoryRecordMap($request)[$path] ?? null;
    }

    /**
     * @param array<string, array<string, mixed>> $directoryMap
     * @param array<string, mixed> $selector
     * @return array<string, mixed>|null
     */
    private function resolveFileSelectorFromMap(array $directoryMap, array $selector): ?array
    {
        $path = trim((string)($selector['path'] ?? ''));
        $directory = $directoryMap[$path] ?? null;
        if ($directory === null) {
            return null;
        }

        $dbId = (int)($selector['db_id'] ?? 0);
        $href = trim((string)($selector['href'] ?? ''));

        foreach ((array)($directory['files'] ?? []) as $file) {
            $file = (array)$file;

            if ($dbId > 0 && (int)($file['db_id'] ?? 0) === $dbId) {
                return $this->augmentResolvedFile($directory, $file, (string)($selector['selector_key'] ?? ''));
            }

            if ($dbId <= 0 && $href !== '' && trim((string)($file['href'] ?? '')) === $href) {
                return $this->augmentResolvedFile($directory, $file, (string)($selector['selector_key'] ?? ''));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $directory
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function augmentResolvedFile(array $directory, array $file, string $selectorKey): array
    {
        $path = trim((string)($file['path'] ?? $directory['path'] ?? ''));

        return [
            'selector_key' => $selectorKey,
            'path' => $path,
            'directory' => $directory,
            'file' => $file,
            'directory_absolute_path' => $this->directoryAbsolutePath($path),
            'absolute_file_path' => trim((string)($file['storage_type'] ?? '')) === 'local'
                ? $this->localFileAbsolutePath($path, trim((string)($file['href'] ?? '')))
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $resolved
     * @return array<string, mixed>
     */
    private function buildFileDeleteAudit(array $resolved): array
    {
        $file = (array)($resolved['file'] ?? []);
        $path = trim((string)($resolved['path'] ?? $file['path'] ?? ''));
        $storageType = trim((string)($file['storage_type'] ?? 'local'));
        $sourceStatus = trim((string)($file['source_status'] ?? 'matched'));
        $existsInDb = !empty($file['exists_in_db']) && (int)($file['db_id'] ?? 0) > 0;
        $existsOnDisk = !empty($file['exists_on_disk']);
        $absoluteFilePath = $resolved['absolute_file_path'] ?? null;
        $warnings = [];
        $blockingReasons = [];

        if ($storageType !== 'local') {
            $blockingReasons[] = '当前后台暂不支持云端素材清理。';
            $warnings[] = '如需清理云存储中的素材，请先使用云存储管理工具完成处理。';
        }

        if ($existsOnDisk && (!is_string($absoluteFilePath) || $absoluteFilePath === '')) {
            $blockingReasons[] = '本地素材路径无法在新的上传仓内安全定位。';
        }

        if ($sourceStatus === 'missing_local') {
            $warnings[] = '本地素材已经不存在，本次只会移除 admin_photo 索引记录。';
        } elseif ($sourceStatus === 'orphan_disk') {
            $warnings[] = '当前素材仅存在于磁盘，本次会直接删除磁盘文件，不会改动 admin_photo。';
        } elseif ($storageType === 'local' && $existsInDb && $existsOnDisk) {
            $warnings[] = '删除后会同时清理本地素材文件和对应的 admin_photo 记录。';
        }

        if (!$existsInDb && !$existsOnDisk) {
            $blockingReasons[] = '当前素材在 admin_photo 和本地上传仓中都已不存在。';
        }

        $canDelete = $blockingReasons === [];

        return [
            'selector_key' => (string)($resolved['selector_key'] ?? ''),
            'path' => $path,
            'file_label' => $this->fileLabel($file),
            'db_id' => $existsInDb ? (int)($file['db_id'] ?? 0) : null,
            'href' => trim((string)($file['href'] ?? '')),
            'storage_type' => $storageType,
            'source_status' => $sourceStatus,
            'can_delete' => $canDelete,
            'confirmation_phrase' => $canDelete ? $this->fileDeleteConfirmationPhrase($resolved) : '',
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_db_row_count' => $canDelete && $existsInDb ? 1 : 0,
                'delete_disk_file_count' => $canDelete && $existsOnDisk ? 1 : 0,
                'missing_local_count' => $sourceStatus === 'missing_local' ? 1 : 0,
                'orphan_disk_count' => $sourceStatus === 'orphan_disk' ? 1 : 0,
                'cloud_record_count' => $storageType === 'local' ? 0 : 1,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $selectors
     * @return array{audit: array<string, mixed>, resolved: array<int, array<string, mixed>>}
     */
    private function prepareBatchFileDelete(Request $request, array $selectors): array
    {
        $directoryMap = $this->directoryRecordMap($request);
        $items = [];
        $resolvedFiles = [];
        $deletableSelectorKeys = [];
        $blockedSelectorKeys = [];
        $missingSelectorKeys = [];
        $deleteDbRowCount = 0;
        $deleteDiskFileCount = 0;
        $missingLocalCount = 0;
        $orphanDiskCount = 0;
        $cloudBlockedCount = 0;

        foreach ($selectors as $selector) {
            $selectorKey = (string)($selector['selector_key'] ?? '');
            $resolved = $this->resolveFileSelectorFromMap($directoryMap, $selector);

            if ($resolved === null) {
                $missingSelectorKeys[] = $selectorKey;
                $items[] = [
                    'selector_key' => $selectorKey,
                    'path' => (string)($selector['path'] ?? ''),
                    'db_id' => $selector['db_id'] ?? null,
                    'href' => (string)($selector['href'] ?? ''),
                    'file_label' => $this->selectorFileLabel($selector),
                    'exists' => false,
                    'can_delete' => false,
                    'storage_type' => 'missing',
                    'source_status' => 'missing',
                    'blocking_reasons' => ['该素材记录不存在，请刷新列表后重试。'],
                    'warnings' => [],
                    'summary' => [
                        'delete_db_row_count' => 0,
                        'delete_disk_file_count' => 0,
                        'missing_local_count' => 0,
                        'orphan_disk_count' => 0,
                        'cloud_record_count' => 0,
                    ],
                ];
                continue;
            }

            $audit = $this->buildFileDeleteAudit($resolved);
            $summary = (array)($audit['summary'] ?? []);

            $items[] = [
                'selector_key' => $selectorKey,
                'path' => (string)($audit['path'] ?? ''),
                'db_id' => $audit['db_id'] ?? null,
                'href' => (string)($audit['href'] ?? ''),
                'file_label' => (string)($audit['file_label'] ?? ''),
                'exists' => true,
                'can_delete' => !empty($audit['can_delete']),
                'storage_type' => (string)($audit['storage_type'] ?? 'local'),
                'source_status' => (string)($audit['source_status'] ?? 'matched'),
                'blocking_reasons' => array_values(array_map(
                    'strval',
                    (array)($audit['blocking_reasons'] ?? [])
                )),
                'warnings' => array_values(array_map(
                    'strval',
                    (array)($audit['warnings'] ?? [])
                )),
                'summary' => $summary,
            ];

            if (!empty($audit['can_delete'])) {
                $resolvedFiles[] = $resolved;
                $deletableSelectorKeys[] = $selectorKey;
                $deleteDbRowCount += (int)($summary['delete_db_row_count'] ?? 0);
                $deleteDiskFileCount += (int)($summary['delete_disk_file_count'] ?? 0);
                $missingLocalCount += (int)($summary['missing_local_count'] ?? 0);
                $orphanDiskCount += (int)($summary['orphan_disk_count'] ?? 0);
                continue;
            }

            $blockedSelectorKeys[] = $selectorKey;
            $cloudBlockedCount += (int)($summary['cloud_record_count'] ?? 0);
        }

        $warnings = [];
        if ($missingSelectorKeys !== []) {
            $warnings[] = '所选素材中存在已失效记录，请刷新列表后重新选择。';
        }
        if ($cloudBlockedCount > 0) {
            $warnings[] = '当前仍存在云端素材记录，请先在对应云存储侧完成清理。';
        }
        if ($orphanDiskCount > 0) {
            $warnings[] = '仅存在于磁盘中的孤立素材会被直接删除，不会影响数据库记录。';
        }
        if ($missingLocalCount > 0) {
            $warnings[] = '部分本地素材文件已经不存在，本次仅会清理对应索引记录。';
        }

        $canDeleteAll = $selectors !== [] && $blockedSelectorKeys === [] && $missingSelectorKeys === [];

        return [
            'audit' => [
                'requested_selector_keys' => array_values(array_map(
                    static fn(array $selector): string => (string)($selector['selector_key'] ?? ''),
                    $selectors
                )),
                'deletable_selector_keys' => $deletableSelectorKeys,
                'blocked_selector_keys' => $blockedSelectorKeys,
                'missing_selector_keys' => $missingSelectorKeys,
                'confirmation_phrase' => $canDeleteAll
                    ? $this->batchFileDeleteConfirmationPhrase($deletableSelectorKeys)
                    : '',
                'can_delete_all' => $canDeleteAll,
                'items' => $items,
                'summary' => [
                    'requested_count' => count($selectors),
                    'existing_count' => count($selectors) - count($missingSelectorKeys),
                    'deletable_count' => count($deletableSelectorKeys),
                    'blocked_count' => count($blockedSelectorKeys),
                    'missing_count' => count($missingSelectorKeys),
                    'delete_db_row_count' => $deleteDbRowCount,
                    'delete_disk_file_count' => $deleteDiskFileCount,
                    'missing_local_count' => $missingLocalCount,
                    'orphan_disk_count' => $orphanDiskCount,
                    'cloud_blocked_count' => $cloudBlockedCount,
                ],
                'warnings' => $warnings,
            ],
            'resolved' => $resolvedFiles,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function buildDirectoryDeleteAudit(array $record): array
    {
        $path = trim((string)($record['path'] ?? ''));
        $cloudRecordCount = max(0, (int)($record['cloud_file_count'] ?? 0));
        $directoryExists = !empty($record['directory_exists']);
        $warnings = [
            '删除目录时，会同步清理该目录下的本地素材和对应索引记录。',
        ];
        $blockingReasons = [];

        if ($cloudRecordCount > 0) {
            $blockingReasons[] = '当前目录中仍包含云端素材记录，暂不支持直接删除。';
            $warnings[] = '请先处理云端素材记录，再重新执行目录删除。';
        }
        if ((int)($record['missing_local_count'] ?? 0) > 0) {
            $warnings[] = '部分已登记素材在磁盘中已不存在，本次仅会清理对应索引记录。';
        }
        if ((int)($record['orphan_disk_count'] ?? 0) > 0) {
            $warnings[] = '目录中的孤立本地文件也会一并删除。';
        }

        $canDelete = $blockingReasons === [];

        return [
            'path' => $path,
            'path_label' => trim((string)($record['path_label'] ?? $path)),
            'can_delete' => $canDelete,
            'confirmation_phrase' => $canDelete ? $this->directoryDeleteConfirmationPhrase($path) : '',
            'blocking_reasons' => $blockingReasons,
            'summary' => [
                'delete_db_row_count' => $canDelete ? max(0, (int)($record['db_file_count'] ?? 0)) : 0,
                'delete_disk_file_count' => $canDelete ? max(0, (int)($record['disk_file_count'] ?? 0)) : 0,
                'delete_directory_count' => $canDelete && $directoryExists ? 1 : 0,
                'cloud_record_count' => $cloudRecordCount,
                'missing_local_count' => max(0, (int)($record['missing_local_count'] ?? 0)),
                'orphan_disk_count' => max(0, (int)($record['orphan_disk_count'] ?? 0)),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $resolved
     */
    private function deleteResolvedFile(array $resolved): void
    {
        $file = (array)($resolved['file'] ?? []);
        $dbId = (int)($file['db_id'] ?? 0);
        $existsOnDisk = !empty($file['exists_on_disk']);
        $existsInDb = !empty($file['exists_in_db']) && $dbId > 0;
        $absoluteFilePath = $resolved['absolute_file_path'] ?? null;
        $directoryRoot = trim((string)($resolved['directory_absolute_path'] ?? ''));

        if ($existsOnDisk && is_string($absoluteFilePath) && $absoluteFilePath !== '' && is_file($absoluteFilePath)) {
            if (!@unlink($absoluteFilePath)) {
                throw new \RuntimeException('删除本地素材文件失败');
            }

            if ($directoryRoot !== '') {
                $this->cleanupEmptyAncestorDirectories(dirname($absoluteFilePath), $directoryRoot);
            }
        }

        if ($existsInDb) {
            Db::table('admin_photo')
                ->where('id', $dbId)
                ->delete();
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function deleteDirectoryRecord(array $record): void
    {
        $path = trim((string)($record['path'] ?? ''));
        $directoryPath = $this->directoryAbsolutePath($path);

        if (is_dir($directoryPath)) {
            $this->deleteDirectoryTree($directoryPath);
        }

        Db::table('admin_photo')
            ->where('path', $path)
            ->delete();
    }

    private function buildDirectoryRecord(
        string $path,
        array $dbRows,
        string $uploadRoot,
        string $publicBase
    ): array {
        $directoryPath = $uploadRoot . DIRECTORY_SEPARATOR . $path;
        $diskFiles = $this->scanLocalFiles($directoryPath, $path, $publicBase);
        $diskFilesByHref = [];
        foreach ($diskFiles as $diskFile) {
            $diskFilesByHref[(string)$diskFile['href']] = $diskFile;
        }

        $dbFileCount = count($dbRows);
        $diskFileCount = count($diskFiles);
        $dbSizeBytes = 0;
        $diskSizeBytes = 0;
        $localDbCount = 0;
        $cloudFileCount = 0;
        $matchedFileCount = 0;
        $orphanDiskCount = 0;
        $missingLocalCount = 0;
        $latestDbTime = null;
        $latestDiskTime = null;
        $previewUrl = null;
        $matchedHrefs = [];
        $files = [];

        foreach ($diskFiles as $diskFile) {
            $diskSizeBytes += (int)($diskFile['size_bytes'] ?? 0);
            $latestDiskTime = $this->maxTimestamp(
                $latestDiskTime,
                (string)($diskFile['disk_mtime'] ?? '')
            );
        }

        foreach ($dbRows as $row) {
            $type = (int)($row['type'] ?? 1);
            $href = $this->normalizeHref((string)($row['href'] ?? ''));
            $diskFile = $href !== '' && isset($diskFilesByHref[$href]) ? $diskFilesByHref[$href] : null;
            $existsOnDisk = $diskFile !== null;
            $isCloud = in_array($type, [2, 3], true);
            $dbSizeBytes += max(0, (int)($row['size'] ?? 0));
            $latestDbTime = $this->maxTimestamp(
                $latestDbTime,
                (string)($row['create_time'] ?? '')
            );

            if ($isCloud) {
                $cloudFileCount++;
            } else {
                $localDbCount++;
                if ($existsOnDisk) {
                    $matchedFileCount++;
                } else {
                    $missingLocalCount++;
                }
            }

            if ($existsOnDisk && $href !== '') {
                $matchedHrefs[$href] = true;
            }

            $filePreview = $this->previewUrl($href, $type, $existsOnDisk, $publicBase);
            if ($previewUrl === null && $filePreview !== null) {
                $previewUrl = $filePreview;
            }

            $files[] = [
                'key' => 'db-' . (int)($row['id'] ?? 0),
                'db_id' => (int)($row['id'] ?? 0),
                'name' => trim((string)($row['name'] ?? '')) ?: ($diskFile['name'] ?? basename($href)),
                'path' => $path,
                'href' => $href,
                'relative_path' => $href,
                'preview_url' => $filePreview,
                'ext' => trim((string)($row['ext'] ?? ($diskFile['ext'] ?? ''))),
                'mime' => trim((string)($row['mime'] ?? '')) ?: null,
                'storage_type' => $this->storageTypeFromPhotoType($type),
                'source_status' => $isCloud ? 'cloud_record' : ($existsOnDisk ? 'matched' : 'missing_local'),
                'exists_on_disk' => $existsOnDisk,
                'exists_in_db' => true,
                'size_bytes' => max(
                    max(0, (int)($row['size'] ?? 0)),
                    max(0, (int)($diskFile['size_bytes'] ?? 0))
                ),
                'db_size_bytes' => max(0, (int)($row['size'] ?? 0)),
                'disk_size_bytes' => max(0, (int)($diskFile['size_bytes'] ?? 0)),
                'create_time' => $this->nullableString($row['create_time'] ?? null),
                'disk_mtime' => $this->nullableString($diskFile['disk_mtime'] ?? null),
            ];
        }

        foreach ($diskFiles as $diskFile) {
            $href = (string)($diskFile['href'] ?? '');
            if ($href !== '' && isset($matchedHrefs[$href])) {
                continue;
            }

            $orphanDiskCount++;
            if ($previewUrl === null && !empty($diskFile['preview_url'])) {
                $previewUrl = (string)$diskFile['preview_url'];
            }

            $files[] = [
                'key' => 'disk-' . md5($href),
                'db_id' => null,
                'name' => (string)($diskFile['name'] ?? basename($href)),
                'path' => $path,
                'href' => $href,
                'relative_path' => $href,
                'preview_url' => $diskFile['preview_url'] ?? null,
                'ext' => $diskFile['ext'] ?? null,
                'mime' => null,
                'storage_type' => 'local',
                'source_status' => 'orphan_disk',
                'exists_on_disk' => true,
                'exists_in_db' => false,
                'size_bytes' => max(0, (int)($diskFile['size_bytes'] ?? 0)),
                'db_size_bytes' => 0,
                'disk_size_bytes' => max(0, (int)($diskFile['size_bytes'] ?? 0)),
                'create_time' => null,
                'disk_mtime' => $this->nullableString($diskFile['disk_mtime'] ?? null),
            ];
        }

        usort($files, static function (array $a, array $b): int {
            $timeA = (string)($a['disk_mtime'] ?? $a['create_time'] ?? '');
            $timeB = (string)($b['disk_mtime'] ?? $b['create_time'] ?? '');
            $timeCompare = strcmp($timeB, $timeA);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }

            return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $storageMode = $this->storageMode($diskFileCount > 0 || $localDbCount > 0, $cloudFileCount > 0);
        $syncStatus = $this->syncStatus($diskFileCount, $dbFileCount, $orphanDiskCount, $missingLocalCount);

        return [
            'path' => $path,
            'directory_exists' => is_dir($directoryPath),
            'storage_mode' => $storageMode,
            'sync_status' => $syncStatus,
            'db_file_count' => $dbFileCount,
            'local_db_count' => $localDbCount,
            'cloud_file_count' => $cloudFileCount,
            'disk_file_count' => $diskFileCount,
            'matched_file_count' => $matchedFileCount,
            'orphan_disk_count' => $orphanDiskCount,
            'missing_local_count' => $missingLocalCount,
            'empty_directory' => $diskFileCount === 0 && $dbFileCount === 0,
            'disk_size_bytes' => $diskSizeBytes,
            'db_size_bytes' => $dbSizeBytes,
            'latest_db_time' => $latestDbTime,
            'latest_disk_time' => $latestDiskTime,
            'latest_file_name' => $files[0]['name'] ?? null,
            'preview_url' => $previewUrl,
            'legacy_page' => self::LEGACY_PAGE,
            'legacy_list_endpoint' => '/admin.photo/list/name/' . rawurlencode($path),
            'files' => $files,
            'sort_priority' => $this->sortPriority($syncStatus),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $records, Request $request): array
    {
        $keyword = mb_strtolower(trim((string)$request->get('keyword', '')));
        $syncStatus = trim((string)$request->get('sync_status', ''));
        $storageMode = trim((string)$request->get('storage_mode', ''));

        return array_values(array_filter($records, static function (array $record) use (
            $keyword,
            $syncStatus,
            $storageMode
        ): bool {
            if ($syncStatus !== '' && (string)($record['sync_status'] ?? '') !== $syncStatus) {
                return false;
            }

            if ($storageMode !== '' && (string)($record['storage_mode'] ?? '') !== $storageMode) {
                return false;
            }

            if ($keyword === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', array_filter([
                (string)($record['path'] ?? ''),
                (string)($record['latest_file_name'] ?? ''),
                (string)($record['storage_label'] ?? ''),
                (string)($record['sync_status_label'] ?? ''),
                (string)($record['legacy_list_endpoint'] ?? ''),
            ])));

            return str_contains($haystack, $keyword);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function summary(array $records): array
    {
        $summary = [
            'directory_count' => count($records),
            'healthy_count' => 0,
            'warning_directory_count' => 0,
            'empty_directory_count' => 0,
            'db_file_count' => 0,
            'disk_file_count' => 0,
            'orphan_disk_count' => 0,
            'missing_local_count' => 0,
            'cloud_file_count' => 0,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        foreach ($records as $record) {
            $syncStatus = (string)($record['sync_status'] ?? '');
            if ($syncStatus === 'healthy') {
                $summary['healthy_count']++;
            } elseif ($syncStatus === 'empty') {
                $summary['empty_directory_count']++;
            } else {
                $summary['warning_directory_count']++;
            }

            $summary['db_file_count'] += (int)($record['db_file_count'] ?? 0);
            $summary['disk_file_count'] += (int)($record['disk_file_count'] ?? 0);
            $summary['orphan_disk_count'] += (int)($record['orphan_disk_count'] ?? 0);
            $summary['missing_local_count'] += (int)($record['missing_local_count'] ?? 0);
            $summary['cloud_file_count'] += (int)($record['cloud_file_count'] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    private function filesystemDirectories(string $uploadRoot): array
    {
        if (!is_dir($uploadRoot)) {
            return [];
        }

        $directories = [];
        foreach (scandir($uploadRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $uploadRoot . DIRECTORY_SEPARATOR . $entry;
            if (
                !is_dir($fullPath)
                || !$this->isSafeDirectoryName($entry)
                || !$this->isAdminVisibleDirectory($entry)
            ) {
                continue;
            }

            $directories[] = $entry;
        }

        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

        return $directories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanLocalFiles(string $directoryPath, string $path, string $publicBase): array
    {
        if (!is_dir($directoryPath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $files = [];
        $baseLength = strlen($directoryPath);
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $fullPath = $item->getPathname();
            $relativeChild = str_replace('\\', '/', substr($fullPath, $baseLength));
            $href = UploadWorkspace::publicHref($path, ltrim($relativeChild, '/'));

            $files[] = [
                'name' => $item->getBasename(),
                'href' => $href,
                'preview_url' => rtrim($publicBase, '/') . $href,
                'ext' => strtolower((string)$item->getExtension()),
                'size_bytes' => (int)$item->getSize(),
                'disk_mtime' => date('Y-m-d H:i:s', (int)$item->getMTime()),
            ];
        }

        return $files;
    }

    private function deleteDirectoryTree(string $directoryPath): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!@rmdir($item->getPathname()) && is_dir($item->getPathname())) {
                    throw new \RuntimeException('删除素材子目录失败');
                }
                continue;
            }

            if (!@unlink($item->getPathname()) && is_file($item->getPathname())) {
                throw new \RuntimeException('删除素材文件失败');
            }
        }

        if (!@rmdir($directoryPath) && is_dir($directoryPath)) {
            throw new \RuntimeException('删除素材目录失败');
        }
    }

    private function cleanupEmptyAncestorDirectories(string $startPath, string $stopPath): void
    {
        $current = $this->normalizePathForCompare($startPath);
        $stop = $this->normalizePathForCompare($stopPath);

        while ($current !== '' && $current !== $stop && str_starts_with($current, $stop . '/')) {
            $actualPath = str_replace('/', DIRECTORY_SEPARATOR, $current);
            if (!is_dir($actualPath)) {
                $current = $this->normalizePathForCompare(dirname($actualPath));
                continue;
            }

            $entries = scandir($actualPath);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) > 0) {
                break;
            }

            @rmdir($actualPath);
            $current = $this->normalizePathForCompare(dirname($actualPath));
        }
    }

    private function directoryAbsolutePath(string $path): string
    {
        return $this->uploadRoot() . DIRECTORY_SEPARATOR . $path;
    }

    private function localFileAbsolutePath(string $path, string $href): ?string
    {
        $href = $this->normalizeHref($href);
        $prefix = '/upload/' . trim($path, '/') . '/';

        if ($href === '' || !str_starts_with($href, $prefix)) {
            return null;
        }

        $relative = str_replace('\\', '/', substr($href, strlen($prefix)));
        if ($relative === '' || str_contains($relative, '../') || str_contains($relative, '..\\')) {
            return null;
        }

        return $this->directoryAbsolutePath($path) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * @return array<int, UploadFile>
     */    private function normalizeUploadFiles(Request $request): array
    {
        $files = [];
        foreach (['file', 'files'] as $key) {
            $this->collectUploadFiles($files, $request->file($key));
        }

        if ($files === []) {
            foreach ($request->file() as $value) {
                $this->collectUploadFiles($files, $value);
            }
        }

        if ($files === []) {
            throw new \InvalidArgumentException('请至少上传一个素材文件');
        }

        if (count($files) > 20) {
            throw new \InvalidArgumentException('单次最多上传 20 个素材文件');
        }

        return $files;
    }

    /**
     * @param array<int, UploadFile> $files
     */
    private function collectUploadFiles(array &$files, mixed $value): void
    {
        if ($value instanceof UploadFile) {
            $files[] = $value;
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->collectUploadFiles($files, $item);
        }
    }

    /**
     * @return array{name: string, ext: string, mime: string, size_bytes: int}
     */
    private function prepareUploadedImage(UploadFile $file, int $maxSizeBytes): array
    {
        $uploadName = trim((string)($file->getUploadName() ?? ''));
        $displayName = $uploadName !== '' ? basename($uploadName) : '未命名文件';

        if (!$file->isValid()) {
            throw new \InvalidArgumentException(sprintf('上传文件“%s”无效', $displayName));
        }

        $sizeBytes = max(0, (int)$file->getSize());
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException(sprintf('上传文件“%s”为空', $displayName));
        }

        if ($sizeBytes > $maxSizeBytes) {
            throw new \InvalidArgumentException(sprintf(
                '上传文件“%s”超过系统限制（%d KB）',
                $displayName,
                (int)ceil($maxSizeBytes / 1024)
            ));
        }

        $uploadExtension = $this->normalizeUploadExtension((string)$file->getUploadExtension());
        if ($uploadExtension !== '' && !in_array($uploadExtension, ['jpg', 'jpeg', 'png', 'bmp', 'gif'], true)) {
            throw new \InvalidArgumentException(sprintf('上传文件“%s”的格式暂不支持', $displayName));
        }

        $imageInfo = @getimagesize($file->getPathname());
        $mime = is_array($imageInfo) ? trim((string)($imageInfo['mime'] ?? '')) : '';
        $allowedMimeMap = $this->allowedImageMimeMap();
        if ($mime === '' || !isset($allowedMimeMap[$mime])) {
            throw new \InvalidArgumentException(sprintf('上传文件“%s”不是支持的图片格式', $displayName));
        }

        return [
            'name' => mb_substr($displayName, 0, 50),
            'ext' => $allowedMimeMap[$mime],
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function allowedImageMimeMap(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
        ];
    }

    private function normalizeUploadExtension(string $extension): string
    {
        return strtolower(trim($extension));
    }

    private function randomUploadFileName(string $extension): string
    {
        return date('His') . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($extension);
    }

    /**
     * @param array<int, string> $paths
     */
    private function removeCreatedUploadFiles(array $paths): void
    {
        foreach (array_reverse($paths) as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }

            @unlink($path);
        }
    }

    private function uploadRoot(): string
    {
        return UploadWorkspace::rootPath();
    }

    private function publicBaseUrl(Request $request): string
    {
        return $this->requestOrigin($request);
    }

    private function requestOrigin(Request $request): string
    {
        $scheme = 'http';
        $forwardedProto = strtolower(trim((string)$request->header('x-forwarded-proto', '')));
        if ($forwardedProto !== '') {
            $scheme = explode(',', $forwardedProto)[0] === 'https' ? 'https' : 'http';
        } elseif ((string)$request->header('front-end-https', '') === 'on' || (string)$request->header('x-forwarded-port', '') === '443') {
            $scheme = 'https';
        }

        return $scheme . '://' . $request->host();
    }

    private function previewUrl(string $href, int $type, bool $existsOnDisk, string $publicBase): ?string
    {
        if ($href === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $href) === 1) {
            return $href;
        }

        if ($type !== 1 && !$existsOnDisk) {
            return null;
        }

        if (!str_starts_with($href, '/')) {
            $href = '/' . ltrim($href, '/');
        }

        return rtrim($publicBase, '/') . $href;
    }

    private function normalizeHref(string $href): string
    {
        $href = trim(str_replace('\\', '/', $href));
        if ($href === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $href) === 1) {
            return $href;
        }

        return '/' . ltrim($href, '/');
    }

    private function storageTypeFromPhotoType(int $type): string
    {
        return match ($type) {
            2 => 'aliyun',
            3 => 'qiniu',
            default => 'local',
        };
    }

    private function storageMode(bool $hasLocal, bool $hasCloud): string
    {
        if ($hasLocal && $hasCloud) {
            return 'mixed';
        }

        return $hasCloud ? 'cloud' : 'local';
    }

    private function syncStatus(
        int $diskFileCount,
        int $dbFileCount,
        int $orphanDiskCount,
        int $missingLocalCount
    ): string {
        if ($diskFileCount === 0 && $dbFileCount === 0) {
            return 'empty';
        }

        if ($orphanDiskCount > 0 && $missingLocalCount > 0) {
            return 'drift';
        }

        if ($missingLocalCount > 0) {
            return 'missing_local';
        }

        if ($orphanDiskCount > 0) {
            return 'orphan_disk';
        }

        return 'healthy';
    }

    private function sortPriority(string $status): int
    {
        return match ($status) {
            'drift' => 0,
            'missing_local' => 1,
            'orphan_disk' => 2,
            'healthy' => 3,
            default => 4,
        };
    }

    private function pathFromRequest(Request $request): ?string
    {
        $path = rawurldecode(trim((string)($request->route ? $request->route->param('path', '') : '')));
        return $this->isSafeDirectoryName($path) ? $path : null;
    }

    private function normalizeNewDirectoryName(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw new \InvalidArgumentException('素材目录名称格式不正确');
        }

        $path = trim((string)$value);
        if ($path === '') {
            throw new \InvalidArgumentException('素材目录名称不能为空');
        }

        if (mb_strlen($path) > 64) {
            throw new \InvalidArgumentException('素材目录名称过长');
        }

        if (!$this->isSafeDirectoryName($path)) {
            throw new \InvalidArgumentException('素材目录名称只能包含字母、数字、下划线和短横线');
        }

        return $path;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalizeFileSelector(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('素材选择参数格式不正确');
        }

        $path = $this->normalizeNewDirectoryName($value['path'] ?? '');
        $dbId = null;
        if (array_key_exists('db_id', $value) && $value['db_id'] !== null && $value['db_id'] !== '') {
            if (!is_numeric($value['db_id']) || (int)$value['db_id'] <= 0) {
                throw new \InvalidArgumentException('素材编号格式不正确');
            }

            $dbId = (int)$value['db_id'];
        }

        $href = '';
        if (array_key_exists('href', $value) && $value['href'] !== null) {
            if (is_array($value['href']) || is_object($value['href'])) {
                throw new \InvalidArgumentException('素材地址格式不正确');
            }

            $href = $this->normalizeHref((string)$value['href']);
        }

        if ($dbId === null && $href === '') {
            throw new \InvalidArgumentException('请选择要操作的素材文件');
        }

        return [
            'path' => $path,
            'db_id' => $dbId,
            'href' => $href,
            'selector_key' => $this->fileSelectorKey($path, $dbId, $href),
        ];
    }

    /**
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFileSelectors(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('素材选择列表格式不正确');
        }

        $selectors = [];
        foreach ($value as $item) {
            $selector = $this->normalizeFileSelector($item);
            $selectors[(string)$selector['selector_key']] = $selector;
        }

        $selectors = array_values($selectors);
        if ($selectors === []) {
            throw new \InvalidArgumentException('请至少选择一个素材文件');
        }

        if (count($selectors) > 200) {
            throw new \InvalidArgumentException('单次最多处理 200 个素材文件');
        }

        return $selectors;
    }

    private function fileSelectorKey(string $path, ?int $dbId, string $href): string
    {
        if ($dbId !== null && $dbId > 0) {
            return 'db-' . $dbId;
        }

        return 'href-' . strtoupper(substr(md5($path . '|' . $href), 0, 10));
    }

    /**
     * @param array<string, mixed> $selector
     */
    private function selectorFileLabel(array $selector): string
    {
        $href = trim((string)($selector['href'] ?? ''));
        if ($href !== '') {
            return basename($href);
        }

        $dbId = (int)($selector['db_id'] ?? 0);
        return $dbId > 0 ? ('素材 #' . $dbId) : trim((string)($selector['path'] ?? ''));
    }

    /**
     * @param array<string, mixed> $file
     */
    private function fileLabel(array $file): string
    {
        $relativePath = trim((string)($file['relative_path'] ?? ''));
        if ($relativePath !== '') {
            return $relativePath;
        }

        $name = trim((string)($file['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $dbId = (int)($file['db_id'] ?? 0);
        return $dbId > 0 ? ('素材 #' . $dbId) : '素材文件';
    }

    /**
     * @param array<string, mixed> $resolved
     */
    private function fileDeleteConfirmationPhrase(array $resolved): string
    {
        $file = (array)($resolved['file'] ?? []);
        $dbId = (int)($file['db_id'] ?? 0);
        if ($dbId > 0) {
            return 'DELETE MEDIA FILE ' . $dbId;
        }

        return sprintf(
            'DELETE MEDIA FILE %s',
            strtoupper(substr(md5((string)($resolved['selector_key'] ?? '')), 0, 6))
        );
    }

    /**
     * @param array<int, string> $selectorKeys
     */
    private function batchFileDeleteConfirmationPhrase(array $selectorKeys): string
    {
        sort($selectorKeys);

        return sprintf(
            'DELETE MEDIA FILE BATCH %d-%s',
            count($selectorKeys),
            strtoupper(substr(md5(implode('|', $selectorKeys)), 0, 6))
        );
    }

    private function directoryDeleteConfirmationPhrase(string $path): string
    {
        return 'DELETE MEDIA DIRECTORY ' . strtoupper($path);
    }

    private function recordAdminCreateDirectory(Request $request, array $item): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $path = trim((string)($item['path'] ?? ''));
        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => self::CREATE_DIRECTORY_URL,
            'desc' => sprintf(
                'media library create path="%s" directory_exists=%d',
                $this->truncateLogText($path, 120),
                !empty($item['directory_exists']) ? 1 : 0
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $uploadedFiles
     */
    private function recordAdminUpload(Request $request, string $path, array $uploadedFiles): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $sizeBytes = 0;
        $labels = [];
        foreach ($uploadedFiles as $file) {
            $sizeBytes += max(0, (int)($file['size_bytes'] ?? 0));
            $labels[] = trim((string)($file['name'] ?? ''));
        }

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => sprintf(self::UPLOAD_URL_TEMPLATE, rawurlencode($path)),
            'desc' => sprintf(
                'media library upload path="%s" uploaded=%d total_bytes=%d files="%s"',
                $this->truncateLogText($path, 120),
                count($uploadedFiles),
                $sizeBytes,
                $this->truncateLogText(implode(',', array_filter($labels)), 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminFileDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => self::FILE_DELETE_URL,
            'desc' => sprintf(
                'media library file delete path="%s" db_id=%d delete_rows=%d delete_disk_files=%d source_status=%s storage_type=%s file="%s"',
                $this->truncateLogText((string)($audit['path'] ?? ''), 80),
                (int)($audit['db_id'] ?? 0),
                (int)($summary['delete_db_row_count'] ?? 0),
                (int)($summary['delete_disk_file_count'] ?? 0),
                trim((string)($audit['source_status'] ?? '')),
                trim((string)($audit['storage_type'] ?? '')),
                $this->truncateLogText((string)($audit['file_label'] ?? ''), 160)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminBatchDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $labels = implode(',', array_map(
            static fn(array $item): string => trim((string)($item['file_label'] ?? '')),
            array_values(array_filter(
                (array)($audit['items'] ?? []),
                static fn(array $item): bool => !empty($item['can_delete'])
            ))
        ));

        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => self::BATCH_DELETE_URL,
            'desc' => sprintf(
                'media library batch delete requested=%d deleted=%d blocked=%d missing=%d delete_rows=%d delete_disk_files=%d missing_local=%d orphan_disk=%d files="%s"',
                (int)($summary['requested_count'] ?? 0),
                (int)($summary['deletable_count'] ?? 0),
                (int)($summary['blocked_count'] ?? 0),
                (int)($summary['missing_count'] ?? 0),
                (int)($summary['delete_db_row_count'] ?? 0),
                (int)($summary['delete_disk_file_count'] ?? 0),
                (int)($summary['missing_local_count'] ?? 0),
                (int)($summary['orphan_disk_count'] ?? 0),
                $this->truncateLogText($labels, 255)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAdminDirectoryDelete(Request $request, array $audit): void
    {
        $adminId = $this->adminIdFromRequest($request);
        if ($adminId <= 0) {
            return;
        }

        $summary = (array)($audit['summary'] ?? []);
        $path = trim((string)($audit['path'] ?? ''));
        Db::table('admin_admin_log')->insert([
            'uid' => $adminId,
            'url' => '/api/admin/media-library/' . rawurlencode($path) . '/delete',
            'desc' => sprintf(
                'media library directory delete path="%s" delete_rows=%d delete_disk_files=%d delete_directories=%d missing_local=%d orphan_disk=%d',
                $this->truncateLogText($path, 120),
                (int)($summary['delete_db_row_count'] ?? 0),
                (int)($summary['delete_disk_file_count'] ?? 0),
                (int)($summary['delete_directory_count'] ?? 0),
                (int)($summary['missing_local_count'] ?? 0),
                (int)($summary['orphan_disk_count'] ?? 0)
            ),
            'ip' => (string)$request->getRealIp(),
            'user_agent' => (string)$request->header('user-agent', ''),
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function adminIdFromRequest(Request $request): int
    {
        return (int)(((array)($request->admin ?? []))['id'] ?? 0);
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'SystemMediaLibrary', $authMark);
    }

    /**
     * @param array<int, string> $authMarks
     */
    private function authorizeAnyWrite(Request $request, array $authMarks): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'SystemMediaLibrary', $authMarks);
    }

    private function truncateLogText(string $value, int $limit): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if ($value === '') {
            return '';
        }

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 3) . '...' : $value;
    }

    private function normalizePathForCompare(string $path): string
    {
        return strtolower(str_replace('\\', '/', rtrim($path, '\\/')));
    }

    private function maxTimestamp(?string $left, string $right): ?string
    {
        $right = trim($right);
        if ($right === '') {
            return $left;
        }

        if ($left === null || $left === '' || strcmp($right, $left) > 0) {
            return $right;
        }

        return $left;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private function isSafeDirectoryName(string $path): bool
    {
        return $path !== ''
            && preg_match('/^[A-Za-z0-9_-]+$/', $path) === 1;
    }

    private function isAdminVisibleDirectory(string $path): bool
    {
        return !in_array(strtolower(trim($path)), self::ADMIN_HIDDEN_DIRECTORIES, true);
    }
}
