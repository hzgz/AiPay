<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\controller;

use app\support\AdminConfigCatalog;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use app\support\SystemConfig;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class ConfigController
{
    public function index(Request $request): Response
    {
        $items = $this->items();
        $groupFilter = trim((string)$request->get('group', ''));
        $keyword = trim((string)$request->get('keyword', ''));
        $groups = AdminConfigCatalog::buildGroups($items, $groupFilter, $keyword);
        $filteredItems = array_values(array_merge(...array_map(
            static fn (array $group): array => (array)($group['items'] ?? []),
            $groups
        )));
        $editableForms = AdminConfigCatalog::buildEditableForms($filteredItems);

        $matchedKeys = array_reduce($groups, static function (int $carry, array $group): int {
            return $carry + (int)($group['item_count'] ?? 0);
        }, 0);
        $editableKeyCount = array_reduce($editableForms, static function (int $carry, array $group): int {
            return $carry + count((array)($group['fields'] ?? []));
        }, 0);
        $editableFilledCount = array_reduce($editableForms, static function (int $carry, array $group): int {
            return $carry + count(array_filter((array)($group['fields'] ?? []), static function (array $field): bool {
                return (bool)($field['filled'] ?? false);
            }));
        }, 0);

        return ApiResponse::success([
            'summary' => [
                'total_keys' => count($items),
                'filled_keys' => count(array_filter($items, static fn (array $item): bool => $item['filled'])),
                'empty_keys' => count(array_filter($items, static fn (array $item): bool => !$item['filled'])),
                'masked_keys' => count(array_filter($items, static fn (array $item): bool => $item['masked'])),
                'matched_keys' => $matchedKeys,
                'group_count' => count($groups),
                'editable_group_count' => count($editableForms),
                'editable_key_count' => $editableKeyCount,
                'editable_filled_count' => $editableFilledCount,
                'generated_at' => date('Y-m-d H:i:s'),
            ],
            'group_options' => AdminConfigCatalog::buildGroupOptions($items),
            'groups' => $groups,
            'filters' => [
                'group' => $groupFilter,
                'keyword' => $keyword,
            ],
            'editable_forms' => $editableForms,
        ]);
    }

    public function update(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'update');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $key = trim((string)($payload['key'] ?? ''));
        if ($key === '') {
            return ApiResponse::error('配置项键名不能为空', 422);
        }

        if (!AdminConfigCatalog::isEditable($key)) {
            return ApiResponse::error('当前配置项暂不支持在线编辑', 422);
        }

        try {
            $value = AdminConfigCatalog::sanitizeEditableValue($key, $payload['value'] ?? '');
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        $this->persistValue($key, $value);

        return ApiResponse::success([
            'item' => $this->findItem($key),
        ], '配置保存成功');
    }

    public function groupUpdate(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'groupUpdate');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $group = trim((string)($payload['group'] ?? ''));
        if ($group === '') {
            return ApiResponse::error('配置分组不能为空', 422);
        }

        $values = $payload['values'] ?? null;
        if (!is_array($values)) {
            return ApiResponse::error('配置内容格式不正确', 422);
        }

        try {
            $sanitized = AdminConfigCatalog::sanitizeEditableGroupValues($group, $values);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        foreach ($sanitized as $key => $value) {
            $this->persistValue($key, $value);
        }

        return ApiResponse::success([
            'group' => $group,
            'items' => $this->findItems(array_keys($sanitized)),
        ], '分类配置保存成功');
    }

    private function items(): array
    {
        return AdminConfigCatalog::buildItems(SystemConfig::all(), $this->databaseConfig());
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'SystemConfigOverview', [$authMark, 'index']);
    }

    private function databaseConfig(): array
    {
        $config = [];
        $rows = Db::table('admin_config')
            ->select('config_name', 'config_value')
            ->get()
            ->toArray();

        foreach ($rows as $row) {
            $item = (array)$row;
            $name = trim((string)($item['config_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $config[$name] = trim((string)($item['config_value'] ?? ''));
        }

        return $config;
    }

    private function findItem(string $key): ?array
    {
        foreach ($this->items() as $item) {
            if (($item['key'] ?? '') !== $key) {
                continue;
            }

            unset($item['raw_value']);
            return $item;
        }

        return null;
    }

    private function findItems(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $lookup = array_fill_keys($keys, true);
        $items = [];
        foreach ($this->items() as $item) {
            $key = (string)($item['key'] ?? '');
            if ($key === '' || !isset($lookup[$key])) {
                continue;
            }

            unset($item['raw_value']);
            $items[] = $item;
        }

        usort($items, static function (array $left, array $right) use ($keys): int {
            return array_search($left['key'], $keys, true) <=> array_search($right['key'], $keys, true);
        });

        return $items;
    }

    private function persistValue(string $key, string $value): void
    {
        $exists = Db::table('admin_config')
            ->where('config_name', $key)
            ->exists();

        if ($exists) {
            Db::table('admin_config')
                ->where('config_name', $key)
                ->update(['config_value' => $value]);

            SystemConfig::clearCache();
            return;
        }

        Db::table('admin_config')->insert([
            'config_name' => $key,
            'config_value' => $value,
        ]);

        SystemConfig::clearCache();
    }
}
