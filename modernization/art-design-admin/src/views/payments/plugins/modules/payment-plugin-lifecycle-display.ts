import {
  downtimeLabel,
  rollbackPolicySummary,
  upgradeImpactLabel
} from '@/views/payments/shared/paymentPluginDisplay'

type PaymentPluginBundle = Api.SystemManage.PaymentPluginBundle
type PaymentPluginCleanupReport = Api.SystemManage.PaymentPluginCleanupResponse['cleanup_report']
type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail
type PaymentPluginRegistryResidueCleanupReport =
  Api.SystemManage.PaymentPluginRegistryResidueCleanupResponse['cleanup_report']
type PaymentPluginRegistryResidueItem = Api.SystemManage.PaymentPluginRegistryResidueItem
type PaymentPluginUninstallPlan = Api.SystemManage.PaymentPluginUninstallPlan

export function bundleFilename(bundle: PaymentPluginBundle) {
  const timestamp = bundle.generated_at.replaceAll('-', '').replaceAll(':', '').replaceAll(' ', '_')

  return `${bundle.plugin_code}-record-bundle-${timestamp}.json`
}

export function restoreConfirmationPhrase(code: string) {
  return `确认恢复 ${code}`
}

export function deleteSnapshotConfirmationPhrase(snapshotId: string) {
  return `确认删除快照 ${snapshotId}`
}

export function purgeGuardForDetail(detail: PaymentPluginDetail | null) {
  return detail?.purge_plan.snapshot_guard || null
}

function purgeConfirmationPhrase(code: string) {
  return `确认彻底清理 ${code}`
}

function purgeWithoutSnapshotConfirmationPhrase(code: string) {
  return `无快照彻底清理 ${code}`
}

export function purgeConfirmationPhraseForDetail(detail: PaymentPluginDetail | null) {
  const code = detail?.manifest.code || ''
  const guard = purgeGuardForDetail(detail)
  if (guard && !guard.has_snapshot) {
    return (
      guard.missing_snapshot_confirmation_phrase || purgeWithoutSnapshotConfirmationPhrase(code)
    )
  }

  return guard?.purge_confirmation_phrase || purgeConfirmationPhrase(code)
}

function cleanupResidueConfirmationPhrase(code: string) {
  return `确认清理残留 ${code}`
}

function cleanupResidueWithoutSnapshotConfirmationPhrase(code: string) {
  return `无快照清理残留 ${code}`
}

export function cleanupResiduePhraseForItem(item: PaymentPluginRegistryResidueItem) {
  if (!item.snapshot_guard.has_snapshot) {
    return (
      item.snapshot_guard.cleanup_without_snapshot_confirmation_phrase ||
      cleanupResidueWithoutSnapshotConfirmationPhrase(item.plugin_code)
    )
  }

  return (
    item.snapshot_guard.cleanup_confirmation_phrase ||
    cleanupResidueConfirmationPhrase(item.plugin_code)
  )
}

