<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="merchant-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      @search="handleSearch"
      @reset="resetSearchParams"
    />

    <ElCard class="art-table-card" shadow="never">
      <ArtTableHeader
        v-model:columns="columnChecks"
        :loading="loading"
        layout="refresh"
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <ElButton v-if="hasAuth('add')" type="primary" @click="openCreateDialog"
              >新建商户</ElButton
            >
            <ElButton v-if="hasAuth('email')" plain @click="openEmailDialog()">运营邮件</ElButton>
            <ElTag effect="plain">商户总数 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">本页实名 {{ pageRealnameCount }}</ElTag>
            <ElTag type="warning" effect="plain">本页会员 {{ pageVipCount }}</ElTag>
            <ElTag type="danger" effect="plain">本页冻结 {{ pageFrozenCount }}</ElTag>
            <ElButton
              v-if="hasAuth('batchRemove')"
              plain
              type="danger"
              :disabled="selectedMerchants.length === 0"
              @click="handleBatchDeleteMerchants"
            >
              批量删除
            </ElButton>
            <ElTag v-if="selectedMerchants.length > 0" type="danger" effect="plain">
              已选 {{ selectedMerchants.length }}
            </ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleMerchantSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <MerchantUserDetailDrawer
      :visible="detailVisible"
      :detail-loading="detailLoading"
      :active-merchant="activeMerchant"
      :has-edit-auth="hasAuth('edit')"
      :has-email-auth="hasAuth('email')"
      :has-admin-login-auth="hasAuth('adminLogin')"
      :has-remove-auth="hasAuth('remove')"
      :impersonating-merchant="impersonatingMerchant"
      @update:visible="detailVisible = $event"
      @edit="openEditDialog"
      @business="openBusinessDialog"
      @notification="openNotificationDialog"
      @email="openMerchantEmailDialog"
      @impersonate="handleImpersonateMerchant"
      @status="openStatusDialog"
      @delete="handleDeleteMerchant"
    />

    <MerchantUserCreateDialog
      :visible="createVisible"
      :loading="createPreparing"
      :submitting="creatingMerchant"
      :form="createForm"
      :vip-options="createVipOptions"
      @update:visible="createVisible = $event"
      @update:form="syncCreateForm"
      @submit="submitCreateMerchant"
    />

    <MerchantUserMaintenanceDialogs
      :edit-visible="editVisible"
      :business-visible="businessVisible"
      :notification-visible="notificationVisible"
      :status-visible="statusVisible"
      :saving-edit="savingEdit"
      :saving-business="savingBusiness"
      :saving-notification="savingNotification"
      :saving-status="savingStatus"
      :edit-form="editForm"
      :business-form="businessForm"
      :notification-form="notificationForm"
      :status-form="statusForm"
      :vip-options="vipOptions"
      :notification-channel-options="notificationChannelOptions"
      @update:edit-visible="editVisible = $event"
      @update:business-visible="businessVisible = $event"
      @update:notification-visible="notificationVisible = $event"
      @update:status-visible="statusVisible = $event"
      @update:edit-form="syncEditForm"
      @update:business-form="syncBusinessForm"
      @update:notification-form="syncNotificationForm"
      @update:status-form="syncStatusForm"
      @submit:edit="submitEdit"
      @submit:business="submitBusiness"
      @submit:notification="submitNotifications"
      @submit:status="submitStatus"
    />

    <MerchantUserEmailDialog
      :visible="emailVisible"
      :submitting="sendingEmail"
      :form="emailForm"
      :active-merchant="activeMerchant"
      @update:visible="emailVisible = $event"
      @update:form="syncEmailForm"
      @submit="submitEmailCampaign"
    />
  </div>
</template>

