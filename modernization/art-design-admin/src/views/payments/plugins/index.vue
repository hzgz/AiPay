<template>
  <div class="payment-plugin-page">
    <PaymentPluginListWorkspace
      v-model:keyword="keyword"
      v-model:active-plugin-view="activePluginView"
      v-model:active-plugin-payment-filter="activePluginPaymentFilter"
      :loading="loading"
      :plugin-views="pluginViews"
      :plugin-payment-filters="pluginPaymentFilters"
      :filtered-plugins="filteredPlugins"
      :columns="columns"
      :has-plugin-scaffold-auth="hasPluginScaffoldAuth"
      :has-governance-data="hasGovernanceData"
      :show-governance-panels="showGovernancePanels"
      :governance-attention-count="governanceAttentionCount"
      :governance-snapshot-count="governanceSnapshotCount"
      :governance-residue-count="governanceResidueCount"
      :governance-ledger-count="governanceLedgerCount"
      @scaffold="openScaffoldDialog"
      @toggle-governance="governanceExpanded = !governanceExpanded"
      @refresh="loadPlugins"
    />

    <PaymentPluginGovernancePanels
      v-if="showGovernancePanels"
      :loading="loading"
      :recovery-vault="recoveryVault"
      :registry-residue="registryResidue"
      :registry-residue-ledger="registryResidueLedger"
      :has-plugin-restore-snapshot-auth="hasPluginRestoreSnapshotAuth"
      :has-plugin-delete-snapshot-auth="hasPluginDeleteSnapshotAuth"
      :has-plugin-cleanup-residue-auth="hasPluginCleanupResidueAuth"
      :snapshot-restoring-id="snapshotRestoringId"
      :snapshot-deleting-id="snapshotDeletingId"
      :residue-cleaning-code="residueCleaningCode"
      @open-detail="openDetail"
      @restore-vault-snapshot="restoreVaultSnapshot"
      @delete-vault-snapshot="deleteVaultSnapshot"
      @cleanup-registry-residue="cleanupRegistryResidueItem"
    />

    <PluginScaffoldDialog
      v-model:visible="scaffoldDialogVisible"
      :creating="scaffoldCreating"
      @submit="submitScaffold"
    />

    <ElDrawer
      v-model="detailVisible"
      size="min(680px, calc(100vw - 24px))"
      destroy-on-close
      class="plugin-detail-drawer"
      :title="normalizePluginCopy(activeDetail?.manifest.name) || '支付插件详情'"
    >
      <template v-if="activeDetail">
        <div class="plugin-detail-switcher">
          <button
            v-for="view in pluginDetailViews"
            :key="view.key"
            type="button"
            class="plugin-detail-switcher__item"
            :class="{ 'plugin-detail-switcher__item--active': activePluginDetailView === view.key }"
            @click="activePluginDetailView = view.key"
          >
            <span class="plugin-detail-switcher__label">{{ view.label }}</span>
          </button>
        </div>

        <PaymentPluginDetailOverview
          v-if="activePluginDetailView === 'overview'"
          :detail="activeDetail"
          :plugin-overview-cards="pluginOverviewCards"
          :lifecycle-action-loading="lifecycleActionLoading"
          :bundle-exporting="bundleExporting"
          :can-install-detail="canInstallDetail"
          :can-enable-detail="canEnableDetail"
          :can-disable-detail="canDisableDetail"
          :can-uninstall-detail="canUninstallDetail"
          :can-upgrade-detail="canUpgradeDetail"
          :can-repair-detail="canRepairDetail"
          @install="installPlugin"
          @enable="enablePlugin"
          @disable="disablePlugin"
          @uninstall="uninstallPlugin"
          @upgrade="upgradePlugin"
          @repair="repairPlugin"
          @download-bundle="downloadPluginBundle"
        />

        <PaymentPluginDetailSnapshot
          v-if="activePluginDetailView === 'snapshot'"
          :detail="activeDetail"
          :active-snapshots="activeSnapshots"
          :snapshots-loading="snapshotsLoading"
          :recovery-only-mode="recoveryOnlyMode"
          :has-plugin-create-snapshot-auth="hasPluginCreateSnapshotAuth"
          :has-plugin-restore-snapshot-auth="hasPluginRestoreSnapshotAuth"
          :has-plugin-delete-snapshot-auth="hasPluginDeleteSnapshotAuth"
          :snapshot-creating="snapshotCreating"
          :snapshot-restoring-id="snapshotRestoringId"
          :snapshot-deleting-id="snapshotDeletingId"
          :format-bytes="formatBytes"
          @capture-snapshot="captureSnapshot"
          @restore-snapshot="restorePluginSnapshot"
          @delete-snapshot="deletePluginSnapshot"
        />

        <template v-if="!recoveryOnlyMode">
          <PaymentPluginDetailConfig
            v-if="activePluginDetailView === 'config'"
            :detail="activeDetail"
            :legacy-profile="activeLegacyProfile"
            :config-sections="activePluginConfigSections"
            :config-form="configForm"
            :has-plugin-save-config-auth="hasPluginSaveConfigAuth"
            :config-saving="configSaving"
            :can-save-config="canSaveConfig"
            @open-workspace="openPluginWorkspace"
            @save="savePluginConfig"
            @update-config-field="handlePluginConfigFieldUpdate"
          />

          <PaymentPluginDetailCleanup
            v-if="activePluginDetailView === 'cleanup'"
            :detail="activeDetail"
            :cleanup-panels="cleanupPanels"
            :active-cleanup-panel="activeCleanupPanel"
            :cleanup-visibility="cleanupVisibility"
            :cleanup-loading="cleanupLoading"
            :purge-cleanup-loading="purgeCleanupLoading"
            :snapshot-creating="snapshotCreating"
            :recovery-only-mode="recoveryOnlyMode"
            :has-plugin-create-snapshot-auth="hasPluginCreateSnapshotAuth"
            :has-plugin-cleanup-safe-auth="hasPluginCleanupSafeAuth"
            :has-plugin-cleanup-purge-auth="hasPluginCleanupPurgeAuth"
            :can-run-safe-cleanup="canRunSafeCleanup"
            :can-run-purge-cleanup="canRunPurgeCleanup"
            :purge-action-hint="purgeActionHint"
            :format-bytes="formatBytes"
            @change-panel="activeCleanupPanel = $event"
            @toggle-visibility="toggleCleanupVisibility"
            @run-safe-cleanup="runSafeCleanup"
            @run-purge-cleanup="runPurgeCleanup"
            @capture-snapshot="captureSnapshot"
          />
        </template>
      </template>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElButton, ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useRouter } from 'vue-router'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import PluginScaffoldDialog from './modules/plugin-scaffold-dialog.vue'
  import PaymentPluginGovernancePanels from './modules/plugin-governance-panels.vue'
  import PaymentPluginListWorkspace from './modules/plugin-list-workspace.vue'
  import PaymentPluginDetailCleanup from './modules/plugin-detail-cleanup.vue'
  import PaymentPluginDetailConfig from './modules/plugin-detail-config.vue'
  import PaymentPluginDetailOverview from './modules/plugin-detail-overview.vue'
  import PaymentPluginDetailSnapshot from './modules/plugin-detail-snapshot.vue'
  import {
    buildPluginCleanupSuccessMessage,
    buildPluginConfigFormState,
    buildPluginConfigPayload,
    buildPluginPurgeCleanupPrompt,
    buildPluginRepairConfirmationMessage,
    buildPluginSafeCleanupConfirmationMessage,
    buildPluginUninstallConfirmationMessage,
    buildPluginUpgradeConfirmationMessage,
    buildRegistryResidueCleanupSuccessMessage,
    bundleFilename,
    cleanupResiduePhraseForItem,
    deleteSnapshotConfirmationPhrase,
    downloadTextFile,
    escapeRegExp,
    purgeConfirmationPhraseForDetail,
    purgeGuardForDetail,
    restoreConfirmationPhrase
  } from './modules/payment-plugin-lifecycle-display'
  import type { PaymentPluginScaffoldSubmitPayload } from '@/views/shared/paymentPluginScaffold'
  import {
    getPaymentPluginLegacyProfile,
    type PaymentPluginLegacyProfile
  } from '@/views/payments/shared/paymentPluginLegacyProfiles'
  import {
    auditLabel,
    auditTagType,
    auditSummaryLabel,
    buildPluginConfigSections,
    formatBytes,
    normalizePluginCopy,
    overviewAuditTone,
    overviewChannelTone,
    overviewChannelValue,
    overviewConfigTone,
    overviewConfigValue,
    overviewMigrationTone,
    overviewMigrationValue,
    pluginAccessLabel,
    pluginPaymentLabel,
    pluginPaymentTagType,
    residueManagedChannelBlockSummary,
    resolvePluginPaymentFilter,
    type PluginConfigSection,
    type PluginOverviewCardTone,
    type PluginPaymentFilterKey
  } from '@/views/payments/shared/paymentPluginDisplay'
  import {
    fetchCleanupPaymentPlugin,
    fetchCleanupPaymentPluginRegistryResidue,
    fetchCreatePaymentPluginScaffold,
    fetchCreatePaymentPluginSnapshot,
    fetchDeletePaymentPluginSnapshot,
    fetchDisablePaymentPlugin,
    fetchGetPaymentPluginBundle,
    fetchEnablePaymentPlugin,
    fetchGetPaymentPluginDetail,
    fetchGetPaymentPluginRecoveryVault,
    fetchGetPaymentPluginSnapshots,
    fetchGetPaymentPluginUninstallPlan,
    fetchGetPaymentPlugins,
    fetchInstallPaymentPlugin,
    fetchPurgeCleanupPaymentPlugin,
    fetchRepairPaymentPlugin,
    fetchRestorePaymentPluginSnapshot,
    fetchUpgradePaymentPlugin,
    fetchSavePaymentPluginConfig,
    fetchUninstallPaymentPlugin
  } from '@/api/system-manage'

  defineOptions({ name: 'PaymentPlugins' })

  type PaymentPluginItem = Api.SystemManage.PaymentPluginItem
  type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail
  type PaymentPluginCleanupResponse = Api.SystemManage.PaymentPluginCleanupResponse
  type PaymentPluginSnapshotItem = Api.SystemManage.PaymentPluginSnapshotItem
  type PaymentPluginSnapshotList = Api.SystemManage.PaymentPluginSnapshotList
  type PaymentPluginSnapshotDeleteResponse = Api.SystemManage.PaymentPluginSnapshotDeleteResponse
  type PaymentPluginRegistryResidueItem = Api.SystemManage.PaymentPluginRegistryResidueItem
  type PaymentPluginRegistryResidueResponse = Api.SystemManage.PaymentPluginRegistryResidueResponse
  type PaymentPluginRegistryResidueLedgerResponse =
    Api.SystemManage.PaymentPluginRegistryResidueLedgerResponse
  type PaymentPluginRecoveryVaultItem = Api.SystemManage.PaymentPluginRecoveryVaultItem
  type PaymentPluginRecoveryVaultResponse = Api.SystemManage.PaymentPluginRecoveryVaultResponse
  type PaymentPluginScaffoldResponse = Api.SystemManage.PaymentPluginScaffoldResponse
  type PluginListViewKey = 'all' | 'installed' | 'enabled' | 'attention'
  type PluginDetailViewKey = 'overview' | 'config' | 'snapshot' | 'cleanup'
  type PluginCleanupPanelKey = 'safe' | 'purge'
  type CleanupVisibilityState = {
    safeFiles: boolean
    safeTables: boolean
    safeChannels: boolean
    safeNotes: boolean
    purgeFiles: boolean
    purgeTables: boolean
    purgeChannels: boolean
    purgeNotes: boolean
  }

  const createCleanupVisibility = (): CleanupVisibilityState => ({
    safeFiles: false,
    safeTables: false,
    safeChannels: false,
    safeNotes: false,
    purgeFiles: false,
    purgeTables: false,
    purgeChannels: false,
    purgeNotes: false
  })

  const { hasAuth } = useAuth()
  const router = useRouter()
  const loading = ref(false)
  const cleanupLoading = ref(false)
  const pluginTableCompact = ref(typeof window !== 'undefined' ? window.innerWidth <= 560 : false)
  const purgeCleanupLoading = ref(false)
  const configSaving = ref(false)
  const bundleExporting = ref(false)
  const snapshotsLoading = ref(false)
  const lifecycleActionLoading = ref<'install' | 'enable' | 'disable' | 'uninstall' | ''>('')
  const snapshotCreating = ref(false)
  const snapshotRestoringId = ref('')
  const snapshotDeletingId = ref('')
  const residueCleaningCode = ref('')
  const keyword = ref('')
  const governanceExpanded = ref(false)
  const activePluginView = ref<PluginListViewKey>('all')
  const activePluginPaymentFilter = ref<PluginPaymentFilterKey>('all')
  const plugins = ref<PaymentPluginItem[]>([])
  const registryResidue = ref<PaymentPluginRegistryResidueResponse | null>(null)
  const registryResidueLedger = ref<PaymentPluginRegistryResidueLedgerResponse | null>(null)
  const recoveryVault = ref<PaymentPluginRecoveryVaultResponse | null>(null)
  const detailVisible = ref(false)
  const activeDetail = ref<PaymentPluginDetail | null>(null)
  const activeSnapshots = ref<PaymentPluginSnapshotList | null>(null)
  const recoveryOnlyMode = ref(false)
  const activePluginDetailView = ref<PluginDetailViewKey>('overview')
  const activeCleanupPanel = ref<PluginCleanupPanelKey>('safe')
  const cleanupVisibility = ref<CleanupVisibilityState>(createCleanupVisibility())
  const configForm = ref<Record<string, string>>({})
  const scaffoldDialogVisible = ref(false)
  const scaffoldCreating = ref(false)
  const hasPluginScaffoldAuth = computed(() => hasAuth('scaffold'))
  const hasPluginInstallAuth = computed(() => hasAuth('install'))
  const hasPluginRepairAuth = computed(() => hasAuth('repair'))
  const hasPluginUpgradeAuth = computed(() => hasAuth('upgrade'))
  const hasPluginEnableAuth = computed(() => hasAuth('enable'))
  const hasPluginDisableAuth = computed(() => hasAuth('disable'))
  const hasPluginSaveConfigAuth = computed(() => hasAuth('saveConfig'))
  const hasPluginCreateSnapshotAuth = computed(() => hasAuth('createSnapshot'))
  const hasPluginRestoreSnapshotAuth = computed(() => hasAuth('restoreSnapshot'))
  const hasPluginDeleteSnapshotAuth = computed(() => hasAuth('deleteSnapshot'))
  const hasPluginUninstallAuth = computed(() => hasAuth('uninstall'))
  const hasPluginCleanupSafeAuth = computed(() => hasAuth('cleanupSafe'))
  const hasPluginCleanupPurgeAuth = computed(() => hasAuth('cleanupPurge'))
  const hasPluginCleanupResidueAuth = computed(() => hasAuth('cleanupRegistryResidue'))
  const showGovernancePanels = computed(() => governanceExpanded.value)
  const activeLegacyProfile = computed<PaymentPluginLegacyProfile | null>(() =>
    getPaymentPluginLegacyProfile(activeDetail.value?.manifest.code)
  )
  const activePluginConfigSections = computed<PluginConfigSection[]>(() =>
    buildPluginConfigSections(activeDetail.value?.config_schema || [])
  )
  const handlePluginConfigFieldUpdate = (payload: { field: string; value: string }) => {
    configForm.value = {
      ...configForm.value,
      [payload.field]: payload.value
    }
  }

  const pluginDetailViews = computed<Array<{ key: PluginDetailViewKey; label: string }>>(() => {
    const views = [
      { key: 'overview' as const, label: '概览' },
      { key: 'config' as const, label: '接入字段' },
      { key: 'snapshot' as const, label: '快照' },
      { key: 'cleanup' as const, label: '清理' }
    ]

    if (recoveryOnlyMode.value) {
      return views.filter((item) => item.key === 'overview' || item.key === 'snapshot')
    }

    return views
  })

  watch(
    pluginDetailViews,
    (views) => {
      if (!views.some((item) => item.key === activePluginDetailView.value)) {
        activePluginDetailView.value = recoveryOnlyMode.value ? 'snapshot' : 'overview'
      }
    },
    { immediate: true }
  )

  const installedCount = computed(() => plugins.value.filter((item) => item.installed).length)
  const enabledCount = computed(() => plugins.value.filter((item) => item.enabled).length)
  const attentionCount = computed(
    () =>
      plugins.value.filter(
        (item) =>
          item.state_audit.health !== 'healthy' ||
          item.state_audit.repair_recommended ||
          item.state_audit.upgrade_recommended
      ).length
  )
  const governanceSnapshotCount = computed(() => recoveryVault.value?.summary.total_snapshots || 0)
  const governanceResidueCount = computed(() => registryResidue.value?.summary.total_items || 0)
  const governanceLedgerCount = computed(
    () => registryResidueLedger.value?.summary.total_events || 0
  )
  const governanceAttentionCount = computed(
    () =>
      (recoveryVault.value?.summary.catalog_missing_count || 0) +
      (registryResidue.value?.summary.total_items || 0)
  )
  const hasGovernanceData = computed(
    () =>
      governanceSnapshotCount.value > 0 ||
      governanceResidueCount.value > 0 ||
      governanceLedgerCount.value > 0
  )
  const pluginViews = computed<Array<{ key: PluginListViewKey; label: string; count: number }>>(
    () => [
      {
        key: 'all',
        label: '全部插件',
        count: plugins.value.length
      },
      {
        key: 'installed',
        label: '已安装',
        count: installedCount.value
      },
      {
        key: 'enabled',
        label: '已启用',
        count: enabledCount.value
      },
      {
        key: 'attention',
        label: '待处理',
        count: attentionCount.value
      }
    ]
  )
  const pluginPaymentFilterCatalog = [
    { key: 'all' as const, label: '全部方式' },
    { key: 'alipay' as const, label: '支付宝' },
    { key: 'wxpay' as const, label: '微信' },
    { key: 'qqpay' as const, label: 'QQ' },
    { key: 'usdt' as const, label: 'USDT' },
    { key: 'other' as const, label: '其它' }
  ]
  const pluginOverviewCards = computed<
    Array<{ key: string; label: string; value: string; tone: PluginOverviewCardTone }>
  >(() => {
    const detail = activeDetail.value
    if (!detail) return []

    return [
      {
        key: 'audit',
        label: '运行',
        value: auditSummaryLabel(detail.state_audit),
        tone: overviewAuditTone(detail)
      },
      {
        key: 'config',
        label: '配置',
        value: overviewConfigValue(detail),
        tone: overviewConfigTone(detail)
      },
      {
        key: 'migration',
        label: '版本',
        value: overviewMigrationValue(detail),
        tone: overviewMigrationTone(detail)
      },
      {
        key: 'channel',
        label: '通道',
        value: overviewChannelValue(detail),
        tone: overviewChannelTone(detail)
      }
    ]
  })
  const cleanupPanels = computed<Array<{ key: PluginCleanupPanelKey; label: string }>>(() => [
    {
      key: 'safe',
      label: '安全清理'
    },
    {
      key: 'purge',
      label: '彻底清理'
    }
  ])
  const canRunSafeCleanup = computed(() => {
    const detail = activeDetail.value
    if (!detail) {
      return false
    }
    if (detail.state.installed || detail.state.enabled) {
      return false
    }

    return (
      detail.uninstall_plan.summary.existing_file_count > 0 ||
      detail.uninstall_plan.summary.existing_table_count > 0 ||
      detail.uninstall_plan.summary.existing_managed_channel_count > 0
    )
  })

  const canRunPurgeCleanup = computed(() => {
    const detail = activeDetail.value
    if (!detail) {
      return false
    }
    if (detail.state.installed || detail.state.enabled) {
      return false
    }

    return (
      detail.purge_plan.summary.existing_file_count > 0 ||
      detail.purge_plan.summary.existing_table_count > 0 ||
      detail.purge_plan.summary.existing_managed_channel_count > 0
    )
  })

  const purgeActionHint = computed(() => {
    const detail = activeDetail.value
    if (!detail) {
      return '请先选择一个插件，查看其彻底清理范围。'
    }
    if (detail.state.installed || detail.state.enabled) {
      return '请先停用并卸载插件，再继续。'
    }
    if (detail.purge_plan.snapshot_guard && !detail.purge_plan.snapshot_guard.has_snapshot) {
      return '当前没有恢复快照，建议先创建后再继续。'
    }

    return '当前没有可清理项。'
  })

  const toggleCleanupVisibility = (key: keyof CleanupVisibilityState) => {
    cleanupVisibility.value = {
      ...cleanupVisibility.value,
      [key]: !cleanupVisibility.value[key]
    }
  }

  const resetDetailWorkspace = (view: PluginDetailViewKey = 'overview') => {
    activePluginDetailView.value = view
    activeCleanupPanel.value = 'safe'
    cleanupVisibility.value = createCleanupVisibility()
  }

  const canSaveConfig = computed(() => {
    const detail = activeDetail.value
    return Boolean(detail?.state.installed && detail.state_audit.config_table_exists)
  })

  const canInstallDetail = computed(
    () =>
      hasPluginInstallAuth.value &&
      Boolean(activeDetail.value && !activeDetail.value.state.installed)
  )
  const canEnableDetail = computed(
    () =>
      hasPluginEnableAuth.value &&
      Boolean(activeDetail.value?.state.installed && !activeDetail.value.state.enabled)
  )
  const canDisableDetail = computed(
    () => hasPluginDisableAuth.value && Boolean(activeDetail.value?.state.enabled)
  )
  const canUninstallDetail = computed(
    () => hasPluginUninstallAuth.value && Boolean(activeDetail.value?.state.installed)
  )
  const canRepairDetail = computed(
    () => hasPluginRepairAuth.value && Boolean(activeDetail.value?.state_audit.repair_recommended)
  )
  const canUpgradeDetail = computed(
    () => hasPluginUpgradeAuth.value && Boolean(activeDetail.value?.state_audit.upgrade_recommended)
  )

  const viewFilteredPlugins = computed(() =>
    plugins.value.filter((item) => {
      if (activePluginView.value === 'installed') {
        return item.installed
      }

      if (activePluginView.value === 'enabled') {
        return item.enabled
      }

      if (activePluginView.value === 'attention') {
        return (
          item.state_audit.health !== 'healthy' ||
          item.state_audit.repair_recommended ||
          item.state_audit.upgrade_recommended
        )
      }

      return true
    })
  )
  const keywordFilteredPlugins = computed(() => {
    const text = keyword.value.trim().toLowerCase()
    if (!text) {
      return viewFilteredPlugins.value
    }

    return viewFilteredPlugins.value.filter((item) => {
      const fields = [
        item.code,
        item.name,
        item.description,
        item.provider,
        item.state_audit.health,
        pluginPaymentLabel(item.code),
        ...(item.capabilities || [])
      ]
      return fields.some((field) =>
        String(field || '')
          .toLowerCase()
          .includes(text)
      )
    })
  })
  const pluginPaymentFilters = computed<
    Array<{ key: PluginPaymentFilterKey; label: string; count: number }>
  >(() => {
    return pluginPaymentFilterCatalog
      .map((item) => ({
        ...item,
        count:
          item.key === 'all'
            ? keywordFilteredPlugins.value.length
            : keywordFilteredPlugins.value.filter(
                (plugin) => resolvePluginPaymentFilter(plugin.code) === item.key
              ).length
      }))
      .filter((item) => item.key === 'all' || item.count > 0)
  })
  const filteredPlugins = computed(() => {
    if (activePluginPaymentFilter.value === 'all') {
      return keywordFilteredPlugins.value
    }

    return keywordFilteredPlugins.value.filter(
      (item) => resolvePluginPaymentFilter(item.code) === activePluginPaymentFilter.value
    )
  })

  watch(
    pluginPaymentFilters,
    (filters) => {
      if (!filters.some((item) => item.key === activePluginPaymentFilter.value)) {
        activePluginPaymentFilter.value = 'all'
      }
    },
    { immediate: true }
  )
  const openPluginWorkspace = (profile: PaymentPluginLegacyProfile, code: string) => {
    if (profile.workspace === 'account') {
      detailVisible.value = false
      router.push({
        path: '/payments/accounts',
        query: {
          keyword: code
        }
      })
      return
    }

    if (profile.workspace === 'merchant-channel') {
      detailVisible.value = false
      router.push({
        path: '/merchant/channels'
      })
    }
  }

  const ensurePluginWriteAuth = (allowed: boolean, actionLabel: string) => {
    if (allowed) {
      return true
    }

    ElMessage.warning(`当前账号没有支付插件${actionLabel}权限`)
    return false
  }

  const openScaffoldDialog = () => {
    if (!ensurePluginWriteAuth(hasPluginScaffoldAuth.value, '创建支付插件')) {
      return
    }

    scaffoldDialogVisible.value = true
  }

  const { columns, toggleColumn, updateColumn } = useTableColumns<PaymentPluginItem>(() => [
    {
      prop: 'name',
      label: '插件',
      minWidth: 208,
      formatter: (row) =>
        h('div', { class: 'plugin-main-cell plugin-main-cell--compact plugin-main-cell--dense' }, [
          h('strong', { class: 'plugin-name' }, normalizePluginCopy(row.name)),
          pluginTableCompact.value
            ? h(
                ElButton,
                {
                  type: 'primary',
                  link: true,
                  class: 'plugin-inline-action',
                  onClick: () => openDetail(row.code)
                },
                () => '详情'
              )
            : null
        ])
    },
    {
      prop: 'status',
      label: '状态',
      minWidth: 110,
      formatter: (row) => {
        const issueCount = row.state_audit.issues.length
        const attentionLabel = row.state_audit.repair_recommended
          ? '需修复'
          : row.state_audit.upgrade_recommended
            ? '待升级'
            : row.state_audit.health !== 'healthy'
              ? auditLabel(row.state_audit.health)
              : issueCount > 0
                ? `${issueCount} 项提示`
                : ''
        const attentionType =
          row.state_audit.health !== 'healthy' &&
          !row.state_audit.repair_recommended &&
          !row.state_audit.upgrade_recommended
            ? auditTagType(row.state_audit.health)
            : 'warning'

        return h('div', { class: 'capability-cell plugin-status-cell' }, [
          h(ElTag, { type: row.installed ? 'success' : 'info', effect: 'plain' }, () =>
            row.installed ? '已安装' : '未安装'
          ),
          h(ElTag, { type: row.enabled ? 'success' : 'warning', effect: 'plain' }, () =>
            row.enabled ? '已启用' : '已停用'
          ),
          attentionLabel
            ? h(
                ElTag,
                {
                  type: attentionType,
                  effect: 'plain',
                  title: row.state_audit.repair_recommended
                    ? normalizePluginCopy(row.state_audit.repair_reason || '插件资源需要重新同步')
                    : attentionLabel
                },
                () => attentionLabel
              )
            : null
        ])
      }
    },
    {
      prop: 'capabilities',
      label: '接入',
      minWidth: 150,
      formatter: (row) => {
        const paymentLabel = pluginPaymentLabel(row.code)
        const accessLabel = pluginAccessLabel(row.code)

        return h('div', { class: 'capability-cell capability-summary-cell' }, [
          h(
            ElTag,
            { effect: 'plain', type: pluginPaymentTagType(paymentLabel) },
            () => paymentLabel
          ),
          h(ElTag, { effect: 'plain', type: 'info' }, () => accessLabel)
        ])
      }
    },
    {
      prop: 'operation',
      label: '操作',
      width: 70,
      align: 'right',
      formatter: (row) =>
        h('div', { class: 'plugin-action-row' }, [
          h(
            ElButton,
            {
              type: 'primary',
              link: true,
              class: 'plugin-action-link',
              onClick: () => openDetail(row.code)
            },
            () => '详情'
          )
        ])
    }
  ])

  const syncPluginTableLayout = () => {
    if (typeof window === 'undefined') {
      return
    }

    const compact = window.innerWidth <= 560
    pluginTableCompact.value = compact

    updateColumn([
      { prop: 'name', updates: { minWidth: compact ? 184 : 208 } },
      { prop: 'status', updates: { minWidth: compact ? 96 : 110 } },
      { prop: 'capabilities', updates: { minWidth: compact ? 84 : 96 } },
      { prop: 'operation', updates: { width: compact ? 70 : 70 } }
    ])
    toggleColumn('operation', !compact)
  }

  if (typeof window !== 'undefined') {
    syncPluginTableLayout()
  }

  onMounted(() => {
    loadPlugins()
    window.addEventListener('resize', syncPluginTableLayout)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', syncPluginTableLayout)
  })

  const loadPlugins = async () => {
    loading.value = true
    try {
      const pluginResponse = await fetchGetPaymentPlugins()
      const vaultResponse = await fetchGetPaymentPluginRecoveryVault().catch(() => null)

      plugins.value = pluginResponse.items || []
      registryResidue.value = pluginResponse.registry_residue || null
      registryResidueLedger.value = pluginResponse.registry_residue_ledger || null
      recoveryVault.value = vaultResponse
    } finally {
      loading.value = false
    }
  }

  const applyDetailState = (
    detail: PaymentPluginDetail,
    snapshots: PaymentPluginSnapshotList,
    visible = true
  ) => {
    activeDetail.value = detail
    activeSnapshots.value = snapshots
    recoveryOnlyMode.value = false
    resetDetailWorkspace('overview')
    detailVisible.value = visible
    configForm.value = buildPluginConfigFormState(activeDetail.value)
  }

  const openDetail = async (code: string) => {
    const [detail, snapshots] = await Promise.all([
      fetchGetPaymentPluginDetail(code),
      fetchGetPaymentPluginSnapshots(code)
    ])

    applyDetailState(detail, snapshots)
  }

  const submitScaffold = async (payload: PaymentPluginScaffoldSubmitPayload) => {
    if (!ensurePluginWriteAuth(hasPluginScaffoldAuth.value, '创建支付插件')) {
      return
    }

    scaffoldCreating.value = true
    try {
      const response: PaymentPluginScaffoldResponse =
        await fetchCreatePaymentPluginScaffold(payload)

      await loadPlugins()
      const snapshots = await fetchGetPaymentPluginSnapshots(response.created.plugin_code)
      applyDetailState(response.detail, snapshots)
      scaffoldDialogVisible.value = false
      ElMessage.success(`支付插件已创建：${response.created.plugin_code}`)
    } finally {
      scaffoldCreating.value = false
    }
  }

  const applySnapshotDeleteState = async (
    code: string,
    response: PaymentPluginSnapshotDeleteResponse
  ) => {
    await loadPlugins()

    if (detailVisible.value && activeDetail.value?.manifest.code === code) {
      activeSnapshots.value = response.snapshots

      if (response.detail) {
        recoveryOnlyMode.value = false
        activeDetail.value = response.detail
        resetDetailWorkspace('snapshot')
        configForm.value = buildPluginConfigFormState(activeDetail.value)
        return
      }

      if (!response.catalog_available) {
        recoveryOnlyMode.value = true
        resetDetailWorkspace('snapshot')
        configForm.value = buildPluginConfigFormState(null)
      }
    }
  }

  const installPlugin = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginInstallAuth.value, '安装')) {
      return
    }

    lifecycleActionLoading.value = 'install'
    try {
      const detail = await fetchInstallPaymentPlugin(code)
      ElMessage.success('插件安装成功')
      await reloadAfterAction(code, detail)
    } finally {
      lifecycleActionLoading.value = ''
    }
  }

  const repairPlugin = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginRepairAuth.value, '修复')) {
      return
    }

    const detail =
      activeDetail.value?.manifest.code === code
        ? activeDetail.value
        : await fetchGetPaymentPluginDetail(code)

    await ElMessageBox.confirm(buildPluginRepairConfirmationMessage(detail), '确认修复', {
      confirmButtonText: '执行修复',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const nextDetail = await fetchRepairPaymentPlugin(code)
    ElMessage.success('插件修复成功')
    await reloadAfterAction(code, nextDetail)
  }

  const upgradePlugin = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginUpgradeAuth.value, '升级')) {
      return
    }

    const detail =
      activeDetail.value?.manifest.code === code
        ? activeDetail.value
        : await fetchGetPaymentPluginDetail(code)

    await ElMessageBox.confirm(buildPluginUpgradeConfirmationMessage(detail), '确认升级', {
      confirmButtonText: '执行升级',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const nextDetail = await fetchUpgradePaymentPlugin(code)
    ElMessage.success('插件升级成功')
    await reloadAfterAction(code, nextDetail)
  }

  const enablePlugin = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginEnableAuth.value, '启用')) {
      return
    }

    lifecycleActionLoading.value = 'enable'
    try {
      const detail = await fetchEnablePaymentPlugin(code)
      ElMessage.success('插件已启用')
      await reloadAfterAction(code, detail)
    } finally {
      lifecycleActionLoading.value = ''
    }
  }

  const disablePlugin = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginDisableAuth.value, '停用')) {
      return
    }

    lifecycleActionLoading.value = 'disable'
    try {
      const detail = await fetchDisablePaymentPlugin(code)
      ElMessage.success('插件已关闭')
      await reloadAfterAction(code, detail)
    } finally {
      lifecycleActionLoading.value = ''
    }
  }

  const uninstallPlugin = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginUninstallAuth.value, '卸载')) {
      return
    }

    const plan = await fetchGetPaymentPluginUninstallPlan(code, { purge: false })
    const purgePlan = await fetchGetPaymentPluginUninstallPlan(code, { purge: true })

    await ElMessageBox.confirm(
      buildPluginUninstallConfirmationMessage(plan, purgePlan),
      '确认卸载',
      {
        confirmButtonText: '继续卸载',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    lifecycleActionLoading.value = 'uninstall'
    try {
      const detail = await fetchUninstallPaymentPlugin(code, { purge: false })
      ElMessage.success('插件已标记为未安装')
      await reloadAfterAction(code, detail)
    } finally {
      lifecycleActionLoading.value = ''
    }
  }

  const savePluginConfig = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginSaveConfigAuth.value, '配置保存')) {
      return
    }

    const detail = activeDetail.value
    if (!detail || !detail.state.installed) {
      ElMessage.warning('请先安装插件后再保存配置')
      return
    }

    if (!detail.state_audit.config_table_exists) {
      ElMessage.warning('插件配置表不存在')
      return
    }

    configSaving.value = true
    try {
      const nextDetail = await fetchSavePaymentPluginConfig(code, {
        config: buildPluginConfigPayload(detail, configForm.value)
      })
      ElMessage.success('插件配置已保存')
      await reloadAfterAction(code, nextDetail)
    } finally {
      configSaving.value = false
    }
  }

  const downloadPluginBundle = async (code: string) => {
    bundleExporting.value = true
    try {
      const bundle = await fetchGetPaymentPluginBundle(code)
      downloadTextFile(bundleFilename(bundle), JSON.stringify(bundle, null, 2))
      ElMessage.success('插件记录包已导出')
    } finally {
      bundleExporting.value = false
    }
  }

  const loadActiveSnapshots = async (code: string) => {
    snapshotsLoading.value = true
    try {
      activeSnapshots.value = await fetchGetPaymentPluginSnapshots(code)
    } finally {
      snapshotsLoading.value = false
    }
  }

  const captureSnapshot = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginCreateSnapshotAuth.value, '创建快照')) {
      return
    }

    const detail = activeDetail.value
    if (!detail || recoveryOnlyMode.value) {
      return
    }

    const { value } = await ElMessageBox.prompt(
      [
        '建议在彻底清理、替换插件包或高风险修复前先创建恢复快照。',
        '快照会归档插件目录、运行目录、运行记录、插件专属数据表以及托管通道记录。',
        `当前彻底清理范围：${detail.purge_plan.summary.existing_file_count} 个文件根目录、${detail.purge_plan.summary.existing_table_count} 张数据表、${detail.purge_plan.summary.existing_managed_channel_count} 条托管通道、${detail.purge_plan.summary.table_row_count} 行数据。`,
        '可选：输入一个简短标签，方便后续识别该恢复点。'
      ].join('\n'),
      '创建恢复快照',
      {
        confirmButtonText: '创建快照',
        cancelButtonText: '取消',
        inputPlaceholder: '如：清理前 / 升级前 / 回滚点'
      }
    )

    snapshotCreating.value = true
    try {
      const response = await fetchCreatePaymentPluginSnapshot(code, {
        label: String(value || '').trim() || null
      })

      ElMessage.success(`恢复快照已创建：${response.snapshot.snapshot_id}`)
      await reloadAfterAction(code, response.detail)
    } finally {
      snapshotCreating.value = false
    }
  }

  const restorePluginSnapshot = async (snapshot: PaymentPluginSnapshotItem) => {
    if (!ensurePluginWriteAuth(hasPluginRestoreSnapshotAuth.value, '恢复快照')) {
      return
    }

    const detail = activeDetail.value
    if (!detail) {
      return
    }

    const phrase = restoreConfirmationPhrase(detail.manifest.code)
    const label = snapshot.label || snapshot.snapshot_id
    const { value } = await ElMessageBox.prompt(
      [
        '恢复快照会用归档内容覆盖当前插件专属文件和数据表。',
        `快照：${label}`,
        `创建时间：${snapshot.created_at || '--'}`,
        `归档内容：文件 ${snapshot.summary.archived_file_count}，数据表 ${snapshot.summary.table_count}，托管通道 ${snapshot.summary.existing_managed_channel_count}/${snapshot.summary.managed_channel_count}，记录 ${snapshot.summary.row_count}`,
        `请输入 ${phrase} 后继续。`
      ].join('\n'),
      '恢复快照',
      {
        confirmButtonText: '确认恢复',
        cancelButtonText: '取消',
        type: 'warning',
        inputPlaceholder: phrase,
        inputPattern: new RegExp(`^${escapeRegExp(phrase)}$`),
        inputErrorMessage: `请输入完整的 ${phrase} 后继续。`
      }
    )

    snapshotRestoringId.value = snapshot.snapshot_id
    try {
      const response = await fetchRestorePaymentPluginSnapshot(detail.manifest.code, {
        snapshot_id: snapshot.snapshot_id,
        confirm_code: detail.manifest.code,
        confirm_phrase: String(value || '')
      })

      ElMessage.success(`恢复快照已恢复：${label}`)
      await reloadAfterAction(detail.manifest.code, response.detail)
    } finally {
      snapshotRestoringId.value = ''
    }
  }

  const deletePluginSnapshot = async (snapshot: PaymentPluginSnapshotItem) => {
    if (!ensurePluginWriteAuth(hasPluginDeleteSnapshotAuth.value, '删除快照')) {
      return
    }

    const detail = activeDetail.value
    if (!detail) {
      return
    }

    const phrase = deleteSnapshotConfirmationPhrase(snapshot.snapshot_id)
    const label = snapshot.label || snapshot.snapshot_id
    const { value } = await ElMessageBox.prompt(
      [
        '删除恢复快照会永久移除该归档回滚包。',
        '如果这是该插件最后一个快照，空快照目录也会一并清除。',
        `快照：${label}`,
        `创建时间：${snapshot.created_at || '--'}`,
        `归档内容：文件 ${snapshot.summary.archived_file_count}，数据表 ${snapshot.summary.table_count}，托管通道 ${snapshot.summary.existing_managed_channel_count}/${snapshot.summary.managed_channel_count}，记录 ${snapshot.summary.row_count}`,
        `请输入 ${phrase} 后继续。`
      ].join('\n'),
      '删除恢复快照',
      {
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
        type: 'error',
        inputPlaceholder: phrase,
        inputPattern: new RegExp(`^${escapeRegExp(phrase)}$`),
        inputErrorMessage: `请输入完整的 ${phrase} 后继续。`
      }
    )

    snapshotDeletingId.value = snapshot.snapshot_id
    try {
      const response = await fetchDeletePaymentPluginSnapshot(detail.manifest.code, {
        snapshot_id: snapshot.snapshot_id,
        confirm_code: detail.manifest.code,
        confirm_phrase: String(value || '')
      })

      ElMessage.success(`恢复快照已删除：${label}`)
      await applySnapshotDeleteState(detail.manifest.code, response)
    } finally {
      snapshotDeletingId.value = ''
    }
  }

  const restoreVaultSnapshot = async (snapshot: PaymentPluginRecoveryVaultItem) => {
    if (!ensurePluginWriteAuth(hasPluginRestoreSnapshotAuth.value, '恢复快照')) {
      return
    }

    if (!snapshot.restorable) {
      ElMessage.warning(snapshot.restore_blocked_reason || '请先停用插件后再恢复快照')
      return
    }

    const phrase = restoreConfirmationPhrase(snapshot.plugin_code)
    const label = snapshot.label || snapshot.snapshot_id
    const { value } = await ElMessageBox.prompt(
      [
        '本次恢复来自恢复中心，可重建已从本地目录彻底移除的插件。',
        `插件：${snapshot.plugin_name}（${snapshot.plugin_code}）`,
        `快照：${label}`,
        `归档内容：文件 ${snapshot.summary.archived_file_count}，数据表 ${snapshot.summary.table_count}，托管通道 ${snapshot.summary.existing_managed_channel_count}/${snapshot.summary.managed_channel_count}，记录 ${snapshot.summary.row_count}`,
        `请输入 ${phrase} 后继续。`
      ].join('\n'),
      '从恢复中心恢复',
      {
        confirmButtonText: '确认恢复',
        cancelButtonText: '取消',
        type: 'warning',
        inputPlaceholder: phrase,
        inputPattern: new RegExp(`^${escapeRegExp(phrase)}$`),
        inputErrorMessage: `请输入完整的 ${phrase} 后继续。`
      }
    )

    snapshotRestoringId.value = snapshot.snapshot_id
    try {
      const response = await fetchRestorePaymentPluginSnapshot(snapshot.plugin_code, {
        snapshot_id: snapshot.snapshot_id,
        confirm_code: snapshot.plugin_code,
        confirm_phrase: String(value || '')
      })

      ElMessage.success(`恢复中心快照已恢复：${label}`)
      await loadPlugins()
      const snapshots = await fetchGetPaymentPluginSnapshots(snapshot.plugin_code)
      applyDetailState(response.detail, snapshots)
    } finally {
      snapshotRestoringId.value = ''
    }
  }

  const deleteVaultSnapshot = async (snapshot: PaymentPluginRecoveryVaultItem) => {
    if (!ensurePluginWriteAuth(hasPluginDeleteSnapshotAuth.value, '删除快照')) {
      return
    }

    const phrase = deleteSnapshotConfirmationPhrase(snapshot.snapshot_id)
    const label = snapshot.label || snapshot.snapshot_id
    const { value } = await ElMessageBox.prompt(
      [
        '删除该恢复中心条目会永久移除归档回滚包。',
        '仅在确认该恢复点已失效、后续不再需要时使用。',
        `插件：${snapshot.plugin_name}（${snapshot.plugin_code}）`,
        `快照：${label}`,
        `归档内容：文件 ${snapshot.summary.archived_file_count}，数据表 ${snapshot.summary.table_count}，托管通道 ${snapshot.summary.existing_managed_channel_count}/${snapshot.summary.managed_channel_count}，记录 ${snapshot.summary.row_count}`,
        `请输入 ${phrase} 后继续。`
      ].join('\n'),
      '删除恢复中心条目',
      {
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
        type: 'error',
        inputPlaceholder: phrase,
        inputPattern: new RegExp(`^${escapeRegExp(phrase)}$`),
        inputErrorMessage: `请输入完整的 ${phrase} 后继续。`
      }
    )

    snapshotDeletingId.value = snapshot.snapshot_id
    try {
      const response = await fetchDeletePaymentPluginSnapshot(snapshot.plugin_code, {
        snapshot_id: snapshot.snapshot_id,
        confirm_code: snapshot.plugin_code,
        confirm_phrase: String(value || '')
      })

      ElMessage.success(`恢复中心快照已删除：${label}`)
      await applySnapshotDeleteState(snapshot.plugin_code, response)
    } finally {
      snapshotDeletingId.value = ''
    }
  }

  const cleanupRegistryResidueItem = async (item: PaymentPluginRegistryResidueItem) => {
    if (!ensurePluginWriteAuth(hasPluginCleanupResidueAuth.value, '清理残留')) {
      return
    }

    if (item.summary.blocked_managed_channel_count > 0) {
      ElMessage.warning(`托管通道清理受阻。${residueManagedChannelBlockSummary(item)}`)
      return
    }

    const phrase = cleanupResiduePhraseForItem(item)
    const missingSnapshot = !item.snapshot_guard.has_snapshot
    const { value } = await ElMessageBox.prompt(
      [
        '本次清理针对已经不在当前插件目录中的孤立插件项。',
        missingSnapshot
          ? '当前没有可用恢复快照。继续清理将移除最后一份运行目录、运行记录和数据表，后台将无法直接恢复。'
          : `当前保留恢复快照：${item.snapshot_guard.snapshot_total} 个。清理后这些恢复点仍会保留在恢复中心中。`,
        `插件编码：${item.plugin_code}`,
        `运行目录：${item.runtime_audit.exists ? '有' : '无'}，运行记录：${item.history_audit.exists ? '有' : '无'}，插件目录：${item.plugin_directory_audit.exists ? '有' : '无'}`,
        `命名空间数据表：${item.summary.existing_table_count}，记录：${item.summary.table_row_count}`,
        `托管通道：${item.summary.existing_managed_channel_count}（可清理 ${item.summary.deletable_managed_channel_count}，阻塞 ${item.summary.blocked_managed_channel_count}）`,
        `请输入 ${phrase} 后继续。`
      ].join('\n'),
      '清理孤立插件项',
      {
        confirmButtonText: missingSnapshot ? '无快照清理' : '确认清理',
        cancelButtonText: '取消',
        type: 'error',
        inputPlaceholder: phrase,
        inputPattern: new RegExp(`^${escapeRegExp(phrase)}$`),
        inputErrorMessage: `请输入完整的 ${phrase} 后继续。`
      }
    )

    residueCleaningCode.value = item.plugin_code
    try {
      const response = await fetchCleanupPaymentPluginRegistryResidue(item.plugin_code, {
        confirm_code: item.plugin_code,
        confirm_phrase: String(value || '')
      })

      registryResidue.value = response.registry_residue
      registryResidueLedger.value = response.registry_residue_ledger
      recoveryVault.value = response.recovery_vault
      await loadPlugins()
      ElMessage.success(buildRegistryResidueCleanupSuccessMessage(response.cleanup_report))
    } finally {
      residueCleaningCode.value = ''
    }
  }

  const runSafeCleanup = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginCleanupSafeAuth.value, '安全清理')) {
      return
    }

    const detail = activeDetail.value
    if (!detail) {
      return
    }

    await ElMessageBox.confirm(buildPluginSafeCleanupConfirmationMessage(detail), '确认安全清理', {
      confirmButtonText: '执行清理',
      cancelButtonText: '取消',
      type: 'warning'
    })

    cleanupLoading.value = true
    try {
      const response = await fetchCleanupPaymentPlugin(code, {
        confirm_code: code
      })

      ElMessage.success(buildPluginCleanupSuccessMessage(response.cleanup_report, '安全清理完成'))
      await reloadAfterAction(code, response.detail)
    } finally {
      cleanupLoading.value = false
    }
  }

  const runPurgeCleanup = async (code: string) => {
    if (!ensurePluginWriteAuth(hasPluginCleanupPurgeAuth.value, '彻底清理')) {
      return
    }

    const detail = activeDetail.value
    if (!detail) {
      return
    }

    const phrase = purgeConfirmationPhraseForDetail(detail)
    const guard = purgeGuardForDetail(detail)
    const missingSnapshot = Boolean(guard && !guard.has_snapshot)
    const { value } = await ElMessageBox.prompt(
      buildPluginPurgeCleanupPrompt(detail, phrase),
      '确认彻底清理',
      {
        confirmButtonText: missingSnapshot ? '无快照彻底清理' : '立即彻底清理',
        cancelButtonText: '取消',
        type: 'error',
        inputPlaceholder: phrase,
        inputPattern: new RegExp(`^${escapeRegExp(phrase)}$`),
        inputErrorMessage: `请输入完整的 ${phrase} 后继续。`
      }
    )

    purgeCleanupLoading.value = true
    try {
      const response = await fetchPurgeCleanupPaymentPlugin(code, {
        confirm_code: code,
        confirm_phrase: String(value || '')
      })

      ElMessage.success(buildPluginCleanupSuccessMessage(response.cleanup_report, '彻底清理完成'))
      await reloadAfterAction(
        code,
        response.detail,
        response.plugin_removed_from_catalog,
        response.cleanup_report
      )
    } finally {
      purgeCleanupLoading.value = false
    }
  }

  const reloadAfterAction = async (
    code: string,
    nextDetail: PaymentPluginDetail | PaymentPluginCleanupResponse['detail'] | null = null,
    pluginRemovedFromCatalog = false,
    cleanupReport: PaymentPluginCleanupResponse['cleanup_report'] | null = null
  ) => {
    await loadPlugins()
    if (pluginRemovedFromCatalog) {
      if (detailVisible.value && activeDetail.value?.manifest.code === code) {
        recoveryOnlyMode.value = true
        resetDetailWorkspace('snapshot')
        activeDetail.value = buildRecoveryOnlyDetail(activeDetail.value, cleanupReport)
        configForm.value = buildPluginConfigFormState(null)
        await loadActiveSnapshots(code)
        return
      }

      activeDetail.value = null
      activeSnapshots.value = null
      recoveryOnlyMode.value = false
      detailVisible.value = false
      resetDetailWorkspace('overview')
      configForm.value = buildPluginConfigFormState(null)
      return
    }

    if (detailVisible.value && activeDetail.value?.manifest.code === code) {
      recoveryOnlyMode.value = false
      resetDetailWorkspace('overview')
      activeDetail.value = nextDetail || (await fetchGetPaymentPluginDetail(code))
      await loadActiveSnapshots(code)
      configForm.value = buildPluginConfigFormState(activeDetail.value)
    }
  }

  const buildRecoveryOnlyDetail = (
    detail: PaymentPluginDetail,
    cleanupReport: PaymentPluginCleanupResponse['cleanup_report'] | null
  ): PaymentPluginDetail => {
    const finishedAt =
      cleanupReport?.finished_at || detail.state.updated_at || detail.state.uninstalled_at
    const remainingManagedChannels = detail.managed_channels.map((item) => {
      const cleanupItem = cleanupReport?.items.find(
        (reportItem) => reportItem.type === 'managed_channel' && reportItem.target === item.code
      )

      if (cleanupItem?.removed) {
        return {
          ...item,
          exists: false,
          deleted: detail.purge_plan.mode !== 'purge',
          can_cleanup: false,
          row: null,
          blocking_reasons: ['托管通道记录已在彻底清理中移除。']
        }
      }

      return item
    })
    const existingManagedChannelCount = remainingManagedChannels.filter(
      (item) => item.exists
    ).length
    const missingManagedChannelCount = remainingManagedChannels.filter(
      (item) => !item.exists
    ).length
    const driftedManagedChannelCount = remainingManagedChannels.filter(
      (item) => Object.keys(item.drift || {}).length > 0
    ).length

    return {
      ...detail,
      state: {
        ...detail.state,
        installed: false,
        enabled: false,
        status: 'not_installed',
        updated_at: finishedAt,
        uninstalled_at: finishedAt,
        last_action: 'purge_cleanup',
        cleanup_execution: 'purge_completed',
        last_cleanup_report: cleanupReport || detail.state.last_cleanup_report
      },
      state_audit: {
        ...detail.state_audit,
        runtime_exists: false,
        runtime_kind: 'missing',
        config_table_exists: false,
        config_table_rows: null,
        managed_channel_existing_count: existingManagedChannelCount,
        managed_channel_missing_count: missingManagedChannelCount,
        managed_channel_drift_count: driftedManagedChannelCount
      },
      managed_channels: remainingManagedChannels,
      uninstall_plan: {
        ...detail.uninstall_plan,
        summary: {
          existing_file_count: 0,
          existing_table_count: 0,
          table_row_count: 0,
          managed_channel_count: detail.uninstall_plan.summary.managed_channel_count,
          existing_managed_channel_count: existingManagedChannelCount,
          deletable_managed_channel_count: 0,
          blocked_managed_channel_count: existingManagedChannelCount
        },
        file_audit: detail.uninstall_plan.file_audit.map((item) => ({
          ...item,
          exists: false,
          kind: 'missing',
          entry_count: null,
          size_bytes: null
        })),
        table_audit: detail.uninstall_plan.table_audit.map((item) => ({
          ...item,
          exists: false,
          row_count: null
        })),
        managed_channel_audit: remainingManagedChannels.map((item) => ({
          ...item,
          can_cleanup: false
        }))
      },
      purge_plan: {
        ...detail.purge_plan,
        summary: {
          existing_file_count: 0,
          existing_table_count: 0,
          table_row_count: 0,
          managed_channel_count: detail.purge_plan.summary.managed_channel_count,
          existing_managed_channel_count: existingManagedChannelCount,
          deletable_managed_channel_count: 0,
          blocked_managed_channel_count: existingManagedChannelCount
        },
        file_audit: detail.purge_plan.file_audit.map((item) => ({
          ...item,
          exists: false,
          kind: 'missing',
          entry_count: null,
          size_bytes: null
        })),
        table_audit: detail.purge_plan.table_audit.map((item) => ({
          ...item,
          exists: false,
          row_count: null
        })),
        managed_channel_audit: remainingManagedChannels.map((item) => ({
          ...item,
          can_cleanup: false
        }))
      }
    }
  }
