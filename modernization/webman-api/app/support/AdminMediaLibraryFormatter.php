<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminMediaLibraryFormatter
{
    public static function formatDirectory(array $directory): array
    {
        $syncStatus = (string)($directory['sync_status'] ?? 'healthy');
        $storageMode = (string)($directory['storage_mode'] ?? 'local');
        $path = trim((string)($directory['path'] ?? ''));

        return [
            'path' => $path,
            'path_label' => self::pathLabel($path),
            'directory_exists' => (bool)($directory['directory_exists'] ?? false),
            'storage_mode' => $storageMode,
            'storage_label' => self::storageModeLabel($storageMode),
            'storage_tag' => self::storageModeTag($storageMode),
            'sync_status' => $syncStatus,
            'sync_status_label' => self::syncStatusLabel($syncStatus),
            'sync_status_type' => self::syncStatusType($syncStatus),
            'db_file_count' => max(0, (int)($directory['db_file_count'] ?? 0)),
            'local_db_count' => max(0, (int)($directory['local_db_count'] ?? 0)),
            'cloud_file_count' => max(0, (int)($directory['cloud_file_count'] ?? 0)),
            'disk_file_count' => max(0, (int)($directory['disk_file_count'] ?? 0)),
            'matched_file_count' => max(0, (int)($directory['matched_file_count'] ?? 0)),
            'orphan_disk_count' => max(0, (int)($directory['orphan_disk_count'] ?? 0)),
            'missing_local_count' => max(0, (int)($directory['missing_local_count'] ?? 0)),
            'empty_directory' => (bool)($directory['empty_directory'] ?? false),
            'disk_size_bytes' => max(0, (int)($directory['disk_size_bytes'] ?? 0)),
            'disk_size_label' => self::bytesLabel((int)($directory['disk_size_bytes'] ?? 0)),
            'db_size_bytes' => max(0, (int)($directory['db_size_bytes'] ?? 0)),
            'db_size_label' => self::bytesLabel((int)($directory['db_size_bytes'] ?? 0)),
            'latest_db_time' => self::nullableString($directory['latest_db_time'] ?? null),
            'latest_disk_time' => self::nullableString($directory['latest_disk_time'] ?? null),
            'latest_file_name' => AdminFixtureTextNormalizer::normalizeNullable(
                self::nullableString($directory['latest_file_name'] ?? null)
            ),
            'preview_url' => self::nullableString($directory['preview_url'] ?? null),
            'legacy_page' => AdminFixtureTextNormalizer::normalize(trim((string)($directory['legacy_page'] ?? ''))),
            'legacy_list_endpoint' => AdminFixtureTextNormalizer::normalize(trim((string)($directory['legacy_list_endpoint'] ?? ''))),
            'readonly_note' => '当前阶段已支持目录创建、本地图片上传、本地素材删除、批量删图与目录删除；如果系统当前使用云端存储，请通过对应存储入口处理上传与云端素材清理。',
            'files' => array_map(
                static fn (array $file): array => self::formatFile($file),
                $directory['files'] ?? []
            ),
        ];
    }

    public static function formatFile(array $file): array
    {
        $storageType = (string)($file['storage_type'] ?? 'local');
        $sourceStatus = (string)($file['source_status'] ?? 'matched');

        return [
            'key' => (string)($file['key'] ?? ''),
            'db_id' => isset($file['db_id']) ? (int)$file['db_id'] : null,
            'name' => AdminFixtureTextNormalizer::normalize(trim((string)($file['name'] ?? ''))),
            'path' => trim((string)($file['path'] ?? '')),
            'href' => trim((string)($file['href'] ?? '')),
            'relative_path' => trim((string)($file['relative_path'] ?? '')),
            'preview_url' => self::nullableString($file['preview_url'] ?? null),
            'ext' => self::nullableString($file['ext'] ?? null),
            'mime' => self::nullableString($file['mime'] ?? null),
            'storage_type' => $storageType,
            'storage_label' => self::storageTypeLabel($storageType),
            'storage_tag' => self::storageTypeTag($storageType),
            'source_status' => $sourceStatus,
            'source_status_label' => self::sourceStatusLabel($sourceStatus),
            'source_status_type' => self::sourceStatusType($sourceStatus),
            'exists_on_disk' => (bool)($file['exists_on_disk'] ?? false),
            'exists_in_db' => (bool)($file['exists_in_db'] ?? false),
            'size_bytes' => max(0, (int)($file['size_bytes'] ?? 0)),
            'size_label' => self::bytesLabel((int)($file['size_bytes'] ?? 0)),
            'db_size_bytes' => max(0, (int)($file['db_size_bytes'] ?? 0)),
            'db_size_label' => self::bytesLabel((int)($file['db_size_bytes'] ?? 0)),
            'disk_size_bytes' => max(0, (int)($file['disk_size_bytes'] ?? 0)),
            'disk_size_label' => self::bytesLabel((int)($file['disk_size_bytes'] ?? 0)),
            'create_time' => self::nullableString($file['create_time'] ?? null),
            'disk_mtime' => self::nullableString($file['disk_mtime'] ?? null),
        ];
    }

    private static function syncStatusLabel(string $status): string
    {
        return match ($status) {
            'orphan_disk' => '存在孤立文件',
            'missing_local' => '索引缺档',
            'drift' => '双向偏差',
            'empty' => '空目录',
            default => '状态正常',
        };
    }

    private static function syncStatusType(string $status): string
    {
        return match ($status) {
            'orphan_disk' => 'warning',
            'missing_local', 'drift' => 'danger',
            'empty' => 'info',
            default => 'success',
        };
    }

    private static function storageModeLabel(string $mode): string
    {
        return match ($mode) {
            'cloud' => '云端记录',
            'mixed' => '混合存储',
            default => '本地目录',
        };
    }

    private static function storageModeTag(string $mode): string
    {
        return match ($mode) {
            'cloud' => 'warning',
            'mixed' => 'primary',
            default => 'success',
        };
    }

    private static function storageTypeLabel(string $type): string
    {
        return match ($type) {
            'aliyun' => '阿里云',
            'qiniu' => '七牛云',
            default => '本地',
        };
    }

    private static function storageTypeTag(string $type): string
    {
        return match ($type) {
            'aliyun' => 'warning',
            'qiniu' => 'info',
            default => 'success',
        };
    }

    private static function sourceStatusLabel(string $status): string
    {
        return match ($status) {
            'orphan_disk' => '仅磁盘存在',
            'missing_local' => '仅索引存在',
            'cloud_record' => '云端记录',
            default => '索引匹配',
        };
    }

    private static function sourceStatusType(string $status): string
    {
        return match ($status) {
            'orphan_disk' => 'warning',
            'missing_local' => 'danger',
            'cloud_record' => 'info',
            default => 'success',
        };
    }

    private static function pathLabel(string $path): string
    {
        $normalized = trim($path);
        return match ($normalized) {
            'images' => '系统图片目录',
            'news' => '公告图片目录',
            'plugins' => '插件素材目录',
            'qrcode' => '二维码目录',
            'pay_qr' => '支付二维码目录',
            'merchant_assets' => '商户素材目录',
            default => $normalized,
        };
    }

    private static function bytesLabel(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $index = 0;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        $precision = $value >= 100 || $index === 0 ? 0 : 2;

        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') . ' ' . $units[$index];
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