<script setup lang="ts">
  import { ElAvatar, ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import MerchantUserCreateDialog from './modules/MerchantUserCreateDialog.vue'
  import MerchantUserDetailDrawer from './modules/MerchantUserDetailDrawer.vue'
  import MerchantUserEmailDialog from './modules/MerchantUserEmailDialog.vue'
  import MerchantUserMaintenanceDialogs from './modules/MerchantUserMaintenanceDialogs.vue'
  import {
    createMerchantUserBusinessFormState,
    createMerchantUserCreateFormState,
    createMerchantUserEditFormState,
    createMerchantUserEmailFormState,
    createMerchantUserNotificationFormState,
    createMerchantUserStatusFormState
  } from './modules/merchantUserFormState'
  import type { MerchantUserEmailFormState } from './modules/merchantUserFormState'
  import { useAuth } from '@/hooks'
  import { useTable } from '@/hooks/core/useTable'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'
  import {
    fetchAuditMerchantBatchDelete,
    fetchAuditMerchantEmail,
    fetchBatchDeleteMerchants,
    fetchCreateMerchant,
    fetchDeleteMerchant,
    fetchGetMerchantImpersonationAudit,
    fetchGetMerchantDeleteAudit,
    fetchGetMerchantDetail,
    fetchGetMerchantList,
    fetchGetMerchantTemplate,
    fetchImpersonateMerchant,
    fetchSendMerchantEmail,
    fetchUpdateMerchant,
    fetchUpdateMerchantBusiness,
    fetchUpdateMerchantNotifications,
    fetchUpdateMerchantStatus
  } from '@/api/users'

  defineOptions({ name: 'MerchantUsers' })

  type UserListItem = Api.Users.UserListItem
  type UserEditable = Api.Users.UserEditable
  type UserCreateEditable = Api.Users.UserCreateEditable
  type UserDeleteAudit = Api.Users.UserDeleteAudit
  type UserBatchDeleteAudit = Api.Users.UserBatchDeleteAudit
  type UserEmailAudit = Api.Users.UserEmailAudit
  type UserEmailScope = Api.Users.UserEmailScope
  type UserImpersonationAudit = Api.Users.UserImpersonationAudit
  type UserVipOption = Api.Users.UserVipOption
  type UserNotificationChannelOption = Api.Users.UserNotificationChannelOption

  const { hasAuth } = useAuth()
  const tableRef = ref<any>(null)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const createPreparing = ref(false)
  const emailVisible = ref(false)
  const editVisible = ref(false)
  const businessVisible = ref(false)
  const notificationVisible = ref(false)
  const statusVisible = ref(false)
  const creatingMerchant = ref(false)
  const impersonatingMerchant = ref(false)
  const sendingEmail = ref(false)
  const savingEdit = ref(false)
  const savingBusiness = ref(false)
  const savingNotification = ref(false)
  const savingStatus = ref(false)
  const activeMerchant = ref<UserListItem | null>(null)
  const editableMerchant = ref<UserEditable | null>(null)
  const createTemplate = ref<UserCreateEditable | null>(null)
  const selectedMerchants = ref<UserListItem[]>([])
  const searchForm = ref<{
    keyword?: string
    status?: string
    realname_status?: string
    vip_status?: string
  }>({})

  const editForm = reactive(createMerchantUserEditFormState())

  const createForm = reactive(createMerchantUserCreateFormState())

  const emailForm = reactive(createMerchantUserEmailFormState())

  const businessForm = reactive(createMerchantUserBusinessFormState())

  const notificationForm = reactive(createMerchantUserNotificationFormState())

  const statusForm = reactive(createMerchantUserStatusFormState())

  function displayMerchantName(
    merchant: Pick<UserListItem, 'id' | 'userName'> | null | undefined
  ): string {
    if (!merchant) {
      return '--'
    }

    return displayAdminFixtureText(merchant.userName) || `商户 #${merchant.id}`
  }

  function displayMerchantProfileName(
    merchant: Pick<UserListItem, 'merchant_name'> | null | undefined,
    fallback = '未填写实名信息'
  ): string {
    return displayAdminFixtureText(merchant?.merchant_name) || fallback
  }

  function displayMerchantContact(
    merchant: Pick<UserListItem, 'email' | 'mobile'> | null | undefined,
    fallback = '--'
  ): string {
    if (!merchant) {
      return fallback
    }

    return (
      displayAdminFixtureText(merchant.email) ||
      displayAdminFixtureText(merchant.mobile) ||
      fallback
    )
  }

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索商户账号、实名、邮箱、手机号、通讯密钥或 ID'
      }
    },
    {
      label: '账户状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部账户状态',
        options: [
          { label: '正常', value: '1' },
          { label: '已冻结', value: '2' }
        ]
      }
    },
    {
      label: '实名状态',
      key: 'realname_status',
      type: 'select',
      props: {
        placeholder: '全部实名状态',
        options: [
          { label: '已实名', value: '1' },
          { label: '未实名', value: '0' }
        ]
      }
    },
    {
      label: '会员状态',
      key: 'vip_status',
      type: 'select',
      props: {
        placeholder: '全部会员状态',
        options: [
          { label: '会员商户', value: '1' },
          { label: '普通商户', value: '0' }
        ]
      }
    }
  ])

  const vipOptions = computed<UserVipOption[]>(() => editableMerchant.value?.vip_options || [])
  const createVipOptions = computed<UserVipOption[]>(() => createTemplate.value?.vip_options || [])
  const notificationChannelOptions = computed<UserNotificationChannelOption[]>(
    () => editableMerchant.value?.notification_channel_options || []
  )

  const pageRealnameCount = computed(
    () => data.value.filter((item) => item.real_name_verified).length
  )
  const pageVipCount = computed(() => data.value.filter((item) => item.is_vip).length)
  const pageFrozenCount = computed(() => data.value.filter((item) => item.is_frozen).length)

  const {
    columns,
    columnChecks,
    data,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    resetSearchParams,
    handleSizeChange,
    handleCurrentChange,
    refreshData,
    refreshUpdate
  } = useTable({
    core: {
      apiFn: fetchGetMerchantList,
      apiParams: {
        current: 1,
        size: 20
      },
      columnsFactory: () => [
        { type: 'selection', width: 54, fixed: 'left' },
        { type: 'globalIndex', width: 70, label: '序号' },
        {
          prop: 'merchant',
          label: '商户信息',
          minWidth: 280,
          formatter: (row) =>
            h('div', { class: 'merchant-cell' }, [
              h(ElAvatar, { src: row.avatar, size: 40 }),
              h('div', { class: 'merchant-copy' }, [
                h('strong', {}, displayMerchantName(row)),
                h('p', {}, displayMerchantProfileName(row, '未实名')),
                h('span', {}, displayMerchantContact(row))
              ])
            ])
        },
        {
          prop: 'real_name_status_label',
          label: '认证 / 套餐',
          minWidth: 180,
          formatter: (row) =>
            h('div', { class: 'stack-cell' }, [
              h(
                ElTag,
                { type: row.real_name_verified ? 'success' : 'info', effect: 'plain' },
                () => row.real_name_status_label
              ),
              h(
                ElTag,
                { type: row.is_vip ? 'warning' : 'info', effect: 'light' },
                () => row.vip_status_label
              )
            ])
        },
        {
          prop: 'balance',
          label: '余额 / 费率',
          minWidth: 160,
          align: 'right',
          formatter: (row) =>
            h('div', { class: 'amount-cell' }, [
              h('strong', {}, formatAmount(row.balance)),
              h('p', {}, `费率 ${row.fee_rate_display}`)
            ])
        },
        {
          prop: 'order_count',
          label: '交易统计',
          minWidth: 220,
          formatter: (row) =>
            h('div', { class: 'stats-cell' }, [
              h('p', {}, `总订单 ${row.order_count}`),
              h('p', {}, `成功 ${row.paid_order_count}`),
              h('p', {}, `累计 ${formatAmount(row.paid_amount)}`),
              h('p', {}, `今日 ${formatAmount(row.today_paid_amount)}`)
            ])
        },
        {
          prop: 'status_label',
          label: '状态',
          width: 110,
          align: 'center',
          formatter: (row) =>
            h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => row.status_label)
        },
        {
          prop: 'createTime',
          label: '注册时间',
          minWidth: 170,
          formatter: (row) => row.createTime || '--'
        },
        {
          prop: 'operation',
          label: '操作',
          width: 90,
          align: 'center',
          fixed: 'right',
          formatter: (row) =>
            h(ArtButtonTable, {
              type: 'view',
              title: '详情',
              onClick: () => openDetail(row)
            })
        }
      ]
    }
  })

  function handleSearch(params: Api.Users.UserSearchParams) {
    replaceSearchParams({
      keyword: params.keyword,
      status: params.status,
      realname_status: params.realname_status,
      vip_status: params.vip_status
    })
    getData()
  }

  function handleMerchantSelectionChange(selection: UserListItem[]) {
    selectedMerchants.value = Array.isArray(selection) ? selection : []
  }

  async function openDetail(row: UserListItem) {
    activeMerchant.value = row
    editableMerchant.value = buildEditableFromMerchant(row)
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchGetMerchantDetail(row.id)
      applyMerchantDetail(response.item, response.editable)
    } catch {
      ElMessage.error('商户详情加载失败')
    } finally {
      detailLoading.value = false
    }
  }

  async function openCreateDialog() {
    createVisible.value = true
    createPreparing.value = true
    createTemplate.value = buildEmptyCreateEditable()
    syncCreateForm(createTemplate.value)

    try {
      const response = await fetchGetMerchantTemplate()
      createTemplate.value = response.editable
      syncCreateForm(createTemplate.value)
    } catch {
      createVisible.value = false
      ElMessage.error('商户创建模板加载失败')
    } finally {
      createPreparing.value = false
    }
  }

  function openEmailDialog(scope: UserEmailScope = activeMerchant.value ? 'merchant' : 'vip') {
    resetEmailForm(scope)
    emailVisible.value = true
  }

  function openMerchantEmailDialog() {
    if (!activeMerchant.value) {
      ElMessage.warning('请先打开商户详情')
      return
    }

    openEmailDialog('merchant')
  }

  function openEditDialog() {
    if (!activeMerchant.value) {
      return
    }

    syncEditForm(editableMerchant.value || buildEditableFromMerchant(activeMerchant.value))
    editVisible.value = true
  }

  function openBusinessDialog() {
    if (!activeMerchant.value) {
      return
    }

    syncBusinessForm(editableMerchant.value || buildEditableFromMerchant(activeMerchant.value))
    businessVisible.value = true
  }

  function openNotificationDialog() {
    if (!activeMerchant.value) {
      return
    }

    syncNotificationForm(editableMerchant.value || buildEditableFromMerchant(activeMerchant.value))
    notificationVisible.value = true
  }

  function openStatusDialog() {
    if (!activeMerchant.value) {
      return
    }

    syncStatusForm(editableMerchant.value || buildEditableFromMerchant(activeMerchant.value))
    statusVisible.value = true
  }

  function renderImpersonationLoadingWindow(targetWindow: Window) {
    targetWindow.document.open()
    targetWindow.document.write(`<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>商户中心</title>
    <style>
      :root {
        color-scheme: light;
        --accent: #27ae60;
        --accent-soft: rgba(39, 174, 96, 0.16);
        --text-main: #1f2937;
        --text-subtle: #6b7280;
        --surface: rgba(255, 255, 255, 0.92);
        --border: rgba(15, 23, 42, 0.08);
        --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
      }

      * {
        box-sizing: border-box;
      }

      html,
      body {
        width: 100%;
        height: 100%;
        margin: 0;
      }

      body {
        display: flex;
        align-items: center;
        justify-content: center;
        background:
          radial-gradient(circle at top, rgba(39, 174, 96, 0.12), transparent 34%),
          linear-gradient(180deg, #f7faf8 0%, #eef6f0 100%);
        color: var(--text-main);
        font-family:
          "PingFang SC",
          "Microsoft YaHei",
          "Helvetica Neue",
          Arial,
          sans-serif;
      }

      .loading-shell {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
        min-width: 300px;
        padding: 34px 42px;
        border: 1px solid var(--border);
        border-radius: 24px;
        background: var(--surface);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
      }

      .spinner {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        border: 4px solid var(--accent-soft);
        border-top-color: var(--accent);
        animation: merchant-impersonation-spin 0.9s linear infinite;
      }

      .loading-text {
        font-size: 17px;
        line-height: 1.6;
        font-weight: 600;
        letter-spacing: 0.02em;
      }

      .loading-tip {
        font-size: 13px;
        line-height: 1.6;
        color: var(--text-subtle);
      }

      @keyframes merchant-impersonation-spin {
        from {
          transform: rotate(0deg);
        }

        to {
          transform: rotate(360deg);
        }
      }
    </style>
  </head>
  <body>
    <main class="loading-shell" aria-live="polite">
      <div class="spinner" aria-hidden="true"></div>
      <div class="loading-text">代登录商户加载中~~~</div>
      <div class="loading-tip">正在安全跳转到商户中心</div>
    </main>
  </body>
</html>`)
    targetWindow.document.close()
  }

  async function handleImpersonateMerchant() {
    if (!activeMerchant.value) {
      return
    }

    let pendingWindow: Window | null = window.open('about:blank', '_blank')
    impersonatingMerchant.value = true

    try {
      if (!pendingWindow) {
        ElMessage.error('浏览器拦截了新窗口，请允许弹窗后重试。')
        return
      }

      renderImpersonationLoadingWindow(pendingWindow)

      const response = await fetchGetMerchantImpersonationAudit(activeMerchant.value.id)
      const audit = response.audit
      const title = displayMerchantName(activeMerchant.value)

      if (!audit.can_impersonate) {
        pendingWindow.close()
        pendingWindow = null
        await ElMessageBox.alert(buildImpersonationBlockedMessage(title, audit), '当前无法代登录', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const impersonation = await fetchImpersonateMerchant(activeMerchant.value.id)

      pendingWindow.location.replace(impersonation.redirect_url)
      pendingWindow.focus()
      ElMessage.success(
        `已为 ${displayAdminFixtureText(impersonation.merchant_username) || title} 打开商户中心`
      )
    } catch (error) {
      if (pendingWindow && !pendingWindow.closed) {
        pendingWindow.close()
      }

      if (isDialogCancel(error)) {
        return
      }

      throw error
    } finally {
      impersonatingMerchant.value = false
    }
  }

  async function handleBatchDeleteMerchants() {
    if (selectedMerchants.value.length === 0) {
      ElMessage.warning('请先勾选要删除的商户')
      return
    }

    const merchantIds = selectedMerchants.value.map((item) => item.id)

    try {
      const response = await fetchAuditMerchantBatchDelete({
        merchant_ids: merchantIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildBatchDeleteBlockedMessage(audit), '当前暂不能批量删除', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildBatchDeletePromptMessage(audit),
        '批量删除商户',
        {
          confirmButtonText: '确认批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteMerchants({
        merchant_ids: merchantIds,
        confirmation_phrase: String(value || '')
      })

      detailVisible.value = false
      activeMerchant.value = null
      editableMerchant.value = null
      clearMerchantSelection()
      await refreshUpdate()
      ElMessage.success(
        `已删除 ${deleteResponse.deleted_count} 个商户，并清理 ${deleteResponse.audit.summary.delete_row_count} 行关联数据`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleDeleteMerchant() {
    if (!activeMerchant.value) {
      return
    }

    try {
      const response = await fetchGetMerchantDeleteAudit(activeMerchant.value.id)
      const audit = response.audit
      const title = displayMerchantName(activeMerchant.value)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildDeleteBlockedMessage(title, audit), '当前暂不能删除', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildDeletePromptMessage(title, audit),
        '删除商户',
        {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchDeleteMerchant(activeMerchant.value.id, {
        confirmation_phrase: String(value || '')
      })

      detailVisible.value = false
      activeMerchant.value = null
      editableMerchant.value = null
      await refreshUpdate()
      ElMessage.success(
        `商户 ${displayAdminFixtureText(deleteResponse.deleted_username) || title} 已删除，已清理 ${deleteResponse.audit.summary.delete_row_count} 行关联数据`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      throw error
    }
  }

  async function submitEmailCampaign() {
    emailForm.email = emailForm.email.trim()
    emailForm.title = emailForm.title.trim()
    emailForm.content = emailForm.content.trim()

    if (!emailForm.title) {
      ElMessage.warning('请输入邮件标题')
      return
    }

    if (!emailForm.content) {
      ElMessage.warning('请输入邮件内容')
      return
    }

    if (emailForm.scope === 'merchant' && emailForm.merchant_ids.length === 0) {
      ElMessage.warning('请先从商户详情中选择一个商户目标')
      return
    }

    if (emailForm.scope === 'direct' && !emailForm.email) {
      ElMessage.warning('请输入接收邮箱')
      return
    }

    sendingEmail.value = true
    try {
      const payload = buildEmailPayload()
      const auditResponse = await fetchAuditMerchantEmail(payload)
      const audit = auditResponse.audit

      if (!audit.can_send) {
        await ElMessageBox.alert(buildEmailBlockedMessage(audit), '当前无法发送', {
          type: 'warning',
          confirmButtonText: '我知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(buildEmailPromptMessage(audit), '发送邮件确认', {
        confirmButtonText: '确认发送',
        cancelButtonText: '取消',
        type: 'warning',
        inputPlaceholder: audit.confirmation_phrase,
        inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
        inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
      })

      const response = await fetchSendMerchantEmail({
        ...payload,
        title: emailForm.title,
        content: emailForm.content,
        confirmation_phrase: String(value || '')
      })

      emailVisible.value = false

      if (response.summary.failed_count > 0) {
        ElMessage.warning(
          `邮件已发送 ${response.summary.sent_count} 封，失败 ${response.summary.failed_count} 封，跳过 ${response.summary.skipped_count} 个目标`
        )
        return
      }

      ElMessage.success(
        `邮件已发送 ${response.summary.sent_count} 封，跳过 ${response.summary.skipped_count} 个目标`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }
      throw error
    } finally {
      sendingEmail.value = false
    }
  }

  async function submitCreateMerchant() {
    createForm.username = createForm.username.trim()
    createForm.password = createForm.password.trim()
    createForm.email = createForm.email.trim()
    createForm.mobile = createForm.mobile.trim()
    createForm.remarks = createForm.remarks.trim()
    createForm.fee_rate = createForm.fee_rate.trim()

    if (!createForm.username) {
      ElMessage.warning('请输入商户账号')
      return
    }

    if (!createForm.password) {
      ElMessage.warning('请输入登录密码')
      return
    }

    creatingMerchant.value = true
    try {
      const response = await fetchCreateMerchant({
        username: createForm.username,
        password: createForm.password,
        email: createForm.email,
        mobile: createForm.mobile,
        remarks: createForm.remarks,
        vip_id: createForm.vip_id,
        vip_time: createForm.vip_time || null,
        fee_rate: createForm.fee_rate || null,
        is_rate: createForm.is_rate
      })

      applyMerchantDetail(response.item, response.editable)
      await refreshUpdate()
      createVisible.value = false
      detailVisible.value = true
      ElMessage.success('商户已创建')
    } finally {
      creatingMerchant.value = false
    }
  }

  async function submitEdit() {
    if (!activeMerchant.value) {
      return
    }

    editForm.email = editForm.email.trim()
    editForm.mobile = editForm.mobile.trim()
    editForm.remarks = editForm.remarks.trim()

    savingEdit.value = true
    try {
      const response = await fetchUpdateMerchant(activeMerchant.value.id, {
        email: editForm.email,
        mobile: editForm.mobile,
        remarks: editForm.remarks
      })

      applyMerchantDetail(response.item, response.editable)
      await refreshUpdate()
      editVisible.value = false
    } finally {
      savingEdit.value = false
    }
  }

  async function submitBusiness() {
    if (!activeMerchant.value) {
      return
    }

    businessForm.fee_rate = businessForm.fee_rate.trim()

    savingBusiness.value = true
    try {
      const response = await fetchUpdateMerchantBusiness(activeMerchant.value.id, {
        vip_id: businessForm.vip_id,
        vip_time: businessForm.vip_time || null,
        fee_rate: businessForm.fee_rate || null,
        is_rate: businessForm.is_rate
      })

      applyMerchantDetail(response.item, response.editable)
      await refreshUpdate()
      businessVisible.value = false
    } finally {
      savingBusiness.value = false
    }
  }

  async function submitNotifications() {
    if (!activeMerchant.value) {
      return
    }

    notificationForm.money_tips = notificationForm.money_tips.trim()

    savingNotification.value = true
    try {
      const response = await fetchUpdateMerchantNotifications(activeMerchant.value.id, {
        order_tips: notificationForm.order_tips,
        is_money_tips: notificationForm.is_money_tips,
        money_tips: notificationForm.money_tips
      })

      applyMerchantDetail(response.item, response.editable)
      await refreshUpdate()
      notificationVisible.value = false
    } finally {
      savingNotification.value = false
    }
  }

  async function submitStatus() {
    if (!activeMerchant.value) {
      return
    }

    savingStatus.value = true
    try {
      const response = await fetchUpdateMerchantStatus(activeMerchant.value.id, {
        status: statusForm.status,
        frozen_reason: statusForm.status ? statusForm.frozen_reason.trim() : ''
      })

      if (response.item) {
        applyMerchantDetail(response.item, {
          ...(editableMerchant.value || buildEditableFromMerchant(response.item)),
          status: response.item.is_frozen ? 1 : 0,
          frozen_reason: response.item.frozen_reason || ''
        })
      }

      await refreshUpdate()
      statusVisible.value = false
    } finally {
      savingStatus.value = false
    }
  }

  function applyMerchantDetail(item: UserListItem, editable?: UserEditable | null) {
    activeMerchant.value = item
    editableMerchant.value = editable || buildEditableFromMerchant(item)
    syncEditForm(editableMerchant.value)
    syncBusinessForm(editableMerchant.value)
    syncNotificationForm(editableMerchant.value)
    syncStatusForm(editableMerchant.value)
  }

  function buildEditableFromMerchant(item: UserListItem): UserEditable {
    return {
      email: item.email || '',
      mobile: item.mobile || '',
      remarks: item.remarks || '',
      status: item.is_frozen ? 1 : 0,
      frozen_reason: item.frozen_reason || '',
      vip_id: item.vip_id || 0,
      vip_time: item.vip_expire_time || '',
      fee_rate: item.fee_rate !== null ? String(item.fee_rate) : '',
      is_rate: item.is_rate ? 1 : 0,
      vip_options: [],
      order_tips: item.order_tips || 'close',
      is_money_tips: item.low_balance_tips || 'close',
      money_tips: item.low_balance_threshold || '0',
      notification_channel_options: []
    }
  }

  function buildEmptyCreateEditable(): UserCreateEditable {
    return {
      username: '',
      password: '',
      email: '',
      mobile: '',
      remarks: '',
      vip_id: 0,
      vip_time: '',
      fee_rate: '',
      is_rate: 0,
      vip_options: []
    }
  }

  function syncEditForm(editable: {
    email?: string | null
    mobile?: string | null
    remarks?: string | null
  }) {
    editForm.email = editable.email || ''
    editForm.mobile = editable.mobile || ''
    editForm.remarks = editable.remarks || ''
  }

  function syncBusinessForm(editable: {
    vip_id?: number | null
    vip_time?: string | null
    fee_rate?: string | null
    is_rate?: number | boolean | null
  }) {
    businessForm.vip_id = Number(editable.vip_id || 0)
    businessForm.vip_time = editable.vip_time || ''
    businessForm.fee_rate = editable.fee_rate || ''
    businessForm.is_rate = Number(editable.is_rate || 0)
  }

  function syncNotificationForm(editable: {
    order_tips?: string | null
    is_money_tips?: string | null
    money_tips?: string | null
  }) {
    notificationForm.order_tips = editable.order_tips || 'close'
    notificationForm.is_money_tips = editable.is_money_tips || 'close'
    notificationForm.money_tips = editable.money_tips || '0'
  }

  function syncStatusForm(editable: {
    status?: number | boolean | null
    frozen_reason?: string | null
  }) {
    statusForm.status = Number(editable.status || 0) === 1
    statusForm.frozen_reason = editable.frozen_reason || ''
  }

  function syncCreateForm(editable: {
    username?: string | null
    password?: string | null
    email?: string | null
    mobile?: string | null
    remarks?: string | null
    vip_id?: number | null
    vip_time?: string | null
    fee_rate?: string | null
    is_rate?: number | boolean | null
  }) {
    createForm.username = editable.username || ''
    createForm.password = editable.password || ''
    createForm.email = editable.email || ''
    createForm.mobile = editable.mobile || ''
    createForm.remarks = editable.remarks || ''
    createForm.vip_id = Number(editable.vip_id || 0)
    createForm.vip_time = editable.vip_time || ''
    createForm.fee_rate = editable.fee_rate || ''
    createForm.is_rate = Number(editable.is_rate || 0)
  }

  function syncEmailForm(form: MerchantUserEmailFormState) {
    emailForm.scope = form.scope
    emailForm.merchant_ids = Array.isArray(form.merchant_ids) ? [...form.merchant_ids] : []
    emailForm.email = form.email || ''
    emailForm.title = form.title || ''
    emailForm.content = form.content || ''
  }

  function resetEmailForm(scope: UserEmailScope) {
    syncEmailForm({
      ...createMerchantUserEmailFormState(scope),
      merchant_ids: scope === 'merchant' && activeMerchant.value ? [activeMerchant.value.id] : []
    })
  }

  function clearMerchantSelection() {
    selectedMerchants.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function buildEmailPayload(): Api.Users.UserEmailAuditPayload {
    return {
      scope: emailForm.scope,
      merchant_ids: emailForm.scope === 'merchant' ? [...emailForm.merchant_ids] : [],
      email: emailForm.scope === 'direct' ? emailForm.email : ''
    }
  }

  function formatAmount(value: number, digits = 2) {
    return Number(value || 0).toLocaleString('zh-CN', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    })
  }

  function tagType(
    value: string
  ): 'success' | 'warning' | 'info' | 'danger' | 'primary' | undefined {
    if (
      value === 'success' ||
      value === 'warning' ||
      value === 'info' ||
      value === 'danger' ||
      value === 'primary'
    ) {
      return value
    }

    return 'info'
  }

  function buildImpersonationBlockedMessage(title: string, audit: UserImpersonationAudit) {
    return [
      `${title} 当前暂时不能代登录。`,
      '',
      ...buildImpersonationWarningLines(audit),
      '',
      `目标地址：${audit.target_url}`
    ].join('\n')
  }

  function buildImpersonationWarningLines(audit: UserImpersonationAudit) {
    const lines = [...audit.warnings]

    if (!audit.can_impersonate) {
      lines.unshift('该商户当前不可代登录。')
    }

    if (audit.possible_redirects.length > 0) {
      lines.push(`代登录后可能跳转到：${audit.possible_redirects.join(' , ')}`)
    }

    return lines.length > 0 ? lines : ['未发现额外风险提示。']
  }

  function buildBatchDeleteBlockedMessage(audit: UserBatchDeleteAudit) {
    const blockedLines = audit.items
      .filter((item) => !item.can_delete)
      .map((item) => {
        const label = item.merchant_username
          ? `${displayAdminFixtureText(item.merchant_username)} (#${item.merchant_id})`
          : `商户 #${item.merchant_id}`
        const reason = item.blocking_reasons[0] || '该商户当前不可删除。'
        return `${label}: ${reason}`
      })

    return [
      `已选商户：${audit.summary.requested_count}`,
      `可删除：${audit.summary.deletable_count}`,
      `受阻：${audit.summary.blocked_count}`,
      `缺失：${audit.summary.missing_count}`,
      '',
      ...audit.warnings,
      ...(blockedLines.length > 0 ? ['', '受阻商户：', ...blockedLines] : [])
    ].join('\n')
  }

  function buildBatchDeletePromptMessage(audit: UserBatchDeleteAudit) {
    const merchantLines = audit.items
      .filter((item) => item.can_delete)
      .map((item) =>
        item.merchant_username
          ? `${displayAdminFixtureText(item.merchant_username)} (#${item.merchant_id})`
          : `商户 #${item.merchant_id}`
      )

    return [
      `已选商户：${audit.summary.requested_count}`,
      `将删除数据行：${audit.summary.delete_row_count}`,
      `命中的非空数据域：${audit.summary.non_empty_target_count}`,
      '',
      '商户列表：',
      ...merchantLines,
      ...(audit.warnings.length > 0 ? ['', ...audit.warnings] : []),
      '',
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ].join('\n')
  }

  function buildDeleteBlockedMessage(title: string, audit: UserDeleteAudit) {
    return [
      `${title} 当前还不能删除。`,
      ...audit.blocking_reasons,
      '',
      ...audit.related_counts
        .filter((item) => item.count > 0 && item.delete_action === 'block')
        .map((item) => `${item.label}: ${item.count}`)
    ].join('\n')
  }

  function buildDeletePromptMessage(title: string, audit: UserDeleteAudit) {
    const nonEmptyTargets = audit.related_counts.filter(
      (item) => item.count > 0 && item.delete_action === 'delete'
    )

    const targetLines =
      nonEmptyTargets.length > 0
        ? nonEmptyTargets.map((item) => `${item.label}: ${item.count}`).join('\n')
        : '未发现额外的商户关联数据，将仅删除商户账号本身。'

    return [
      `商户：${title}`,
      `将删除 ${audit.summary.delete_row_count} 行关联数据，覆盖 ${audit.summary.non_empty_target_count} 个已命中数据域。`,
      '',
      targetLines,
      '',
      ...audit.warnings,
      '',
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ].join('\n')
  }

  function buildEmailBlockedMessage(audit: UserEmailAudit) {
    const skippedLines = audit.skipped_recipients.map(
      (item) => `${item.label || item.email || '未命名目标'}: ${item.reason || '已跳过'}`
    )

    return [
      `范围：${audit.scope_label}`,
      `命中 ${audit.recipient_total} 个目标，可投递 ${audit.deliverable_total} 个，跳过 ${audit.skipped_total} 个。`,
      '',
      ...audit.warnings,
      ...(skippedLines.length > 0 ? ['', '样例跳过目标：', ...skippedLines] : [])
    ].join('\n')
  }

  function buildEmailPromptMessage(audit: UserEmailAudit) {
    const deliverableLines =
      audit.sample_recipients.length > 0
        ? audit.sample_recipients.map((item) => `${item.label} <${item.email}>`)
        : ['当前没有可投递目标']
    const skippedLines =
      audit.skipped_recipients.length > 0
        ? audit.skipped_recipients.map(
            (item) => `${item.label || item.email || '未命名目标'}: ${item.reason || '已跳过'}`
          )
        : []

    return [
      `范围：${audit.scope_label}`,
      `命中 ${audit.recipient_total} 个目标，可投递 ${audit.deliverable_total} 个，跳过 ${audit.skipped_total} 个。`,
      '',
      '样例投递目标：',
      ...deliverableLines,
      ...(skippedLines.length > 0 ? ['', '样例跳过目标：', ...skippedLines] : []),
      ...(audit.warnings.length > 0 ? ['', ...audit.warnings] : []),
      '',
      `请输入 ${audit.confirmation_phrase} 后继续。`
    ].join('\n')
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped>
  .merchant-cell {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .merchant-copy {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .merchant-copy strong,
  .amount-cell strong {
    color: var(--el-text-color-primary);
  }

  .merchant-copy p,
  .merchant-copy span,
  .amount-cell p,
  .stats-cell p {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .stack-cell,
  .amount-cell,
  .stats-cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
</style>
