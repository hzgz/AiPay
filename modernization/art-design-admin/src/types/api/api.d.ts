/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

/**
 * API 接口类型定义模块
 *
 * 提供所有后端接口的类型定义
 *
 * ## 主要功能
 *
 * - 通用类型（分页参数、响应结构等）
 * - 认证类型（登录、用户信息等）
 * - 系统管理类型（用户、角色等）
 * - 全局命名空间声明
 *
 * ## 使用场景
 *
 * - API 请求参数类型约束
 * - API 响应数据类型定义
 * - 接口文档类型同步
 *
 * ## 注意事项
 *
 * - 在 `.vue` 文件中使用时，需要在 `eslint.config.mjs` 中配置 `globals: { Api: 'readonly' }`
 * - 使用全局命名空间，无需导入即可使用
 *
 * ## 使用方式
 *
 * ```typescript
 * const params: Api.Auth.LoginParams = { userName: 'admin', password: '123456' }
 * const response: Api.Auth.UserInfo = await fetchUserInfo()
 * ```
 *
 * @module types/api/api
 * @author AiPay
 */

declare namespace Api {
  /** 通用类型 */
  namespace Common {
    /** 分页参数 */
    interface PaginationParams {
      /** 当前页码 */
      current: number
      /** 每页条数 */
      size: number
      /** 总条数 */
      total: number
    }

    /** 通用搜索参数 */
    type CommonSearchParams = Pick<PaginationParams, 'current' | 'size'>

    /** 分页响应基础结构 */
    interface PaginatedResponse<T = any> {
      records: T[]
      current: number
      size: number
      total: number
    }

    /** 启用状态 */
    type EnableStatus = '1' | '2'
  }

  /** 认证类型 */
  namespace Auth {
    /** 登录参数 */
    interface LoginParams {
      userName: string
      password: string
    }

    /** 登录响应 */
    interface LoginResponse {
      token: string
      refreshToken: string
    }

    /** 用户信息 */
    interface UserInfo {
      buttons: string[]
      roles: string[]
      userId: number
      userName: string
      email: string
      avatar?: string
    }
  }

  /** 系统管理类型 */
  namespace SystemManage {
    /** 用户列表 */
    type UserList = Api.Common.PaginatedResponse<UserListItem>

    /** 用户列表项 */

    interface UserListItem {
      id: number
      avatar: string
      status: string
      userName: string
      userGender: string
      nickName: string
      userPhone: string
      userEmail: string
      userRoles: string[]
      createBy: string
      createTime: string
      updateBy: string
      updateTime: string
    }

    /** 用户搜索参数 */

    type UserSearchParams = Partial<
      Pick<UserListItem, 'id' | 'userName' | 'userGender' | 'userPhone' | 'userEmail' | 'status'> &
        Api.Common.CommonSearchParams
    >

    /** 角色列表 */

    type RoleList = Api.Common.PaginatedResponse<RoleListItem>

    /** 角色列表项 */

    interface RoleListItem {
      roleId: number
      roleName: string
      roleCode: string
      description: string
      enabled: boolean
      createTime: string
    }

    /** 角色搜索参数 */

    type RoleSearchParams = Partial<
      Pick<RoleListItem, 'roleId' | 'roleName' | 'roleCode' | 'description' | 'enabled'> &
        Api.Common.CommonSearchParams & {
          startTime: string | null
          endTime: string | null
        }
    >

    interface ProcessWorkerRecord {
      pid: number
      parent_pid: number | null
      name: string
      executable_path: string | null
      command_line: string | null
      started_at: string | null
    }

    interface ProcessListenerRecord {
      local_address: string | null
      local_port: number | null
      owning_process: number | null
      state: string | null
    }

    interface ProcessDefinition {
      key: string
      title: string
      scope: 'core' | 'plugin' | 'payment_plugin_manifest' | string
      source: string
      handler: string
      listen: string | null
      configured_workers: number | null
      reloadable: boolean | null
      plugin_code: string | null
      plugin_name: string | null
      running: boolean
      process_count: number
      workers: ProcessWorkerRecord[]
      listening: boolean
      listeners: ProcessListenerRecord[]
    }

    interface ProcessRuntimeFile {
      key: string
      label: string
      path: string
      exists: boolean
      size: number | null
      updated_at: string | null
    }

    interface ProcessEnvironment {
      os_family: string
      php_binary: string
      project_root: string
      runtime_root: string
      server_listen: string
      windows_runtime_directory: string
    }

    interface ProcessSummary {
      core_total: number
      core_running_total: number
      core_worker_total: number
      plugin_total: number
      plugin_running_total: number
      payment_plugin_total: number
      payment_plugin_manifest_process_total: number
      supervisor_total: number
    }

    interface ProcessDuplicateCleanupPreview {
      can_cleanup: boolean
      strategy: string
      summary: string
      keep_supervisor_pid: number | null
      keep_supervisor: ProcessWorkerRecord | null
      keep_workers: ProcessWorkerRecord[]
      remove_supervisors: ProcessWorkerRecord[]
      remove_workers: ProcessWorkerRecord[]
      remove_supervisor_pids: number[]
      remove_worker_pids: number[]
      current_webman_worker_total: number
      expected_webman_worker_total: number
      warnings: string[]
    }

    interface ProcessOverviewResponse {
      generated_at: string
      summary: ProcessSummary
      environment: ProcessEnvironment
      duplicate_cleanup: ProcessDuplicateCleanupPreview
      supervisors: {
        count: number
        items: ProcessWorkerRecord[]
      }
      core_processes: ProcessDefinition[]
      plugin_processes: ProcessDefinition[]
      payment_plugin_manifest_processes: ProcessDefinition[]
      runtime_files: ProcessRuntimeFile[]
    }

    interface PaymentPluginCleanupPolicy {
      safe_files: string[]
      safe_tables: string[]
      retain_scopes: string[]
      purge_requires_confirmation: boolean
    }

    interface PaymentPluginItem {
      code: string
      name: string
      description: string
      version: string
      provider: string
      directory: string
      installed: boolean
      enabled: boolean
      status: string
      installed_at: string | null
      updated_at: string | null
      capabilities: string[]
      merchant_enabled: boolean
      supported_payment_types: string[]
      state_audit: PaymentPluginStateAudit
      cleanup_policy: PaymentPluginCleanupPolicy
    }

    interface PaymentPluginListResponse {
      items: PaymentPluginItem[]
      registry_residue: PaymentPluginRegistryResidueResponse
      registry_residue_ledger: PaymentPluginRegistryResidueLedgerResponse
    }

    interface PaymentPluginScaffoldCreated {
      plugin_code: string
      plugin_name: string
      version: string
      provider: string
      description: string
      plugin_directory: string
      namespace: string
      class: string
      config_table: string
      log_table: string
      runtime_directory: string
      capabilities: string[]
      files: string[]
    }

    interface PaymentPluginScaffoldResponse {
      created: PaymentPluginScaffoldCreated
      detail: PaymentPluginDetail
    }

    interface PaymentPluginRegistryResidueGuard {
      snapshot_total: number
      has_snapshot: boolean
      latest_snapshot_id: string | null
      latest_snapshot_label: string | null
      latest_snapshot_created_at: string | null
      cleanup_confirmation_phrase: string
      cleanup_without_snapshot_confirmation_phrase: string
      warning: string | null
    }

    interface PaymentPluginRegistryResidueItem {
      plugin_code: string
      catalog_available: boolean
      current_state: PaymentPluginStateSnapshot
      runtime_audit: PaymentPluginPlanFileAudit
      history_audit: PaymentPluginPlanFileAudit
      plugin_directory_audit: PaymentPluginPlanFileAudit
      table_audit: PaymentPluginPlanTableAudit[]
      managed_channel_audit: PaymentPluginManagedChannelAudit[]
      snapshot_guard: PaymentPluginRegistryResidueGuard
      summary: {
        existing_file_target_count: number
        existing_table_count: number
        table_row_count: number
        existing_managed_channel_count: number
        deletable_managed_channel_count: number
        blocked_managed_channel_count: number
      }
    }

    interface PaymentPluginRegistryResidueResponse {
      summary: {
        total_items: number
        runtime_residue_count: number
        history_residue_count: number
        snapshot_backed_count: number
        table_residue_count: number
        table_row_count: number
        managed_channel_residue_count: number
        blocked_managed_channel_count: number
      }
      items: PaymentPluginRegistryResidueItem[]
    }

    interface PaymentPluginRegistryResidueLedgerItem {
      id: string
      plugin_code: string
      action: string
      label: string
      status: string
      created_at: string
      operator: {
        id: number | null
        username: string
        nickname: string
      } | null
      summary: string | null
      details: {
        mode: string
        cleanup_guard_mode: 'snapshot_backed' | 'without_snapshot' | string
        snapshot_retained: boolean
        retained_snapshot_count: number
        registry_removed: boolean
        removed_file_count: number
        removed_table_count: number
        removed_row_count: number
        removed_managed_channel_count: number
        existing_file_target_count: number
        existing_table_count: number
        table_row_count: number
        existing_managed_channel_count: number
        blocked_managed_channel_count: number
        runtime_exists: boolean
        history_exists: boolean
        plugin_directory_exists: boolean
        latest_snapshot_id: string | null
        latest_snapshot_label: string | null
        latest_snapshot_created_at: string | null
      } | null
    }

    interface PaymentPluginRegistryResidueLedgerResponse {
      ledger_path: string
      summary: {
        total_events: number
        visible_items: number
        without_snapshot_count: number
        snapshot_retained_count: number
        removed_file_count: number
        removed_table_count: number
        removed_row_count: number
        removed_managed_channel_count: number
        latest_event_at: string | null
      }
      items: PaymentPluginRegistryResidueLedgerItem[]
    }

    interface PaymentPluginState {
      installed: boolean
      enabled: boolean
      status: string
      version: string
      installed_at: string | null
      enabled_at: string | null
      disabled_at: string | null
      uninstalled_at: string | null
      updated_at: string | null
      last_action: string | null
      last_operator: {
        id: number | null
        username: string
        nickname: string
      } | null
      hook_execution: string
      cleanup_execution: string
      last_uninstall_plan: PaymentPluginUninstallPlan | null
      last_cleanup_report: PaymentPluginCleanupReport | null
    }

    interface PaymentPluginManifest {
      code: string
      name: string
      description: string
      version: string
      provider: string
      entry: string
      class: string
      directory: string
      capabilities: string[]
      merchant_enabled: boolean
      supported_payment_types: string[]
      managed_channels: PaymentPluginManagedChannelDefinition[]
      migrations: PaymentPluginManifestMigrations
      upgrade: PaymentPluginManifestUpgrade
      cleanup: {
        safe: {
          files: string[]
          tables: string[]
          notes: string[]
        }
        purge: {
          files: string[]
          tables: string[]
          notes: string[]
        }
        retain: string[]
        purge_requires_confirmation: boolean
      }
    }

    interface PaymentPluginManagedChannelDefinition {
      code: string
      name: string
      type: string
      info: string | null
      status: number
      sort: number
      maxcount: number
    }

    interface PaymentPluginManagedChannelRow {
      id?: number
      code: string
      name: string
      type: string
      info: string | null
      status: number | string
      sort: number | string
      maxcount: number | string
      create_type: number | string
      create_time?: string | null
      delete_time?: string | null
    }

    interface PaymentPluginManagedChannelDependencySummary {
      account_count: number
      merchant_count: number
      online_account_count: number
      enabled_account_count: number
      pool_count: number
      pool_item_count: number
      order_count: number
      paid_order_count: number
      paid_amount: number
      latest_account_time: string | null
      latest_order_time: string | null
    }

    interface PaymentPluginManagedChannelDriftItem {
      expected: string | number | null
      actual: string | number | null
    }

    interface PaymentPluginManagedChannelAudit {
      code: string
      declared: boolean
      exists: boolean
      deleted: boolean
      can_cleanup: boolean
      mode: string
      row: PaymentPluginManagedChannelRow | null
      definition: PaymentPluginManagedChannelDefinition | null
      drift: Record<string, PaymentPluginManagedChannelDriftItem>
      blocking_reasons: string[]
      dependency_summary: PaymentPluginManagedChannelDependencySummary
    }

    interface PaymentPluginConfigField {
      field: string
      label: string
      type: string
      required: boolean
      value: string | null
      configured: boolean
      secret: boolean
      masked_value: string | null
      placeholder: string | null
    }

    interface PaymentPluginConfigSummary {
      total_fields: number
      configured_fields: number
      required_fields: number
      missing_required_fields: number
    }

    interface PaymentPluginManifestUpgrade {
      impact: 'low' | 'medium' | 'high' | 'critical' | string
      downtime: string
      requires_disable_after_upgrade: boolean
      notes: string[]
      checklist: string[]
      changelog: PaymentPluginUpgradeChangelogEntry[]
      rollback: PaymentPluginRollbackPolicy
    }

    interface PaymentPluginUpgradeChangelogEntry {
      version: string
      summary: string
      breaking: boolean
      migration_files: string[]
      notes: string[]
    }

    interface PaymentPluginRollbackPolicy {
      supported: boolean
      mode: string
      automatic: boolean
      requires_backup: boolean
      notes: string[]
    }

    interface PaymentPluginManifestMigrations {
      strategy: string
      directory: string
      releases: PaymentPluginMigrationRelease[]
    }

    interface PaymentPluginMigrationRelease {
      version: string
      files: string[]
      notes: string[]
    }

    interface PaymentPluginMigrationAuditRelease {
      version: string
      files: string[]
      notes: string[]
      file_count: number
      applied_files: number
      pending_files: number
      applied: boolean
      pending: boolean
    }

    interface PaymentPluginMigrationAuditEntry {
      file: string
      release_version: string
      relative_path: string
      absolute_path: string
      exists: boolean
      checksum: string | null
      applied: boolean
      recorded: boolean
      status: string | null
      source: string | null
      applied_at: string | null
      checksum_matches: boolean | null
    }

    interface PaymentPluginMigrationAudit {
      strategy: string
      journal_exists: boolean
      journal_path: string
      baseline_version: string | null
      baseline_recorded: boolean
      baseline_at: string | null
      manifest_version: string
      installed_version: string
      release_count: number
      applied_file_count: number
      pending_file_count: number
      drifted_file_count: number
      applied_release_versions: string[]
      pending_release_versions: string[]
      last_applied_at: string | null
      releases: PaymentPluginMigrationAuditRelease[]
      entries: PaymentPluginMigrationAuditEntry[]
    }

    interface PaymentPluginUpgradePreview {
      available: boolean
      from_version: string
      to_version: string
      impact: 'low' | 'medium' | 'high' | 'critical' | string
      downtime: string
      requires_disable_after_upgrade: boolean
      notes: string[]
      checklist: string[]
      pending_migration_files: number
      pending_release_versions: string[]
      breaking_change_count: number
      summary: string
      changelog: PaymentPluginUpgradeChangelogEntry[]
      rollback: PaymentPluginRollbackPolicy
    }

