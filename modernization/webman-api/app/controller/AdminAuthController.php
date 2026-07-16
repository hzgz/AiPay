<?php

namespace app\controller;

use app\support\AdminFixtureTextNormalizer;
use app\support\AdminPermissionMigrationMapper;
use app\support\AdminRouteAuthMapBuilder;
use app\support\ApiResponse;
use app\support\DatabaseColumnInspector;
use app\support\LegacyPassword;
use app\support\ProductionSecurity;
use app\support\RequestPayload;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;

class AdminAuthController
{
    public function login(Request $request): Response
    {
        $payload = RequestPayload::all($request);
        $username = trim((string)($payload['username'] ?? ''));
        $password = trim((string)($payload['password'] ?? ''));

        if ($username === '' || $password === '') {
            return ApiResponse::error('请输入管理员账号和密码', 422);
        }

        if (ProductionSecurity::isProductionLike() && ProductionSecurity::isWeakAdminCredentialAttempt($username, $password)) {
            return ApiResponse::error('生产环境不允许使用弱管理员口令，请改用强密码后再登录', 403, null, 403);
        }

        $query = Db::table('admin_admin')
            ->select('id', 'username', 'nickname', 'status')
            ->where('username', $username)
            ->where('password', LegacyPassword::hash($password))
            ->where('status', 1);

        if (DatabaseColumnInspector::hasColumn('admin_admin', 'delete_time')) {
            $query->whereNull('delete_time');
        }

        $admin = $query->first();
        if (!$admin) {
            return ApiResponse::error('管理员账号或密码错误', 401, null, 401);
        }

        $token = bin2hex(random_bytes(24)) . (int)$admin->id;

        Db::table('admin_admin')
            ->where('id', (int)$admin->id)
            ->update([
                'token' => $token,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'admin' => [
                'id' => (int)$admin->id,
                'username' => (string)$admin->username,
                'nickname' => AdminFixtureTextNormalizer::normalize((string)($admin->nickname ?? '')),
            ],
        ], '登录成功');
    }

    public function me(Request $request): Response
    {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);

