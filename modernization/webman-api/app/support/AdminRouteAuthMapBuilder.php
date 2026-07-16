<?php

declare(strict_types=1);

namespace app\support;

use support\Db;

class AdminRouteAuthMapBuilder
{
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $permissionCache = [];

    /**
     * @var array<int, array<string, array<int, array<string, string>>>>
     */
    private array $routeAuthMapCache = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function permissions(int $adminId): array
    {
        if (isset($this->permissionCache[$adminId])) {
            return $this->permissionCache[$adminId];
        }

        $query = Db::table('admin_permission')
            ->select('id', 'pid', 'title', 'href', 'icon', 'sort', 'status')
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id');

        if ($adminId !== 1) {
            $rolePermissionIds = Db::table('admin_role_permission')
                ->join('admin_admin_role', 'admin_role_permission.role_id', '=', 'admin_admin_role.role_id')
                ->where('admin_admin_role.admin_id', $adminId)
                ->pluck('admin_role_permission.permission_id')
                ->toArray();

            $directPermissionIds = Db::table('admin_admin_permission')
                ->where('admin_id', $adminId)
                ->pluck('permission_id')
                ->toArray();

            $permissionIds = array_values(array_unique(array_merge($rolePermissionIds, $directPermissionIds)));
            if ($permissionIds === []) {
                return $this->permissionCache[$adminId] = [];
            }

            $query->whereIn('id', $permissionIds);
        }

        return $this->permissionCache[$adminId] = array_map(
            static fn ($row): array => (array) $row,
            $query->get()->toArray()
        );
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function build(int $adminId): array
    {
        if (isset($this->routeAuthMapCache[$adminId])) {
            return $this->routeAuthMapCache[$adminId];
        }

        $map = [];

        foreach ($this->permissions($adminId) as $permission) {
            $permissionPath = trim((string) ($permission['href'] ?? ''));
            $description = AdminPermissionMigrationMapper::describe($permission);
            $routeName = trim((string) ($description['modern_route_name'] ?? ''));
            $action = trim((string) ($description['legacy_action'] ?? ''));
            $authMark = $this->normalizeRouteAuthMark($routeName, $action);
            $status = trim((string) ($description['migration_status'] ?? ''));

            if (trim($permissionPath, '/') === 'admin.admin/removeLog') {
                $routeName = 'SystemAdminLogs';
                $authMark = 'removeLog';
                $status = 'write_enabled';
            }

            if ($routeName === '' || $authMark === '' || $status !== 'write_enabled') {
                continue;
            }

            $map[$routeName][$authMark] = [
                'title' => $this->normalizeUiText((string) ($permission['title'] ?? $authMark), $authMark),
                'authMark' => $authMark,
            ];
        }

        // Some legacy installs never seeded dedicated VIP status/sort nodes.
        // When edit access already exists, backfill the migrated write marks
        // so the new maintenance entry points stay visible in the admin UI.
        $this->appendRouteWriteCompatibility($map, 'SystemVips', 'edit', [
            'status' => '状态切换',
            'sort' => '排序维护',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemUser', 'remove', [
            'batchRemove' => '批量删除',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemUser', 'edit', [
            'adminLogin' => '商户代登',
        ]);

        $this->appendRouteWriteCompatibility($map, 'PaymentMethods', 'edit', [
            'add' => '新增支付方式',
            'status' => '状态切换',
            'remove' => '删除支付方式',
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        $this->appendRouteWriteCompatibility($map, 'PaymentAccounts', 'edit', [
            'add' => '新增收款账号',
            'status' => '在线状态',
            'is_status' => '启用状态',
            'remove' => '删除收款账号',
            'batchRemove' => '批量删除',
        ]);

        $this->appendDerivedRouteWriteCompatibility($map, 'PaymentPools', 'PaymentAccounts', 'edit', [
            'index' => '轮询池',
            'add' => '新增轮询池',
            'edit' => '编辑轮询池',
            'status' => '状态切换',
            'remove' => '删除轮询池',
        ]);

        $this->appendDerivedRouteWriteCompatibility($map, 'PaymentPools', 'PaymentAccounts', 'index', [
            'index' => '轮询池',
            'add' => '新增轮询池',
            'edit' => '编辑轮询池',
            'status' => '状态切换',
            'remove' => '删除轮询池',
        ]);

        $this->appendRouteWriteCompatibility($map, 'PaymentPlugins', 'index', [
            'add' => '新增支付插件',
            'edit' => '编辑支付插件',
            'status' => '切换插件状态',
            'remove' => '删除支付插件',
            'batchRemove' => '批量删除支付插件',
            'recycle' => '恢复插件记录',
            'scaffold' => '创建插件脚手架',
            'install' => '安装支付插件',
            'repair' => '修复支付插件',
            'upgrade' => '升级支付插件',
            'enable' => '启用支付插件',
            'disable' => '停用支付插件',
            'saveConfig' => '保存插件配置',
            'createSnapshot' => '创建恢复快照',
            'restoreSnapshot' => '恢复插件快照',
            'deleteSnapshot' => '删除插件快照',
            'uninstall' => '卸载支付插件',
            'cleanupSafe' => '执行安全清理',
            'cleanupPurge' => '执行彻底清理',
            'cleanupRegistryResidue' => '清理注册残留',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemConfigOverview', 'index', [
            'update' => '配置更新',
            'groupUpdate' => '分组更新',
        ]);

        $this->appendDerivedRouteWriteCompatibility($map, 'SystemProcesses', 'SystemConfigOverview', 'index', [
            'index' => '进程管理',
            'pauseMonitor' => '暂停监控',
            'resumeMonitor' => '恢复监控',
            'cleanupSupervisors' => '清理重复进程',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemCleanupAudit', 'index', [
            'execute' => '执行清理',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemDomains', 'index', [
            'add' => '新增域名',
            'edit' => '编辑域名',
            'status' => '域名审核',
            'remove' => '删除域名',
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemQuickLogins', 'index', [
            'add' => '新增快捷登录',
            'edit' => '编辑快捷登录',
            'status' => '状态切换',
            'remove' => '删除快捷登录',
            'batchRemove' => '批量删除',
        ]);

        $this->appendRouteWriteCompatibility($map, 'FinanceCdks', 'index', [
            'add' => '新增卡券',
            'remove' => '删除卡券',
            'batchRemove' => '批量删除',
        ]);

        $this->appendRouteWriteCompatibility($map, 'FinanceMoneyLogs', 'index', [
            'add' => '手工余额调整',
        ]);

        $this->appendRouteWriteCompatibility($map, 'TicketList', 'index', [
            'reply' => '回复工单',
            'status' => '工单状态',
            'remove' => '删除工单',
            'batchRemove' => '批量删除',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemFrontLogs', 'remove', [
            'batchRemove' => '批量删除',
        ]);

        $this->appendRouteWriteCompatibility($map, 'TicketCategories', 'edit', [
            'status' => '状态切换',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemVips', 'remove', [
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemAdmins', 'remove', [
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemRole', 'edit', [
            'permission' => '角色权限分配',
        ]);

        $this->appendRouteWriteCompatibility($map, 'SystemMenu', 'edit', [
            'add' => '新增菜单节点',
            'sort' => '同级排序',
            'status' => '状态切换',
            'remove' => '删除菜单节点',
        ]);

        $this->appendRouteWriteCompatibility($map, 'ContentNavs', 'edit', [
            'status' => '状态切换',
            'target' => '打开方式',
            'sort' => '拖拽排序',
        ]);

        $this->appendRouteWriteCompatibility($map, 'ContentNavs', 'remove', [
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        $this->appendRouteWriteCompatibility($map, 'ContentNews', 'edit', [
            'status' => '状态切换',
        ]);

        $this->appendRouteWriteCompatibility($map, 'ContentNews', 'remove', [
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        $this->appendRouteWriteCompatibility($map, 'ContentPluginDownloads', 'edit', [
            'status' => '状态切换',
        ]);

        $this->appendRouteWriteCompatibility($map, 'ContentPluginDownloads', 'remove', [
            'batchRemove' => '批量删除',
            'recycle' => '回收站',
        ]);

        if ($this->shouldApplySuperAdminFullFallback($adminId)) {
            $this->applySuperAdminFullFallback($map);
        }

        foreach ($map as $routeName => $authList) {
            $map[$routeName] = array_values($authList);
        }

        return $this->routeAuthMapCache[$adminId] = $map;
    }

    public function hasAuthMark(int $adminId, string $routeName, string $authMark): bool
    {
        foreach ($this->build($adminId)[$routeName] ?? [] as $item) {
            if (($item['authMark'] ?? '') === $authMark) {
                return true;
            }
        }

        return false;
    }

    public function routeHasAnyAuthMarks(string $routeName): bool
    {
        return ($this->build(1)[$routeName] ?? []) !== [];
    }

    /**
     * @param array<string, array<string, array<string, string>>> $map
     * @param array<string, string> $fallbackActions
     */
    private function appendRouteWriteCompatibility(
        array &$map,
        string $routeName,
        string $guardAction,
        array $fallbackActions
    ): void {
        $routeActions = $map[$routeName] ?? [];
        if ($routeActions === [] || !isset($routeActions[$guardAction])) {
            return;
        }

        foreach ($fallbackActions as $action => $title) {
            if (isset($routeActions[$action])) {
                continue;
            }

            $routeActions[$action] = [
                'title' => $this->normalizeUiText($title, $action),
                'authMark' => $action,
            ];
        }

        $map[$routeName] = $routeActions;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $map
     * @param array<string, string> $fallbackActions
     */
    private function appendSuperAdminWriteFallback(
        array &$map,
        string $routeName,
        array $fallbackActions
    ): void {
        $routeActions = $map[$routeName] ?? [];

        foreach ($fallbackActions as $action => $title) {
            if (isset($routeActions[$action])) {
                continue;
            }

            $routeActions[$action] = [
                'title' => $this->normalizeUiText($title, $action),
                'authMark' => $action,
            ];
        }

        $map[$routeName] = $routeActions;
    }

    private function shouldApplySuperAdminFullFallback(int $adminId): bool
    {
        if ($adminId !== 1) {
            return false;
        }

        if ($this->permissions($adminId) !== []) {
            return false;
        }

        return (int) Db::table('admin_permission')->where('status', 1)->count() === 0;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $map
     */
    private function applySuperAdminFullFallback(array &$map): void
    {
        $fallbacks = [
            'SystemAdmins' => [
                'add' => '新增管理员',
                'edit' => '编辑管理员',
                'status' => '状态切换',
                'remove' => '删除管理员',
                'batchRemove' => '批量删除',
                'role' => '角色分配',
                'permission' => '权限分配',
                'recycle' => '回收站',
                'removeLog' => '删除日志',
            ],
            'SystemUser' => [
                'index' => '商户管理',
                'add' => '新增商户',
                'edit' => '编辑商户',
                'remove' => '删除商户',
                'batchRemove' => '批量删除',
                'email' => '邮箱通知',
                'adminLogin' => '后台代登',
            ],
            'SystemVips' => [
                'index' => '会员套餐',
                'add' => '新增套餐',
                'edit' => '编辑套餐',
                'status' => '状态切换',
                'sort' => '排序更新',
                'remove' => '删除套餐',
                'batchRemove' => '批量删除',
                'recycle' => '回收站',
            ],
            'SystemRole' => [
                'add' => '新增角色',
                'edit' => '编辑角色',
                'remove' => '删除角色',
                'permission' => '角色权限分配',
            ],
            'SystemMenu' => [
                'add' => '新增菜单',
                'edit' => '编辑菜单',
                'sort' => '排序更新',
                'status' => '状态切换',
                'remove' => '删除菜单',
            ],
            'SystemConfigOverview' => [
                'index' => '配置总览',
                'update' => '配置更新',
                'groupUpdate' => '分组更新',
            ],
            'SystemProcesses' => [
                'index' => '进程管理',
                'pauseMonitor' => '暂停监控',
                'resumeMonitor' => '恢复监控',
                'cleanupSupervisors' => '清理重复进程',
            ],
            'SystemAdminLogs' => [
                'removeLog' => '删除日志',
            ],
            'SystemFrontLogs' => [
                'index' => '商户日志',
                'remove' => '删除日志',
                'batchRemove' => '批量删除',
            ],
            'SystemDomains' => [
                'index' => '域名管理',
                'add' => '新增域名',
                'edit' => '编辑域名',
                'status' => '域名状态',
                'remove' => '删除域名',
                'batchRemove' => '批量删除',
                'recycle' => '回收站',
            ],
            'SystemCleanupAudit' => [
                'index' => '数据清理',
                'execute' => '执行清理',
            ],
            'SystemMediaLibrary' => [
                'index' => '素材库',
                'list' => '素材列表',
                'add' => '新增目录',
                'del' => '删除目录',
                'addPhoto' => '添加图片',
                'addPhotos' => '批量上传',
                'remove' => '删除素材',
                'batchRemove' => '批量删除',
            ],
            'SystemQuickLogins' => [
                'index' => '快捷登录',
                'add' => '新增快捷登录',
                'edit' => '编辑快捷登录',
                'status' => '状态切换',
                'remove' => '删除快捷登录',
                'batchRemove' => '批量删除',
            ],
            'PaymentPlugins' => [
                'index' => '支付插件',
                'add' => '新增支付插件',
                'edit' => '编辑支付插件',
                'status' => '切换插件状态',
                'remove' => '删除支付插件',
                'batchRemove' => '批量删除支付插件',
                'recycle' => '恢复插件记录',
                'scaffold' => '创建插件脚手架',
                'install' => '安装支付插件',
                'repair' => '修复支付插件',
                'upgrade' => '升级支付插件',
                'enable' => '启用支付插件',
                'disable' => '停用支付插件',
                'saveConfig' => '保存插件配置',
                'createSnapshot' => '创建恢复快照',
                'restoreSnapshot' => '恢复插件快照',
                'deleteSnapshot' => '删除插件快照',
                'uninstall' => '卸载支付插件',
                'cleanupSafe' => '安全清理',
                'cleanupPurge' => '彻底清理',
                'cleanupRegistryResidue' => '清理注册残留',
            ],
            'PaymentMethods' => [
                'index' => '支付方式',
                'add' => '新增支付方式',
                'edit' => '编辑支付方式',
                'status' => '状态切换',
                'remove' => '删除支付方式',
                'batchRemove' => '批量删除',
                'recycle' => '回收站',
            ],
            'PaymentAccounts' => [
                'index' => '收款账号',
                'add' => '新增收款账号',
                'edit' => '编辑收款账号',
                'status' => '在线状态',
                'is_status' => '启用状态',
                'remove' => '删除收款账号',
                'batchRemove' => '批量删除',
            ],
            'PaymentPools' => [
                'index' => '轮询池',
                'add' => '新增轮询池',
                'edit' => '编辑轮询池',
                'status' => '状态切换',
                'remove' => '删除轮询池',
            ],
            'FinanceMoneyLogs' => [
                'add' => '手工余额调整',
            ],
            'FinanceCdks' => [
                'index' => '卡券管理',
                'add' => '新增卡券',
                'remove' => '删除卡券',
                'batchRemove' => '批量删除',
            ],
            'RiskRecords' => [
                'index' => '风控记录',
                'add' => '新增风控',
                'edit' => '编辑风控',
                'remove' => '删除风控',
                'batchRemove' => '批量删除',
            ],
            'TicketList' => [
                'index' => '工单列表',
                'reply' => '回复工单',
                'status' => '工单状态',
                'remove' => '删除工单',
                'batchRemove' => '批量删除',
            ],
            'TicketCategories' => [
                'index' => '工单分类',
                'add' => '新增分类',
                'edit' => '编辑分类',
                'status' => '状态切换',
                'remove' => '删除分类',
                'batchRemove' => '批量删除',
            ],
            'ContentNavs' => [
                'index' => '导航管理',
                'add' => '新增导航',
                'edit' => '编辑导航',
                'status' => '状态切换',
                'target' => '打开方式',
                'sort' => '排序更新',
                'remove' => '删除导航',
                'batchRemove' => '批量删除',
                'recycle' => '回收站',
            ],
            'ContentNews' => [
                'index' => '公告管理',
                'add' => '新增公告',
                'edit' => '编辑公告',
                'status' => '状态切换',
                'remove' => '删除公告',
                'batchRemove' => '批量删除',
                'recycle' => '回收站',
            ],
            'ContentPluginDownloads' => [
                'index' => '插件下载',
                'add' => '新增下载',
                'edit' => '编辑下载',
                'status' => '状态切换',
                'remove' => '删除下载',
                'batchRemove' => '批量删除',
                'recycle' => '回收站',
            ],
        ];

        foreach ($fallbacks as $routeName => $actions) {
            $this->appendSuperAdminWriteFallback($map, $routeName, $actions);
        }
    }

    private function normalizeRouteAuthMark(string $routeName, string $action): string
    {
        if ($routeName === 'FinanceCdks' && $action === 'cdk') {
            return 'index';
        }

        if ($routeName === 'FinanceMoneyLogs' && $action === 'plus') {
            return 'index';
        }

        if ($routeName === 'SystemCleanupAudit' && $action === 'clear') {
            return 'index';
        }

        if ($routeName === 'SystemMediaLibrary' && $action === 'list') {
            return 'index';
        }

        if ($routeName === 'TicketList' && $action === 'ticket') {
            return 'index';
        }

        return $action;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $map
     * @param array<string, string> $targetActions
     */
    private function appendDerivedRouteWriteCompatibility(
        array &$map,
        string $targetRouteName,
        string $sourceRouteName,
        string $sourceGuardAction,
        array $targetActions
    ): void {
        $sourceActions = $map[$sourceRouteName] ?? [];
        if ($sourceActions === [] || !isset($sourceActions[$sourceGuardAction])) {
            return;
        }

        $routeActions = $map[$targetRouteName] ?? [];
        foreach ($targetActions as $action => $title) {
            if (isset($routeActions[$action])) {
                continue;
            }

            $routeActions[$action] = [
                'title' => $this->normalizeUiText($title, $action),
                'authMark' => $action,
            ];
        }

        $map[$targetRouteName] = $routeActions;
    }

    private function normalizeUiText(string $text, string $fallback = ''): string
    {
        $text = trim($text);
        if ($text === '') {
            return $fallback;
        }

        $repaired = @mb_convert_encoding($text, 'GB18030', 'UTF-8');
        if (is_string($repaired) && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8')) {
            if ($this->mojibakeScore($repaired) < $this->mojibakeScore($text)) {
                $text = $repaired;
            }
        }

        $text = str_replace("\u{FFFD}", '', $text);
        $text = preg_replace('/\?+(?=$|[\s，。；：、】【》\]])/u', '', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return $fallback;
        }

        if ($fallback !== '' && $this->looksLikeMojibake($text)) {
            return $fallback;
        }

        return $text;
    }

    private function looksLikeMojibake(string $text): bool
    {
        return $this->mojibakeScore($text) > 0;
    }

    private function mojibakeScore(string $text): int
    {
        preg_match_all(
            '/(?:\x{FFFD}|\x{20AC}|\x{9369}|\x{95AB}|\x{9352}|\x{95B0}|\x{7487}|\x{5BF0}|\x{7F01}|\x{95C8}|\x{7EEF}|\x{7EFE}|\x{7F03}|\x{935F}|\x{93C0}|\x{95C2}|\x{9427}|\x{7039}|\x{7490}|\x{93B4}|\x{95AD}|\x{8930}|\x{93BA}|\x{59AF}|\x{9365}|\x{9366}|\x{9368}|\x{942D}|\x{9475}|\x{74A7}|\x{93C2}|\x{935A}|\x{7ED4}|\x{935B}|\x{7EE0}|\x{9286}|\x{581D})/u',
            $text,
            $matches
        );

        return count($matches[0]);
    }
}