    interface PaymentPluginStateAudit {
      health: 'healthy' | 'warning' | 'drifted'
      issues: string[]
      runtime_exists: boolean
      runtime_kind: 'directory' | 'file' | 'missing'
      config_table: string
      config_table_exists: boolean
      config_table_rows: number | null
      managed_channel_count: number
      managed_channel_existing_count: number
      managed_channel_missing_count: number
      managed_channel_drift_count: number
      required_config_ready: boolean
      missing_required_fields: string[]
      registry_installed: boolean
      registry_enabled: boolean
      registry_status: string
      registry_version: string
      manifest_version: string
      version_matches: boolean
      effective_installed: boolean
      effective_enabled: boolean
      effective_status: string
      reconciled: boolean
      reconciled_actions: string[]
      migration_journal_exists: boolean
      pending_migration_files: number
      pending_migration_releases: string[]
      drifted_migration_files: number
      repair_recommended: boolean
      repair_reason: string | null
      upgrade_recommended: boolean
      upgrade_reason: string | null
    }

    interface PaymentPluginUninstallPlan {
      plugin_code: string
      mode: string
      will_execute_now: boolean
      execution_mode: string
      requires_confirmation: boolean
      files: string[]
      tables: string[]
      file_audit: PaymentPluginPlanFileAudit[]
      table_audit: PaymentPluginPlanTableAudit[]
      managed_channel_audit: PaymentPluginManagedChannelAudit[]
      summary: PaymentPluginPlanSummary
      snapshot_guard: PaymentPluginSnapshotGuard
      retain_scopes: string[]
      notes: string[]
    }

    interface PaymentPluginSnapshotGuard {
      mode: string
      snapshot_total: number
      has_snapshot: boolean
      latest_snapshot_id: string | null
      latest_snapshot_label: string | null
      latest_snapshot_created_at: string | null
      purge_confirmation_phrase: string
      missing_snapshot_confirmation_phrase: string
      warning: string | null
    }

    interface PaymentPluginPlanFileAudit {
      target: string
      absolute_path: string
      exists: boolean
      kind: 'directory' | 'file' | 'missing'
      entry_count: number | null
      size_bytes: number | null
    }

    interface PaymentPluginPlanTableAudit {
      table: string
      exists: boolean
      row_count: number | null
    }

    interface PaymentPluginPlanSummary {
      existing_file_count: number
      existing_table_count: number
      table_row_count: number
      managed_channel_count: number
      existing_managed_channel_count: number
      deletable_managed_channel_count: number
      blocked_managed_channel_count: number
    }

    interface PaymentPluginCleanupReportItem {
      type: 'file' | 'table' | 'managed_channel'
      target: string
      removed: boolean
      kind: 'directory' | 'file' | 'table' | 'row' | 'missing'
      reason: string | null
      row_count: number | null
    }

    interface PaymentPluginCleanupHookReport {
      supported: boolean
      executed: boolean
      mode: string
      summary: string | null
      steps: string[]
      metadata: Record<string, any> | null
    }

    interface PaymentPluginCleanupReport {
      mode: string
      removed_file_count: number
      removed_table_count: number
      removed_row_count: number
      removed_managed_channel_count: number
      items: PaymentPluginCleanupReportItem[]
      plugin_hook: PaymentPluginCleanupHookReport
      finished_at: string
    }

    interface PaymentPluginRegistryResidueCleanupReport {
      mode: string
      removed_file_count: number
      removed_table_count: number
      removed_row_count: number
      removed_managed_channel_count: number
      items: PaymentPluginCleanupReportItem[]
      registry_removed: boolean
      snapshot_retained: boolean
      retained_snapshot_count: number
      finished_at: string
    }

    interface PaymentPluginRegistryResidueCleanupResponse {
      plugin_code: string
      cleanup_report: PaymentPluginRegistryResidueCleanupReport
      registry_residue: PaymentPluginRegistryResidueResponse
      registry_residue_ledger: PaymentPluginRegistryResidueLedgerResponse
      recovery_vault: PaymentPluginRecoveryVaultResponse
    }

    interface PaymentPluginHistoryEntry {
      id: string
      action: string
      label: string
      status: string
      created_at: string
      operator: {
        id: number | null
        username: string
        nickname: string
      } | null
      summary: string | null
      details: Record<string, any> | null
      state_snapshot: {
        installed: boolean
        enabled: boolean
        status: string
        version: string
        installed_at: string | null
        enabled_at: string | null
        disabled_at: string | null
        uninstalled_at: string | null
        updated_at: string | null
        last_action: string | null
        hook_execution: string
        cleanup_execution: string
      }
    }

    interface PaymentPluginHistory {
      plugin_code: string
      history_path: string
      total: number
      items: PaymentPluginHistoryEntry[]
    }

    interface PaymentPluginMigrationJournalEntry {
      file: string
      release_version: string
      checksum: string | null
      status: string
      source: string
      applied_at: string | null
    }

    interface PaymentPluginMigrationJournal {
      plugin: string
      strategy: string
      baseline_version: string | null
      baseline_at: string | null
      updated_at: string | null
      entries: PaymentPluginMigrationJournalEntry[]
    }

    interface PaymentPluginBundle {
      plugin_code: string
      generated_at: string
      paths: {
        plugin_directory: string
        runtime_directory: string
        history_path: string
        migration_journal_path: string
        snapshot_directory: string
      }
      detail: PaymentPluginDetail
      migration_journal: PaymentPluginMigrationJournal
    }

    interface PaymentPluginSnapshotSummary {
      file_root_count: number
      existing_file_root_count: number
      archived_file_count: number
      archived_directory_count: number
      archived_bytes: number
      table_count: number
      existing_table_count: number
      managed_channel_count: number
      existing_managed_channel_count: number
      row_count: number
    }

    interface PaymentPluginStateSnapshot {
      installed: boolean
      enabled: boolean
      status: string
      version: string
      installed_at: string | null
      enabled_at: string | null
      disabled_at: string | null
      uninstalled_at: string | null
      updated_at: string | null
      last_action: string | null
      hook_execution: string
      cleanup_execution: string
    }

    interface PaymentPluginSnapshotItem {
      snapshot_id: string
      label: string | null
      created_at: string | null
      operator: {
        id: number | null
        username: string
        nickname: string
      } | null
      manifest_version: string
      snapshot_path: string
      size_bytes: number | null
      state_snapshot: PaymentPluginStateSnapshot
      summary: PaymentPluginSnapshotSummary
    }

    interface PaymentPluginSnapshotList {
      plugin_code: string
      snapshot_directory: string
      total: number
      items: PaymentPluginSnapshotItem[]
    }

    interface PaymentPluginRecoveryVaultItem extends PaymentPluginSnapshotItem {
      plugin_code: string
      plugin_name: string
      provider: string
      catalog_available: boolean
      runtime_available: boolean
      history_available: boolean
      current_state: PaymentPluginStateSnapshot
      restorable: boolean
      restore_blocked_reason: string | null
    }

    interface PaymentPluginRecoveryVaultResponse {
      snapshot_root: string
      summary: {
        total_snapshots: number
        plugin_count: number
        catalog_missing_count: number
        restore_ready_count: number
      }
      items: PaymentPluginRecoveryVaultItem[]
    }

    interface PaymentPluginDetail {
      manifest: PaymentPluginManifest
      state: PaymentPluginState
      state_audit: PaymentPluginStateAudit
      migration_audit: PaymentPluginMigrationAudit
      upgrade_preview: PaymentPluginUpgradePreview
      managed_channels: PaymentPluginManagedChannelAudit[]
      config_schema: PaymentPluginConfigField[]
      config_summary: PaymentPluginConfigSummary
      uninstall_plan: PaymentPluginUninstallPlan
      purge_plan: PaymentPluginUninstallPlan
      history: PaymentPluginHistory
    }

    interface PaymentPluginCleanupResponse {
      plugin_code: string
      detail: PaymentPluginDetail | null
      cleanup_report: PaymentPluginCleanupReport
      plugin_removed_from_catalog: boolean
    }

    interface PaymentPluginSnapshotActionResponse {
      detail: PaymentPluginDetail
      snapshot: PaymentPluginSnapshotItem
    }

    interface PaymentPluginSnapshotDeleteResponse {
      plugin_code: string
      deleted_snapshot_id: string
      catalog_available: boolean
      detail: PaymentPluginDetail | null
      snapshots: PaymentPluginSnapshotList
    }
  }

  namespace Users {
    type UserList = Api.Common.PaginatedResponse<UserListItem>

    interface UserListItem {
      id: number
      avatar: string
      status: string
      userName: string
      userGender: string
      nickName: string
      userPhone: string
      userEmail: string
      userRoles: string[]
      createBy: string
      createTime: string | null
      updateBy: string
      updateTime: string | null
      merchant_name: string
      name: string
      email: string
      mobile: string
      money: number
      balance: number
      fee_rate: number | null
      fee_rate_display: string
      vip_id: number
      vip_name: string
      vip_status_label: string
      vip_expire_time: string | null
      is_vip: boolean
      is_frozen: boolean
      status_label: string
      status_type: string
      real_name_verified: boolean
      real_name_status_label: string
      appkey: string
      loginfailure: number
      timeout_time: number
      is_rate: boolean
      order_count: number
      paid_order_count: number
      paid_amount: number
      today_paid_amount: number
      last_order_time: string | null
      frozen_reason: string | null
      remarks: string | null
      superior_id: number | null
      order_tips: string
      order_tips_label: string
      low_balance_tips: string
      low_balance_tips_label: string
      low_balance_threshold: string
      wxpusher_uid_configured: boolean
      wxpusher_uid_masked: string | null
      tg_chat_id_configured: boolean
      tg_chat_id_masked: string | null
    }

    interface UserVipOption {
      value: number
      label: string
      fee_rate: string
      vip_days: number
      status: number
      disabled: boolean
    }

    interface UserNotificationChannelOption {
      value: string
      label: string
      enabled: boolean
      target_ready: boolean
      requires: string | null
      help_text: string | null
    }

    interface UserEditable {
      email: string
      mobile: string
      remarks: string
      status: number
      frozen_reason: string
      vip_id: number
      vip_time: string
      fee_rate: string
      is_rate: number
      vip_options: UserVipOption[]
      order_tips: string
      is_money_tips: string
      money_tips: string
      notification_channel_options: UserNotificationChannelOption[]
    }

    interface UserCreateEditable {
      username: string
      password: string
      email: string
      mobile: string
      remarks: string
      vip_id: number
      vip_time: string
      fee_rate: string
      is_rate: number
      vip_options: UserVipOption[]
    }

    interface UserDeleteAuditItem {
      key: string
      label: string
      table_name: string
      column_name: string
      count: number
      delete_action: 'delete' | 'block' | 'release'
      help_text: string
    }

    interface UserDeleteAuditSummary {
      delete_row_count: number
      non_empty_target_count: number
      blocking_reference_count: number
    }

    interface UserDeleteAudit {
      merchant_id: number
      merchant_username: string
      confirmation_phrase: string
      can_delete: boolean
      blocking_reasons: string[]
      related_counts: UserDeleteAuditItem[]
      summary: UserDeleteAuditSummary
      warnings: string[]
    }

    interface UserImpersonationAudit {
      merchant_id: number
      merchant_username: string
      can_impersonate: boolean
      target_url: string
      warnings: string[]
      possible_redirects: string[]
      system_security_enabled: boolean
      security_force_enabled: boolean
      security_login_enabled: boolean
      googlekey_configured: boolean
    }

    interface UserBatchDeleteAuditItem {
      merchant_id: number
      merchant_username: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      summary: UserDeleteAuditSummary
      warnings: string[]
      related_counts: UserDeleteAuditItem[]
    }

    interface UserBatchDeleteAuditSummary {
      requested_count: number
      existing_count: number
      deletable_count: number
      blocked_count: number
      missing_count: number
      delete_row_count: number
      non_empty_target_count: number
      blocking_reference_count: number
    }

    interface UserBatchDeleteAudit {
      requested_merchant_ids: number[]
      deletable_merchant_ids: number[]
      blocked_merchant_ids: number[]
      missing_merchant_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: UserBatchDeleteAuditItem[]
      summary: UserBatchDeleteAuditSummary
      warnings: string[]
    }

    type UserEmailScope = 'merchant' | 'vip' | 'all' | 'direct'

    interface UserEmailAuditRecipient {
      merchant_id: number | null
      merchant_username: string
      email: string
      label: string
      reason: string | null
    }

    interface UserEmailAuditSmtp {
      enabled: boolean
      configured: boolean
      ready: boolean
      host: string
      port: number | null
      secure: string
      from_address: string
      from_name: string
    }

    interface UserEmailAudit {
      scope: UserEmailScope
      scope_label: string
      selected_merchant_ids: number[]
      confirmation_phrase: string
      can_send: boolean
      recipient_total: number
      deliverable_total: number
      skipped_total: number
      warnings: string[]
      smtp: UserEmailAuditSmtp
      sample_recipients: UserEmailAuditRecipient[]
      skipped_recipients: UserEmailAuditRecipient[]
    }

    interface UserEmailAuditPayload {
      scope: UserEmailScope
      merchant_ids?: number[]
      email?: string
    }

    interface UserEmailSendPayload extends UserEmailAuditPayload {
      title: string
      content: string
      confirmation_phrase: string
    }

    interface UserEmailFailureItem {
      merchant_id: number | null
      merchant_username: string
      email: string
      label: string
      error: string
    }

    interface UserEmailSendSummary {
      attempted_count: number
      sent_count: number
      failed_count: number
      skipped_count: number
    }

    interface UserDetailResponse {
      item: UserListItem
      editable: UserEditable
    }

    interface UserCreateTemplateResponse {
      editable: UserCreateEditable
    }

    interface UserCreatePayload {
      username: string
      password: string
      email: string
      mobile: string
      remarks: string
      vip_id: number | string | null
      vip_time?: string | null
      fee_rate?: string | null
      is_rate: number | boolean | string
    }

    interface UserUpdatePayload {
      email: string
      mobile: string
      remarks: string
    }

    interface UserStatusUpdatePayload {
      status: number | boolean | string
      frozen_reason?: string | null
    }

    interface UserBusinessUpdatePayload {
      vip_id: number | string | null
      vip_time?: string | null
      fee_rate?: string | null
      is_rate: number | boolean | string
    }

    interface UserNotificationUpdatePayload {
      order_tips: string
      is_money_tips: string
      money_tips: string
    }

    interface UserDeletePayload {
      confirmation_phrase: string
    }

    interface UserImpersonationExecutePayload {}

    interface UserBatchDeleteAuditPayload {
      merchant_ids: number[]
    }

    interface UserBatchDeletePayload extends UserBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface UserDeleteAuditResponse {
      item: UserListItem
      audit: UserDeleteAudit
    }

    interface UserBatchDeleteAuditResponse {
      audit: UserBatchDeleteAudit
    }