export function escapeRegExp(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export function downloadTextFile(
  filename: string,
  contents: string,
  mimeType = 'application/json'
) {
  const blob = new Blob([contents], { type: `${mimeType};charset=utf-8` })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export function buildPluginConfigFormState(detail: PaymentPluginDetail | null) {
  const nextForm: Record<string, string> = {}
  for (const field of detail?.config_schema || []) {
    nextForm[field.field] = field.value || ''
  }

  return nextForm
}

export function buildPluginConfigPayload(
  detail: PaymentPluginDetail,
  configForm: Record<string, string>
) {
  return Object.fromEntries(
    detail.config_schema.map((field) => [field.field, configForm[field.field] ?? ''])
  )
}

export function buildPluginRepairConfirmationMessage(detail: PaymentPluginDetail) {
  const reason = detail.state_audit.repair_reason || '需要重建插件专属安装资源。'
  const pendingMigrationLine =
    detail.migration_audit.pending_file_count > 0
      ? `待执行数据库脚本：${detail.migration_audit.pending_file_count}`
      : '待执行数据库脚本：0（本次修复只会校准现有资源）'

  return [
    '修复会重新执行插件初始化流程。',
    '它会补建缺失的运行目录、配置表和默认配置骨架，不会删除业务数据。',
    pendingMigrationLine,
    `原因：${reason}`
  ].join('\n')
}

export function buildPluginUpgradeConfirmationMessage(detail: PaymentPluginDetail) {
  const reason =
    detail.state_audit.upgrade_reason ||
    `当前已安装版本 ${detail.state_audit.registry_version} 落后于清单版本 ${detail.state_audit.manifest_version}。`
  const preview = detail.upgrade_preview
  const pendingReleaseSummary =
    preview.pending_release_versions.join(', ') || detail.manifest.version
  const checklistSummary =
    preview.checklist.length > 0 ? `检查项：${preview.checklist.join(' / ')}` : null
  const noteSummary = preview.notes.length > 0 ? `说明：${preview.notes.join(' / ')}` : null

  return [
    '升级只会应用仍处于待执行状态的数据库脚本，并完成插件升级流程。',
    `影响等级：${upgradeImpactLabel(preview.impact)}`,
    `验证窗口：${downtimeLabel(preview.downtime)}`,
    `升级后状态：${preview.requires_disable_after_upgrade ? '需重新校验后再启用' : '可继续使用'}`,
    `待执行数据库脚本：${preview.pending_migration_files}`,
    `待处理版本：${pendingReleaseSummary}`,
    `回滚策略：${rollbackPolicySummary(preview.rollback)}`,
    checklistSummary,
    noteSummary,
    `原因：${reason}`
  ]
    .filter(Boolean)
    .join('\n')
}

export function buildPluginUninstallConfirmationMessage(
  plan: PaymentPluginUninstallPlan,
  purgePlan: PaymentPluginUninstallPlan
) {
  return [
    '该操作会将插件标记为未安装，并保留业务数据。',
    `安全清理文件：${plan.files.length}，安全清理数据表：${plan.tables.length}`,
    `彻底清理是否需要二次确认：${purgePlan.requires_confirmation ? '是' : '否'}`
  ].join('\n')
}

export function buildPluginSafeCleanupConfirmationMessage(detail: PaymentPluginDetail) {
  return [
    '只会处理清理清单中声明过的安全插件资源。',
    `运行目录目标：${detail.uninstall_plan.summary.existing_file_count}`,
    `配置表：${detail.uninstall_plan.summary.existing_table_count}`,
    `托管通道：${detail.uninstall_plan.summary.existing_managed_channel_count}（可清理 ${detail.uninstall_plan.summary.deletable_managed_channel_count}，阻塞 ${detail.uninstall_plan.summary.blocked_managed_channel_count}）`,
    `待清理数据行：${detail.uninstall_plan.summary.table_row_count}`
  ].join('\n')
}

export function buildPluginPurgeCleanupPrompt(detail: PaymentPluginDetail, phrase: string) {
  const guard = purgeGuardForDetail(detail)
  const missingSnapshot = Boolean(guard && !guard.has_snapshot)

  return [
    '该操作具有破坏性，无法在后台界面中直接撤销。',
    '它会移除已确认的插件专属配置表、日志表、运行目录、运行记录、托管通道记录以及插件目录。',
    missingSnapshot
      ? '当前没有恢复快照。继续后，插件目录和插件专属数据表可能无法再通过后台恢复。'
      : `可用恢复快照：${guard?.snapshot_total || 0}`,
    `现存文件：${detail.purge_plan.summary.existing_file_count}`,
    `现存数据表：${detail.purge_plan.summary.existing_table_count}`,
    `托管通道：${detail.purge_plan.summary.existing_managed_channel_count}（可清理 ${detail.purge_plan.summary.deletable_managed_channel_count}，阻塞 ${detail.purge_plan.summary.blocked_managed_channel_count}）`,
    `待删除数据行：${detail.purge_plan.summary.table_row_count}`,
    `请输入 ${phrase} 后继续。`
  ].join('\n')
}

export function buildPluginCleanupSuccessMessage(
  report: PaymentPluginCleanupReport,
  prefix: '安全清理完成' | '彻底清理完成'
) {
  return [
    `${prefix}：共移除 ${report.removed_file_count} 个文件目标、${report.removed_table_count} 张数据表、${Number(report.removed_managed_channel_count || 0)} 条托管通道`,
    report.plugin_hook.executed ? `清理流程执行 ${report.plugin_hook.steps.length} 步` : null
  ]
    .filter(Boolean)
    .join(', ')
}

export function buildRegistryResidueCleanupSuccessMessage(
  report: PaymentPluginRegistryResidueCleanupReport
) {
  return [
    `注册表残留已清理：共移除 ${report.removed_file_count} 个文件目标、${report.removed_table_count} 张数据表、${Number(report.removed_managed_channel_count || 0)} 条托管通道`,
    report.snapshot_retained ? `保留 ${report.retained_snapshot_count} 个恢复快照` : null
  ]
    .filter(Boolean)
    .join(', ')
}