</script>

<style scoped lang="scss">
  .payment-plugin-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    padding-bottom: 16px;
  }

  .plugin-main-cell {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .plugin-main-cell--compact {
    gap: 6px;
  }

  .plugin-main-cell--dense {
    gap: 4px;
  }

  .capability-cell,
  .plugin-action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .plugin-name {
    color: #111827;
    flex: 1;
    min-width: 0;
    font-size: 14px;
    line-height: 1.45;
  }

  .capability-summary-cell {
    gap: 4px;
  }

  .plugin-status-cell {
    gap: 4px;
  }

  .plugin-action-link {
    padding: 0 2px;
    font-weight: 600;
  }

  .plugin-inline-action {
    padding: 0;
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
  }

  .plugin-detail-drawer :deep(.el-drawer__body) {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .plugin-detail-switcher {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 5px;
  }

  .plugin-detail-switcher__item {
    display: flex;
    flex-direction: row;
    gap: 0;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 6px 10px;
    text-align: center;
    border: 1px solid rgb(226 232 240 / 0.92);
    border-radius: 8px;
    background: linear-gradient(180deg, rgb(248 250 252 / 0.96), rgb(255 255 255 / 1));
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      transform 0.2s ease;
  }

  .plugin-detail-switcher__item:hover {
    border-color: rgb(59 130 246 / 0.32);
    box-shadow: 0 6px 16px rgb(59 130 246 / 0.08);
    transform: translateY(-1px);
  }

  .plugin-detail-switcher__item--active {
    border-color: rgb(59 130 246 / 0.48);
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.12), transparent 36%),
      linear-gradient(180deg, rgb(239 246 255 / 0.98), rgb(255 255 255 / 1));
    box-shadow: 0 8px 18px rgb(59 130 246 / 0.1);
  }

  .plugin-detail-switcher__label {
    color: #111827;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
  }

  @media (width <= 991px) {
    .plugin-detail-switcher {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