    interface UserImpersonationAuditResponse {
      item: UserListItem
      audit: UserImpersonationAudit
    }

    interface UserEmailAuditResponse {
      audit: UserEmailAudit
    }

    interface UserCreateResponse extends UserDetailResponse {}

    interface UserUpdateResponse extends UserDetailResponse {}

    interface UserStatusUpdateResponse {
      item: UserListItem | null
    }

    interface UserBusinessUpdateResponse extends UserDetailResponse {}

    interface UserNotificationUpdateResponse extends UserDetailResponse {}

    interface UserDeleteResponse {
      deleted_user_id: number
      deleted_username: string
      audit: UserDeleteAudit
    }

    interface UserImpersonationExecuteResponse {
      merchant_id: number
      merchant_username: string
      redirect_url: string
      target_url: string
      expires_at: string | null
      audit: UserImpersonationAudit
    }

    interface UserBatchDeleteResponse {
      deleted_user_ids: number[]
      deleted_count: number
      audit: UserBatchDeleteAudit
    }

    interface UserEmailSendResponse {
      title: string
      audit: UserEmailAudit
      summary: UserEmailSendSummary
      failures: UserEmailFailureItem[]
    }

    type UserSearchParams = Partial<
      Pick<UserListItem, 'status'> &
        Api.Common.CommonSearchParams & {
          keyword: string
          realname_status: string
          vip_status: string
        }
    >
  }

  namespace Payments {
    type MethodList = Api.Common.PaginatedResponse<MethodListItem>
    type ChannelList = Api.Common.PaginatedResponse<ChannelListItem>
    type AccountList = Api.Common.PaginatedResponse<AccountListItem> & {
      summary: AccountSummary
    }
    type PoolList = Api.Common.PaginatedResponse<PoolListItem> & {
      summary: PoolSummary
    }
    type ChannelCatalogList = Api.Common.PaginatedResponse<ChannelCatalogListItem> & {
      summary: ChannelCatalogSummary
    }

    interface StatusUpdatePayload {
      status: number | boolean
    }

    interface MethodEditable {
      name: string
      type: string
      sort: string
      status: number
    }

    interface ChannelEditable {
      name: string
      type?: string
      url: string
      pid: string
      key: string
      other: string
      payment_method_type?: string
      payment_method_label?: string
      plugin_code?: string
      plugin_name?: string
      status: number
    }

    interface ChannelCreatePayload {
      user_id?: number | string
      type?: string
      payment_method_type?: string
      plugin_code?: string
      name: string
      url: string
      pid?: string
      key?: string
      other?: string
      status?: number | boolean | string
    }

    interface AccountEditable {
      memo: string
      daymaxcount: string
      daymaxmoney: string
      allmaxcount: string
      allmaxmoney: string
      status: number
      is_status: number
      code: string
      credential_supported: boolean
      pid: string
      identifier: string
      qr_type: string
      qr_url: string
      cookie: string
      remark: string
      wx_guid: string
      cloud_id: string
      extra_value: string
    }

    interface AccountCreatePayload {
      user_id: number | string
      payment_method_type?: string
      plugin_code?: string
      code: string
      identifier: string
      pid?: string
      qr_type?: string
      qr_url?: string
      cookie?: string
      memo?: string
      remark?: string
      wx_guid?: string
      cloud_id?: string
      extra_value?: string
      daymaxcount?: number | string
      daymaxmoney?: string
      allmaxcount?: number | string
      allmaxmoney?: string
      status?: number | boolean
      is_status?: number | boolean
    }

    interface PoolEditable {
      name: string
      type: string
      round_type: number
      status: number
    }

    interface MethodListItem {
      id: number
      name: string
      type: string
      type_text?: string
      type_label: string
      sort: number
      status: number
      status_text?: string
      status_label: string
      status_type: string
      create_time: string | null
      update_time: string | null
      delete_time: string | null
      deleted: boolean
      order_count: number
      paid_order_count: number
      paid_amount: number
      enabled_account_count: number
      online_account_count: number
      account_count: number
    }

    interface MethodDeleteAudit {
      payment_id: number
      payment_label: string
      type: string
      type_label: string
      status: number
      status_label: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
        account_count: number
        enabled_account_count: number
        online_account_count: number
      }
    }

