<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\support;

use Webman\Http\Response;

class ApiResponse
{
    private const MOJIBAKE_FRAGMENTS = [
        "\u{FFFD}", "\u{20AC}", "\u{935F}", "\u{93B4}", "\u{7487}", "\u{95AB}",
        "\u{93C0}", "\u{9427}", "\u{934F}", "\u{5BF0}", "\u{7039}", "\u{7490}",
        "\u{7F01}", "\u{95C8}", "\u{7EEF}", "\u{7EFE}", "\u{7F03}", "\u{9422}",
        "\u{9352}", "\u{9359}", "\u{95C2}", "\u{8930}", "\u{748B}", "\u{9365}",
        "\u{93C3}", "\u{951B}", "\u{9286}", "\u{9369}", "\u{59AF}", "\u{95B0}",
        "\u{93BB}", "\u{935A}", "\u{7481}", "\u{9475}", "\u{95C3}", "\u{942D}",
        "\u{95AD}", "\u{74A7}", "\u{7F02}", "\u{93BA}", "\u{7ED4}", "\u{7EE0}",
        "\u{935B}", "\u{6A40}", "\u{5056}", "\u{5D85}", "\u{608A}",
    ];
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
            'merchant id is invalid' => '商户 ID 无效',
            'merchant ids are required' => '请至少选择一个商户 ID',
            'too many merchants were selected for one batch delete' => '单次批量删除选择的商户数量过多',
            'merchant status must be 0 or 1' => '商户状态只能为 0 或 1',
            'merchant scope requires at least one merchant id' => '商户范围至少需要选择一个商户 ID',
            'merchant email format is invalid' => '商户邮箱格式不正确',
            'merchant username already exists' => '商户账号已存在',
            'merchant email already exists' => '商户邮箱已存在',
            'merchant mobile already exists' => '商户手机号已存在',
            'merchant wxpusher uid is required before enabling wxpusher notifications' => '启用微信推送通知前，请先绑定商户微信推送标识',
            'payment method updated' => '支付方式已更新',
            'payment pool created' => '轮询池已创建',
            'payment pool updated' => '轮询池已更新',
            'payment pool status updated' => '轮询池状态已更新',
            'payment pool deleted' => '轮询池已删除',
            'payment pool channels saved' => '轮询池通道已保存',
            'payment pool channels cleared' => '轮询池通道已清空',
            'payment pool created but detail reload failed' => '轮询池创建后详情加载失败',
            'payment pool updated but detail reload failed' => '轮询池更新后详情加载失败',
            'payment pool channels saved but reload failed' => '轮询池通道保存后详情加载失败',
            'quick login config created' => '快捷登录配置已创建',
            'quick login config updated' => '快捷登录配置已更新',
            'quick login status updated' => '快捷登录状态已更新',
            'quick login config deleted' => '快捷登录配置已删除',
            'quick login batch delete completed' => '快捷登录批量删除已完成',
            'risk record created' => '风控记录已创建',
            'risk record updated' => '风控记录已更新',
            'risk record deleted' => '风控记录已删除',
            'risk batch delete completed' => '风控记录批量删除已完成',
            'ticket category created' => '工单分类已创建',
            'ticket category updated' => '工单分类已更新',
            'ticket category status updated' => '工单分类状态已更新',
            'ticket category deleted' => '工单分类已删除',
            'ticket category batch delete completed' => '工单分类批量删除已完成',
            'ticket status updated' => '工单状态已更新',
            'ticket deleted' => '工单已删除',
            'ticket batch delete completed' => '工单批量删除已完成',
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
            'email title must be 120 characters or fewer' => '邮件标题不能超过 120 个字符',
            'email content is invalid' => '邮件内容参数无效',
            'email content is required' => '请输入邮件内容',
            'email content must be 20000 characters or fewer' => '邮件内容不能超过 20000 个字符',
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
            'payment plugin scaffold created' => '支付插件脚手架已创建',
            'recovery snapshot created' => '恢复快照已创建',
            'recovery snapshot restored' => '恢复快照已还原',
            'recovery snapshot deleted' => '恢复快照已删除',
            'payment plugin registry residue was not found' => '支付插件注册残留不存在',
            'plugin registry residue cleaned' => '插件注册残留已清理',
            'plugin installed' => '插件已安装',
            'plugin repaired' => '插件已修复',
            'plugin upgraded' => '插件已升级',
            'plugin enabled' => '插件已启用',
            'plugin config saved' => '插件配置已保存',
            'plugin disabled' => '插件已停用',
            'uninstall plan generated' => '卸载计划已生成',
            'plugin marked uninstalled with purge plan' => '插件已标记为卸载，并生成彻底清理计划',
            'plugin marked uninstalled' => '插件已标记为卸载',
            'plugin safe cleanup completed' => '插件安全清理已完成',
            'plugin purge cleanup completed' => '插件彻底清理已完成',
            'payment plugin operation failed' => '支付插件操作失败',
            'snapshot_id is required' => '快照 ID 不能为空',
            'confirm_code must match plugin code' => '确认插件编码不匹配',
            'config payload must be an object' => '配置内容必须为对象',
            'payment gateway migration entry failed' => '支付网关处理失败',
            'notify migration error' => '回调处理失败',
            'return received' => '回调已接收',
            'no enabled gateway payment plugin is available' => '当前没有可用的网关支付插件',
            'multiple gateway payment plugins are enabled; please specify plugin' => '当前启用了多个网关支付插件，请先配置通道或显式传入 plugin',
            'gateway plugin resolution returned an empty plugin code' => '网关插件解析结果为空',
            'pid is required' => '商户 ID 不能为空',
            'out_trade_no is required' => '商户订单号不能为空',
            'duplicated out_trade_no' => '商户订单号重复',
            'order was not found' => '订单不存在',
            'order was created but could not be reloaded' => '订单创建成功后重新加载失败',
            'order identity changed before settlement' => '订单标识在落账前发生变化',
            'merchant was not found' => '商户不存在',
            'merchant account is frozen' => '商户账户已冻结',
            'merchant was not found for callback enqueue' => '创建商户回调任务时未找到商户',
            'order id is required when enqueueing merchant callback' => '创建商户回调任务时缺少订单ID',
            'failed to persist merchant callback task' => '商户回调任务写入失败',
            'callback task id is required for synchronous dispatch' => '同步派发回调任务时缺少回调任务ID',
            'callback task was not found for synchronous dispatch' => '同步派发回调任务时未找到回调任务',
            'failed to persist reconciliation task' => '对账任务写入失败',
            'alipay signature verification failed' => '支付宝签名校验失败',
            'alipay trade is not paid' => '支付宝订单未支付成功',
            'alipay trade_no is required' => '支付宝交易号不能为空',
            'alipay callback payload is invalid' => '支付宝回调参数格式不正确',
            'order has no bound payment account' => '订单未绑定收款账号',
            'payment account was not found' => '收款账号不存在',
            'payment account does not accept alipay official callbacks' => '当前收款账号不支持支付宝官方版回调',
            'payment account is not bound to the order merchant' => '收款账号不属于当前订单商户',
            'order payment type is not alipay' => '当前订单支付方式不是支付宝',
            'alipay app_id does not match the payment account' => '支付宝应用ID与收款账号配置不一致',
            'alipay total_amount does not match the order' => '支付宝回调金额与订单不一致',
            'alipay trade_no does not match the settled order' => '支付宝交易号与已落账订单不一致',
            'quick login config cannot be deleted until every binding is removed' => '当前快捷登录配置仍存在绑定关系，请先移除全部绑定后再删除',
            'selected quick login configs cannot be batch deleted until every binding is removed' => '所选快捷登录配置仍存在绑定关系，请先移除全部绑定后再批量删除',
            'ticket category cannot be deleted until every linked ticket is cleared or reassigned' => '当前工单分类仍有关联工单，请先清理或改派后再删除',
            'selected risk records cannot be batch deleted until the selection is refreshed' => '所选风控记录中包含失效项，请刷新选择后再批量删除',
            'selected front logs cannot be batch deleted until the selection is refreshed' => '所选前台日志中包含失效项，请刷新选择后再批量删除',
            'no front logs are available for cleanup' => '当前没有可清理的前台日志',
            'front logs cleaned up' => '前台日志已清理',
            'managed channel code is required' => '插件托管通道编码不能为空',
            'managed channel code is too long' => '插件托管通道编码过长',
            'managed channel code must start with a letter and contain only lowercase letters, digits, or underscores' => '插件托管通道编码必须以字母开头，且只能包含小写字母、数字或下划线',
            'managed channel info must be a scalar' => '插件托管通道说明必须是标量值',
            'managed channel info is too long' => '插件托管通道说明过长',
            'managed channel status must be 0 or 1' => '插件托管通道状态只能为 0 或 1',
            'snapshot managed channel code is outside the allowed plugin scope' => '快照中的托管通道编码超出允许的插件范围',
            'the requested field does not support qrcode decoding' => '当前字段不支持二维码解析',
            'plugin must be installed before config can be updated' => '请先安装插件，再更新配置',
            'plugin config table is not available; install migrations first' => '插件配置表不可用，请先完成插件数据表安装',
            'plugin install assets are already healthy; no repair is required' => '插件安装资源正常，无需修复',
            'plugin does not currently require repair; use install to initialize it' => '当前插件无需修复，请使用安装进行初始化',
            'plugin must be installed before it can be upgraded' => '请先安装插件，再执行升级',
            'plugin install assets are incomplete; repair the plugin before upgrading it' => '插件安装资源不完整，请先修复后再升级',
            'plugin is already on the latest manifest version' => '插件已是最新版本',
            'plugin must be installed before it can be enabled' => '请先安装插件，再执行启用',
            'plugin install assets are incomplete; repair or reinstall the plugin before enabling it' => '插件安装资源不完整，请先修复或重装后再启用',
            'plugin-managed channels are incomplete; repair the plugin before enabling it' => '插件托管通道不完整，请先修复插件后再启用',
            'plugin is not installed' => '插件未安装',
            'plugin must be uninstalled before safe cleanup can run' => '请先卸载插件，再执行安全清理',
            'plugin must be uninstalled before purge cleanup can run' => '请先卸载插件，再执行彻底清理',
            'plugin catalog is still available; use the standard plugin lifecycle actions instead' => '插件目录仍存在，请使用标准插件生命周期操作',
            'plugin class must implement PaymentPluginInterface' => '插件类必须实现 PaymentPluginInterface 接口',
            'payment plugin runtime path is not a directory' => '支付插件运行目录目标不是文件夹',
            'failed to create payment plugin runtime directory' => '创建支付插件运行目录失败',
            'failed to encode payment plugin lifecycle metadata' => '编码支付插件生命周期元数据失败',
            'failed to write payment plugin lifecycle metadata' => '写入支付插件生命周期元数据失败',
            'invalid payment plugin snapshot id' => '支付插件快照编号无效',
            'invalid payment plugin snapshot payload' => '支付插件快照数据无效',
            'snapshot archive entry path is outside the restore root' => '快照归档条目路径超出恢复目录范围',
            'invalid database identifier' => '数据库标识无效',
            'post is required' => '请求方式必须为 POST',
            'temporary processing failure' => '系统暂时处理失败',
            'empty request body' => '请求体不能为空',
            'payment resource is missing' => '支付资源数据缺失',
            'unsupported resource algorithm' => '支付资源加密算法不受支持',
            'encrypted payment resource is incomplete' => '加密支付资源不完整',
            'signature headers are incomplete' => '签名请求头不完整',
            'signature timestamp is outside the accepted window' => '签名时间戳超出允许范围',
            'wxpay v3 account was not found' => '未找到微信官方 V3 收款账号',
            'merchant binding mismatch' => '商户绑定关系不匹配',
            'order payment type mismatch' => '订单支付方式不匹配',
            'signature, resource, or order verification failed' => '签名、资源或订单校验失败',
            'appid or mchid binding mismatch' => '应用ID或商户号绑定关系不匹配',
            'transaction id is missing' => '交易号缺失',
            'payment amount is missing' => '支付金额缺失',
            'payment amount does not match the order' => '支付金额与订单不一致',
            'payment currency does not match the order' => '支付币种与订单不一致',
            'paid order transaction id mismatch' => '已支付订单的交易号不一致',
            'invalid json payload' => 'JSON 载荷无效',
            'json payload must be an object' => 'JSON 载荷必须为对象',
            'missing_query_identifier' => '缺少微信订单查询标识',
            'transaction_id_missing' => '微信订单查询结果缺少交易号',
            'amount_missing' => '微信订单查询结果缺少金额信息',
            'amount_mismatch' => '订单金额与微信查询结果不一致',
            'currency_mismatch' => '微信订单币种不是人民币',
            'transaction_mismatch' => '微信交易号与已落账订单不一致',
            'wxpay_v3_appid_missing' => '微信官方 V3 通道缺少应用ID',
            'wxpay_v3_mchid_missing' => '微信官方 V3 通道缺少商户号',
            'wxpay_v3_api_v3_key_invalid' => '微信官方 V3 通道 API V3 密钥无效',
            'wxpay_v3_merchant_serial_missing' => '微信官方 V3 通道缺少商户证书序列号',
            'wxpay_v3_private_key_missing' => '微信官方 V3 通道缺少商户私钥',
            'alipay bill query returned an invalid response' => '支付宝账单接口返回无效响应',
            'alipay bill query failed' => '支付宝账单查询失败',
            'alipay bill query hit the page cap with a full final page' => '支付宝账单查询超过分页上限',
            'trongrid base url must use https' => 'TronGrid 接口地址必须使用 HTTPS',
            'usdt account wallet address is invalid' => 'USDT 钱包地址无效',
            'trongrid returned an invalid transfer response' => 'TronGrid 返回的转账数据无效',
            'trongrid returned conflicting events for one transaction' => 'TronGrid 返回了冲突的转账事件',
            'trongrid full transfer page is missing a pagination fingerprint' => 'TronGrid 分页指纹缺失',
            'trongrid transfer page limit was exceeded' => 'TronGrid 转账分页超过上限',
            'usdt order has an invalid reconciliation window' => 'USDT 订单对账时间窗口无效',
            'usdt reconciliation window has not started' => 'USDT 对账时间窗口尚未开始',
            'trongrid transport returned an invalid response' => 'TronGrid 传输层返回无效响应',
            'the php curl extension is required for trongrid queries' => '当前 PHP 环境缺少 cURL 扩展，无法请求 TronGrid',
            'failed to initialize trongrid request' => '初始化 TronGrid 请求失败',
            'trongrid returned malformed json' => 'TronGrid 返回的 JSON 格式无效',
            'trongrid returned an invalid json object' => 'TronGrid 返回的 JSON 对象无效',
            'user_id is required' => '商户ID不能为空',
            'direction must be income or expense' => '资金方向只能为 income 或 expense',
            'amount is required' => '金额不能为空',
            'amount must be a positive number with up to 2 decimals' => '金额必须为最多两位小数的正数',
            'memo must be 32 characters or fewer' => '备注不能超过 32 个字符',
        ];

        if (isset($exact[$message])) {
            return self::repairMessage($exact[$message]);
        }

        if (preg_match('/^confirm_phrase must equal "(.+)"$/i', $message, $matches)) {
            return '确认口令必须填写为“' . self::repairMessage($matches[1]) . '”';
        }

        if (preg_match('/^at least one (.+?) id is required$/i', $message, $matches)) {
            return '请至少选择一个' . self::translateEntity($matches[1]) . 'ID';
        }

        if (preg_match('/^(.+?) ids must be provided as an array$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须以数组形式传入';
        }

        if (preg_match('/^(.+?) ids must contain only positive integers$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '只能包含正整数';
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

        if (preg_match('/^(.+?) created but detail reload failed$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '创建后详情加载失败';
        }

        if (preg_match('/^(.+?) updated but detail reload failed$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '更新后详情加载失败';
        }

        if (preg_match('/^(.+?) channels saved but reload failed$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '保存后详情加载失败';
        }

        if (preg_match('/^(.+?) created$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '已创建';
        }

        if (preg_match('/^(.+?) updated$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '已更新';
        }

        if (preg_match('/^(.+?) deleted$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '已删除';
        }

        if (preg_match('/^(.+?) status updated$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '状态已更新';
        }

        if (preg_match('/^(.+?) order updated$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '排序已更新';
        }

        if (preg_match('/^(.+?) target updated$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '打开方式已更新';
        }

        if (preg_match('/^(.+?) roles updated$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '角色已更新';
        }

        if (preg_match('/^(.+?) direct permissions updated$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '直连权限已更新';
        }

        if (preg_match('/^(.+?) batch delete completed$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '批量删除已完成';
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

        if (preg_match('/^payment plugin \\[(.+?)\\] does not declare the \\[(.+?)\\] capability$/i', $message, $matches)) {
            return '支付插件[' . $matches[1] . ']未声明[' . $matches[2] . ']能力';
        }

        if (preg_match('/^(.+?) must be a scalar$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须为标量值';
        }

        if (preg_match('/^(.+?) is required$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '不能为空';
        }

        if (preg_match('/^(.+?) is too long$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '过长';
        }

        if (preg_match('/^(.+?) format is invalid$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '格式不正确';
        }

        if (preg_match('/^(.+?) must be greater than 0$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须大于 0';
        }

        if (preg_match('/^(.+?) must be 0 or 1$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '只能为 0 或 1';
        }

        if (preg_match('/^(.+?) must be 1 or 2$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '只能为 1 或 2';
        }

        if (preg_match('/^(.+?) must be 0, 1, 2, or 3$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '只能为 0、1、2 或 3';
        }

        if (preg_match('/^(.+?) must be a positive integer with up to 9 digits$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须为最多 9 位的正整数';
        }

        if (preg_match('/^(.+?) must be an array$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须为数组';
        }

        if (preg_match('/^(.+?) must contain objects$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须包含对象项';
        }

        if (preg_match('/^(.+?) contain an invalid account_id$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '包含无效的收款账号ID';
        }

        if (preg_match('/^(.+?) cannot contain duplicate account ids$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '不能包含重复的收款账号ID';
        }

        if (preg_match('/^(.+?) does not exist$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '不存在';
        }

        if (preg_match('/^too many (.+?) were selected for one batch action$/i', $message, $matches)) {
            return '单次批量操作选择的' . self::translateEntity($matches[1]) . '数量过多';
        }

        if (preg_match('/^payment plugin \\[(.+?)\\] does not support gateway order creation$/i', $message, $matches)) {
            return '支付插件[' . $matches[1] . ']不支持网关下单';
        }

        if (preg_match('/^payment plugin \\[(.+?)\\] does not support notify callbacks$/i', $message, $matches)) {
            return '支付插件[' . $matches[1] . ']不支持回调处理';
        }

        if (preg_match('/^payment plugin \\[(.+?)\\] is not installed$/i', $message, $matches)) {
            return '支付插件[' . $matches[1] . ']未安装';
        }

        if (preg_match('/^payment plugin \\[(.+?)\\] is disabled$/i', $message, $matches)) {
            return '支付插件[' . $matches[1] . ']已停用';
        }

        if (preg_match('/^payment plugin \\[(.+?)\\] was not found$/i', $message, $matches)) {
            return '支付插件[' . $matches[1] . ']不存在';
        }

        if (preg_match('/^payment plugin registry residue \\[(.+?)\\] was not found$/i', $message, $matches)) {
            return '支付插件注册残留[' . $matches[1] . ']不存在';
        }

        if (preg_match('/^payment plugin snapshot \\[(.+?)\\] was not found$/i', $message, $matches)) {
            return '支付插件快照[' . $matches[1] . ']不存在';
        }

        if (preg_match('/^plugin manifest code \\[(.+?)\\] does not match directory \\[(.+?)\\]$/i', $message, $matches)) {
            return '插件清单编码[' . $matches[1] . ']与目录[' . $matches[2] . ']不一致';
        }

        if (preg_match('/^invalid plugin manifest: (.+)$/i', $message, $matches)) {
            return '插件清单无效：' . $matches[1];
        }

        if (preg_match('/^plugin entry file was not found: (.+)$/i', $message, $matches)) {
            return '插件入口文件不存在：' . $matches[1];
        }

        if (preg_match('/^plugin class was not found: (.+)$/i', $message, $matches)) {
            return '插件类不存在：' . $matches[1];
        }

        if (preg_match('/^safe cleanup target \\[(.+?)\\] is outside the allowed runtime scope$/i', $message, $matches)) {
            return '安全清理目标[' . $matches[1] . ']超出允许的运行目录范围';
        }

        if (preg_match('/^failed to remove cleanup target \\[(.+?)\\]$/i', $message, $matches)) {
            return '清理目标[' . $matches[1] . ']删除失败';
        }

        if (preg_match('/^safe cleanup table \\[(.+?)\\] is outside the allowed plugin namespace$/i', $message, $matches)) {
            return '安全清理数据表[' . $matches[1] . ']超出允许的插件命名空间';
        }

        if (preg_match('/^purge cleanup target \\[(.+?)\\] is outside the allowed plugin scope$/i', $message, $matches)) {
            return '彻底清理目标[' . $matches[1] . ']超出允许的插件范围';
        }

        if (preg_match('/^plugin config is incomplete: (.+)$/i', $message, $matches)) {
            return '插件配置不完整：' . self::repairMessage($matches[1]);
        }

        if (preg_match('/^managed channel code must equal the plugin code \\[(.+?)\\] or start with the plugin code prefix \\[(.+?)\\]$/i', $message, $matches)) {
            return '插件托管通道编码必须等于插件编码[' . $matches[1] . ']，或以插件前缀[' . $matches[2] . ']开头';
        }

        if (preg_match('/^snapshot managed channel payload is missing row data for \\[(.+?)\\]$/i', $message, $matches)) {
            return '快照中的托管通道[' . $matches[1] . ']缺少行数据';
        }

        if (preg_match('/^managed channel \\[(.+?)\\] is outside the allowed plugin namespace$/i', $message, $matches)) {
            return '插件托管通道[' . $matches[1] . ']超出允许的插件命名空间';
        }

        if (preg_match('/^snapshot target \\[(.+?)\\] is outside the allowed plugin scope$/i', $message, $matches)) {
            return '快照文件目标[' . $matches[1] . ']超出允许的插件范围';
        }

        if (preg_match('/^snapshot table \\[(.+?)\\] is outside the allowed plugin namespace$/i', $message, $matches)) {
            return '快照数据表[' . $matches[1] . ']超出允许的插件命名空间';
        }

        if (preg_match('/^alipay bill credentials are incomplete: (.+)$/i', $message, $matches)) {
            return '支付宝账单插件凭证不完整：' . self::translateEntity($matches[1]);
        }

        if (preg_match('/^alipay sdk file is missing: (.+)$/i', $message, $matches)) {
            return '支付宝 SDK 文件缺失：' . $matches[1];
        }

        if (preg_match('/^trongrid request failed: (.+)$/i', $message, $matches)) {
            return 'TronGrid 请求失败：' . self::repairMessage($matches[1]);
        }

        if (preg_match('/^trongrid request returned http (\\d+)$/i', $message, $matches)) {
            return 'TronGrid 请求返回 HTTP ' . $matches[1];
        }

        if (preg_match('/^failed to encode payment plugin (.+)$/i', $message, $matches)) {
            return '编码支付插件' . self::translateEntity($matches[1]) . '失败';
        }

        if (preg_match('/^failed to write payment plugin (.+)$/i', $message, $matches)) {
            return '写入支付插件' . self::translateEntity($matches[1]) . '失败';
        }

        if (preg_match('/^failed to read snapshot file target: (.+)$/i', $message, $matches)) {
            return '读取快照文件失败：' . $matches[1];
        }

        if (preg_match('/^failed to restore snapshot file target: (.+)$/i', $message, $matches)) {
            return '恢复快照文件失败：' . $matches[1];
        }

        if (preg_match('/^failed to remove snapshot directory: (.+)$/i', $message, $matches)) {
            return '删除快照目录失败：' . $matches[1];
        }

        if (preg_match('/^failed to decode snapshot file content for \\[(.+?)\\]$/i', $message, $matches)) {
            return '解析快照文件内容失败：' . $matches[1];
        }

        if (preg_match('/^snapshot table definition is missing for \\[(.+?)\\]$/i', $message, $matches)) {
            return '快照缺少数据表定义：' . $matches[1];
        }

        if (preg_match('/^failed to load create statement for table \\[(.+?)\\]$/i', $message, $matches)) {
            return '读取数据表创建语句失败：' . $matches[1];
        }

        if (preg_match('/^failed to normalize create statement for table \\[(.+?)\\]$/i', $message, $matches)) {
            return '整理数据表创建语句失败：' . $matches[1];
        }

        if (preg_match('/^create statement was not returned for table \\[(.+?)\\]$/i', $message, $matches)) {
            return '未获取到数据表创建语句：' . $matches[1];
        }

        if (preg_match('/^failed to remove purge target \\[(.+?)\\]$/i', $message, $matches)) {
            return '彻底清理目标[' . $matches[1] . ']删除失败';
        }

        if (preg_match('/^failed to remove directory: (.+)$/i', $message, $matches)) {
            return '删除目录失败：' . $matches[1];
        }

        if (preg_match('/^failed to remove file: (.+)$/i', $message, $matches)) {
            return '删除文件失败：' . $matches[1];
        }

        if (preg_match('/^(.+?) must be an integer$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '必须为整数';
        }

        if (preg_match('/^(.+?) is out of range$/i', $message, $matches)) {
            return self::translateEntity($matches[1]) . '超出允许范围';
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
            'admin account roles' => '管理员账号',
            'admin account direct permissions' => '管理员账号',
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
            'payment pool' => '轮询池',
            'payment pool name' => '轮询池名称',
            'payment pool type' => '轮询池类型',
            'payment pool round type' => '轮询池轮询方式',
            'payment pool status' => '轮询池状态',
            'payment pool channels' => '轮询池通道',
            'payment pool channels saved' => '轮询池通道',
            'media library' => '媒体库',
            'media library item' => '媒体库项',
            'media library path' => '媒体库路径',
            'nav' => '导航',
            'nav order' => '导航',
            'nav target' => '导航',
            'news' => '公告',
            'plugin' => '插件',
            'plugin config' => '插件配置',
            'payment plugin registry residue' => '支付插件注册残留',
            'payment plugin lifecycle metadata' => '生命周期元数据',
            'payment plugin registry' => '注册表',
            'payment plugin registry residue ledger' => '注册残留账本',
            'payment plugin snapshot' => '支付插件快照',
            'payment plugin migration journal' => '插件更新日志',
            'payment plugin lifecycle history' => '插件生命周期历史',
            'money log adjustment' => '资金调整',
            'ticket' => '工单',
            'ticket category' => '工单分类',
            'role' => '角色',
            'recharge' => '充值记录',
            'recharge record' => '充值记录',
            'vip' => 'VIP 套餐',
            'vip package' => 'VIP 套餐',
            'vip packages' => 'VIP 套餐',
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
            'quick login type' => '快捷登录类型',
            'quick login name' => '快捷登录名称',
            'quick login url' => '快捷登录地址',
            'quick login appid' => '快捷登录APPID',
            'quick login appkey' => '快捷登录APPKEY',
            'quick login status' => '快捷登录状态',
            'email scope' => '邮件发送范围',
            'direct email' => '直发邮箱',
            'email title' => '邮件标题',
            'email content' => '邮件内容',
            'ticket status' => '工单状态',
            'ticket ids' => '工单',
            'ticket category ids' => '工单分类',
            'ticket category name' => '工单分类名称',
            'ticket category sort' => '工单分类排序',
            'ticket category status' => '工单分类状态',
            'risk ids' => '风控记录',
            'reply content' => '回复内容',
            'merchant user_id' => '商户ID',
            'merchant id' => '商户ID',
            'account_id' => '收款账号ID',
            'direction' => '资金方向',
            'amount' => '金额',
            'memo' => '备注',
            'transaction id' => '交易号',
            'query identifier' => '查询标识',
            'app_id' => '应用ID',
            'appid' => '应用ID',
            'mchid' => '商户号',
            'api v3 key' => 'API V3 密钥',
            'merchant serial' => '商户证书序列号',
            'private key' => '私钥',
            'public key' => '公钥',
            'signature' => '签名',
            'process snapshot' => '进程快照',
            'duplicate supervisor cleanup' => '重复进程清理',
            'system cache targets' => '缓存清理目标',
            'gateway payment plugin' => '网关支付插件',
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

        $repaired = @iconv('UTF-8', 'GB18030//IGNORE', $message);
        if (is_string($repaired) && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8')) {
            if (self::mojibakeScore($repaired) < self::mojibakeScore($message)) {
                $message = $repaired;
            }
        }

        $message = str_replace("\u{FFFD}", '', $message);
        $message = str_replace("\u{95FF}?", '', $message);
        $message = preg_replace('/\?+(?=$|[\s，。；：、】【）])/u', '', $message) ?? $message;

        return trim($message);
    }

    private static function mojibakeScore(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $score = 0;
        foreach (self::MOJIBAKE_FRAGMENTS as $fragment) {
            $score += substr_count($text, $fragment);
        }

        return $score;
    }
}
