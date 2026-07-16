<?php

declare(strict_types=1);

namespace app\support;

use Webman\Http\Response;

class ApiResponse
{
    public static function success(array|object|null $data = null, string $message = '成功', int $code = 200): Response
    {
        return self::json($code, $message, $data);
    }

    public static function error(string $message, int $code = 400, array|object|null $data = null, int $status = 200): Response
    {
        return self::json($code, $message, $data, $status);
    }

    public static function json(int $code, string $message, array|object|null $data = null, int $status = 200): Response
    {
        $message = self::normalizeMessage($message);

        return json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE)->withStatus($status);
    }

    public static function normalizeText(string $message): string
    {
        return self::normalizeMessage($message);
    }

    private static function normalizeMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return $message;
        }

        $passthroughMessages = [
            'confirmation phrase mismatch',
            'recycled domain must be restored before approval',
            'recycled domain must be restored before rejection',
        ];
        if (in_array($message, $passthroughMessages, true)) {
            return $message;
        }

        $exact = [
            'ok' => '成功',
            'unauthorized' => '登录状态已失效，请重新登录',
            'forbidden' => '无权限执行当前操作',
            'confirmation phrase mismatch' => '确认口令不匹配',
            'merchant login is required' => '请先登录商户账号',
            'merchant is frozen' => '商户账户已冻结',
            'merchant not found after creation' => '商户创建后加载失败',
            'merchant created' => '商户已创建',
            'merchant profile updated' => '商户资料已更新',
            'merchant vip and fee settings updated' => '商户费率与 VIP 设置已更新',
            'merchant notification settings updated' => '商户通知设置已更新',
            'merchant email campaign sent' => '商户邮件已发送',
            'merchant email campaign cannot be sent' => '当前邮件发送条件不满足，暂不能发送',
            'merchant impersonation ready' => '商户代登录已就绪',
            'merchant cannot be impersonated' => '当前商户暂不允许代登录',
            'merchant deleted' => '商户已删除',
            'merchant batch delete completed' => '商户批量删除已完成',
            'merchant id is invalid' => '商户ID无效',
            'merchant ids are required' => '请至少选择一个商户ID',
            'too many merchants were selected for one batch delete' => '单次批量删除选择的商户数量过多',
            'merchant status must be 0 or 1' => '商户状态只能为0或1',
            'merchant scope requires at least one merchant id' => '商户范围至少需要选择一个商户ID',
            'merchant email format is invalid' => '商户邮箱格式不正确',
            'merchant username already exists' => '商户账号已存在',
            'merchant email already exists' => '商户邮箱已存在',
            'merchant mobile already exists' => '商户手机号已存在',
            'merchant wxpusher uid is required before enabling wxpusher notifications' => '启用微信推送通知前，请先绑定商户微信推送标识',
            'payment method updated' => '支付方式已更新',
            'payment pool updated' => '轮询池已更新',
            'vip package was not found' => '会员套餐不存在',
            'vip package is invalid' => '会员套餐无效',
            'vip package is disabled and cannot be assigned' => '该会员套餐已停用，无法分配',
            'vip package is disabled and cannot be newly assigned' => '该会员套餐已停用，无法新分配',
            'email scope is invalid' => '邮件发送范围无效',
            'direct email is invalid' => '直发邮箱参数无效',
            'direct email is required' => '请输入直发邮箱',
            'direct email format is invalid' => '直发邮箱格式不正确',
            'email title is invalid' => '邮件标题参数无效',
            'email title is required' => '请输入邮件标题',
            'email title must be 120 characters or fewer' => '邮件标题不能超过120个字符',
            'email content is invalid' => '邮件内容参数无效',
            'email content is required' => '请输入邮件内容',
            'email content must be 20000 characters or fewer' => '邮件内容不能超过20000个字符',
            'monitor paused' => '监控已暂停',
            'monitor resumed' => '监控已恢复',
            'admin logs cleaned up' => '管理员日志已清理',
            'process snapshot failed' => '进程快照获取失败',
            'pause monitor failed' => '暂停监控失败',
            'resume monitor failed' => '恢复监控失败',
            'duplicate supervisor cleanup completed' => '重复进程清理完成',
            'duplicate supervisor cleanup failed' => '重复进程清理失败',
            'failed to create merchant impersonation ticket' => '创建商户代登录凭证失败',
            'signature verification failed' => '签名校验失败',
            'system cache targets are invalid' => '缓存清理目标无效',
            'cleanup audit key is invalid' => '清理审计标识无效',
            'cleanup action is not available' => '当前清理操作不可用',
            'status updates must use the dedicated status endpoint' => '状态变更请使用独立状态接口',
            'role assignment must use the dedicated roles endpoint' => '角色分配请使用独立角色接口',
        ];

        if (isset($exact[$message])) {
            return self::repairMessage($exact[$message]);
        }

        if (preg_match('/^at least one (.+?) id is required$/i', $message, $matches)) {
            return '请至少选择一个' . self::translateEntity($matches[1]) . 'ID';
        }

        if (preg_match('/^(.+?) id is required$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . 'ID不能为空';
        }

        if (preg_match('/^(.+?) not found$/i', $message, $matches)) {
            return '未找到' . self::translateEntity($matches[1]);
        }

        if (preg_match('/^(created|updated|status-updated|role-updated|permission-updated|approved|rejected|restored) (.+?) could not be loaded$/i', $message, $matches)) {
            $prefix = match (strtolower($matches[1])) {
                'created' => '新建后的',
                'updated' => '更新后的',
                'status-updated' => '状态更新后的',
                'role-updated' => '角色更新后的',
                'permission-updated' => '权限更新后的',
                'approved' => '审核通过后的',
                'rejected' => '驳回后的',
                'restored' => '恢复后的',
                default => '',
            };

            return $prefix . self::translateEntity($matches[2]) . '加载失败';
        }

        if (preg_match('/^recycled (.+?) must be restored before (editing|changing status|approval|rejection)$/i', $message, $matches)) {
            $action = match (strtolower($matches[2])) {
                'editing' => '编辑',
                'changing status' => '修改状态',
                'approval' => '审核',
                'rejection' => '驳回',
                default => '操作',
            };

            return '已删除的' . self::translateEntity($matches[1]) . '请先恢复后再' . $action;
        }

        if (preg_match('/^(.+?) is already active$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '已处于启用状态';
        }

        if (preg_match('/^selected (.+?) cannot be batch deleted until all blocking items are cleared$/i', $message, $matches)) {
            return '所选' . self::translateEntity($matches[1]) . '存在阻塞项，清理后才能批量删除';
        }

        if (preg_match('/^(.+?) cannot be deleted until all blocking references are cleared$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '仍有关联阻塞项，清理后才能删除';
        }

        if (preg_match('/^(.+?) cannot be deleted$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '当前不可删除';
        }

        if (preg_match('/^(.+?) cannot be restored yet$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '当前暂不可恢复';
        }

        if (preg_match('/^(.+?) restore failed$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '恢复失败';
        }

        if (preg_match('/^no recycled (.+?) matched the restore request$/i', $message, $matches)) {
            return '没有匹配到可恢复的' . self::translateEntity($matches[1]);
        }

        if (preg_match('/^no (.+?) matched the restore request$/i', $message, $matches)) {
            return '没有匹配到' . self::translateEntity($matches[1]);
        }

        if (preg_match('/^no (.+?) are available for cleanup$/i', $message, $matches)) {
            return '暂无可清理的' . self::translateEntity($matches[1]);
        }

        if (preg_match('/^one or more (.+?) were not found$/i', $message, $matches)) {
            return '部分' . self::translateEntity($matches[1]) . '不存在';
        }

        if (preg_match('/^(.+?) reorder indexes are out of range$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '排序序号超出范围';
        }

        if (preg_match('/^(.+?) config not found$/i', $message, $matches)) {
            return '未找到' . self::translateEntity($matches[1] . ' config');
        }

        return self::repairMessage($message);
    }

    private static function translateEntity(string $entity): string
    {
        $entity = trim(strtolower($entity));

        $map = [
            'admin' => '管理员',
            'admin account' => '管理员账号',
            'admin accounts' => '管理员账号',
            'admin log' => '管理员日志',
            'admin logs' => '管理员日志',
            'front log' => '前台日志',
            'front logs' => '前台日志',
            'channel catalog' => '通道目录',
            'channel catalog item' => '通道目录项',
            'channel catalog items' => '通道目录项',
            'domain' => '域名',
            'domains' => '域名',
            'cdk' => '卡密',
            'used cdks' => '已使用卡密',
            'cleanup audit' => '清理审计项',
            'media library' => '媒体库',
            'media library item' => '媒体库项',
            'media library path' => '媒体库路径',
            'plugin' => '插件',
            'plugin download' => '插件下载记录',
            'plugin download row' => '插件下载记录',
            'plugin download rows' => '插件下载记录',
            'ticket' => '工单',
            'ticket category' => '工单分类',
            'role' => '角色',
            'recharge' => '充值记录',
            'recharge record' => '充值记录',
            'vip' => 'VIP套餐',
            'vip package' => 'VIP套餐',
            'vip packages' => 'VIP套餐',
            'risk' => '风控记录',
            'risk record' => '风控记录',
            'user' => '商户',
            'merchant' => '商户',
            'merchant email campaign' => '商户邮件发送',
            'merchant impersonation' => '商户代登录',
            'merchant username' => '商户账号',
            'merchant email' => '商户邮箱',
            'merchant mobile' => '商户手机号',
            'merchant wxpusher uid' => '商户微信推送标识',
            'quick login' => '快捷登录',
            'quick login config' => '快捷登录配置',
            'email scope' => '邮件发送范围',
            'direct email' => '直发邮箱',
            'email title' => '邮件标题',
            'email content' => '邮件内容',
            'signature' => '签名',
            'process snapshot' => '进程快照',
            'duplicate supervisor cleanup' => '重复进程清理',
            'system cache targets' => '缓存清理目标',
        ];

        if (isset($map[$entity])) {
            return $map[$entity];
        }

        if (str_ends_with($entity, 'ies')) {
            $singular = substr($entity, 0, -3) . 'y';
            if (isset($map[$singular])) {
                return $map[$singular];
            }
        }

        if (str_ends_with($entity, 's')) {
            $singular = substr($entity, 0, -1);
            if (isset($map[$singular])) {
                return $map[$singular];
            }
        }

        return self::repairMessage($entity);
    }

    private static function repairMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return $message;
        }

        $repaired = @mb_convert_encoding($message, 'GB18030', 'UTF-8');
        if (is_string($repaired) && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8')) {
            if (self::mojibakeScore($repaired) < self::mojibakeScore($message)) {
                $message = $repaired;
            }
        }

        $message = str_replace('锟?', '', $message);
        $message = preg_replace('/\?+(?=$|[\s，。；：、】【>])/u', '', $message) ?? $message;

        return trim($message);
    }

    private static function mojibakeScore(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/[鍟鎴璇閫鏀鐧鍏寰缁闈绯绾缃鍒鍙闂褰璋鍥鏃锛銆鍩妯閰鎻鍚璁鑵闃鐭閭璧缂鎺绔绠鍛橀偖嶅悊]/u', $text, $matches);

        return count($matches[0]);
    }
}