    interface MethodBatchDeleteAuditItem {
      payment_id: number
      payment_label: string
      type: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        order_count: number
        paid_order_count: number
        account_count: number
      }
      warnings: string[]
    }

    interface MethodBatchDeleteAudit {
      requested_payment_ids: number[]
      deletable_payment_ids: number[]
      blocked_payment_ids: number[]
      missing_payment_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: MethodBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        order_count: number
        paid_order_count: number
        account_count: number
      }
      warnings: string[]
    }

    interface ChannelListItem {
      id: number
      user_id: number
      scope: string
      scope_text?: string
      scope_label: string
      merchant_username: string
      merchant_display: string
      type: string
      gateway_text?: string
      gateway_label: string
      status: number
      status_text?: string
      status_label: string
      status_type: string
      name: string
      url: string
      url_host: string | null
      pid_preview: string | null
      has_key: boolean
      has_other: boolean
      create_time: string | null
      order_count: number
      paid_order_count: number
      paid_amount: number
      latest_order_time: string | null
      payment_method_type?: string
      payment_method_label?: string
      plugin_code?: string
      plugin_name?: string
      channel_type?: string
    }

    interface ChannelDeleteAudit {
      channel_id: number
      channel_label: string
      merchant_display: string
      scope: string
      scope_label: string
      type: string
      gateway_label: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
      }
    }

    interface ChannelBatchDeleteAuditItem {
      channel_id: number
      channel_label: string
      merchant_display: string
      scope: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
      }
    }

    interface ChannelBatchDeleteAudit {
      requested_channel_ids: number[]
      deletable_channel_ids: number[]
      blocked_channel_ids: number[]
      missing_channel_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: ChannelBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
      }
      warnings: string[]
    }

    interface AccountListItem {
      id: number
      code: string
      code_label: string
      type: string
      type_text?: string
      type_label: string
      type_tag: string
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_display: string
      identifier: string | null
      identifier_source: string
      identifier_masked: string | null
      has_identifier: boolean
      identifier_length: number
      status: number
      status_text?: string
      status_label: string
      status_type: string
      is_status: number
      is_status_text?: string
      is_status_label: string
      is_status_type: string
      qr_type: string | null
      qr_type_text?: string
      qr_type_label: string
      has_qr_url: boolean
      qr_url_length: number
      has_cookie: boolean
      cookie_length: number
      has_remark: boolean
      has_wx_guid: boolean
      has_cloud_id: boolean
      credential_ready: boolean
      allmaxcount: number
      allmaxmoney: string | null
      daymaxcount: number
      daymaxmoney: string | null
      account_balance: number
      memo: string | null
      memo_text?: string
      memo_label: string
      create_time: string | null
      update_time: string | null
      order_count: number
      paid_order_count: number
      pending_order_count: number
      paid_amount: number
      latest_order_time: string | null
    }

    interface AccountDeleteAudit {
      account_id: number
      account_label: string
      merchant_display: string
      channel_label: string
      type: string
      type_label: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
        pool_count: number
        pool_item_count: number
        last_selected_pool_count: number
        active_last_selected_pool_count: number
      }
    }

    interface AccountBatchDeleteAuditItem {
      account_id: number
      account_label: string
      merchant_display: string
      channel_label: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
        pool_count: number
        pool_item_count: number
        last_selected_pool_count: number
      }
    }

    interface AccountBatchDeleteAudit {
      requested_account_ids: number[]
      deletable_account_ids: number[]
      blocked_account_ids: number[]
      missing_account_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: AccountBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
        pool_count: number
        pool_item_count: number
        last_selected_pool_count: number
      }
      warnings: string[]
    }

    interface PoolListItem {
      id: number
      name: string
      name_label: string
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_display: string
      type: string
      type_text?: string
      type_label: string
      type_tag: string
      round_type: number
      round_type_text?: string
      round_type_label: string
      round_type_tag: string
      status: number
      status_text?: string
      status_label: string
      status_type: string
      current_index: number
      current_weight: number
      progress_label: string
      last_account_id: number
      last_account_label: string | null
      item_count: number
      active_item_count: number
      disabled_item_count: number
      missing_item_count: number
      total_weight: number
      has_items: boolean
      pool_state_text?: string
      pool_state_label: string
      pool_state_type: string
      selected_preview_text?: string
      selected_preview: string
      create_time: string | null
      update_time: string | null
      latest_item_time: string | null
      readonly_note_text?: string
      readonly_note: string
    }

    interface PoolChannelItem {
      item_id: number
      account_id: number
      account_label: string
      channel_label: string
      weight: number
      sort_order: number
      status_text?: string
      status_label: string
      status_type: string
      account_exists: boolean
      is_last_selected: boolean
      update_time: string | null
    }

    interface PoolChannelEditorAccount {
      account_id: number
      account_label: string
      channel_label: string
      memo: string
      status: number
      is_status: number
      status_text?: string
      status_label: string
      status_type: string
      selected: boolean
      weight: number
      sort_order: number | null
      update_time: string | null
    }

    interface PoolMissingChannelItem {
      account_id: number
      account_label: string
      channel_label: string
      weight: number
      sort_order: number
      status_text?: string
      status_label: string
      status_type: string
      update_time: string | null
    }

    interface PoolChannelEditor {
      available_accounts: PoolChannelEditorAccount[]
      missing_selected_accounts: PoolMissingChannelItem[]
      summary: {
        available_count: number
        selected_count: number
        active_available_count: number
        disabled_available_count: number
        missing_selected_count: number
        total_weight: number
      }
      warnings: string[]
    }

    interface PoolDeleteAudit {
      pool_id: number
      pool_label: string
      merchant_display: string
      type: string
      type_label: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        selected_channel_count: number
        active_selected_channel_count: number
        missing_selected_channel_count: number
        total_weight: number
      }
    }

    interface PoolDetailItem extends PoolListItem {
      selected_items: PoolChannelItem[]
    }

    interface ChannelCatalogListItem {
      id: number
      name: string
      type: string
      type_label: string
      type_tag: string
      payment_name: string | null
      payment_display: string
      create_type: number
      create_type_label: string
      create_type_type: string
      code: string
      info: string | null
      info_preview: string
      has_info: boolean
      status: number
      status_label: string
      status_type: string
      sort: number
      maxcount: number
      account_count: number
      merchant_count: number
      online_account_count: number
      enabled_account_count: number
      pool_count: number
      pool_item_count: number
      order_count: number
      paid_order_count: number
      paid_amount: number
      latest_account_time: string | null
      latest_order_time: string | null
      usage_preview: string
      dependency_preview: string
      create_time: string | null
      delete_time: string | null
      plugin_owner_code: string | null
      plugin_owner_name: string | null
      plugin_catalog_available: boolean
      plugin_owner_label: string | null
      deleted: boolean
      is_local: boolean
      is_plugin_managed: boolean
      has_dependencies: boolean
      can_edit: boolean
      can_delete: boolean
      can_change_type: boolean
      can_change_code: boolean
      lifecycle_mode_label: string
      lifecycle_mode_type: string
      maintenance_note: string
      readonly_note: string
      blocking_reasons: string[]
      warnings: string[]
    }

    interface ChannelCatalogDependencySummary {
      account_count: number
      merchant_count: number
      online_account_count: number
      enabled_account_count: number
      pool_count: number
      pool_item_count: number
      order_count: number
      paid_order_count: number
      paid_amount: number
      latest_account_time: string | null
      latest_order_time: string | null
    }

    interface ChannelCatalogEditable {
      name: string
      type: string
      code: string
      info: string
      sort: number
      maxcount: number
      status: number
      plugin_owner_code: string | null
      plugin_owner_name: string | null
      plugin_catalog_available: boolean
      plugin_owner_label: string | null
      is_local: boolean
      is_plugin_managed: boolean
      has_dependencies: boolean
      can_edit: boolean
      can_delete: boolean
      can_change_type: boolean
      can_change_code: boolean
      blocking_reasons: string[]
      warnings: string[]
      dependency_summary: ChannelCatalogDependencySummary
    }

    interface ChannelCatalogDeleteAudit {
      channel_id: number
      channel_label: string
      name: string
      code: string
      type: string
      type_label: string
      create_type: number
      create_type_label: string
      status: number
      status_label: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      summary: ChannelCatalogDependencySummary & {
        delete_row_count: number
      }
    }

    interface ChannelCatalogDeleteAuditItem {
      channel_id: number
      channel_label: string
      name: string
      code: string
      type: string
      type_label: string
      create_type: number
      create_type_label: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        account_count: number
        merchant_count: number
        pool_count: number
        pool_item_count: number
        order_count: number
        paid_order_count: number
        paid_amount: number
      }
    }

    interface ChannelCatalogBatchDeleteAudit {
      requested_channel_ids: number[]
      deletable_channel_ids: number[]
      blocked_channel_ids: number[]
      missing_channel_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: ChannelCatalogDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        account_count: number
        merchant_count: number
        pool_count: number
        pool_item_count: number
        order_count: number
        paid_order_count: number
      }
      warnings: string[]
    }

    interface AccountSummary {
      online_count: number
      offline_count: number
      enabled_count: number
      disabled_count: number
      identifier_ready_count: number
      credential_ready_count: number
      paid_order_count: number
      paid_amount: number
    }

    interface PoolSummary {
      total_count: number
      merchant_count: number
      enabled_count: number
      disabled_count: number
      configured_pool_count: number
      empty_pool_count: number
      configured_channel_count: number
      healthy_pool_count: number
      generated_at: string
    }

    interface ChannelCatalogSummary {
      total_count: number
      enabled_count: number
      disabled_count: number
      local_count: number
      plugin_count: number
      linked_account_count: number
      online_account_count: number
      pooled_channel_count: number
      paid_order_count: number
      deleted_count: number
      generated_at: string
    }

    interface MethodSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
      type?: string
    }

    type ChannelSearchParams = Partial<
      Pick<ChannelListItem, 'status' | 'type'> &
        Api.Common.CommonSearchParams & {
          keyword: string
          scope: string
        }
    >

    interface AccountSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      user_id?: number | string
      type?: string
      status?: number | string
      is_status?: number | string
      start_date?: string
      end_date?: string
    }

    interface PoolSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      user_id?: number | string
      type?: string
      round_type?: number | string
      status?: number | string
    }

    interface ChannelCatalogSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      type?: string
      create_type?: number | string
      status?: number | string
    }

    interface MethodCreatePayload {
      name: string
      type: string
      sort: string
      status?: number | boolean | string
    }

    interface MethodUpdatePayload {
      name: string
      sort: string
    }

    interface MethodDeletePayload {
      confirmation_phrase: string
    }

    interface MethodBatchDeleteAuditPayload {
      payment_ids?: number[] | string
      ids?: number[] | string
    }

    interface MethodBatchDeletePayload extends MethodBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface MethodBatchRestorePayload {
      payment_ids?: number[] | string
      ids?: number[] | string
    }

    interface ChannelUpdatePayload {
      name: string
      url: string
      pid: string
      key: string
      other: string
    }

    interface ChannelDeletePayload {
      confirmation_phrase: string
    }

    interface ChannelBatchDeleteAuditPayload {
      channel_ids?: number[] | string
      ids?: number[] | string
    }

    interface ChannelBatchDeletePayload extends ChannelBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface AccountUpdatePayload {
      memo: string
      daymaxcount: string
      daymaxmoney: string
      allmaxcount: string
      allmaxmoney: string
    }

    interface AccountCredentialUpdatePayload {
      identifier: string
      pid?: string
      qr_type?: string
      qr_url?: string
      cookie?: string
      remark?: string
      wx_guid?: string
      cloud_id?: string
      extra_value?: string
    }

    interface AccountStatusUpdatePayload {
      status?: number | boolean
      is_status?: number | boolean
    }

    interface AccountDeletePayload {
      confirmation_phrase: string
    }

    interface AccountBatchDeleteAuditPayload {
      account_ids?: number[] | string
      ids?: number[] | string
    }

    interface AccountBatchDeletePayload extends AccountBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface PoolUpdatePayload {
      name: string
      round_type: number | string
    }

    interface PoolCreatePayload {
      user_id: number | string
      name: string
      type: string
      round_type: number | string
      status: number | boolean
    }

    interface PoolStatusUpdatePayload {
      status: number | boolean
    }

    interface PoolChannelSavePayload {
      channels: Array<{
        account_id: number
        weight: number
        sort?: number
      }>
    }

    interface PoolDeletePayload {
      confirmation_phrase: string
    }

    interface ChannelCatalogWritePayload {
      name: string
      type: string
      code: string
      info?: string
      sort?: number | string
      maxcount?: number | string
      status?: number | boolean
    }

    interface ChannelCatalogStatusUpdatePayload {
      status: number | boolean
    }

    interface ChannelCatalogDeletePayload {
      confirmation_phrase: string
    }

    interface ChannelCatalogRestorePayload {}

    interface ChannelCatalogBatchDeleteAuditPayload {
      channel_ids?: number[] | string
      ids?: number[] | string
    }

    interface ChannelCatalogBatchDeletePayload extends ChannelCatalogBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface ChannelCatalogBatchRestorePayload {
      channel_ids?: number[] | string
      ids?: number[] | string
    }

    interface MethodDetailResponse {
      item: MethodListItem | null
      editable: MethodEditable
    }

    interface MethodCreateResponse extends MethodDetailResponse {
      created_payment_id: number
      created_payment_label: string
    }

    interface MethodDeleteAuditResponse {
      item: MethodListItem | null
      audit: MethodDeleteAudit
    }

    interface MethodDeleteResponse {
      deleted_payment_id: number
      deleted_payment_label: string
      audit: MethodDeleteAudit
    }

    interface MethodRestoreResponse extends MethodDetailResponse {
      restored_payment_id: number
      restored_payment_label: string
    }

    interface MethodBatchDeleteAuditResponse {
      audit: MethodBatchDeleteAudit
    }

    interface MethodBatchDeleteResponse {
      deleted_payment_ids: number[]
      deleted_count: number
      audit: MethodBatchDeleteAudit
    }

    interface MethodBatchRestoreBlockedItem {
      payment_id: number
      payment_label: string
      reason: string
    }

    interface MethodBatchRestoreResponse {
      requested_payment_ids: number[]
      restored_payment_ids: number[]
      restored_count: number
      already_active_payment_ids: number[]
      missing_payment_ids: number[]
      blocked_items: MethodBatchRestoreBlockedItem[]
    }

    interface ChannelDetailResponse {
      item: ChannelListItem | null
      editable: ChannelEditable
    }

    interface ChannelCreateResponse extends ChannelDetailResponse {
      created_channel_id: number
      created_channel_label: string
    }

    interface ChannelDeleteAuditResponse {
      item: ChannelListItem | null
      audit: ChannelDeleteAudit
    }

    interface ChannelDeleteResponse {
      deleted_channel_id: number
      deleted_channel_label: string
      audit: ChannelDeleteAudit
    }

    interface ChannelBatchDeleteAuditResponse {
      audit: ChannelBatchDeleteAudit
    }

    interface ChannelBatchDeleteResponse {
      deleted_channel_ids: number[]
      deleted_count: number
      audit: ChannelBatchDeleteAudit
    }

    interface AccountDetailResponse {
      item: AccountListItem | null
      editable: AccountEditable
    }

    interface AccountCreateResponse extends AccountDetailResponse {
      created_account_id: number
      created_account_label: string
    }

    interface AccountDeleteAuditResponse {
      item: AccountListItem | null
      audit: AccountDeleteAudit
    }

    interface AccountCredentialUpdateResponse extends AccountDetailResponse {}

    interface AccountDeleteResponse {
      deleted_account_id: number
      deleted_account_label: string
      audit: AccountDeleteAudit
    }

    interface AccountBatchDeleteAuditResponse {
      audit: AccountBatchDeleteAudit
    }

    interface AccountBatchDeleteResponse {
      deleted_account_ids: number[]
      deleted_count: number
      audit: AccountBatchDeleteAudit
    }

    interface PoolDetailResponse {
      item: PoolDetailItem | null
      editable: PoolEditable
    }

    interface PoolChannelEditorResponse {
      item: PoolDetailItem | null
      editable: PoolEditable
      editor: PoolChannelEditor
    }

    interface ChannelCatalogDetailResponse {
      item: ChannelCatalogListItem | null
      editable: ChannelCatalogEditable
    }

    interface MethodStatusUpdateResponse {
      item: MethodListItem | null
    }

    interface ChannelStatusUpdateResponse {
      item: ChannelListItem | null
    }

    interface AccountStatusUpdateResponse {
      item: AccountListItem | null
    }

    interface PoolStatusUpdateResponse {
      item: PoolListItem | null
    }

    interface PoolCreateResponse extends PoolDetailResponse {
      created_pool_id: number
      created_pool_label: string
    }

    interface PoolDeleteAuditResponse {
      item: PoolDetailItem | null
      audit: PoolDeleteAudit
    }

    interface PoolDeleteResponse {
      deleted_pool_id: number
      deleted_pool_label: string
      audit: PoolDeleteAudit
    }

    interface PoolChannelSaveResponse {
      item: PoolDetailItem | null
      editable: PoolEditable
      channel_editor: PoolChannelEditor
      saved_channel_count: number
      cleared_channel_count: number
    }

    interface ChannelCatalogCreateResponse extends ChannelCatalogDetailResponse {
      created_channel_id: number
      created_channel_label: string
    }

    interface ChannelCatalogUpdateResponse extends ChannelCatalogDetailResponse {
      updated_channel_id: number
      updated_channel_label: string
    }

    interface ChannelCatalogStatusUpdateResponse extends ChannelCatalogDetailResponse {
      updated_channel_id: number
      updated_channel_label: string
    }

    interface ChannelCatalogDeleteAuditResponse {
      item: ChannelCatalogListItem | null
      audit: ChannelCatalogDeleteAudit
    }

    interface ChannelCatalogDeleteResponse {
      deleted_channel_id: number
      deleted_channel_label: string
      audit: ChannelCatalogDeleteAudit
    }

    interface ChannelCatalogRestoreResponse extends ChannelCatalogDetailResponse {
      restored_channel_id: number
      restored_channel_label: string
    }

    interface ChannelCatalogBatchDeleteAuditResponse {
      audit: ChannelCatalogBatchDeleteAudit
    }

    interface ChannelCatalogBatchDeleteResponse {
      deleted_channel_ids: number[]
      deleted_count: number
      audit: ChannelCatalogBatchDeleteAudit
    }

    interface ChannelCatalogBatchRestoreResponse {
      requested_channel_ids: number[]
      restored_channel_ids: number[]
      restored_count: number
      already_active_channel_ids: number[]
      missing_channel_ids: number[]
      blocked_items: Array<{
        channel_id: number
        channel_label: string
        reason: string
      }>
    }

    interface MethodUpdateResponse extends MethodDetailResponse {}

    interface ChannelUpdateResponse extends ChannelDetailResponse {}

    interface AccountUpdateResponse extends AccountDetailResponse {}

    interface PoolUpdateResponse extends PoolDetailResponse {}
  }

  namespace Configs {
    interface SearchParams {
      keyword?: string
      group?: string
    }

    interface ConfigOption {
      label: string
      value: string
    }

    interface Summary {
      total_keys: number
      filled_keys: number
      empty_keys: number
      masked_keys: number
      matched_keys: number
      group_count: number
      editable_group_count: number
      editable_key_count: number
      editable_filled_count: number
      generated_at: string
    }

    interface GroupOption {
      key: string
      count: number
      filled_count: number
    }

    interface ConfigItem {
      key: string
      label: string
      group: string
      type: string
      value: string
      editable_value?: string
      preview_value: string
      filled: boolean
      masked: boolean
      editable: boolean
      editor: 'input' | 'textarea' | 'switch' | 'password' | 'select' | null
      placeholder: string
      help_text: string
      max_length: number | null
      options: ConfigOption[]
      source: 'database' | 'default'
      length: number
      has_line_breaks: boolean
    }

    interface Group {
      key: string
      item_count: number
      filled_count: number
      items: ConfigItem[]
    }

    interface SummaryResponse {
      summary: Summary
      group_options: GroupOption[]
      groups: Group[]
      filters: SearchParams
      editable_forms: EditableForm[]
    }

    interface UpdatePayload {
      key: string
      value: string | boolean
    }

    interface UpdateResponse {
      item: ConfigItem | null
    }

    interface EditableForm {
      key: string
      title: string
      description: string
      fields: ConfigItem[]
    }

    interface GroupUpdatePayload {
      group: string
      values: Record<string, string | boolean>
    }

    interface GroupUpdateResponse {
      group: string
      items: ConfigItem[]
    }
  }

  namespace Dashboard {
    interface Summary {
      today_order_count: number
      today_paid_order_count: number
      today_paid_amount: number
      today_fee_amount: number
      total_order_count: number
      total_paid_order_count: number
      total_paid_amount: number
      total_fee_amount: number
      pending_order_count: number
      merchant_count: number
      success_rate: number
    }

    interface DutyBoard {
      pending_recharge_count: number
      pending_recharge_amount: number
      new_ticket_count: number
      processing_ticket_count: number
      pending_ticket_count: number
      online_account_count: number
      enabled_account_count: number
    }

    interface Trend {
      labels: string[]
      order_counts: number[]
      paid_order_counts: number[]
      paid_amounts: number[]
    }

    interface PaymentDistributionItem {
      type: string
      label: string
      order_count: number
      paid_order_count: number
      paid_amount: number
      share: number
    }

    interface RecentOrderItem {
      id: number
      name: string
      sitename: string
      trade_no: string
      out_trade_no: string
      upstream_trade_no: string
      user_id: number
      merchant_username: string
      merchant_display: string
      account_id: number
      type: string
      type_text?: string
      type_label: string
      pay_type: number
      channel_text?: string
      channel_label: string
      money: number
      settled_amount: number
      fee_amount: number
      status: number
      status_text?: string
      status_label: string
      status_type: string
      notify_url: string
      return_url: string
      ip: string
      create_time: string | null
      end_time: string | null
      api_memo: string | null
    }

    interface OverviewResponse {
      summary: Summary
      duty_board: DutyBoard
      trend: Trend
      payment_distribution: PaymentDistributionItem[]
      recent_orders: RecentOrderItem[]
      generated_at: string
    }
  }

  namespace CommerceOverview {
    interface Summary {
      total_user_count: number
      total_paid_order_count: number
      total_paid_recharge_count: number
      total_paid_trade_count: number
      total_balance_pool: number
      total_online_account_count: number
      today_new_user_count: number
      today_paid_recharge_count: number
      yesterday_paid_order_count: number
      yesterday_paid_amount: number
      last_week_paid_amount: number
      last_month_paid_amount: number
      qq_online_account_count: number
      wx_online_account_count: number
      alipay_online_account_count: number
    }

    interface PeriodItem {
      key: 'day' | 'month' | 'year'
      label: string
      paid_order_count: number
      total_order_count: number
      unpaid_order_count: number
      success_rate: number
      paid_amount: number
      total_amount: number
    }

    interface Trend {
      labels: string[]
      full_labels: string[]
      total_order_counts: number[]
      paid_order_counts: number[]
      unpaid_order_counts: number[]
    }

    interface ComparisonSeriesItem {
      key: 'wxpay' | 'alipay' | 'qqpay'
      label: string
      data: number[]
    }

    interface ComparisonChart {
      labels: string[]
      series: ComparisonSeriesItem[]
    }

    interface OverviewResponse {
      summary: Summary
      periods: PeriodItem[]
      order_trend: Trend
      collection_comparison: ComparisonChart
      recharge_comparison: ComparisonChart
      readonly_note: string
      generated_at: string
    }
  }

  namespace Orders {
    type OrderList = Api.Common.PaginatedResponse<OrderListItem> & {
      summary: OrderSummary
    }

    interface OrderListItem extends Api.Dashboard.RecentOrderItem {}

    interface OrderSummary {
      total_count: number
      paid_count: number
      pending_count: number
      unknown_status_count: number
      merchant_count: number
      gross_amount: number
      paid_amount: number
      pending_amount: number
      fee_amount: number
      success_rate: number
      generated_at: string
    }

    interface OrderDetailResponse {
      item: OrderListItem
    }

    interface OrderSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
      type?: string
      start_date?: string
      end_date?: string
    }
  }

  namespace Recharges {
    type RechargeList = Api.Common.PaginatedResponse<RechargeListItem> & {
      summary: RechargeSummary
    }

    interface RechargeListItem {
      id: number
      out_trade_no: string
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_email: string | null
      merchant_mobile: string | null
      merchant_display: string
      type: string
      type_text?: string
      type_label: string
      rtype: number
      rtype_text?: string
      rtype_label: string
      money: number
      status: number
      status_text?: string
      status_label: string
      status_type: string
      create_time: string | null
      end_time: string | null
      update_time: string | null
      expires_at: string | null
      timeout_status_text?: string
      timeout_status: string
      is_expired: boolean
      has_qrcode: boolean
      qrcode_preview: string
      qrcode_url: string | null
      has_regdata: boolean
      regdata_preview: string
      regdata_text: string | null
    }

    interface RechargeSummary {
      total_count: number
      paid_count: number
      pending_count: number
      unknown_status_count: number
      merchant_count: number
      merchant_recharge_count: number
      registration_count: number
      expired_pending_count: number
      gross_amount: number
      paid_amount: number
      pending_amount: number
      success_rate: number
      generated_at: string
    }

    interface RechargeDetailResponse {
      item: RechargeListItem
    }

    interface RechargeSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
      type?: string
      rtype?: number | string
      start_date?: string
      end_date?: string
    }
  }

  namespace Roles {
    type RoleList = Api.Common.PaginatedResponse<RoleListItem>

    interface RoleAdminItem {
      id: number
      username: string
      nickname: string
      display: string
      status: number
    }

    interface PermissionTreeItem {
      id: number
      parent_id: number
      title: string
      path: string
      icon: string
      sort: number
      type: number
      type_label: string
      status: number
      status_label: string
      checked: boolean
      children: PermissionTreeItem[]
    }

    interface RoleListItem {
      id: number
      name: string
      code: string
      description: string | null
      scope_label: string
      grants_all_permissions: boolean
      permission_count: number
      total_permission_count: number
      assigned_admin_count: number
      admins: RoleAdminItem[]
      status_label: string
      status_type: string
      create_time: string | null
      update_time: string | null
    }

    interface RoleDetailResponse {
      item: RoleListItem
      permission_tree: PermissionTreeItem[]
      assigned_permission_ids: number[]
    }

    interface RoleWritePayload {
      name: string
      description?: string | null
    }

    interface RolePermissionPayload {
      permission_ids: number[]
    }

    interface RoleDeleteAudit {
      role_id: number
      role_label: string
      role_code: string
      grants_all_permissions: boolean
      assigned_admin_count: number
      assigned_admins: RoleAdminItem[]
      permission_count: number
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_role_row_count: number
        delete_admin_role_row_count: number
        delete_role_permission_row_count: number
        assigned_admin_count: number
        permission_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface RoleCreateResponse {
      item: RoleListItem
      created_role_id: number
      created_role_label: string
    }

    interface RoleUpdateResponse {
      item: RoleListItem
      updated_role_id: number
      updated_role_label: string
    }

    interface RolePermissionUpdateResponse {
      item: RoleListItem
      updated_role_id: number
      updated_role_label: string
      assigned_permission_ids: number[]
      permission_tree: PermissionTreeItem[]
    }

    interface RoleDeleteAuditResponse {
      item: RoleListItem
      audit: RoleDeleteAudit
    }

    interface RoleDeletePayload {
      confirmation_phrase: string
    }

    interface RoleDeleteResponse {
      deleted_role_id: number
      deleted_role_label: string
      audit: RoleDeleteAudit
    }

    interface RoleSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      start_date?: string
      end_date?: string
    }
  }

  namespace AdminAccounts {
    type AdminAccountList = Api.Common.PaginatedResponse<AdminAccountListItem>

    interface AdminAccountRoleItem {
      id: number
      name: string
      description: string | null
      code: string
    }

    interface AdminAccountEditableRoleItem {
      id: number
      name: string
      description: string | null
      code: string
      grants_all_permissions: boolean
      assigned_admin_count: number
    }

    interface AdminAccountListItem {
      id: number
      username: string
      nickname: string
      display: string
      status: number
      status_text?: string
      status_label: string
      status_type: string
      is_root: boolean
      scope_text?: string
      scope_label: string
      roles: AdminAccountRoleItem[]
      role_names: string[]
      role_count: number
      direct_permission_count: number
      effective_permission_count: number
      total_permission_count: number
      permission_coverage_label: string
      token_active: boolean
      create_time: string | null
      update_time: string | null
      deleted: boolean
      delete_time: string | null
    }

    interface AdminAccountEditable {
      username: string
      nickname: string
      status: number
      current_role_ids: number[]
      current_direct_permission_ids: number[]
      available_roles: AdminAccountEditableRoleItem[]
      read_only_reasons: string[]
    }

    interface AdminAccountDetailResponse {
      item: AdminAccountListItem
      roles: AdminAccountRoleItem[]
      permission_tree: Api.Roles.PermissionTreeItem[]
      direct_permission_tree: Api.Roles.PermissionTreeItem[]
      editable: AdminAccountEditable
    }

    interface AdminAccountTemplateResponse {
      editable: AdminAccountEditable
    }

    interface AdminAccountWritePayload {
      username: string
      nickname: string
      password?: string | null
      status?: number | string
      role_ids?: Array<number | string>
    }

    interface AdminAccountStatusPayload {
      status: number | string
    }

    interface AdminAccountRolePayload {
      role_ids: Array<number | string>
    }

    interface AdminAccountPermissionPayload {
      permission_ids: Array<number | string>
    }

    interface AdminAccountBatchDeleteAuditItem {
      admin_id: number
      admin_label: string
      username: string
      nickname: string
      status: number
      status_label: string
      token_active: boolean
      assigned_role_count: number
      direct_permission_count: number
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        recycle_admin_row_count: number
        retained_admin_role_row_count: number
        retained_admin_permission_row_count: number
        retained_admin_log_row_count: number
        assigned_role_count: number
        direct_permission_count: number
        blocked_count: number
      }
    }

    interface AdminAccountBatchDeleteAudit {
      requested_admin_ids: number[]
      deletable_admin_ids: number[]
      blocked_admin_ids: number[]
      missing_admin_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: AdminAccountBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        recycle_admin_row_count: number
        retained_admin_role_row_count: number
        retained_admin_permission_row_count: number
        retained_admin_log_row_count: number
        assigned_role_count: number
        direct_permission_count: number
        active_token_count: number
      }
      warnings: string[]
    }

    interface AdminAccountBatchDeleteAuditPayload {
      admin_ids: number[]
    }

    interface AdminAccountBatchDeletePayload extends AdminAccountBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface AdminAccountBatchRestorePayload extends AdminAccountBatchDeleteAuditPayload {}

    interface AdminAccountDeleteAudit {
      admin_id: number
      admin_label: string
      username: string
      nickname: string
      status: number
      status_label: string
      token_active: boolean
      role_ids: number[]
      roles: AdminAccountRoleItem[]
      assigned_role_count: number
      direct_permission_count: number
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        recycle_admin_row_count: number
        retained_admin_role_row_count: number
        retained_admin_permission_row_count: number
        retained_admin_log_row_count: number
        assigned_role_count: number
        direct_permission_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface AdminAccountCreateResponse {
      item: AdminAccountListItem
      created_admin_id: number
      created_admin_label: string
    }

    interface AdminAccountUpdateResponse {
      item: AdminAccountListItem
      updated_admin_id: number
      updated_admin_label: string
      password_reset: boolean
    }

    interface AdminAccountStatusResponse {
      item: AdminAccountListItem
      updated_admin_id: number
      updated_admin_label: string
      token_cleared: boolean
    }

    interface AdminAccountRoleUpdateResponse {
      item: AdminAccountListItem
      updated_admin_id: number
      updated_admin_label: string
      assigned_role_ids: number[]
      token_cleared: boolean
    }

    interface AdminAccountPermissionUpdateResponse {
      item: AdminAccountListItem
      updated_admin_id: number
      updated_admin_label: string
      assigned_permission_ids: number[]
      token_cleared: boolean
    }

    interface AdminAccountDeleteAuditResponse {
      item: AdminAccountListItem
      audit: AdminAccountDeleteAudit
    }

    interface AdminAccountDeletePayload {
      confirmation_phrase: string
    }

    interface AdminAccountDeleteResponse {
      deleted_admin_id: number
      deleted_admin_label: string
      audit: AdminAccountDeleteAudit
    }

    interface AdminAccountRestoreResponse {
      restored_admin_id: number
      restored_admin_label: string
      item: AdminAccountListItem
    }

    interface AdminAccountBatchDeleteAuditResponse {
      audit: AdminAccountBatchDeleteAudit
    }

    interface AdminAccountBatchDeleteResponse {
      deleted_admin_ids: number[]
      deleted_count: number
      audit: AdminAccountBatchDeleteAudit
    }

    interface AdminAccountBatchRestoreBlockedItem {
      admin_id: number
      admin_label: string
      reason: string
    }

    interface AdminAccountBatchRestoreResponse {
      requested_admin_ids: number[]
      restored_admin_ids: number[]
      restored_count: number
      already_active_admin_ids: number[]
      missing_admin_ids: number[]
      blocked_items: AdminAccountBatchRestoreBlockedItem[]
    }

    interface AdminAccountSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
    }
  }

  namespace Vips {
    type VipList = Api.Common.PaginatedResponse<VipListItem> & {
      summary: VipSummary
    }

    interface VipListItem {
      id: number
      name: string
      icon: string | null
      avatar_frame: string | null
      fee_rate: number | null
      fee_rate_display: string
      money: number
      money_display: string
      vip_days: number
      duration_label: string
      status: number
      status_text?: string
      status_label: string
      status_type: string
      sort: number
      deleted: boolean
      delete_time: string | null
      profit_enabled: boolean
      add_channel_enabled: boolean
      add_channel_num: number
      quota_enabled: boolean
      today_quota: string | null
      month_quota: string | null
      passage_enabled: boolean
      passage_codes: string[]
      passage_count: number
      create_time: string | null
      merchant_count: number
      active_merchant_count: number
      expired_merchant_count: number
    }

    interface VipMerchantItem {
      id: number
      username: string
      name: string | null
      display: string
      vip_time: string | null
      is_active: boolean
      status_text?: string
      status_label: string
      status_type: string
      create_time: string | null
    }

    interface VipSummary {
      total: number
      enabled_count: number
      disabled_count: number
      merchant_count: number
      active_merchant_count: number
    }

    interface VipDetailResponse {
      item: VipListItem
      editable: VipEditable
      merchants: VipMerchantItem[]
    }

    interface VipDeleteAudit {
      vip_id: number
      vip_name: string
      confirmation_phrase: string
      can_delete: boolean
      blocking_reasons: string[]
      linked_merchants: VipMerchantItem[]
      summary: {
        delete_row_count: number
        non_empty_target_count: number
        blocking_reference_count: number
        linked_merchant_count: number
        active_linked_merchant_count: number
        expired_linked_merchant_count: number
      }
      warnings: string[]
    }

    interface VipDeleteAuditResponse {
      item: VipListItem
      audit: VipDeleteAudit
    }

    interface VipDeletePayload {
      confirmation_phrase: string
    }

    interface VipDeleteResponse {
      deleted_vip_id: number
      deleted_vip_name: string
      audit: VipDeleteAudit
    }

    interface VipRestoreResponse extends VipDetailResponse {
      restored_vip_id: number
      restored_vip_name: string
    }

    interface VipCreateTemplateResponse {
      editable: VipEditable
    }

    interface VipBatchDeleteAuditItem {
      vip_id: number
      vip_name: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      linked_merchants: VipMerchantItem[]
      summary: {
        delete_row_count: number
        non_empty_target_count: number
        blocking_reference_count: number
        linked_merchant_count: number
        active_linked_merchant_count: number
        expired_linked_merchant_count: number
      }
      warnings: string[]
    }

    interface VipBatchDeleteAudit {
      requested_vip_ids: number[]
      deletable_vip_ids: number[]
      blocked_vip_ids: number[]
      missing_vip_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: VipBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        non_empty_target_count: number
        blocking_reference_count: number
        linked_merchant_count: number
        active_linked_merchant_count: number
      }
      warnings: string[]
    }

    interface VipBatchDeleteAuditPayload {
      vip_ids: number[]
    }

    interface VipBatchDeletePayload extends VipBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface VipBatchDeleteAuditResponse {
      audit: VipBatchDeleteAudit
    }

    interface VipBatchDeleteResponse {
      deleted_vip_ids: number[]
      deleted_count: number
      audit: VipBatchDeleteAudit
    }

    interface VipBatchRestorePayload extends VipBatchDeleteAuditPayload {}

    interface VipBatchRestoreResponse {
      requested_vip_ids: number[]
      restored_vip_ids: number[]
      restored_count: number
      already_active_vip_ids: number[]
      missing_vip_ids: number[]
    }

    interface VipPassageOption {
      label: string
      value: string
      code: string
      type: string
      status: number
    }

    interface VipPassageOptionGroup {
      label: string
      value: string
      disabled?: boolean
      children: VipPassageOption[]
    }

    interface VipEditable {
      name: string
      money: string
      vip_days: number
      fee_rate: string
      sort: number
      status: number
      profit_enabled: number
      add_channel_enabled: number
      add_channel_num: number
      quota_enabled: number
      today_quota: string
      month_quota: string
      passage_enabled: number
      passage_codes: string[]
      passage_option_groups: VipPassageOptionGroup[]
    }

    interface VipUpdatePayload {
      name: string
      money: string
      vip_days: string | number
      fee_rate: string
      sort: string | number
      profit_enabled: boolean | number
      add_channel_enabled: boolean | number
      add_channel_num: string | number
      quota_enabled: boolean | number
      today_quota: string
      month_quota: string
      passage_enabled: boolean | number
      passage_codes: string[]
    }

    interface VipCreatePayload extends VipUpdatePayload {}

    interface VipStatusUpdatePayload {
      status: boolean | number
    }

    interface VipSortUpdatePayload {
      sort: boolean | number | string
    }

    interface VipReorderPayload {
      visible_vip_ids: number[]
      from_index: number
      to_index: number
    }

    interface VipReorderResponse {
      moved_vip_id: number
      from_index: number
      to_index: number
      updated_count: number
      sort_baseline_reset: boolean
      visible_vip_ids: number[]
    }

    type VipCreateResponse = VipDetailResponse
    type VipUpdateResponse = VipDetailResponse

    interface VipSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
      passage_enabled?: number | string
    }
  }

  namespace Permissions {
    type PermissionMigrationStatus =
      | 'write_enabled'
      | 'read_only'
      | 'pending_write'
      | 'group_split'
      | 'legacy_only'
      | 'unmapped'

    interface PermissionItem {
      id: number
      parent_id: number
      title: string
      path: string
      icon: string
      sort: number
      type: number
      type_label: string
      type_tag: string
      status: number
      status_label: string
      status_type: string
      legacy_module: string | null
      legacy_action: string | null
      modern_group_title: string | null
      modern_menu_title: string | null
      modern_route_name: string | null
      modern_route_path: string | null
      modern_component: string | null
      migration_status: PermissionMigrationStatus
      migration_status_label: string
      migration_status_type: string
      migration_note: string | null
      depth: number
      children: PermissionItem[]
    }

    interface PermissionSummary {
      total: number
      enabled_count: number
      disabled_count: number
      root_count: number
      tree_root_count: number
      orphan_count: number
      directory_count: number
      permission_count: number
      write_enabled_count: number
      read_only_count: number
      pending_write_count: number
      group_split_count: number
      legacy_only_count: number
      unmapped_count: number
    }

    interface PermissionTreeResponse {
      records: PermissionItem[]
      tree: PermissionItem[]
      summary: PermissionSummary
    }

    interface PermissionDetailResponse {
      item: PermissionItem
      children: PermissionItem[]
    }

    interface PermissionSearchParams {
      keyword?: string
      status?: number | string
      type?: number | string
    }

    interface PermissionWritePayload {
      parent_id?: number | string
      title: string
      path?: string
      icon?: string
      sort?: number | string
      type?: number | string
      status?: number | string
    }

    interface PermissionWriteResponse {
      item: PermissionItem
      created_permission_id?: number
      created_permission_label?: string
      updated_permission_id?: number
      updated_permission_label?: string
    }

    interface PermissionStatusPayload {
      status: number | string
    }

    interface PermissionReorderPayload {
      parent_id?: number | string
      permission_ids: Array<number | string>
    }

    interface PermissionReorderResponse {
      parent_id: number
      ordered_permission_ids: number[]
      items: PermissionItem[]
    }

    interface PermissionDeleteAuditSummary {
      delete_permission_row_count: number
      delete_role_permission_row_count: number
      delete_admin_permission_row_count: number
      direct_child_count: number
      descendant_count: number
      protected_row_count: number
    }

    interface PermissionDeleteAudit {
      permission_id: number
      permission_label: string
      path: string
      requires_cascade: boolean
      can_delete: boolean
      confirmation_phrase: string
      affected_permission_ids: number[]
      direct_child_ids: number[]
      blocking_reasons: string[]
      warnings: string[]
      summary: PermissionDeleteAuditSummary
    }

    interface PermissionDeleteAuditResponse {
      item: PermissionItem
      audit: PermissionDeleteAudit
    }

    interface PermissionDeletePayload {
      confirmation_phrase: string
      cascade_children?: boolean
    }

    interface PermissionDeleteResponse {
      deleted_permission_id: number
      deleted_permission_label: string
      deleted_permission_ids: number[]
      audit: PermissionDeleteAudit
    }

    type PermissionCreateResponse = PermissionWriteResponse
    type PermissionUpdateResponse = PermissionWriteResponse
    type PermissionStatusResponse = PermissionWriteResponse
  }

  namespace AdminLogs {
    type LogList = Api.Common.PaginatedResponse<LogListItem>

    interface LogListItem {
      id: number
      admin_id: number
      admin_username: string
      admin_nickname: string
      admin_display: string
      url: string
      path: string
      ip: string
      create_time: string | null
      payload_preview: string
      payload_text: string | null
      payload_is_empty: boolean
      user_agent_preview: string
      user_agent: string | null
    }

    interface LogDetailResponse {
      item: LogListItem
    }

    interface LogSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      admin_id?: number | string
      start_date?: string
      end_date?: string
    }

    interface LogCleanupAudit {
      can_cleanup: boolean
      confirmation_phrase: string
      summary: {
        total_count: number
        admin_count: number
        payload_log_count: number
        first_log_id: number
        last_log_id: number
      }
      warnings: string[]
    }

    interface LogCleanupAuditResponse {
      audit: LogCleanupAudit
    }

    interface LogCleanupPayload {
      confirmation_phrase: string
    }

    interface LogCleanupResponse {
      deleted_count: number
      audit: LogCleanupAudit
    }
  }

  namespace FrontLogs {
    type LogList = Api.Common.PaginatedResponse<LogListItem> & {
      summary: LogSummary
    }

    interface LogListItem {
      id: number
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_email: string | null
      merchant_mobile: string | null
      merchant_display: string
      url: string
      path: string
      ip: string
      create_time: string | null
      payload_preview: string
      payload_text: string | null
      payload_is_empty: boolean
      user_agent_preview: string
      user_agent: string | null
    }

    interface LogSummary {
      total_count: number
      merchant_count: number
      payload_count: number
      today_count: number
    }

    interface LogDetailResponse {
      item: LogListItem
    }

    interface LogDeleteAudit {
      log_id: number
      log_label: string
      merchant_id: number
      merchant_display: string
      path: string
      ip: string
      has_payload: boolean
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        payload_log_count: number
        merchant_linked_count: number
      }
      warnings: string[]
    }

    interface LogDeleteAuditResponse {
      item: LogListItem
      audit: LogDeleteAudit
    }

    interface LogDeletePayload {
      confirmation_phrase: string
    }

    interface LogDeleteResponse {
      deleted_log_id: number
      deleted_log_label: string
      audit: LogDeleteAudit
    }

    interface LogBatchDeleteAuditItem {
      log_id: number
      log_label: string
      exists: boolean
      can_delete: boolean
      merchant_id: number
      merchant_display: string
      path: string
      ip: string
      has_payload: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        payload_log_count: number
        merchant_linked_count: number
      }
    }

    interface LogBatchDeleteAudit {
      requested_log_ids: number[]
      deletable_log_ids: number[]
      missing_log_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: LogBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        missing_count: number
        payload_log_count: number
        merchant_count: number
      }
      warnings: string[]
    }

    interface LogBatchDeleteAuditPayload {
      log_ids: number[]
    }

    interface LogBatchDeletePayload extends LogBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface LogBatchDeleteAuditResponse {
      audit: LogBatchDeleteAudit
    }

    interface LogBatchDeleteResponse {
      deleted_log_ids: number[]
      deleted_count: number
      audit: LogBatchDeleteAudit
    }

    interface LogCleanupAudit {
      can_cleanup: boolean
      confirmation_phrase: string
      summary: {
        total_count: number
        merchant_count: number
        payload_log_count: number
        first_log_id: number
        last_log_id: number
      }
      warnings: string[]
    }

    interface LogCleanupAuditResponse {
      audit: LogCleanupAudit
    }

    interface LogCleanupPayload {
      confirmation_phrase: string
    }

    interface LogCleanupResponse {
      deleted_count: number
      audit: LogCleanupAudit
    }

    interface LogSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      user_id?: number | string
      uid?: number | string
      ip?: string
      start_date?: string
      end_date?: string
    }
  }

  namespace Domains {
    type DomainList = Api.Common.PaginatedResponse<DomainListItem> & {
      summary: DomainSummary
    }

    interface DomainListItem {
      id: number
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_email: string | null
      merchant_mobile: string | null
      merchant_display: string
      sitename: string
      siteurl: string
      siteurl_link: string | null
      status: number
      status_label: string
      status_text?: string
      status_type: string
      is_deleted: boolean
      reason: string | null
      reason_preview: string
      create_time: string | null
      delete_time: string | null
    }

    interface DomainSummary {
      pending_count: number
      approved_count: number
      rejected_count: number
      deleted_count: number
    }

    interface DomainDetailResponse {
      item: DomainListItem
    }

    interface DomainWritePayload {
      user_id: number | string
      sitename: string
      siteurl: string
    }

    interface DomainCreateResponse {
      item: DomainListItem
      created_domain_id: number
      created_domain_label: string
    }

    interface DomainUpdateResponse {
      item: DomainListItem
      updated_domain_id: number
      updated_domain_label: string
    }

    interface DomainDeleteAudit {
      domain_id: number
      domain_label: string
      merchant_id: number
      merchant_display: string
      siteurl: string
      confirmation_phrase: string
      can_delete: boolean
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        non_empty_target_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface DomainDeleteAuditResponse {
      item: DomainListItem
      audit: DomainDeleteAudit
    }

    interface DomainDeletePayload {
      confirmation_phrase: string
    }

    interface DomainDeleteResponse {
      deleted_domain_id: number
      deleted_domain_label: string
      audit: DomainDeleteAudit
    }

    interface DomainApprovalNotification {
      attempted: boolean
      sent: boolean
      status: 'sent' | 'skipped' | 'failed' | string
      message: string
    }

    interface DomainApproveResponse {
      item: DomainListItem
      approved_domain_id: number
      approved_domain_label: string
      notification: DomainApprovalNotification
    }

    interface DomainRestoreResponse {
      item: DomainListItem
      restored_domain_id: number
      restored_domain_label: string
    }

    interface DomainRejectPayload {
      reason: string
    }

    interface DomainRejectResponse {
      item: DomainListItem
      rejected_domain_id: number
      rejected_domain_label: string
      reason: string
    }

    interface DomainBatchRestorePayload {
      domain_ids: number[]
    }

    interface DomainBatchDeleteAuditItem {
      domain_id: number
      domain_label: string
      merchant_id: number
      merchant_display: string
      siteurl: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        non_empty_target_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface DomainBatchDeleteAudit {
      requested_domain_ids: number[]
      deletable_domain_ids: number[]
      blocked_domain_ids: number[]
      missing_domain_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: DomainBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        non_empty_target_count: number
      }
      warnings: string[]
    }

    interface DomainBatchDeleteAuditPayload {
      domain_ids: number[]
    }

    interface DomainBatchDeletePayload extends DomainBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface DomainBatchDeleteAuditResponse {
      audit: DomainBatchDeleteAudit
    }

    interface DomainBatchDeleteResponse {
      deleted_domain_ids: number[]
      deleted_count: number
      audit: DomainBatchDeleteAudit
    }

    interface DomainBatchRestoreResponse {
      restored_domain_ids: number[]
      restored_count: number
      already_active_domain_ids: number[]
      missing_domain_ids: number[]
    }

    interface DomainSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      user_id?: number | string
      sitename?: string
      siteurl?: string
      status?: number | string
    }
  }

  namespace Risks {
    type RiskList = Api.Common.PaginatedResponse<RiskListItem> & {
      summary: RiskSummary
    }

    interface RiskListItem {
      id: number
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_email: string | null
      merchant_mobile: string | null
      merchant_display: string
      name: string
      name_label: string
      url: string
      url_preview: string
      url_link: string | null
      url_host: string | null
      create_time: string | null
      update_time: string | null
    }

    interface RiskSummary {
      total_count: number
      merchant_count: number
      named_count: number
      source_count: number
      today_count: number
    }

    interface RiskDetailResponse {
      item: RiskListItem
    }

    interface RiskWritePayload {
      user_id?: number | string
      name?: string
      url?: string
    }

    interface RiskCreateResponse {
      item: RiskListItem
      created_risk_id: number
      created_risk_label: string
    }

    interface RiskUpdateResponse {
      item: RiskListItem
      updated_risk_id: number
      updated_risk_label: string
    }

    interface RiskDeleteAudit {
      risk_id: number
      risk_label: string
      merchant_id: number
      merchant_display: string
      name: string
      url: string
      url_host: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        merchant_count: number
        named_count: number
        source_count: number
      }
      warnings: string[]
    }

    interface RiskDeleteAuditResponse {
      item: RiskListItem
      audit: RiskDeleteAudit
    }

    interface RiskDeletePayload {
      confirmation_phrase: string
    }

    interface RiskDeleteResponse {
      deleted_risk_id: number
      deleted_risk_label: string
      audit: RiskDeleteAudit
    }

    interface RiskBatchDeleteAuditItem {
      risk_id: number
      risk_label: string
      merchant_id: number
      merchant_display: string
      name: string
      url: string
      url_host: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        merchant_count: number
        named_count: number
        source_count: number
      }
      warnings: string[]
    }

    interface RiskBatchDeleteAudit {
      requested_risk_ids: number[]
      deletable_risk_ids: number[]
      missing_risk_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: RiskBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        missing_count: number
        delete_row_count: number
        merchant_count: number
        named_count: number
        source_count: number
      }
      warnings: string[]
    }

    interface RiskBatchDeleteAuditPayload {
      risk_ids: number[]
    }

    interface RiskBatchDeletePayload extends RiskBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface RiskBatchDeleteAuditResponse {
      audit: RiskBatchDeleteAudit
    }

    interface RiskBatchDeleteResponse {
      deleted_risk_ids: number[]
      deleted_count: number
      audit: RiskBatchDeleteAudit
    }

    interface RiskSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      user_id?: number | string
      name?: string
      url?: string
      start_date?: string
      end_date?: string
    }
  }

  namespace Tickets {
    type TicketList = Api.Common.PaginatedResponse<TicketListItem> & {
      summary: TicketSummary
      categories: TicketCategory[]
    }

    interface TicketListItem {
      id: number
      ticket_label: string
      type: number
      type_name_text?: string
      type_name: string
      title: string
      content: string | null
      content_preview: string
      reply_content: string | null
      reply_preview: string
      reply_state_text?: string
      reply_state_label: string
      creator_id: number
      creator_username: string
      creator_name: string | null
      creator_email: string | null
      creator_mobile: string | null
      creator_display: string
      assignee_id: number
      assignee_username: string
      assignee_nickname: string | null
      assignee_display: string
      create_time: string | null
      update_time: string | null
      reply_time: string | null
      status: number
      status_text?: string
      status_label: string
      status_type: string
      is_replied: boolean
      is_open: boolean
      delete_blocked: boolean
      delete_guard_reason: string
    }

    interface TicketCategory {
      id: number
      name: string
      status: number | null
    }

    type TicketCategoryList = Api.Common.PaginatedResponse<TicketCategoryListItem> & {
      summary: TicketCategorySummary
    }

    interface TicketCategoryListItem {
      id: number
      name: string
      name_label: string
      sort: string | null
      sort_number: number | null
      status: number | null
      status_text?: string
      status_label: string
      status_type: string
      create_time: string | null
      update_time: string | null
      ticket_count: number
      open_ticket_count: number
      replied_ticket_count: number
      latest_ticket_time: string | null
      is_linked: boolean
      link_status_text?: string
      link_status_label: string
      delete_blocked: boolean
      delete_guard_reason: string
    }

    interface TicketCategorySummary {
      total_count: number
      enabled_count: number
      disabled_count: number
      linked_count: number
      unused_count: number
      open_ticket_count: number
    }

    interface TicketSummary {
      new_count: number
      processing_count: number
      resolved_count: number
      closed_count: number
      replied_count: number
    }

    interface TicketDetailResponse {
      item: TicketListItem
      categories: TicketCategory[]
    }

    interface TicketReplyPayload {
      reply_content?: string
      status?: number | string
    }

    interface TicketReplyResponse {
      item: TicketListItem
      updated_ticket_id: number
      updated_ticket_label: string
      status: number
      status_text?: string
      status_label: string
      reply_state_text?: string
      reply_state_label: string
    }

    interface TicketStatusPayload {
      status: number | string
    }

    interface TicketStatusResponse {
      item: TicketListItem
      updated_ticket_id: number
      updated_ticket_label: string
      status: number
      status_text?: string
      status_label: string
    }

    interface TicketDeleteAudit {
      ticket_id: number
      ticket_label: string
      status: number
      status_label: string
      type: number
      type_name: string
      creator_id: number
      assignee_id: number
      is_replied: boolean
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        open_ticket_count: number
        replied_count: number
      }
      warnings: string[]
    }

    interface TicketDeleteAuditResponse {
      item: TicketListItem
      audit: TicketDeleteAudit
    }

    interface TicketDeletePayload {
      confirmation_phrase: string
    }

    interface TicketDeleteResponse {
      deleted_ticket_id: number
      deleted_ticket_label: string
      audit: TicketDeleteAudit
    }

    interface TicketBatchDeleteAuditItem {
      ticket_id: number
      ticket_label: string
      exists: boolean
      can_delete: boolean
      status: number | null
      status_label: string
      type: number
      type_name: string
      creator_id: number
      assignee_id: number
      is_replied: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        open_ticket_count: number
        replied_count: number
      }
    }

    interface TicketBatchDeleteAudit {
      requested_ticket_ids: number[]
      deletable_ticket_ids: number[]
      missing_ticket_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: TicketBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        missing_count: number
        open_ticket_count: number
        replied_count: number
      }
      warnings: string[]
    }

    interface TicketBatchDeleteAuditPayload {
      ticket_ids: number[]
    }

    interface TicketBatchDeletePayload extends TicketBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface TicketBatchDeleteAuditResponse {
      audit: TicketBatchDeleteAudit
    }

    interface TicketBatchDeleteResponse {
      deleted_ticket_ids: number[]
      deleted_count: number
      audit: TicketBatchDeleteAudit
    }

    interface TicketCategoryDetailResponse {
      item: TicketCategoryListItem
    }

    interface TicketCategoryWritePayload {
      name?: string
      sort?: number | string | null
      status?: number | string
    }

    interface TicketCategoryCreateResponse {
      item: TicketCategoryListItem
      created_category_id: number
      created_category_label: string
    }

    interface TicketCategoryUpdateResponse {
      item: TicketCategoryListItem
      updated_category_id: number
      updated_category_label: string
    }

    interface TicketCategoryStatusPayload {
      status: number | string | boolean
    }

    interface TicketCategoryStatusResponse {
      item: TicketCategoryListItem
      updated_category_id: number
      updated_category_label: string
      status: number
      status_text?: string
      status_label: string
    }

    interface TicketCategoryDeleteAudit {
      category_id: number
      category_label: string
      status: number
      ticket_count: number
      open_ticket_count: number
      replied_ticket_count: number
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        linked_ticket_count: number
        open_ticket_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface TicketCategoryDeleteAuditResponse {
      item: TicketCategoryListItem
      audit: TicketCategoryDeleteAudit
    }

    interface TicketCategoryDeletePayload {
      confirmation_phrase: string
    }

    interface TicketCategoryDeleteResponse {
      deleted_category_id: number
      deleted_category_label: string
      audit: TicketCategoryDeleteAudit
    }

    interface TicketCategoryBatchDeleteAuditItem {
      category_id: number
      category_label: string
      exists: boolean
      can_delete: boolean
      ticket_count: number
      open_ticket_count: number
      replied_ticket_count: number
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        linked_ticket_count: number
        open_ticket_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface TicketCategoryBatchDeleteAudit {
      requested_category_ids: number[]
      deletable_category_ids: number[]
      blocked_category_ids: number[]
      missing_category_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: TicketCategoryBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        linked_ticket_count: number
        open_ticket_count: number
      }
      warnings: string[]
    }

    interface TicketCategoryBatchDeleteAuditPayload {
      category_ids: number[]
    }

    interface TicketCategoryBatchDeletePayload extends TicketCategoryBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface TicketCategoryBatchDeleteAuditResponse {
      audit: TicketCategoryBatchDeleteAudit
    }

    interface TicketCategoryBatchDeleteResponse {
      deleted_category_ids: number[]
      deleted_count: number
      audit: TicketCategoryBatchDeleteAudit
    }

    interface TicketSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      creator_id?: number | string
      status?: number | string
      type?: number | string
      start_date?: string
      end_date?: string
    }

    interface TicketCategorySearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
    }
  }

  namespace QuickLogins {
    type QuickLoginList = Api.Common.PaginatedResponse<QuickLoginListItem> & {
      summary: QuickLoginSummary
    }

    interface QuickLoginListItem {
      id: number
      type: string
      type_text?: string
      type_label: string
      type_tag: string
      type_help_text?: string
      type_help: string
      status: number
      status_text?: string
      status_label: string
      status_type: string
      name: string
      name_label: string
      url: string
      url_link: string | null
      appid_masked: string | null
      appkey_masked: string | null
      has_appid: boolean
      has_appkey: boolean
      credential_ready: boolean
      credential_summary_text?: string
      credential_summary: string
      callback_path: string | null
      create_time: string | null
      is_bound: boolean
      binding_count: number
      binding_config_names: string[]
      binding_labels: string[]
      binding_summary_text?: string
      binding_summary: string
    }

    interface QuickLoginSummary {
      enabled_count: number
      disabled_count: number
      qq_count: number
      polymerization_count: number
      credential_ready_count: number
    }

    interface QuickLoginDetailResponse {
      item: QuickLoginListItem
    }

    interface QuickLoginWritePayload {
      type?: string
      name?: string
      url?: string
      appid?: string | null
      appkey?: string | null
      status?: number | string
    }

    interface QuickLoginCreateResponse {
      item: QuickLoginListItem
      created_quick_login_id: number
      created_quick_login_label: string
    }

    interface QuickLoginUpdateResponse {
      item: QuickLoginListItem
      updated_quick_login_id: number
      updated_quick_login_label: string
    }

    interface QuickLoginStatusPayload {
      status: number | string | boolean
    }

    interface QuickLoginStatusResponse {
      item: QuickLoginListItem
      updated_quick_login_id: number
      updated_quick_login_label: string
      status: number
      status_text?: string
      status_label: string
    }

    interface QuickLoginDeleteAudit {
      quick_login_id: number
      quick_login_label: string
      type: string
      status: number
      binding_config_names: string[]
      binding_labels: string[]
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        binding_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface QuickLoginDeleteAuditResponse {
      item: QuickLoginListItem
      audit: QuickLoginDeleteAudit
    }

    interface QuickLoginDeletePayload {
      confirmation_phrase: string
    }

    interface QuickLoginDeleteResponse {
      deleted_quick_login_id: number
      deleted_quick_login_label: string
      audit: QuickLoginDeleteAudit
    }

    interface QuickLoginBatchDeleteAuditItem {
      quick_login_id: number
      quick_login_label: string
      type: string
      exists: boolean
      can_delete: boolean
      binding_config_names: string[]
      binding_labels: string[]
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        binding_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface QuickLoginBatchDeleteAudit {
      requested_quick_login_ids: number[]
      deletable_quick_login_ids: number[]
      blocked_quick_login_ids: number[]
      missing_quick_login_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: QuickLoginBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
        binding_blocked_count: number
      }
      warnings: string[]
    }

    interface QuickLoginBatchDeleteAuditPayload {
      quick_login_ids: number[]
    }

    interface QuickLoginBatchDeletePayload extends QuickLoginBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface QuickLoginBatchDeleteAuditResponse {
      audit: QuickLoginBatchDeleteAudit
    }

    interface QuickLoginBatchDeleteResponse {
      deleted_quick_login_ids: number[]
      deleted_count: number
      audit: QuickLoginBatchDeleteAudit
    }

    interface QuickLoginSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      type?: string
      status?: number | string
      name?: string
    }
  }

  namespace Navs {
    type NavList = Api.Common.PaginatedResponse<NavListItem> & {
      summary: NavSummary
    }

    interface NavListItem {
      id: number
      name: string
      url: string
      url_link: string | null
      is_external: boolean
      is_target: number
      target_text?: string
      target_label: string
      target_type: string
      status: number
      status_text?: string
      status_label: string
      status_type: string
      create_time: string | null
      sort: number
      delete_time: string | null
      is_deleted: boolean
    }

    interface NavSummary {
      enabled_count: number
      disabled_count: number
      new_window_count: number
      same_window_count: number
      deleted_count: number
    }

    interface NavDetailResponse {
      item: NavListItem
    }

    interface NavWritePayload {
      name: string
      url?: string
      status?: number | string
      is_target?: number | string
      sort?: number | string
    }

    interface NavCreateResponse {
      item: NavListItem
      created_nav_id: number
      created_nav_label: string
    }

    interface NavUpdateResponse {
      item: NavListItem
      updated_nav_id: number
      updated_nav_label: string
    }

    interface NavStatusPayload {
      status: number | string | boolean
    }

    interface NavStatusResponse {
      item: NavListItem
      updated_nav_id: number
      updated_nav_label: string
      status: number
      status_text?: string
      status_label: string
    }

    interface NavTargetPayload {
      is_target: number | string | boolean
    }

    interface NavTargetResponse {
      item: NavListItem
      updated_nav_id: number
      updated_nav_label: string
      is_target: number
      target_text?: string
      target_label: string
    }

    interface NavReorderPayload {
      visible_nav_ids: number[]
      from_index: number
      to_index: number
    }

    interface NavReorderResponse {
      moved_nav_id: number
      from_index: number
      to_index: number
      updated_count: number
      sort_values_rebased: boolean
      visible_nav_ids: number[]
    }

    interface NavDeleteAudit {
      nav_id: number
      nav_label: string
      url: string
      status: number
      is_target: number
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface NavDeleteAuditResponse {
      item: NavListItem
      audit: NavDeleteAudit
    }

    interface NavDeletePayload {
      confirmation_phrase: string
    }

    interface NavDeleteResponse {
      deleted_nav_id: number
      deleted_nav_label: string
      audit: NavDeleteAudit
    }

    interface NavRestoreResponse {
      item: NavListItem
      restored_nav_id: number
      restored_nav_label: string
    }

    interface NavBatchDeleteAuditItem {
      nav_id: number
      nav_label: string
      url: string
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface NavBatchDeleteAudit {
      requested_nav_ids: number[]
      deletable_nav_ids: number[]
      blocked_nav_ids: number[]
      missing_nav_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: NavBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
      }
      warnings: string[]
    }

    interface NavBatchDeleteAuditPayload {
      nav_ids: number[]
    }

    interface NavBatchDeletePayload extends NavBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface NavBatchDeleteAuditResponse {
      audit: NavBatchDeleteAudit
    }

    interface NavBatchDeleteResponse {
      deleted_nav_ids: number[]
      deleted_count: number
      audit: NavBatchDeleteAudit
    }

    interface NavBatchRestorePayload {
      nav_ids: number[]
    }

    interface NavBatchRestoreResponse {
      restored_nav_ids: number[]
      restored_count: number
      already_active_nav_ids: number[]
      missing_nav_ids: number[]
    }

    interface NavSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      status?: number | string
      is_target?: number | string
    }
  }

  namespace News {
    type NewsList = Api.Common.PaginatedResponse<NewsListItem> & {
      summary: NewsSummary
    }

    interface NewsListItem {
      id: number
      type: number
      type_text?: string
      type_label: string
      type_tag: string
      title: string
      color: string | null
      content: string | null
      content_text: string | null
      content_preview: string
      has_content: boolean
      status: number
      status_text?: string
      status_label: string
      status_type: string
      create_time: string | null
      update_time: string | null
      delete_time: string | null
      is_deleted: boolean
    }

    interface NewsSummary {
      enabled_count: number
      disabled_count: number
      platform_count: number
      industry_count: number
      faq_count: number
      content_count: number
      deleted_count: number
    }

    interface NewsDetailResponse {
      item: NewsListItem
    }

    interface NewsWritePayload {
      type: number | string
      title?: string
      color?: string
      content?: string
      status?: number | string
    }

    interface NewsCreateResponse {
      item: NewsListItem
      created_news_id: number
      created_news_label: string
    }

    interface NewsUpdateResponse {
      item: NewsListItem
      updated_news_id: number
      updated_news_label: string
    }

    interface NewsStatusPayload {
      status: number | string | boolean
    }

    interface NewsStatusResponse {
      item: NewsListItem
      updated_news_id: number
      updated_news_label: string
      status: number
      status_text?: string
      status_label: string
    }

    interface NewsDeleteAudit {
      news_id: number
      news_label: string
      type: number
      status: number
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface NewsDeleteAuditResponse {
      item: NewsListItem
      audit: NewsDeleteAudit
    }

    interface NewsDeletePayload {
      confirmation_phrase: string
    }

    interface NewsDeleteResponse {
      deleted_news_id: number
      deleted_news_label: string
      audit: NewsDeleteAudit
    }

    interface NewsRestoreResponse {
      item: NewsListItem
      restored_news_id: number
      restored_news_label: string
    }

    interface NewsBatchDeleteAuditItem {
      news_id: number
      news_label: string
      type: number
      exists: boolean
      can_delete: boolean
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        blocked_count: number
      }
      warnings: string[]
    }

    interface NewsBatchDeleteAudit {
      requested_news_ids: number[]
      deletable_news_ids: number[]
      blocked_news_ids: number[]
      missing_news_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: NewsBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_row_count: number
      }
      warnings: string[]
    }

    interface NewsBatchDeleteAuditPayload {
      news_ids: number[]
    }

    interface NewsBatchDeletePayload extends NewsBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface NewsBatchDeleteAuditResponse {
      audit: NewsBatchDeleteAudit
    }

    interface NewsBatchDeleteResponse {
      deleted_news_ids: number[]
      deleted_count: number
      audit: NewsBatchDeleteAudit
    }

    interface NewsBatchRestorePayload {
      news_ids: number[]
    }

    interface NewsBatchRestoreResponse {
      restored_news_ids: number[]
      restored_count: number
      already_active_news_ids: number[]
      missing_news_ids: number[]
    }

    interface NewsSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      type?: number | string
      status?: number | string
    }
  }

  namespace Themes {
    type ThemeList = Api.Common.PaginatedResponse<ThemeListItem> & {
      summary: ThemeSummary
      scope_options: ThemeScopeOption[]
    }

    interface ThemeListItem {
      id: string
      scope: string
      scope_label: string
      title: string | null
      title_label: string
      description: string | null
      description_preview: string
      version: string | null
      version_label: string
      relative_path: string
      asset_path: string
      style_path: string | null
      screenshot_path: string | null
      has_style: boolean
      has_screenshot: boolean
      metadata_complete: boolean
      metadata_label: string
      metadata_type: string
      is_active: boolean
      status_label: string
      status_type: string
      config_key: string | null
      configured_value: string | null
      effective_value: string | null
      config_missing: boolean
      config_state_label: string
      activate_supported: boolean
      delete_supported: boolean
      readonly_note: string
    }

    interface ThemeSummary {
      total_count: number
      scope_count: number
      active_count: number
      screenshot_count: number
      metadata_ready_count: number
      config_missing_count: number
      style_missing_count: number
      generated_at: string
    }

    interface ThemeScopeOption {
      label: string
      value: string
      count: number
      config_key: string | null
      default_value: string | null
      activation_supported: boolean
    }

    interface ThemeDetailResponse {
      item: ThemeListItem
    }

    interface ThemeActivateResponse {
      item: ThemeListItem
      activated_scope: string
      activated_scope_label: string
      activated_theme_id: string
      activated_theme_label: string
      config_key: string
      config_value: string
      previous_theme_id: string | null
      previous_theme_label: string | null
    }

    interface ThemeDeleteAudit {
      scope: string
      scope_label: string
      theme_id: string
      theme_label: string
      is_active: boolean
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      directory: {
        exists: boolean
        absolute_path: string
        relative_path: string
        file_count: number
        directory_count: number
        entry_count: number
        size_bytes: number
      }
      fallback: {
        required: boolean
        config_key: string | null
        theme_id: string | null
        theme_label: string | null
      }
      summary: {
        file_count: number
        directory_count: number
        entry_count: number
        size_bytes: number
        paypage_reference_count: number
      }
    }

    interface ThemeDeleteAuditResponse {
      item: ThemeListItem
      audit: ThemeDeleteAudit
    }

    interface ThemeDeletePayload {
      confirmation_phrase: string
    }

    interface ThemeDeleteResponse {
      deleted_scope: string
      deleted_scope_label: string
      deleted_theme_id: string
      deleted_theme_label: string
      fallback_theme_id: string | null
      fallback_theme_label: string | null
      config_key: string | null
      audit: ThemeDeleteAudit
    }

    interface ThemeSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      scope?: string
      status?: string
    }
  }

  namespace CleanupAudit {
    type CleanupAuditList = Api.Common.PaginatedResponse<CleanupAuditItem> & {
      summary: CleanupAuditSummary
    }

    interface CleanupAuditItem {
      key: string
      title: string
      category: string
      category_label: string
      table_name: string
      target_description: string
      legacy_page: string
      legacy_endpoint: string
      legacy_action_label: string
      note: string | null
      readonly_note: string
      maintenance_note: string
      total_count: number
      target_count: number
      has_targets: boolean
      ratio: number
      ratio_label: string
      threshold_value: number | null
      threshold_label: string
      recommended: boolean
      status_label: string
      status_type: string
      latest_record_time: string | null
      latest_target_time: string | null
      action_available: boolean
      action_mode: string
      action_mode_label: string
      action_mode_type: string
      action_scope_label: string
      action_label: string
    }

    interface CleanupAuditSummary {
      item_count: number
      recommended_count: number
      stable_count: number
      target_row_count: number
      threshold_guarded_count: number
      generated_at: string
    }

    interface CleanupAuditDetailResponse {
      item: CleanupAuditItem
    }

    interface CleanupExecutionAudit {
      key: string
      title: string
      table_name: string
      action_label: string
      action_mode: string
      action_mode_label: string
      action_scope_label: string
      target_description: string
      can_execute: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        total_count: number
        delete_row_count: number
        keep_row_count: number
        ratio: number
        ratio_label: string
        threshold_value: number | null
        threshold_met: boolean | null
        recommended: boolean
        latest_record_time: string | null
        latest_target_time: string | null
      }
    }

    interface CleanupExecutionAuditResponse {
      item: CleanupAuditItem
      audit: CleanupExecutionAudit
    }

    interface CleanupExecutePayload {
      confirmation_phrase: string
    }

    interface CleanupExecuteResponse {
      key: string
      title: string
      action_label: string
      action_mode: string
      deleted_count: number
      audit: CleanupExecutionAudit
      item: CleanupAuditItem
    }

    interface CleanupAuditSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      category?: string
      status?: string
    }
  }

  namespace SystemCache {
    interface ServerCacheTarget {
      key: string
      title: string
      description: string
      relative_path: string
      exists: boolean
      clearable: boolean
      file_count: number
      directory_count: number
      entry_count: number
      size_bytes: number
      size_label: string
    }

    interface ServerCacheSummary {
      target_count: number
      clearable_target_count: number
      file_count: number
      directory_count: number
      entry_count: number
      size_bytes: number
      size_label: string
    }

    interface BrowserCacheHints {
      local_storage_prefix: string
      local_storage_keys: string[]
      session_storage_keys: string[]
      note: string
    }

    interface CacheAuditResponse {
      server_targets: ServerCacheTarget[]
      server_summary: ServerCacheSummary
      browser_hints: BrowserCacheHints
      generated_at: string
    }

    interface ServerCacheCleanupPayload {
      targets?: string[]
    }

    interface ServerCacheCleanupResult {
      key: string
      title: string
      relative_path: string
      before: ServerCacheTarget
      after: ServerCacheTarget
      removed_file_count: number
      removed_directory_count: number
      removed_key_count: number
      released_size_bytes: number
      released_size_label: string
      errors: string[]
    }

    interface ServerCacheCleanupResponse {
      target_keys: string[]
      cleared_target_count: number
      removed_file_count: number
      removed_directory_count: number
      removed_key_count: number
      released_size_bytes: number
      released_size_label: string
      results: ServerCacheCleanupResult[]
      warnings: string[]
      audit: CacheAuditResponse
    }
  }

  namespace MediaLibrary {
    type MediaLibraryList = Api.Common.PaginatedResponse<MediaLibraryDirectoryItem> & {
      summary: MediaLibrarySummary
    }

    interface MediaLibraryDirectoryItem {
      path: string
      path_label: string
      directory_exists: boolean
      storage_mode: string
      storage_label: string
      storage_tag: string
      sync_status: string
      sync_status_label: string
      sync_status_type: string
      db_file_count: number
      local_db_count: number
      cloud_file_count: number
      disk_file_count: number
      matched_file_count: number
      orphan_disk_count: number
      missing_local_count: number
      empty_directory: boolean
      disk_size_bytes: number
      disk_size_label: string
      db_size_bytes: number
      db_size_label: string
      latest_db_time: string | null
      latest_disk_time: string | null
      latest_file_name: string | null
      preview_url: string | null
      legacy_page: string
      legacy_list_endpoint: string
      readonly_note: string
    }

    interface MediaLibraryFileItem {
      key: string
      db_id: number | null
      name: string
      path: string
      href: string
      relative_path: string
      preview_url: string | null
      ext: string | null
      mime: string | null
      storage_type: string
      storage_label: string
      storage_tag: string
      source_status: string
      source_status_label: string
      source_status_type: string
      exists_on_disk: boolean
      exists_in_db: boolean
      size_bytes: number
      size_label: string
      db_size_bytes: number
      db_size_label: string
      disk_size_bytes: number
      disk_size_label: string
      create_time: string | null
      disk_mtime: string | null
    }

    interface MediaLibraryDetailItem extends MediaLibraryDirectoryItem {
      files: MediaLibraryFileItem[]
    }

    interface MediaLibrarySummary {
      directory_count: number
      healthy_count: number
      warning_directory_count: number
      empty_directory_count: number
      db_file_count: number
      disk_file_count: number
      orphan_disk_count: number
      missing_local_count: number
      cloud_file_count: number
      generated_at: string
    }

    interface MediaLibraryDetailResponse {
      item: MediaLibraryDetailItem | null
    }

    interface MediaLibraryCreateDirectoryPayload {
      path: string
    }

    interface MediaLibraryCreateDirectoryResponse {
      created_path: string
      item: MediaLibraryDetailItem | null
    }

    interface MediaLibraryUploadedFile {
      db_id: number
      name: string
      href: string
      relative_path: string
      preview_url: string | null
      ext: string | null
      mime: string | null
      size_bytes: number
    }

    interface MediaLibraryUploadResponse {
      path: string
      uploaded_count: number
      uploaded_db_ids: number[]
      uploaded_files: MediaLibraryUploadedFile[]
      item: MediaLibraryDetailItem | null
    }

    interface MediaLibraryFileSelector {
      path: string
      db_id?: number | null
      href?: string
    }

    interface MediaLibraryFileDeleteAudit {
      selector_key: string
      path: string
      file_label: string
      db_id: number | null
      href: string
      storage_type: string
      source_status: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_db_row_count: number
        delete_disk_file_count: number
        missing_local_count: number
        orphan_disk_count: number
        cloud_record_count: number
      }
      warnings: string[]
    }

    interface MediaLibraryFileDeleteAuditResponse {
      item: MediaLibraryFileItem | null
      audit: MediaLibraryFileDeleteAudit
    }

    interface MediaLibraryFileDeletePayload {
      file: MediaLibraryFileSelector
      confirmation_phrase: string
    }

    interface MediaLibraryFileDeleteResponse {
      deleted_file_label: string
      path: string
      audit: MediaLibraryFileDeleteAudit
    }

    interface MediaLibraryBatchDeleteAuditItem {
      selector_key: string
      path: string
      db_id: number | null
      href: string
      file_label: string
      exists: boolean
      can_delete: boolean
      storage_type: string
      source_status: string
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_db_row_count: number
        delete_disk_file_count: number
        missing_local_count: number
        orphan_disk_count: number
        cloud_record_count: number
      }
    }

    interface MediaLibraryBatchDeleteAudit {
      requested_selector_keys: string[]
      deletable_selector_keys: string[]
      blocked_selector_keys: string[]
      missing_selector_keys: string[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: MediaLibraryBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        blocked_count: number
        missing_count: number
        delete_db_row_count: number
        delete_disk_file_count: number
        missing_local_count: number
        orphan_disk_count: number
        cloud_blocked_count: number
      }
      warnings: string[]
    }

    interface MediaLibraryBatchDeleteAuditResponse {
      audit: MediaLibraryBatchDeleteAudit
    }

    interface MediaLibraryBatchDeletePayload {
      files: MediaLibraryFileSelector[]
      confirmation_phrase: string
    }

    interface MediaLibraryBatchDeleteResponse {
      deleted_count: number
      deleted_selector_keys: string[]
      audit: MediaLibraryBatchDeleteAudit
    }

    interface MediaLibraryDirectoryDeleteAudit {
      path: string
      path_label: string
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_db_row_count: number
        delete_disk_file_count: number
        delete_directory_count: number
        cloud_record_count: number
        missing_local_count: number
        orphan_disk_count: number
      }
      warnings: string[]
    }

    interface MediaLibraryDirectoryDeleteAuditResponse {
      item: MediaLibraryDetailItem | null
      audit: MediaLibraryDirectoryDeleteAudit
    }

    interface MediaLibraryDirectoryDeletePayload {
      confirmation_phrase: string
    }

    interface MediaLibraryDirectoryDeleteResponse {
      deleted_path: string
      audit: MediaLibraryDirectoryDeleteAudit
    }

    interface MediaLibrarySearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      sync_status?: string
      storage_mode?: string
    }
  }

  namespace Cdks {
    type CdkList = Api.Common.PaginatedResponse<CdkListItem> & {
      summary: CdkSummary
    }

    interface CdkListItem {
      id: number
      type: number | null
      type_label: string
      type_tag: string
      value: string | null
      value_label: string
      face_amount: number | null
      vip_id: number | null
      vip_name: string | null
      code_masked: string | null
      has_code: boolean
      code_length: number
      status: number
      status_label: string
      status_type: string
      is_used: boolean
      create_time: string | null
      delete_guard_reason: string
    }

    interface CdkSummary {
      unused_count: number
      used_count: number
      balance_card_count: number
      vip_card_count: number
      total_face_amount: number
      code_ready_count: number
    }

    interface CdkDetailResponse {
      item: CdkListItem
    }

    interface CdkCreatePayload {
      type: number | string
      count: number | string
      amount?: number | string
      vip_id?: number | string
      prefix?: string
    }

    interface CdkCreatedCard {
      id: number
      type: number
      type_label: string
      value: string
      value_label: string
      code: string
    }

    interface CdkCreateResponse {
      created_count: number
      created_cdk_ids: number[]
      created_type: number
      created_type_label: string
      value_label: string
      prefix: string
      generated_codes: string[]
      generated_cards: CdkCreatedCard[]
      records: CdkListItem[]
    }

    interface CdkDeleteAudit {
      cdk_id: number
      cdk_label: string
      type: number | null
      type_label: string
      status: number
      status_label: string
      is_used: boolean
      can_delete: boolean
      confirmation_phrase: string
      blocking_reasons: string[]
      summary: {
        delete_row_count: number
        used_count: number
        unused_count: number
      }
      warnings: string[]
    }

    interface CdkDeleteAuditResponse {
      item: CdkListItem
      audit: CdkDeleteAudit
    }

    interface CdkDeletePayload {
      confirmation_phrase: string
    }

    interface CdkDeleteResponse {
      deleted_cdk_id: number
      deleted_cdk_label: string
      audit: CdkDeleteAudit
    }

    interface CdkBatchDeleteAuditItem {
      cdk_id: number
      cdk_label: string
      exists: boolean
      can_delete: boolean
      type: number | null
      type_label: string
      status: number | null
      status_label: string
      is_used: boolean
      blocking_reasons: string[]
      warnings: string[]
      summary: {
        delete_row_count: number
        used_count: number
        unused_count: number
      }
    }

    interface CdkBatchDeleteAudit {
      requested_cdk_ids: number[]
      deletable_cdk_ids: number[]
      missing_cdk_ids: number[]
      confirmation_phrase: string
      can_delete_all: boolean
      items: CdkBatchDeleteAuditItem[]
      summary: {
        requested_count: number
        existing_count: number
        deletable_count: number
        missing_count: number
        used_count: number
        unused_count: number
      }
      warnings: string[]
    }

    interface CdkBatchDeleteAuditPayload {
      cdk_ids: number[]
    }

    interface CdkBatchDeletePayload extends CdkBatchDeleteAuditPayload {
      confirmation_phrase: string
    }

    interface CdkBatchDeleteAuditResponse {
      audit: CdkBatchDeleteAudit
    }

    interface CdkBatchDeleteResponse {
      deleted_cdk_ids: number[]
      deleted_count: number
      audit: CdkBatchDeleteAudit
    }

    interface CdkCleanupUsedAudit {
      used_cdk_ids: number[]
      can_cleanup: boolean
      confirmation_phrase: string
      summary: {
        used_count: number
        balance_card_count: number
        vip_card_count: number
      }
      warnings: string[]
    }

    interface CdkCleanupUsedAuditResponse {
      audit: CdkCleanupUsedAudit
    }

    interface CdkCleanupUsedPayload {
      confirmation_phrase: string
    }

    interface CdkCleanupUsedResponse {
      deleted_count: number
      audit: CdkCleanupUsedAudit
    }

    interface CdkSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      type?: number | string
      status?: number | string
      start_date?: string
      end_date?: string
    }
  }

  namespace MoneyLogs {
    type MoneyLogList = Api.Common.PaginatedResponse<MoneyLogListItem> & {
      summary: MoneyLogSummary
    }

    interface MoneyLogListItem {
      id: number
      user_id: number
      merchant_username: string
      merchant_name: string | null
      merchant_display: string
      type: number | null
      type_label: string
      type_tag: string
      money: number
      money_display: string
      before_money: number
      after_money: number
      balance_delta_label: string
      direction: 'income' | 'expense'
      direction_label: string
      memo: string
      memo_label: string
      create_time: string | null
    }

    interface MoneyLogSummary {
      income_count: number
      expense_count: number
      income_amount: number
      expense_amount: number
      net_amount: number
    }

    interface MoneyLogDetailResponse {
      item: MoneyLogListItem
    }

    interface MoneyLogCreatePayload {
      user_id: number | string
      direction: 'income' | 'expense'
      amount: number | string
      memo?: string
    }

    interface MoneyLogCreateResponse {
      item: MoneyLogListItem
      created_log_id: number
      merchant_id: number
      merchant_display: string
      balance_before: number
      balance_after: number
      applied_amount: number
      applied_amount_display: string
      operator_note: string | null
    }

    interface MoneyLogSearchParams extends Partial<Api.Common.CommonSearchParams> {
      keyword?: string
      user_id?: number | string
      direction?: 'income' | 'expense' | ''
      memo?: string
      start_date?: string
      end_date?: string
    }
  }
}
