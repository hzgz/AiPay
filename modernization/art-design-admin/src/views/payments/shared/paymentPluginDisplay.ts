import {
  getPaymentPluginLegacyProfile,
  type PaymentPluginLegacyProfile
} from './paymentPluginLegacyProfiles'

type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail
type PaymentPluginConfigField = Api.SystemManage.PaymentPluginConfigField
type PaymentPluginStateAudit = Api.SystemManage.PaymentPluginStateAudit
type PaymentPluginManagedChannelAudit = Api.SystemManage.PaymentPluginManagedChannelAudit
type PaymentPluginCleanupReportItem = Api.SystemManage.PaymentPluginCleanupReportItem
type PaymentPluginHistoryEntry = Api.SystemManage.PaymentPluginHistoryEntry
type PaymentPluginUpgradePreview = Api.SystemManage.PaymentPluginUpgradePreview
type PaymentPluginRollbackPolicy = Api.SystemManage.PaymentPluginRollbackPolicy
type PaymentPluginRegistryResidueItem = Api.SystemManage.PaymentPluginRegistryResidueItem
type PaymentPluginRegistryResidueLedgerItem =
  Api.SystemManage.PaymentPluginRegistryResidueLedgerItem

export interface PluginConfigSection {
  key: string
  title: string
  description: string
  fields: PaymentPluginConfigField[]
}

export type PluginPaymentFilterKey = 'all' | 'alipay' | 'wxpay' | 'qqpay' | 'usdt' | 'other'

export type PluginOverviewCardTone = 'info' | 'success' | 'warning' | 'danger'

type PluginPaymentSource = {
  code?: string | null | undefined
  supported_payment_types?: string[] | null | undefined
}

const PLUGIN_PAYMENT_LABELS: Record<Exclude<PluginPaymentFilterKey, 'all'>, string> = {
  alipay: '支付宝',
  wxpay: '微信',
  qqpay: 'QQ',
  usdt: 'USDT',
  other: '其他'
}

const legacyProfileForCode = (code: string | null | undefined) =>
  getPaymentPluginLegacyProfile(code)

export const statusTagType = (
  status: string
): 'success' | 'info' | 'warning' | 'danger' | 'primary' => {
  if (status === 'enabled') return 'success'
  if (status === 'disabled') return 'warning'
  if (status === 'not_installed') return 'info'
  return 'primary'
}

export const statusLabel = (status: string) => {
  if (status === 'enabled') return '已启用'
  if (status === 'disabled') return '已停用'
  if (status === 'not_installed') return '未安装'
  return status || '未知状态'
}

export const executionStateLabel = (state: string | null | undefined) => {
  const normalized = String(state || '')
    .trim()
    .toLowerCase()
  const labels: Record<string, string> = {
    deferred: '待执行',
    plan_only: '已生成计划',
    install_executed: '安装完成',
    repair_executed: '修复完成',
    upgrade_executed: '升级完成',
    uninstall_executed: '卸载完成',
    uninstall_purge_requested: '已申请彻底清理',
    safe_completed: '安全清理完成',
    purge_completed: '彻底清理完成'
  }

  return labels[normalized] || state || '--'
}

export const resourceKindLabel = (kind: string | null | undefined) => {
  const normalized = String(kind || '')
    .trim()
    .toLowerCase()
  if (normalized === 'directory') return '目录'
  if (normalized === 'file') return '文件'
  if (normalized === 'row') return '记录'
  if (normalized === 'table') return '数据表'
  if (normalized === 'missing') return '缺失'
  return kind || '--'
}

const cleanupPluginVisibleWords = (value: string) =>
  value
    .replace(/认证示例/g, '认证测试')
    .replace(/AiPay官方示例/g, 'AiPay官方测试')
    .replace(/注册残留示例/g, '注册残留检查')
    .replace(/演示/g, '测试')
    .replace(/示例/g, '测试')
    .replace(/旧版/g, '原有')
    .replace(/联调/g, '测试')
    .replace(/\s{2,}/g, ' ')
    .trim()