        return ApiResponse::success([
            'id' => $adminId,
            'username' => (string)($admin['username'] ?? ''),
            'nickname' => AdminFixtureTextNormalizer::normalize((string)($admin['nickname'] ?? '')),
            'roles' => $this->roles($adminId),
        ]);
    }

    public function menus(Request $request): Response
    {
        $adminId = (int)($request->admin['id'] ?? 0);
        $authBuilder = new AdminRouteAuthMapBuilder();
        $authMap = $authBuilder->build($adminId);
        $allowedRouteNames = $this->allowedRouteNames($adminId, $authBuilder);

        return ApiResponse::success($this->buildMenus($authMap, $allowedRouteNames));
    }

    public function logout(Request $request): Response
    {
        $adminId = (int)($request->admin['id'] ?? 0);
        if ($adminId > 0) {
            Db::table('admin_admin')
                ->where('id', $adminId)
                ->update([
                    'token' => null,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        }

        return ApiResponse::success([
            'logged_out' => true,
            'admin_id' => $adminId,
        ], '退出成功');
    }

    /**
     * @return array<int, string>
     */
    private function roles(int $adminId): array
    {
        if ($adminId <= 0) {
            return [];
        }

        if ($adminId === 1) {
            return ['R_SUPER', 'R_ADMIN'];
        }

        $query = Db::table('admin_admin_role')
            ->join('admin_role', 'admin_admin_role.role_id', '=', 'admin_role.id')
            ->select('admin_role.id', 'admin_role.name')
            ->where('admin_admin_role.admin_id', $adminId);

        if (DatabaseColumnInspector::hasColumn('admin_role', 'delete_time')) {
            $query->whereNull('admin_role.delete_time');
        }

        $rows = $query->get()->toArray();
        foreach ($rows as $row) {
            $record = (array)$row;
            if ((int)($record['id'] ?? 0) === 1) {
                return ['R_SUPER', 'R_ADMIN'];
            }
        }

        return ['R_ADMIN'];
    }

    /**
     * @return array<string, bool>|null
     */
    private function allowedRouteNames(int $adminId, AdminRouteAuthMapBuilder $authBuilder): ?array
    {
        if ($adminId === 1) {
            return null;
        }

        $allowed = [];

        foreach ($authBuilder->permissions($adminId) as $permission) {
            $description = AdminPermissionMigrationMapper::describe($permission);
            $routeName = trim((string)($description['modern_route_name'] ?? ''));
            $status = trim((string)($description['migration_status'] ?? ''));

            if ($routeName === '') {
                continue;
            }

            if (in_array($status, ['legacy_only', 'unmapped', 'group_split'], true)) {
                continue;
            }

            $allowed[$routeName] = true;
        }

        return $allowed;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $authMap
     * @param array<string, bool>|null $allowedRouteNames
     * @return array<int, array<string, mixed>>
     */
    private function buildMenus(array $authMap, ?array $allowedRouteNames): array
    {
        $groups = [
            $this->menuGroup(1, '/dashboard', 'Dashboard', '支付控制台', 'ri:pie-chart-line', [
                $this->menuItem(11, 'console', 'AiPayConsole', '/dashboard/console', '经营总览', 'ri:home-smile-2-line', false, 'AiPayConsole', $authMap),
                $this->menuItem(13, 'business', 'AiPayBusinessOverview', '/dashboard/business', '商城总览', 'ri:store-2-line', false, 'AiPayBusinessOverview', $authMap),
            ]),
            $this->menuGroup(2, '/orders-center', 'OrdersCenter', '订单中心', 'ri:file-list-3-line', [
                $this->menuItem(21, '/orders', 'Orders', '/orders', '订单列表', 'ri:file-list-3-line', true, 'Orders', $authMap),
            ]),
            $this->menuGroup(3, '/payments', 'Payments', '支付配置', 'ri:bank-card-line', [
                $this->menuItem(31, 'plugins', 'PaymentPlugins', '/payments/plugins', '支付插件', 'ri:apps-2-line', true, 'PaymentPlugins', $authMap),
                $this->menuItem(32, 'methods', 'PaymentMethods', '/payments/methods', '支付方式', 'ri:wallet-3-line', true, 'PaymentMethods', $authMap),
                $this->menuItem(33, 'accounts', 'PaymentAccounts', '/payments/accounts', '收款账号', 'ri:qr-scan-2-line', true, 'PaymentAccounts', $authMap),
                $this->menuItem(34, 'pools', 'PaymentPools', '/payments/pools', '轮询池', 'ri:stack-line', true, 'PaymentPools', $authMap),
            ]),
            $this->menuGroup(4, '/finance', 'Finance', '财务审计', 'ri:money-cny-circle-line', [
                $this->menuItem(41, 'money-logs', 'FinanceMoneyLogs', '/finance/money-logs', '资金日志', 'ri:exchange-funds-line', true, 'FinanceMoneyLogs', $authMap),
                $this->menuItem(42, 'cdks', 'FinanceCdks', '/finance/cdks', '卡券管理', 'ri:coupon-3-line', true, 'FinanceCdks', $authMap),
                $this->menuItem(43, '/recharge', 'RechargeRecords', '/recharge', '充值记录', 'ri:wallet-line', true, 'RechargeRecords', $authMap),
            ]),
            $this->menuGroup(5, '/content', 'ContentCenter', '内容中心', 'ri:notification-4-line', [
                $this->menuItem(51, 'news', 'ContentNews', '/content/news', '公告管理', 'ri:article-line', true, 'ContentNews', $authMap),
                $this->menuItem(52, 'navs', 'ContentNavs', '/content/navs', '导航管理', 'ri:navigation-line', true, 'ContentNavs', $authMap),
                $this->menuItem(53, 'plugins', 'ContentPluginDownloads', '/content/plugins', '插件下载', 'ri:download-cloud-2-line', true, 'ContentPluginDownloads', $authMap),
            ]),
            $this->menuGroup(6, '/risk', 'RiskCenter', '风控中心', 'ri:shield-check-line', [
                $this->menuItem(61, 'records', 'RiskRecords', '/risk/records', '风控记录', 'ri:alarm-warning-line', true, 'RiskRecords', $authMap),
            ]),
            $this->menuGroup(7, '/tickets', 'TicketCenter', '工单中心', 'ri:customer-service-2-line', [
                $this->menuItem(71, 'list', 'TicketList', '/tickets/list', '工单列表', 'ri:message-3-line', true, 'TicketList', $authMap),
                $this->menuItem(72, 'categories', 'TicketCategories', '/tickets/categories', '工单分类', 'ri:list-check-3', true, 'TicketCategories', $authMap),
            ]),
            $this->menuGroup(8, '/system', 'System', '系统管理', 'ri:settings-3-line', [
                $this->menuItem(81, 'user', 'SystemUser', '/system/user', '商户管理', 'ri:team-line', true, 'SystemUser', $authMap),
                $this->menuItem(82, 'vips', 'SystemVips', '/system/vips', '会员套餐', 'ri:vip-crown-line', true, 'SystemVips', $authMap),
                $this->menuItem(83, 'admins', 'SystemAdmins', '/system/admins', '管理员账号', 'ri:shield-user-line', true, 'SystemAdmins', $authMap),
                $this->menuItem(84, 'role', 'SystemRole', '/system/role', '角色权限', 'ri:user-settings-line', true, 'SystemRole', $authMap),
                $this->menuItem(85, 'menu', 'SystemMenu', '/system/menu', '菜单配置', 'ri:menu-line', true, 'SystemMenu', $authMap),
                $this->menuItem(86, 'config', 'SystemConfigOverview', '/system/config', '配置总览', 'ri:database-2-line', true, 'SystemConfigOverview', $authMap),
                $this->menuItem(87, 'processes', 'SystemProcesses', '/system/processes', '进程管理', 'ri:pulse-line', true, 'SystemProcesses', $authMap),
                $this->menuItem(88, 'cleanup', 'SystemCleanupAudit', '/system/cleanup', '缓存清理', 'ri:delete-bin-6-line', true, 'SystemCleanupAudit', $authMap),
                $this->menuItem(89, 'domains', 'SystemDomains', '/system/domains', '域名审核', 'ri:global-line', true, 'SystemDomains', $authMap),
                $this->menuItem(90, 'front-logs', 'SystemFrontLogs', '/system/front-logs', '商户日志', 'ri:file-text-line', true, 'SystemFrontLogs', $authMap),
                $this->menuItem(91, 'media-library', 'SystemMediaLibrary', '/system/media-library', '素材库', 'ri:image-line', true, 'SystemMediaLibrary', $authMap),
                $this->menuItem(92, 'quick-logins', 'SystemQuickLogins', '/system/quick-logins', '快捷登录', 'ri:flashlight-line', true, 'SystemQuickLogins', $authMap),
            ]),
        ];

        $result = [];
        foreach ($groups as $group) {
            $children = [];
            foreach ((array)($group['children'] ?? []) as $child) {
                $routeName = trim((string)($child['name'] ?? ''));
                if ($allowedRouteNames !== null && !isset($allowedRouteNames[$routeName])) {
                    continue;
                }
                $children[] = $child;
            }

            if ($children === []) {
                continue;
            }

            $group['children'] = $children;
            $group['redirect'] = (string)($children[0]['path'] ?? '');
            $result[] = $group;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function menuGroup(
        int $id,
        string $path,
        string $name,
        string $title,
        string $icon,
        array $children
    ): array {
        return [
            'id' => $id,
            'path' => $path,
            'name' => $name,
            'component' => '/index/index',
            'meta' => [
                'title' => $title,
                'icon' => $icon,
            ],
            'children' => $children,
        ];
    }

    /**
     * @param array<string, array<int, array<string, string>>> $authMap
     * @return array<string, mixed>
     */
    private function menuItem(
        int $id,
        string $path,
        string $name,
        string $component,
        string $title,
        string $icon,
        bool $keepAlive,
        string $authRouteName,
        array $authMap
    ): array {
        $meta = [
            'title' => $title,
            'icon' => $icon,
            'keepAlive' => $keepAlive,
        ];

        $authList = array_values($authMap[$authRouteName] ?? []);
        if ($authList !== []) {
            $meta['authList'] = $authList;
        }

        return [
            'id' => $id,
            'path' => $path,
            'name' => $name,
            'component' => $component,
            'meta' => $meta,
        ];
    }
}
