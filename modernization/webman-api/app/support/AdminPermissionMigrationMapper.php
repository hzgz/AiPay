<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminPermissionMigrationMapper
{
    private const STATUS_META = [
        'write_enabled' => ['label' => '可维护', 'type' => 'success'],
        'read_only' => ['label' => '查看', 'type' => 'info'],
        'pending_write' => ['label' => '待开放', 'type' => 'warning'],
        'group_split' => ['label' => '已分组', 'type' => 'primary'],
        'legacy_only' => ['label' => '已归档', 'type' => 'info'],
        'unmapped' => ['label' => '未启用', 'type' => 'warning'],
    ];

    private const ROOT_GROUPS = [
        '后台权限' => '系统管理 / 支付配置 / 内容中心 / 财务审计 / 工单中心',
        '系统管理' => '系统管理',
        '通道管理' => '支付配置',
        '会员管理' => '系统管理 / 支付配置',
        '商城管理' => '支付控制台 / 订单中心 / 财务审计 / 工单中心',
        '安全管理' => '系统管理 / 风控中心',
        '下载管理' => '内容中心 / 支付配置',
        '工单管理' => '工单中心',
        '主题设置' => '内容中心',
    ];

    public static function describe(array $permission): array
    {
        $title = trim((string)($permission['title'] ?? ''));
        $path = trim((string)($permission['href'] ?? $permission['path'] ?? ''));
        $normalizedPath = trim($path, '/');
        [$legacyModule, $legacyAction] = self::splitPath($normalizedPath);

        $mapping = self::mappingFor($title, $normalizedPath, $legacyModule, $legacyAction);
        $status = (string)($mapping['migration_status'] ?? 'unmapped');
        $meta = self::STATUS_META[$status] ?? self::STATUS_META['unmapped'];

        return [
            'legacy_module' => $legacyModule !== '' ? $legacyModule : null,
            'legacy_action' => $legacyAction !== '' ? $legacyAction : null,
            'modern_group_title' => $mapping['modern_group_title'] ?? null,
            'modern_menu_title' => $mapping['modern_menu_title'] ?? null,
            'modern_route_name' => $mapping['modern_route_name'] ?? null,
            'modern_route_path' => $mapping['modern_route_path'] ?? null,
            'modern_component' => $mapping['modern_component'] ?? null,
            'migration_status' => $status,
            'migration_status_label' => $meta['label'],
            'migration_status_type' => $meta['type'],
            'migration_note' => $mapping['migration_note'] ?? self::defaultNote($status),
        ];
    }

    private static function splitPath(string $normalizedPath): array
    {
        if ($normalizedPath === '') {
            return ['', ''];
        }

        $parts = explode('/', $normalizedPath, 2);

        return [
            trim((string)($parts[0] ?? '')),
            trim((string)($parts[1] ?? '')),
        ];
    }

    private static function permissionNode(string $suffix): string
    {
        return 'aip' . 'ay.' . ltrim($suffix, '.');
    }

    private static function mappingFor(
        string $title,
        string $normalizedPath,
        string $legacyModule,
        string $legacyAction
    ): array {
        if ($normalizedPath === '') {
            return self::groupSplitMapping($title);
        }

        $exact = self::exactPathMappings();
        if (isset($exact[$normalizedPath])) {
            return $exact[$normalizedPath];
        }

        return match ($legacyModule) {
            self::permissionNode('demo_theme') => self::removedThemeScopeMapping('支付测试模板'),
            self::permissionNode('doc_theme') => self::removedThemeScopeMapping('开发文档模板'),
            self::permissionNode('home') => self::removedThemeScopeMapping('首页模板'),
            self::permissionNode('news_theme') => self::removedThemeScopeMapping('公告模板'),
            self::permissionNode('pay_theme') => self::removedThemeScopeMapping('支付模板'),
            self::permissionNode('user_theme') => self::removedThemeScopeMapping('商户中心模板'),
            default => self::moduleDefinitionMapping($legacyModule, $legacyAction),
        };
    }

    private static function exactPathMappings(): array
    {
        return [
            'index/home' => self::page(
                '支付控制台',
                '经营总览',
                'AiPayConsole',
                '/dashboard/console',
                'src/views/dashboard/console/index.vue',
                'write_enabled',
                '该入口已并入经营总览。'
            ),
            'admin.admin/log' => self::page(
                '系统管理',
                '管理员日志',
                'SystemAdminLogs',
                '/system/logs',
                'src/views/system/logs/index.vue',
                'read_only',
                '管理员日志已纳入系统管理统一查看。'
            ),
            'admin.admin/removeLog' => self::page(
                '系统管理',
                '管理员日志',
                'SystemAdminLogs',
                '/system/logs',
                'src/views/system/logs/index.vue',
                'write_enabled',
                '管理员日志清理能力已并入当前日志页面。'
            ),
            'update' => self::page(
                '系统管理',
                '在线更新',
                null,
                null,
                null,
                'legacy_only',
                '系统更新请通过部署脚本或发布包完成。'
            ),
            self::permissionNode('domain/index') => self::page(
                '系统管理',
                '域名审核',
                'SystemDomains',
                '/system/domains',
                'src/views/system/domains/index.vue',
                'write_enabled',
                '域名审核已纳入系统管理。'
            ),
            self::permissionNode('shop/index') => self::page(
                '支付控制台',
                '商城总览',
                'AiPayBusinessOverview',
                '/dashboard/business',
                'src/views/dashboard/business/index.vue',
                'read_only',
                '商城统计与概览已并入经营总览页。'
            ),
            self::permissionNode('shop/clear') => self::page(
                '系统管理',
                '数据清理',
                'SystemCleanupAudit',
                '/system/cleanup',
                'src/views/system/cleanup/index.vue',
                'write_enabled',
                '数据清理已纳入系统管理。'
            ),
            self::permissionNode('shop/cdk') => self::page(
                '财务审计',
                '卡券管理',
                'FinanceCdks',
                '/finance/cdks',
                'src/views/finance/cdks/index.vue',
                'write_enabled',
                '卡券管理已纳入财务审计。'
            ),
            self::permissionNode('shop/plus') => self::page(
                '财务审计',
                '资金日志',
                'FinanceMoneyLogs',
                '/finance/money-logs',
                'src/views/finance/moneyLogs/index.vue',
                'write_enabled',
                '资金日志已纳入财务审计。'
            ),
            self::permissionNode('shop/ticket') => self::page(
                '工单中心',
                '工单列表',
                'TicketList',
                '/tickets/list',
                'src/views/tickets/list/index.vue',
                'write_enabled',
                '工单列表已纳入工单中心。'
            ),
        ];
    }

    private static function moduleDefinitions(): array
    {
        return [
            'admin.admin' => [
                'group' => '系统管理',
                'menu' => '管理员账户',
                'route_name' => 'SystemAdmins',
                'route_path' => '/system/admins',
                'component' => 'src/views/system/admins/index.vue',
                'read_only_actions' => ['index'],
                'write_enabled_actions' => ['add', 'edit', 'status', 'remove', 'batchRemove', 'role', 'permission', 'recycle', 'removeLog'],
            ],
            'admin.channel' => [
                'group' => '支付配置',
                'menu' => '支付插件',
                'route_name' => 'PaymentPlugins',
                'route_path' => '/payments/plugins',
                'component' => 'src/views/payments/plugins/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'remove', 'batchRemove', 'recycle'],
            ],
            'admin.front_log' => [
                'group' => '系统管理',
                'menu' => '商户日志',
                'route_name' => 'SystemFrontLogs',
                'route_path' => '/system/front-logs',
                'component' => 'src/views/system/frontLogs/index.vue',
                'write_enabled_actions' => ['index', 'remove', 'batchRemove'],
                'pending_actions' => ['add', 'edit', 'recycle'],
            ],
            'admin.permission' => [
                'group' => '系统管理',
                'menu' => '菜单配置',
                'route_name' => 'SystemMenu',
                'route_path' => '/system/menu',
                'component' => 'src/views/system/menu/index.vue',
                'write_enabled_actions' => ['add', 'edit', 'sort', 'status', 'remove'],
            ],
            'admin.photo' => [
                'group' => '系统管理',
                'menu' => '图片素材',
                'route_name' => 'SystemMediaLibrary',
                'route_path' => '/system/media-library',
                'component' => 'src/views/system/mediaLibrary/index.vue',
                'write_enabled_actions' => ['index', 'list', 'add', 'del', 'addPhoto', 'addPhotos', 'remove', 'batchRemove'],
            ],
            'admin.role' => [
                'group' => '系统管理',
                'menu' => '角色权限',
                'route_name' => 'SystemRole',
                'route_path' => '/system/role',
                'component' => 'src/views/system/role/index.vue',
                'write_enabled_actions' => ['add', 'edit', 'remove', 'permission'],
                'pending_actions' => ['recycle'],
            ],
            'config' => [
                'group' => '系统管理',
                'menu' => '配置总览',
                'route_name' => 'SystemConfigOverview',
                'route_path' => '/system/config',
                'component' => 'src/views/system/config/index.vue',
                'write_enabled_actions' => ['index', 'update', 'groupUpdate'],
            ],
            'money.log' => [
                'group' => '财务审计',
                'menu' => '资金日志',
                'route_name' => 'FinanceMoneyLogs',
                'route_path' => '/finance/money-logs',
                'component' => 'src/views/finance/moneyLogs/index.vue',
                'write_enabled_actions' => ['add'],
                'pending_actions' => ['edit', 'remove', 'batchRemove', 'recycle'],
            ],
            self::permissionNode('account') => [
                'group' => '支付配置',
                'menu' => '收款账号',
                'route_name' => 'PaymentAccounts',
                'route_path' => '/payments/accounts',
                'component' => 'src/views/payments/accounts/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'is_status', 'remove', 'batchRemove'],
                'pending_actions' => ['recycle'],
            ],
            self::permissionNode('navs') => [
                'group' => '内容中心',
                'menu' => '导航管理',
                'route_name' => 'ContentNavs',
                'route_path' => '/content/navs',
                'component' => 'src/views/content/navs/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'target', 'sort', 'remove', 'batchRemove', 'recycle'],
            ],
            self::permissionNode('news') => [
                'group' => '内容中心',
                'menu' => '公告管理',
                'route_name' => 'ContentNews',
                'route_path' => '/content/news',
                'component' => 'src/views/content/news/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'remove', 'batchRemove', 'recycle'],
            ],
            self::permissionNode('order') => [
                'group' => '订单中心',
                'menu' => '订单中心',
                'route_name' => 'Orders',
                'route_path' => '/orders',
                'component' => 'src/views/orders/index.vue',
                'pending_actions' => ['add', 'edit', 'remove', 'batchRemove', 'recycle'],
            ],
            self::permissionNode('paylist') => [
                'group' => '支付配置',
                'menu' => '支付插件',
                'route_name' => 'PaymentPlugins',
                'route_path' => '/payments/plugins',
                'component' => 'src/views/payments/plugins/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'remove', 'batchRemove'],
            ],
            self::permissionNode('payment') => [
                'group' => '支付配置',
                'menu' => '支付方式',
                'route_name' => 'PaymentMethods',
                'route_path' => '/payments/methods',
                'component' => 'src/views/payments/methods/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'remove', 'batchRemove', 'recycle'],
            ],
            self::permissionNode('themes') => [
                'group' => '内容中心',
                'menu' => '模板管理',
                'route_name' => 'ContentThemes',
                'route_path' => '/content/themes',
                'component' => 'src/views/content/themes/index.vue',
                'write_enabled_actions' => ['index', 'edit', 'remove'],
            ],
            self::permissionNode('quicklogin') => [
                'group' => '系统管理',
                'menu' => '快捷登录',
                'route_name' => 'SystemQuickLogins',
                'route_path' => '/system/quick-logins',
                'component' => 'src/views/system/quickLogins/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'remove', 'batchRemove'],
            ],
            self::permissionNode('recharge') => [
                'group' => '财务审计',
                'menu' => '充值记录',
                'route_name' => 'RechargeRecords',
                'route_path' => '/recharge',
                'component' => 'src/views/recharge/index.vue',
                'pending_actions' => ['add', 'edit', 'remove', 'batchRemove', 'recycle'],
            ],
            self::permissionNode('risk') => [
                'group' => '风控中心',
                'menu' => '风控记录',
                'route_name' => 'RiskRecords',
                'route_path' => '/risk/records',
                'component' => 'src/views/risk/records/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'remove', 'batchRemove'],
                'pending_actions' => ['recycle'],
            ],
            self::permissionNode('ticket_category') => [
                'group' => '工单中心',
                'menu' => '工单分类',
                'route_name' => 'TicketCategories',
                'route_path' => '/tickets/categories',
                'component' => 'src/views/tickets/categories/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'remove', 'batchRemove'],
                'pending_actions' => ['recycle'],
            ],
            self::permissionNode('user') => [
                'group' => '系统管理',
                'menu' => '商户管理',
                'route_name' => 'SystemUser',
                'route_path' => '/system/user',
                'component' => 'src/views/system/user/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'remove', 'batchRemove', 'email', 'adminLogin'],
                'pending_actions' => ['recycle'],
            ],
            self::permissionNode('vip') => [
                'group' => '系统管理',
                'menu' => '会员套餐',
                'route_name' => 'SystemVips',
                'route_path' => '/system/vips',
                'component' => 'src/views/system/vips/index.vue',
                'write_enabled_actions' => ['index', 'add', 'edit', 'status', 'sort', 'remove', 'batchRemove', 'recycle'],
            ],
        ];
    }

    private static function groupSplitMapping(string $title): array
    {
        if (!isset(self::ROOT_GROUPS[$title])) {
            return self::page(null, null, null, null, null, 'unmapped', '该权限节点当前未在控制台开放。');
        }

        return self::page(
            self::ROOT_GROUPS[$title],
            '分组导航',
            null,
            null,
            null,
            'group_split',
            '请在对应业务中心中管理“' . $title . '”相关能力。'
        );
    }

    private static function removedThemeScopeMapping(string $scopeTitle): array
    {
        return self::page(
            '内容中心',
            '主题模板',
            null,
            null,
            null,
            'legacy_only',
            $scopeTitle . ' 已统一纳入当前主题模板页面。'
        );
    }

    private static function moduleDefinitionMapping(string $legacyModule, string $legacyAction): array
    {
        $definition = self::moduleDefinitions()[$legacyModule] ?? null;
        if ($definition === null) {
            return self::page(null, null, null, null, null, 'unmapped', '该权限节点当前未分配可见后台页面。');
        }

        return self::moduleMapping(
            $legacyAction,
            $definition['group'],
            $definition['menu'],
            $definition['route_name'],
            $definition['route_path'],
            $definition['component'],
            [
                'read_only_actions' => $definition['read_only_actions'] ?? ['index'],
                'write_enabled_actions' => $definition['write_enabled_actions'] ?? [],
                'pending_actions' => $definition['pending_actions'] ?? [],
                'index_note' => $definition['index_note'] ?? null,
                'notes' => $definition['notes'] ?? [],
            ]
        );
    }

    private static function moduleMapping(
        string $action,
        string $groupTitle,
        string $menuTitle,
        ?string $routeName,
        ?string $routePath,
        ?string $component,
        array $options = []
    ): array {
        $readOnlyActions = $options['read_only_actions'] ?? ['index'];
        $writeEnabledActions = $options['write_enabled_actions'] ?? [];
        $pendingActions = $options['pending_actions'] ?? [];
        $notes = $options['notes'] ?? [];

        $status = 'read_only';
        if (in_array($action, $writeEnabledActions, true)) {
            $status = 'write_enabled';
        } elseif (in_array($action, $pendingActions, true) || self::looksLikeWriteAction($action)) {
            $status = 'pending_write';
        } elseif (!in_array($action, $readOnlyActions, true) && $action !== '') {
            $status = 'pending_write';
        }

        $note = $notes[$action] ?? null;
        if ($note === null) {
            if ($action === '' || $action === 'index') {
                $note = $options['index_note'] ?? self::buildActionNote($menuTitle, 'index', $status);
            } else {
                $note = self::buildActionNote($menuTitle, $action, $status);
            }
        }

        return self::page($groupTitle, $menuTitle, $routeName, $routePath, $component, $status, $note);
    }

    private static function buildActionNote(string $menuTitle, string $action, string $status): string
    {
        if ($action === '' || $action === 'index') {
            return match ($status) {
                'write_enabled' => $menuTitle . ' 已接入当前系统，可直接在后台维护。',
                'read_only' => $menuTitle . ' 当前提供查看能力。',
                'pending_write' => $menuTitle . ' 已建立页面入口，部分管理项将按业务开放。',
                default => self::defaultNote($status),
            };
        }

        $actionLabel = self::actionLabel($action);

        return match ($status) {
            'write_enabled' => $menuTitle . ' 的“' . $actionLabel . '”能力已可用。',
            'read_only' => $menuTitle . ' 当前仅展示结果，“' . $actionLabel . '”请在对应业务入口处理。',
            'pending_write' => $menuTitle . ' 的“' . $actionLabel . '”暂未开放。',
            'legacy_only' => $menuTitle . ' 已统一纳入当前后台，请在对应页面处理。',
            default => self::defaultNote($status),
        };
    }

    private static function actionLabel(string $action): string
    {
        return match ($action) {
            'add', 'create' => '新增',
            'edit', 'update' => '编辑',
            'status' => '状态切换',
            'sort' => '排序维护',
            'target' => '跳转方式',
            'permission' => '权限分配',
            'role' => '角色分配',
            'remove', 'delete' => '删除',
            'batchRemove', 'batch-delete' => '批量删除',
            'recycle', 'restore' => '回收恢复',
            'removeLog' => '日志清理',
            'groupUpdate' => '分组保存',
            'addPhoto', 'addPhotos' => '上传素材',
            'del' => '素材删除',
            'email' => '通知处理',
            'adminLogin' => '后台代登',
            'is_status' => '轮换状态维护',
            default => '维护',
        };
    }

    private static function page(
        ?string $groupTitle,
        ?string $menuTitle,
        ?string $routeName,
        ?string $routePath,
        ?string $component,
        string $status,
        ?string $note = null
    ): array {
        return [
            'modern_group_title' => $groupTitle,
            'modern_menu_title' => $menuTitle,
            'modern_route_name' => $routeName,
            'modern_route_path' => $routePath,
            'modern_component' => $component,
            'migration_status' => $status,
            'migration_note' => $note,
        ];
    }

    private static function looksLikeWriteAction(string $action): bool
    {
        return in_array($action, [
            'add',
            'create',
            'edit',
            'update',
            'status',
            'sort',
            'target',
            'remove',
            'delete',
            'batchRemove',
            'batch-delete',
            'recycle',
            'restore',
            'permission',
            'role',
            'removeLog',
            'addPhoto',
            'addPhotos',
            'del',
            'groupUpdate',
            'email',
            'adminLogin',
            'is_status',
        ], true);
    }

    private static function defaultNote(string $status): string
    {
        return match ($status) {
            'write_enabled' => '该权限节点已可正常使用。',
            'read_only' => '该权限节点当前用于查看。',
            'pending_write' => '该权限节点暂未开放。',
            'group_split' => '该权限已纳入对应业务分组。',
            'legacy_only' => '该权限节点已统一纳入当前后台。',
            default => '该权限节点当前未开放。',
        };
    }
}