export const normalizePluginCopy = (value: string | null | undefined) => {
  const text = String(value || '').trim()
  if (!text) {
    return value || '--'
  }

  const exactLabels: Record<string, string> = {
    'Forbidden Probe': '禁测探针',
    'Forbidden Probe Default': '禁测探针默认通道',
    'Auth Smoke': '认证测试',
    'Legacy Epay Compatibility': '已移除旧易支付插件',
    'AiPay Official': 'AiPay 官方',
    'AiPay official': 'AiPay 官方',
    'AiPay modernization': 'AiPay 官方',
    'AiPay modernization smoke': 'AiPay官方测试',
    'AiPay 现代化改造': 'AiPay 官方',
    'AiPay 联调': 'AiPay官方测试',
    'AiPay 改造项目': 'AiPay 官方',
    '通用易支付插件': '通用易支付V1插件',
    universal_epay: '通用易支付V1插件',
    'Forbidden probe': '禁测探针插件',
    forbidden_probe_default: '禁测探针默认通道',
    registry_residue_probe: '注册残留检查',
    registry_residue_smoke: '注册残留恢复快照',
    executed: '已执行',
    install: '安装',
    Installed: '已安装',
    Repaired: '已修复',
    Upgraded: '已升级',
    Enabled: '已启用',
    Disabled: '已停用',
    Uninstalled: '已卸载',
    'Safe Cleanup': '安全清理',
    'Purge Cleanup': '彻底清理',
    'Purge Requested': '申请彻底清理',
    'Snapshot Restored': '恢复快照',
    'Snapshot Captured': '创建快照',
    'Snapshot Deleted': '删除快照',
    'Config Updated': '更新配置',
    'Registry Residue Cleanup': '注册残留清理',
    'State Reconciled': '状态对齐',
    'Runtime Defaults': '运行时默认行为',
    not_implemented: '待实现',
    unsupported: '未接入',
    create_order: '创建订单',
    query: '订单查询',
    refund: '退款',
    notify: '异步通知',
    alipay: '支付宝',
    versioned_sql: '数据库脚本',
    'createOrder()': '创建订单方法',
    'query()': '订单查询方法',
    'refund()': '退款方法',
    'handleNotify()': '异步通知方法',
    'Merchant ID': '商户号',
    'Merchant Key': '商户密钥',
    'Notify Secret': '回调密钥',
    merchant_id: '商户号字段',
    merchant_key: '商户密钥字段',
    notify_secret: '回调密钥字段',
    gateway_url: '接口地址字段',
    text: '单行文本',
    password: '密码框',
    'Managed universal epay account plugin for Webman.': '用于统一管理易支付上游账号的托管插件。',
    'Managed universal epay account plugin for Webman': '用于统一管理易支付上游账号的托管插件。',
    ['Compatibility wrapper for the legacy payment flow during the legacy framework to Webman migration.']:
      '用于接入易支付协议的插件。',
    'Plugin-managed channel seeded from plugin.json.': '根据插件清单自动初始化的插件托管通道。',
    'Enabled plugin routing after runtime, config, and migration checks passed.':
      '已通过运行目录、配置和版本检查，并启用插件路由。',
    'Disabled plugin routing and kept install assets intact.': '已停用插件路由，并保留安装资源。',
    'Marked plugin uninstalled and captured a purge plan for operator review.':
      '已将插件标记为卸载，并生成彻底清理方案供人工复核。',
    'Plugin is already on manifest version [0.1.0]. The release policy below documents what the next upgrade window should follow.':
      '插件已处于清单版本 [0.1.0]，下方发布策略用于说明下一次升级窗口应遵循的要求。',
    'Generated scaffolds default to a cautious upgrade window so new SQL releases can be audited before traffic resumes.':
      '默认采用谨慎升级窗口，便于在恢复流量前先核对新的数据库脚本版本。',
    'Only plugin-owned runtime files and pay_plugin_forbidden_probe_* tables should be mutated by future releases.':
      '后续版本只应变更插件专属运行文件和插件专属数据表。',
    'Review plugin.json, configSchema(), and cleanup policy before the first production install.':
      '首次正式安装前，请先检查插件清单、配置结构与清理策略。',
    'Capture a Recovery Vault snapshot before introducing destructive schema changes in a later release.':
      '后续如需引入破坏性结构变更，请先创建恢复快照。',
    'Validate callback signature verification, settlement updates, and replay protection for notify.':
      '请验证异步通知的签名校验、结算更新与重放保护逻辑。',
    'After an upgrade, run the capability-specific smoke checks above and re-enable the plugin only after notify and refund checks pass.':
      '升级后请先执行上述能力验证检查，待异步通知与退款校验通过后再重新启用插件。',
    'Initial scaffold release with isolated config/log tables, lifecycle metadata, and cleanup-hook support.':
      '初始版本已包含独立配置表、日志表与清理支持。',
    'Payment request logic is still a stub and must be implemented inside src/Plugin.php before activation.':
      '支付请求逻辑当前仍是占位实现，启用前必须先补齐核心处理逻辑。',
    'Future SQL files should be appended as new releases instead of editing this initial release in place.':
      '后续数据库脚本应以新版本追加，不要直接改写初始版本文件。',
    'Rollback is expected to restore the plugin package, runtime workspace, and plugin-owned tables from backup or Recovery Vault.':
      '回滚时应通过备份或恢复快照恢复插件包、运行目录与插件专属数据表。',
    'Do not attempt a destructive downgrade without a verified restore point.':
      '没有经过验证的恢复点时，不要尝试破坏性降级。',
    'Creates plugin-owned config and log tables under the pay_plugin_forbidden_probe_* namespace.':
      '会创建插件专属配置表和日志表。',
    'Generated scaffolds start with isolated tables so install, purge, and Recovery Vault flows can stay residue-aware from day 1.':
      '默认使用独立数据表，便于安装、彻底清理与恢复时核对残留。',
    'Safe cleanup removes generated runtime artifacts and plugin-owned config rows only after uninstall audit.':
      '安全清理会在卸载确认后移除生成的运行产物和插件专属配置记录。',
    'Purge executes all audited safe-cleanup targets, then removes the plugin package and lifecycle audit artifacts.':
      '彻底清理会先执行已确认的安全清理目标，再移除插件包和运行记录文件。',
    'Purge removes the package directory and plugin-owned log table after explicit confirmation.':
      '彻底清理会在明确确认后移除插件目录和插件专属日志表。',
    'Managed channel row was removed during purge cleanup.': '托管通道记录已在彻底清理中移除。',
    'Managed channel row is currently missing from admin_channel.': '托管通道主表中缺少该记录。',
    'Managed channel row does not use create_type = 2.': '该托管通道记录的创建类型标记不正确。',
    'channel recycle support requires the admin_channel.delete_time migration':
      '当前环境尚未启用通道回收站能力。',
    'The plugin is marked installed, but both the runtime directory and config table need to be rebuilt.':
      '插件虽标记为已安装，但运行目录和配置表都需要重建。',
    'The plugin runtime directory is missing and should be rebuilt.':
      '插件运行目录缺失，需要重新构建。',
    'The plugin config table is missing and should be rebuilt.': '插件配置表缺失，需要重新构建。',
    'The plugin was auto-reconciled after its install assets disappeared. Run repair to rebuild them.':
      '插件安装资源消失后已被自动校准，请执行修复以重建相关资源。'
  }

  if (exactLabels[text]) {
    return cleanupPluginVisibleWords(exactLabels[text])
  }

  let normalized = text

  normalized = normalized.replace(/^managed_channel_smoke$/g, '托管通道恢复快照')
  normalized = normalized.replace(/^smoke-restore-(\d{8})$/g, '恢复快照 $1')
  normalized = normalized.replace(/^global-vault-smoke-(\d{8})$/g, '恢复中心快照 $1')
  normalized = normalized.replace(/^(\d+)_create_config_table\.sql$/g, '配置表初始化脚本 $1')
  normalized = normalized.replace(/^(\d+)_create_plugin_log_table\.sql$/g, '日志表初始化脚本 $1')
  normalized = normalized.replace(
    /Plugin is already on manifest version \[([^\]]+)\]\. The release policy below documents what the next upgrade window should follow\./g,
    '插件已处于清单版本 [$1]，下方发布策略用于说明下一次升级窗口应遵循的要求。'
  )
  normalized = normalized.replace(
    /Installed plugin version \[([^\]]+)\] is behind manifest version \[([^\]]+)\]\. Run upgrade to apply the latest plugin assets\./g,
    '当前已安装版本 [$1] 落后于清单版本 [$2]，请执行升级以同步最新插件资源。'
  )
  normalized = normalized.replace(
    /Installed plugin version \[([^\]]+)\] is behind manifest version \[([^\]]+)\]\. Run upgrade to apply (\d+) pending migration file\(s\)(?: across release\(s\) \[([^\]]+)\])?\./g,
    (_match, installedVersion, manifestVersion, pendingCount, releaseVersions) =>
      releaseVersions
        ? `当前已安装版本 [${installedVersion}] 落后于清单版本 [${manifestVersion}]，请执行升级以应用 ${pendingCount} 个待执行脚本，涉及版本 [${releaseVersions}]。`
        : `当前已安装版本 [${installedVersion}] 落后于清单版本 [${manifestVersion}]，请执行升级以应用 ${pendingCount} 个待执行脚本。`
  )
  normalized = normalized.replace(
    /Cleaned orphan residue for \[([^\]]+)\], removed (\d+) file targets, (\d+) tables, and (\d+) managed channels, retained (\d+) recovery snapshot\(s\)\./g,
    (_match, code, fileCount, tableCount, channelCount, snapshotCount) =>
      `已清理 [${normalizePluginCopy(code)}] 的孤立残留，共移除 ${fileCount} 个文件目标、${tableCount} 张数据表、${channelCount} 条托管通道，并保留 ${snapshotCount} 个恢复快照。`
  )
  normalized = normalized.replace(
    /Completed safe cleanup: removed (\d+) file target\(s\), (\d+) table\(s\), and (\d+) row\(s\) after the plugin cleanup hook reported (\d+) step\(s\)\./g,
    '安全清理已完成：移除 $1 个文件目标、$2 张数据表、$3 行数据，清理流程共执行 $4 个步骤。'
  )
  normalized = normalized.replace(
    /Installed plugin version ([0-9.]+) and left it disabled for validation\./g,
    '已安装插件版本 $1，并保持停用以便后续验证。'
  )
  normalized = normalized.replace(
    /Marked plugin uninstalled and deferred cleanup to the safe cleanup flow\./g,
    '已将插件标记为卸载，并把清理动作延后到安全清理流程中。'
  )
  normalized = normalized.replace(
    /Restored plugin assets from recovery snapshot ([a-f0-9_]+) \[([^\]]+)\]\./g,
    (_match, _snapshotId, label) => `已从恢复快照 [${normalizePluginCopy(label)}] 恢复插件资源。`
  )
  normalized = normalized.replace(
    /Captured a recovery snapshot \[([^\]]+)\]\./g,
    (_match, label) => `已创建恢复快照 [${normalizePluginCopy(label)}]。`
  )
  normalized = normalized.replace(
    /Channel is still referenced by (\d+) payment account\(s\)\./g,
    '该通道仍被 $1 个支付账号引用。'
  )
  normalized = normalized.replace(
    /Channel is still referenced by (\d+) pool item\(s\)\./g,
    '该通道仍被 $1 个通道池项目引用。'
  )
  normalized = normalized.replace(
    /Channel still has (\d+) historical order\(s\) linked through payment accounts\./g,
    '该通道仍通过支付账号关联 $1 条订单记录。'
  )
  normalized = normalized.replace(
    /Managed channel sync drift detected: (\d+) missing and (\d+) drifted channel row\(s\)\. Run repair to resync plugin-owned channel metadata\./g,
    '检测到托管通道同步漂移：缺失 $1 条、漂移 $2 条。请执行修复以重新同步插件通道元数据。'
  )
  normalized = normalized.replace(
    /The current manifest version still has (\d+) unapplied migration file\(s\)\. Run repair to reconcile plugin-owned database assets\./g,
    '当前清单版本仍有 $1 个未执行脚本，请执行修复以校准插件所属数据库资源。'
  )
  normalized = normalized.replace(
    /Installed plugin version \[([^\]]+)\] is behind manifest version \[([^\]]+)\]\. Run upgrade to apply the latest plugin assets\./g,
    '已安装插件版本 [$1] 落后于清单版本 [$2]，请执行升级以应用最新插件资源。'
  )
  normalized = normalized.replace(
    /Installed plugin version \[([^\]]+)\] is behind manifest version \[([^\]]+)\]\. Run upgrade to apply (\d+) pending migration file\(s\)( across release\(s\) \[([^\]]+)\])?\./g,
    (_match, currentVersion, manifestVersion, fileCount, _segment, releases) => {
      const releaseSuffix = releases ? `，涉及版本 [${releases}]` : ''
      return `已安装插件版本 [${currentVersion}] 落后于清单版本 [${manifestVersion}]，请执行升级以应用 ${fileCount} 个待执行脚本${releaseSuffix}。`
    }
  )
  normalized = normalized.replace(/认证联调/g, '认证测试')
  normalized = normalized.replace(/平台改造联调/g, 'AiPay官方测试')
  normalized = normalized.replace(/禁测探针联调插件/g, '禁测探针插件')
  normalized = normalized.replace(/易支付兼容插件/g, '易支付协议插件')
  normalized = normalized.replace(/易支付兼容/g, '易支付协议')
  normalized = normalized.replace(
    /用于旧版系统迁移期间承接历史支付流程的兼容插件。/g,
    '用于接入易支付协议的插件。'
  )
  normalized = normalized.replace(
    /已通过运行目录、配置与迁移检查，并启用插件路由。/g,
    '已通过运行目录、配置和版本检查，并启用插件路由。'
  )
  normalized = normalized.replace(
    /生成的脚手架默认采用谨慎升级窗口，以便在恢复流量前先审计新的数据库脚本版本。/g,
    '默认采用谨慎升级窗口，便于在恢复流量前先核对新的数据库脚本版本。'
  )
  normalized = normalized.replace(
    /安全清理会在卸载审计后移除生成的运行产物和插件专属配置记录。/g,
    '安全清理会在卸载确认后移除生成的运行产物和插件专属配置记录。'
  )
  normalized = normalized.replace(
    /彻底清理会先执行已审计的安全清理目标，再移除插件包和生命周期审计文件。/g,
    '彻底清理会先执行已确认的安全清理目标，再移除插件包和运行记录文件。'
  )
  normalized = normalized.replace(
    /通道回收依赖主通道表删除时间字段迁移。/g,
    '当前环境尚未启用通道回收站能力。'
  )
  normalized = normalized.replace(/托管通道联调快照/g, '托管通道恢复快照')
  normalized = normalized.replace(/联调恢复快照/g, '恢复快照')
  normalized = normalized.replace(/注册残留联调快照/g, '注册残留恢复快照')
  normalized = normalized.replace(/注册残留联调/g, '注册残留检查')

  return cleanupPluginVisibleWords(normalized)
}

export const pluginCodeSummary = (code: string | null | undefined) => {
  const normalized = String(code || '').trim()
  if (!normalized) {
    return '--'
  }

  const legacyProfile = legacyProfileForCode(normalized)
  if (legacyProfile?.title) {
    return legacyProfile.title
  }

  const normalizedCopy = normalizePluginCopy(normalized)
  return normalizedCopy && normalizedCopy !== normalized ? normalizedCopy : normalized
}

export const formatBytes = (value: number | null) => {
  if (value === null || value < 0) {
    return '--'
  }
  if (value < 1024) {
    return `${value} 字节`
  }
  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} 千字节`
  }
  return `${(value / (1024 * 1024)).toFixed(1)} 兆字节`
}

export const snapshotDisplayTitle = (
  label: string | null | undefined,
  createdAt: string | null | undefined,
  snapshotId: string | null | undefined
) => {
  const normalizedLabel = normalizePluginCopy(label)
  if (label && normalizedLabel !== label) {
    return normalizedLabel
  }
  if (label && /[\u4e00-\u9fff]/.test(label)) {
    return label
  }
  if (createdAt) {
    return `恢复快照 ${createdAt}`
  }
  return snapshotId ? `恢复快照 ${snapshotId}` : '恢复快照'
}

export const resourceTargetLabel = (target: string | null | undefined) => {
  const normalized = String(target || '').trim()
  if (!normalized) return '--'
  if (normalized.startsWith('runtime/payment-plugins/')) return '插件运行目录'
  if (normalized.startsWith('runtime/payment-plugin-audit/')) return '运行记录目录'
  if (normalized.startsWith('runtime/payment-plugin-snapshots/')) return '快照归档目录'
  if (normalized.startsWith('plugins/payments/')) return '插件源码目录'
  return normalizePluginCopy(normalized)
}

export const tableTargetLabel = (table: string | null | undefined) => {
  const normalized = String(table || '').trim()
  if (!normalized) return '--'
  if (/^pay_plugin_[a-z0-9_]+_config$/i.test(normalized)) return '插件配置表'
  if (/^pay_plugin_[a-z0-9_]+_log$/i.test(normalized)) return '插件日志表'
  if (/^pay_plugin_[a-z0-9_]+_/i.test(normalized)) return '插件数据表'
  return normalizePluginCopy(normalized)
}

export const pluginWorkspaceLabel = (profile: PaymentPluginLegacyProfile) => {
  if (profile.workspace === 'account') return '收款账号填写'
  if (profile.workspace === 'merchant-channel') return '商户通道填写'
  return '字段参考'
}

export const pluginAccessLabel = (code: string | null | undefined) => {
  const profile = legacyProfileForCode(code)
  if (profile?.workspace === 'account') return '账号配置'
  if (profile?.workspace === 'merchant-channel') return '通道配置'
  return '独立接入'
}

const fallbackPluginPaymentFilters = (
  code: string | null | undefined
): Array<Exclude<PluginPaymentFilterKey, 'all'>> => {
  const normalized = String(code || '')
    .trim()
    .toLowerCase()

  if (normalized === 'universal_epay') {
    return ['alipay', 'wxpay', 'qqpay']
  }

  if (normalized.startsWith('alipay_') || normalized === 'jiaofeiyi_alipay') return ['alipay']
  if (normalized.startsWith('wxpay_') || normalized === 'jiaofeiyi_wxpay') return ['wxpay']
  if (normalized.startsWith('qqpay_')) return ['qqpay']
  if (normalized === 'usdt') return ['usdt']
  return ['other']
}

const normalizePluginPaymentFilterKey = (
  value: string | null | undefined
): Exclude<PluginPaymentFilterKey, 'all'> | '' => {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()

  if (!normalized) {
    return ''
  }

  if (normalized === 'alipay') return 'alipay'
  if (normalized === 'wxpay' || normalized === 'wechat' || normalized === 'weixin') return 'wxpay'
  if (normalized === 'qqpay' || normalized === 'qq') return 'qqpay'
  if (normalized === 'usdt') return 'usdt'
  return ''
}

export const resolvePluginPaymentFilters = (
  source: string | PluginPaymentSource | null | undefined
): Array<Exclude<PluginPaymentFilterKey, 'all'>> => {
  const payload =
    typeof source === 'string'
      ? { code: source, supported_payment_types: [] }
      : source || { code: '', supported_payment_types: [] }

  const declaredTypes = Array.isArray(payload.supported_payment_types)
    ? payload.supported_payment_types
    : []

  const keys = Array.from(
    new Set(
      declaredTypes
        .map((value) => normalizePluginPaymentFilterKey(value))
        .filter((value): value is Exclude<PluginPaymentFilterKey, 'all'> => Boolean(value))
    )
  )

  if (keys.length > 0) {
    return keys
  }

  return fallbackPluginPaymentFilters(payload.code)
}

export const resolvePluginPaymentFilter = (
  source: string | PluginPaymentSource | null | undefined
): Exclude<PluginPaymentFilterKey, 'all'> => {
  return resolvePluginPaymentFilters(source)[0] || 'other'
}

export const pluginPaymentLabels = (
  source: string | PluginPaymentSource | null | undefined
): string[] => {
  return resolvePluginPaymentFilters(source).map((key) => PLUGIN_PAYMENT_LABELS[key] || '其他')
}

export const pluginPaymentLabel = (
  source: string | PluginPaymentSource | null | undefined
) => {
  return pluginPaymentLabels(source).join(' / ') || '其他'
}

export const pluginPaymentTagType = (
  label: string
): 'success' | 'warning' | 'info' | 'primary' | 'danger' => {
  if (label === '支付宝') return 'primary'
  if (label === '微信') return 'success'
  if (label === 'QQ') return 'warning'
  if (label === 'USDT') return 'danger'
  return 'info'
}

export const pluginWorkspaceButtonLabel = (profile: PaymentPluginLegacyProfile) => {
  if (profile.workspace === 'account') return '去收款账号'
  if (profile.workspace === 'merchant-channel') return '去商户通道'
  return ''
}

export const auditTagType = (
  health: PaymentPluginStateAudit['health']
): 'success' | 'info' | 'warning' | 'danger' => {
  if (health === 'healthy') return 'success'
  if (health === 'warning') return 'warning'
  return 'danger'
}

export const auditLabel = (health: PaymentPluginStateAudit['health']) => {
  if (health === 'healthy') return '正常'
  if (health === 'warning') return '待关注'
  return '需处理'
}

export const auditSummaryLabel = (audit: PaymentPluginStateAudit) => {
  if (audit.repair_recommended) {
    return audit.issues.length > 0 ? `建议修复（${audit.issues.length} 项）` : '建议修复'
  }

  if (audit.upgrade_recommended) {
    return audit.issues.length > 0 ? `建议升级（${audit.issues.length} 项）` : '建议升级'
  }

  if (audit.issues.length > 0) {
    return `${auditLabel(audit.health)} (${audit.issues.length})`
  }

  return auditLabel(audit.health)
}

export function overviewAuditTone(detail: PaymentPluginDetail): PluginOverviewCardTone {
  if (detail.state_audit.repair_recommended || detail.state_audit.health === 'drifted')
    return 'danger'
  if (detail.state_audit.upgrade_recommended || detail.state_audit.issues.length > 0)
    return 'warning'
  return 'success'
}

export function overviewConfigTone(detail: PaymentPluginDetail): PluginOverviewCardTone {
  if (legacyProfileForCode(detail.manifest.code)) return 'info'
  if (!detail.state_audit.config_table_exists) return 'danger'
  if (
    !detail.state_audit.required_config_ready ||
    detail.config_summary.missing_required_fields > 0
  ) {
    return 'warning'
  }
  return 'success'
}

export function overviewConfigValue(detail: PaymentPluginDetail): string {
  const profile = legacyProfileForCode(detail.manifest.code)
  if (profile) {
    return `${profile.fields.length} 项`
  }
  if (!detail.state_audit.config_table_exists) return '配置表缺失'
  if (detail.config_summary.missing_required_fields > 0) {
    return `缺 ${detail.config_summary.missing_required_fields} 项`
  }
  return '已完成'
}

export function overviewMigrationTone(detail: PaymentPluginDetail): PluginOverviewCardTone {
  if (detail.state_audit.upgrade_recommended || detail.state_audit.pending_migration_files > 0) {
    return 'warning'
  }
  if (detail.migration_audit.drifted_file_count > 0) return 'danger'
  return detail.state_audit.migration_journal_exists ? 'success' : 'info'
}

export function overviewMigrationValue(detail: PaymentPluginDetail): string {
  if (detail.state_audit.upgrade_recommended) {
    return `待升级 ${detail.state_audit.registry_version} -> ${detail.state_audit.manifest_version}`
  }
  if (detail.state_audit.pending_migration_files > 0) {
    return `待执行脚本 ${detail.state_audit.pending_migration_files}`
  }
  return detail.state_audit.migration_journal_exists ? '正常' : '待初始化'
}

export function overviewChannelTone(detail: PaymentPluginDetail): PluginOverviewCardTone {
  if (detail.state_audit.managed_channel_missing_count > 0) return 'danger'
  if (detail.state_audit.managed_channel_drift_count > 0) return 'warning'
  return detail.state_audit.managed_channel_count > 0 ? 'success' : 'info'
}

export function overviewChannelValue(detail: PaymentPluginDetail): string {
  if (detail.state_audit.managed_channel_count <= 0) return '未配置'
  return `${detail.state_audit.managed_channel_existing_count}/${detail.state_audit.managed_channel_count}`
}

export const managedChannelDriftCount = (channel: PaymentPluginManagedChannelAudit) =>
  Object.keys(channel.drift || {}).length

export const cleanupModeLabel = (mode: string | null | undefined) => {
  const normalized = String(mode || '')
    .trim()
    .toLowerCase()
  if (normalized === 'safe') return '安全清理'
  if (normalized === 'purge') return '彻底清理'
  return '清理'
}

export const cleanupItemTagType = (
  item: PaymentPluginCleanupReportItem
): 'success' | 'info' | 'warning' | 'danger' => {
  if (item.removed) {
    return 'success'
  }
  if (item.kind === 'missing') {
    return 'info'
  }

  return 'warning'
}

export const managedChannelCleanupSummary = (
  items: PaymentPluginCleanupReportItem[] | null | undefined
) => {
  return (items || []).reduce(
    (carry, item) => {
      if (item.type !== 'managed_channel') {
        return carry
      }

      carry.total++
      if (item.removed) {
        carry.removed++
      } else if (item.kind !== 'missing') {
        carry.blocked++
      }

      return carry
    },
    { total: 0, removed: 0, blocked: 0 }
  )
}

export const residueCleanupActionLabel = (item: PaymentPluginRegistryResidueItem) => {
  if (item.summary.blocked_managed_channel_count > 0) {
    return '清理受阻'
  }

  return item.snapshot_guard.has_snapshot ? '清理残留' : '无快照清理'
}

export const residueManagedChannelBlockSummary = (item: PaymentPluginRegistryResidueItem) => {
  const blocked = item.managed_channel_audit.filter((channel) => !channel.can_cleanup)
  if (blocked.length === 0) {
    return '无'
  }

  return blocked
    .map((channel) => {
      const reason = normalizePluginCopy(channel.blocking_reasons[0] || '清理受阻')
      return `${normalizePluginCopy(channel.code)}：${reason}`
    })
    .join('; ')
}

export const operatorLabel = (
  operator: { nickname?: string; username?: string } | null | undefined
) => operator?.nickname || operator?.username || '系统'

export const capabilityDisplayLabel = (value: string | null | undefined) => {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  const labels: Record<string, string> = {
    create_order: '创建订单',
    query: '订单查询',
    refund: '退款',
    notify: '异步通知',
    admin_account: '后台账户',
    software_collection: '软件版收款',
    merchant_qrcode: '商户二维码',
    gateway_certificate: '证书密钥',
    crypto_wallet: '钱包地址',
    bill_qrcode: '账单二维码',
    remote_gateway: '远程接口'
  }

  return labels[normalized] || value || '--'
}

export const historyActionLabel = (
  action: PaymentPluginHistoryEntry['action'] | string | null | undefined,
  fallback?: string | null
) => {
  const normalized = String(action || '')
    .trim()
    .toLowerCase()
  const labels: Record<string, string> = {
    install: '安装',
    repair: '修复',
    enable: '启用',
    disable: '停用',
    upgrade: '升级',
    uninstall: '卸载',
    uninstall_purge_requested: '彻底清理申请',
    snapshot_restored: '恢复快照',
    snapshot_deleted: '删除快照',
    state_reconciled: '状态对齐',
    registry_residue_cleanup: '注册残留清理',
    snapshot_created: '创建快照',
    safe_cleanup: '安全清理',
    purge_cleanup: '彻底清理',
    config_update: '更新配置'
  }

  if (labels[normalized]) {
    return labels[normalized]
  }

  if (fallback && fallback.trim()) {
    return normalizePluginCopy(fallback)
  }

  return normalized ? normalized.replaceAll('_', ' ') : '--'
}

export const historyActionTagType = (
  action: PaymentPluginHistoryEntry['action']
): 'success' | 'info' | 'warning' | 'danger' | 'primary' => {
  if (action === 'install' || action === 'repair' || action === 'enable') return 'success'
  if (
    action === 'upgrade' ||
    action === 'uninstall_purge_requested' ||
    action === 'snapshot_restored'
  ) {
    return 'warning'
  }
  if (action === 'snapshot_deleted' || action === 'state_reconciled') return 'danger'
  if (action === 'registry_residue_cleanup') return 'danger'
  if (action === 'snapshot_created') return 'primary'
  if (action === 'safe_cleanup') return 'primary'
  return 'info'
}

export const historyStatusTagType = (
  status: PaymentPluginHistoryEntry['status']
): 'success' | 'info' | 'warning' | 'danger' | 'primary' => {
  const normalized = String(status || '')
    .trim()
    .toLowerCase()
  if (normalized === 'success') return 'success'
  if (normalized === 'warning') return 'warning'
  if (normalized === 'error' || normalized === 'failed') return 'danger'
  return 'info'
}

export const historyStatusLabel = (
  status: PaymentPluginHistoryEntry['status'] | string | null | undefined
) => {
  const normalized = String(status || '')
    .trim()
    .toLowerCase()
  if (normalized === 'success') return '成功'
  if (normalized === 'warning') return '警告'
  if (normalized === 'error' || normalized === 'failed') return '失败'
  if (normalized === 'pending' || normalized === 'processing') return '处理中'
  return normalizePluginCopy(status) || '--'
}

export const snapshotPathLabel = (path: string | null | undefined) => (path ? '已归档' : '未归档')

export const retainScopeLabel = (scope: string | null | undefined) => {
  const normalized = String(scope || '')
    .trim()
    .toLowerCase()
  const labels: Record<string, string> = {
    order: '订单',
    orders: '订单',
    recharge: '充值记录',
    recharges: '充值记录',
    recharge_records: '充值记录',
    balance_logs: '资金日志',
    fund_logs: '资金日志',
    money_logs: '资金日志',
    settlement_data: '结算数据',
    settlements: '结算数据',
    notify_traces: '回调轨迹',
    notify_logs: '回调日志',
    audit_logs: '后台日志',
    admin_logs: '后台日志',
    managed_channels: '托管通道',
    pool_items: '池项目',
    merchants: '商户',
    accounts: '账号',
    tickets: '工单',
    'merchant order history': '商户订单记录',
    'recharge records': '充值记录',
    'fund and balance logs': '资金与余额日志',
    'settlement records': '结算记录',
    'notify and audit traces': '回调与运行轨迹'
  }

  if (labels[normalized]) {
    return labels[normalized]
  }

  return scope ? scope.replaceAll('_', ' ') : '--'
}

export const residueLedgerGuardTagType = (
  entry: PaymentPluginRegistryResidueLedgerItem
): 'success' | 'info' | 'warning' | 'danger' => {
  return entry.details?.cleanup_guard_mode === 'without_snapshot' ? 'danger' : 'success'
}

export const residueLedgerGuardLabel = (entry: PaymentPluginRegistryResidueLedgerItem) =>
  entry.details?.cleanup_guard_mode === 'without_snapshot' ? '无快照清理' : '快照保护清理'

export const upgradeImpactLabel = (impact: PaymentPluginUpgradePreview['impact']) => {
  const normalized = String(impact || '')
    .trim()
    .toLowerCase()
  if (normalized === 'low') return '低'
  if (normalized === 'high') return '高'
  if (normalized === 'critical') return '严重'
  if (normalized === 'medium') return '中'
  return normalized || '中'
}

export const downtimeLabel = (downtime: PaymentPluginUpgradePreview['downtime']) => {
  const normalized = String(downtime || '')
    .trim()
    .toLowerCase()
  if (normalized === 'none') return '无需停机'
  if (normalized === 'brief_validation') return '短暂验证窗口'
  if (normalized === 'maintenance_window') return '维护窗口'
  if (normalized === 'manual_window') return '人工操作窗口'
  return normalized.replaceAll('_', ' ') || '短暂验证窗口'
}

export const rollbackPolicySummary = (policy: PaymentPluginRollbackPolicy) => {
  if (policy.automatic) {
    return '当前插件支持自动回滚执行能力。'
  }

  if (policy.supported) {
    return policy.requires_backup
      ? '回滚需要人工执行，并依赖已验证的备份恢复。'
      : '回滚需要人工执行。'
  }

  return '当前版本暂不支持自动回滚。'
}

export const inputTypeForField = (field: PaymentPluginConfigField) => {
  if (field.type === 'password') return 'password'
  if (field.type === 'textarea') return 'textarea'
  return 'text'
}

export const placeholderForField = (field: PaymentPluginConfigField) => {
  if (field.placeholder) {
    return field.placeholder
  }

  if (field.secret && field.configured) {
    return '留空则保留当前值'
  }

  return ''
}

const pluginConfigSectionCatalog = [
  {
    key: 'basic',
    title: '基础接入',
    description: '维护当前插件的常规接入字段和基础参数。'
  },
  {
    key: 'identity',
    title: '身份与密钥',
    description: '集中维护应用身份、商户号、签名密钥和证书字段。'
  },
  {
    key: 'callback',
    title: '地址与回调',
    description: '统一维护接口地址、通知地址和接口域名。'
  },
  {
    key: 'advanced',
    title: '扩展参数',
    description: '放置补充参数、业务字段和场景开关。'
  }
] as const

const resolvePluginConfigSectionKey = (
  field: PaymentPluginConfigField
): PluginConfigSection['key'] => {
  const keyword = `${field.field} ${field.label}`.toLowerCase()

  if (
    /(app_?id|appid|appkey|secret|token|cert|private|public|mch|merchant|partner|pid|uid|sign|serial|license|key)/i.test(
      keyword
    )
  ) {
    return 'identity'
  }

  if (
    /(notify|callback|return|url|uri|endpoint|domain|host|gateway|server|api|webhook)/i.test(
      keyword
    )
  ) {
    return 'callback'
  }

  if (
    /(mode|scene|extra|extend|remark|memo|attach|body|subject|title|version|timeout|switch|status)/i.test(
      keyword
    ) ||
    field.type === 'textarea'
  ) {
    return 'advanced'
  }

  return 'basic'
}

export const buildPluginConfigSections = (
  fields: PaymentPluginConfigField[]
): PluginConfigSection[] => {
  const sectionMap = new Map<PluginConfigSection['key'], PluginConfigSection>()

  for (const config of pluginConfigSectionCatalog) {
    sectionMap.set(config.key, {
      key: config.key,
      title: config.title,
      description: config.description,
      fields: []
    })
  }

  for (const field of fields) {
    const section = sectionMap.get(resolvePluginConfigSectionKey(field))
    if (section) {
      section.fields.push(field)
    }
  }

  return pluginConfigSectionCatalog
    .map((item) => sectionMap.get(item.key)!)
    .filter((item) => item.fields.length > 0)
}

export { type PaymentPluginLegacyProfile }
