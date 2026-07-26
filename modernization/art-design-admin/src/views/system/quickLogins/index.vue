<template>
  <div class="quick-login-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getQuickLoginList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">快捷登录 {{ pagination.total }}</ElTag>
            <ElTag type="success" effect="plain">启用 {{ summary.enabled_count }}</ElTag>
            <ElTag type="info" effect="plain">停用 {{ summary.disabled_count }}</ElTag>
            <ElTag type="primary" effect="plain">QQ 登录 {{ summary.qq_count }}</ElTag>
            <ElTag type="warning" effect="plain">
              聚合 {{ summary.polymerization_count }}
            </ElTag>
            <ElTag effect="plain">凭证已就绪 {{ summary.credential_ready_count }}</ElTag>
            <ElButton v-if="hasQuickLoginCreateAuth" type="primary" @click="openCreateDialog">
              新增快捷登录
            </ElButton>
            <ElButton
              v-if="hasQuickLoginBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedConfigs.length === 0"
              @click="handleBatchDeleteQuickLogins"
            >
              批量删除
            </ElButton>
            <ElTag v-if="selectedConfigs.length > 0" type="danger" effect="plain">
              已选 {{ selectedConfigs.length }}
            </ElTag>
            <ElTag type="info" effect="plain">快捷登录维护</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="quickLoginList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleQuickLoginSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="
        activeConfig
          ? `${displayQuickLoginText(activeConfig.name_label, '快捷登录')} / #${activeConfig.id}`
          : '快捷登录详情'
      "
    >
      <div v-loading="detailLoading" class="quick-login-detail">
        <template v-if="activeConfig">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayQuickLoginText(activeConfig.name_label, '快捷登录') }}</h3>
              <p>{{ displayQuickLoginType(activeConfig) }} / {{ displayQuickLoginStatus(activeConfig) }}</p>
              <span>{{ displayQuickLoginBindingSummary(activeConfig) }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditQuickLogin(activeConfig)" plain @click="openEditDialog()">
                编辑
              </ElButton>
              <ElButton
                v-if="canToggleStatusQuickLogin(activeConfig)"
                :type="activeConfig.status === 1 ? 'warning' : 'success'"
                plain
                @click="handleToggleStatusQuickLogin()"
              >
                {{ activeConfig.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton
                v-if="canDeleteQuickLogin(activeConfig)"
                type="danger"
                plain
                @click="handleDeleteQuickLogin()"
              >
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="配置名称">
                {{ displayQuickLoginText(activeConfig.name_label, '快捷登录') }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="适配器类型">
                {{ displayQuickLoginType(activeConfig) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态">
                {{ displayQuickLoginStatus(activeConfig) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="绑定情况">
                {{ displayQuickLoginBindingSummary(activeConfig) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="接口地址">
                <a
                  v-if="activeConfig.url_link"
                  class="cell-link"
                  :href="activeConfig.url_link"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ displayQuickLoginUrl(activeConfig.url) }}
                </a>
                <span v-else>{{ displayQuickLoginUrl(activeConfig.url) }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="回调路径">
                {{ activeConfig.callback_path || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="凭证状态">
                {{ displayQuickLoginCredentialSummary(activeConfig) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeConfig.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="记录编号">
                {{ activeConfig.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始类型值">
                {{ activeConfig.type || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>凭证概览</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="应用编号">
                {{ activeConfig.appid_masked || '未配置' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="应用密钥">
                {{ activeConfig.appkey_masked || '未配置' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <ElAlert
            type="info"
            :closable="false"
            show-icon
          title="删除为硬删除。凡是仍被 QQ 登录绑定或微信登录绑定引用的配置，必须先解绑后再删除。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="760px"
      destroy-on-close
      align-center
      title="新增快捷登录"
    >
      <ElForm label-position="top">
        <ElFormItem label="适配器类型">
          <ElSelect
            v-model="createForm.type"
            filterable
            allow-create
            default-first-option
            placeholder="请选择或输入类型"
          >
            <ElOption
              v-for="option in typeOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="createForm.status" placeholder="请选择状态">
            <ElOption label="启用" value="1" />
            <ElOption label="停用" value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="配置名称">
          <ElInput
            v-model="createForm.name"
            maxlength="255"
            placeholder="请输入快捷登录配置的显示名称"
          />
        </ElFormItem>
        <ElFormItem label="接口地址">
          <ElInput
            v-model="createForm.url"
            maxlength="255"
            placeholder="https://你的授权域名/oauth/"
          />
        </ElFormItem>
        <ElFormItem label="应用编号">
          <ElInput
            v-model="createForm.appid"
            maxlength="50"
            placeholder="适配器无需应用编号时可留空"
          />
        </ElFormItem>
        <ElFormItem label="应用密钥">
          <ElInput
            v-model="createForm.appkey"
            maxlength="255"
            placeholder="适配器无需应用密钥时可留空"
            show-password
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="应用编号和应用密钥会按密文保存，创建后列表和详情页会显示脱敏值。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton
            v-if="hasQuickLoginCreateAuth"
            type="primary"
            :loading="creatingQuickLogin"
            @click="submitCreateQuickLogin"
          >
            新增快捷登录
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="editVisible"
      width="760px"
      destroy-on-close
      align-center
      title="编辑快捷登录"
    >
      <ElForm label-position="top">
        <ElFormItem label="适配器类型">
          <ElSelect
            v-model="editForm.type"
            filterable
            allow-create
            default-first-option
            placeholder="请选择或输入类型"
          >
            <ElOption
              v-for="option in typeOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="状态">
          <ElSelect v-model="editForm.status" placeholder="请选择状态">
            <ElOption label="启用" value="1" />
            <ElOption label="停用" value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="配置名称">
          <ElInput
            v-model="editForm.name"
            maxlength="255"
            placeholder="请输入快捷登录配置的显示名称"
          />
        </ElFormItem>
        <ElFormItem label="接口地址">
          <ElInput
            v-model="editForm.url"
            maxlength="255"
            placeholder="https://你的授权域名/oauth/"
          />
        </ElFormItem>

        <div class="secret-note" v-if="editSourceItem">
          <ElTag effect="plain">当前应用编号 {{ editSourceItem.appid_masked || '未配置' }}</ElTag>
          <ElTag effect="plain">当前应用密钥 {{ editSourceItem.appkey_masked || '未配置' }}</ElTag>
        </div>

        <ElFormItem label="替换应用编号">
          <ElInput
            v-model="editForm.appid"
            :disabled="editForm.clearAppid"
            maxlength="50"
            placeholder="留空则保留当前应用编号"
          />
          <ElCheckbox v-model="editForm.clearAppid" class="secret-toggle">
            清空已存储的应用编号
          </ElCheckbox>
        </ElFormItem>
        <ElFormItem label="替换应用密钥">
          <ElInput
            v-model="editForm.appkey"
            :disabled="editForm.clearAppkey"
            maxlength="255"
            placeholder="留空则保留当前应用密钥"
            show-password
          />
          <ElCheckbox v-model="editForm.clearAppkey" class="secret-toggle">
            清空已存储的应用密钥
          </ElCheckbox>
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="留空应用编号或应用密钥会保留当前密文，仅在确实要移除时再勾选清空。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton
            v-if="hasQuickLoginEditAuth"
            type="primary"
            :loading="savingEdit"
            @click="submitEditQuickLogin"
          >
            保存修改
          </ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/artButtonTable/index.vue'
  import { useAuth } from '@/hooks'
  import { displayAdminFixtureText, displayAdminFixtureUrl } from '@/utils/adminFixtureText'
  import {
    fetchAuditQuickLoginBatchDelete,
    fetchBatchDeleteQuickLogins,
    fetchCreateQuickLogin,
    fetchDeleteQuickLogin,
    fetchGetQuickLoginDeleteAudit,
    fetchGetQuickLoginDetail,
    fetchGetQuickLoginList,
    fetchUpdateQuickLogin,
    fetchUpdateQuickLoginStatus
  } from '@/api/quickLogins'

  defineOptions({ name: 'SystemQuickLogins' })

  type QuickLoginItem = Api.QuickLogins.QuickLoginListItem
  type QuickLoginSummary = Api.QuickLogins.QuickLoginSummary

  const typeOptions = [
    { label: 'QQ 登录', value: 'qq' },
    { label: '聚合登录', value: 'polymerization' }
  ]

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingQuickLogin = ref(false)
  const savingEdit = ref(false)
  const quickLoginList = ref<QuickLoginItem[]>([])
  const selectedConfigs = ref<QuickLoginItem[]>([])
  const activeConfig = ref<QuickLoginItem | null>(null)
  const editSourceItem = ref<QuickLoginItem | null>(null)
  const editQuickLoginId = ref<number | null>(null)
  const { hasAuth } = useAuth()
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<QuickLoginSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    type?: string
    status?: string
    name?: string
  }>({})
  const createForm = reactive(emptyWriteForm())
  const editForm = reactive(emptyEditForm())
  const hasQuickLoginCreateAuth = computed(() => hasAuth('add') || hasAuth('index'))
  const hasQuickLoginEditAuth = computed(() => hasAuth('edit') || hasAuth('index'))
  const hasQuickLoginStatusAuth = computed(() => hasAuth('status') || hasAuth('index'))
  const hasQuickLoginDeleteAuth = computed(() => hasAuth('remove') || hasAuth('index'))
  const hasQuickLoginBatchDeleteAuth = computed(() => hasAuth('batchRemove') || hasAuth('index'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索编号、类型、名称、接口地址或应用编号'
      }
    },
    {
      label: '适配器类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部类型',
        options: typeOptions
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: [
          { label: '启用', value: '1' },
          { label: '停用', value: '2' }
        ]
      }
    },
    {
      label: '配置名称',
      key: 'name',
      type: 'input',
      props: {
        placeholder: '按配置名称筛选'
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<QuickLoginItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name_label',
      label: '配置',
      minWidth: 240,
      formatter: (row) =>
        h('div', { class: 'config-cell' }, [
          h('strong', { class: 'cell-title' }, displayQuickLoginText(row.name_label, `配置 #${row.id}`)),
          h('p', { class: 'cell-sub' }, `编号：${row.id}`),
          h('p', { class: 'cell-sub' }, displayQuickLoginBindingSummary(row))
        ])
    },
    {
      prop: 'type_label',
      label: '适配器类型',
      minWidth: 200,
      formatter: (row) =>
        h('div', { class: 'config-cell' }, [
          h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () => displayQuickLoginType(row)),
          h('p', { class: 'cell-sub' }, displayQuickLoginTypeHelp(row))
        ])
    },
    {
      prop: 'url',
      label: '接口地址',
      minWidth: 260,
      formatter: (row) =>
        row.url_link
          ? h(
              'a',
              {
                class: 'cell-link',
                href: row.url_link,
                target: '_blank',
                rel: 'noopener noreferrer'
              },
              displayQuickLoginUrl(row.url)
            )
          : h('span', { class: 'cell-sub' }, displayQuickLoginUrl(row.url))
    },
    {
      prop: 'credential_summary',
      label: '凭证信息',
      minWidth: 230,
      formatter: (row) =>
        h('div', { class: 'credential-cell' }, [
          h('p', { class: 'cell-sub' }, displayQuickLoginCredentialSummary(row)),
          h('p', { class: 'cell-sub' }, `应用编号：${row.appid_masked || '未配置'}`),
          h('p', { class: 'cell-sub' }, `应用密钥：${row.appkey_masked || '未配置'}`)
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayQuickLoginStatus(row))
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 180,
      formatter: (row) => row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 320,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderQuickLoginOperationButtons(row)
    }
  ])

  onMounted(() => {
    getQuickLoginList()
  })

  function displayQuickLoginCopy(value: null | number | string | undefined, fallback = '--') {
    const raw = String(value ?? '').trim()
    if (!raw) {
      return fallback
    }

    return displayAdminFixtureText(raw, raw)
      .replaceAll('应用ID', '应用编号')
      .replaceAll('QQ登录', 'QQ 登录')
      .replaceAll('OAuth', '第三方登录')
      .replaceAll('VIP', '会员')
  }

  function displayQuickLoginText(
    value: null | number | string | undefined,
    fallback = '--'
  ): string {
    return displayAdminFixtureText(value, fallback)
  }

  function displayQuickLoginType(config?: Partial<QuickLoginItem> | null, fallback = '--') {
    return displayQuickLoginText(config?.type_text || config?.type_label || config?.type, fallback)
  }

  function displayQuickLoginTypeHelp(config?: Partial<QuickLoginItem> | null, fallback = '--') {
    return displayQuickLoginCopy(config?.type_help_text || config?.type_help, fallback)
  }

  function displayQuickLoginStatus(config?: Partial<QuickLoginItem> | null, fallback = '--') {
    return displayQuickLoginText(config?.status_text || config?.status_label, fallback)
  }

  function displayQuickLoginCredentialSummary(
    config?: Partial<QuickLoginItem> | null,
    fallback = '--'
  ) {
    return displayQuickLoginCopy(
      config?.credential_summary_text || config?.credential_summary,
      fallback
    )
  }

  function displayQuickLoginBindingSummary(
    config?: Partial<QuickLoginItem> | null,
    fallback = '--'
  ) {
    return displayQuickLoginCopy(config?.binding_summary_text || config?.binding_summary, fallback)
  }

  function displayQuickLoginUrl(
    value: null | number | string | undefined,
    fallback = '--'
  ): string {
    return displayAdminFixtureUrl(value, fallback)
  }

  function renderQuickLoginOperationButtons(row: QuickLoginItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditQuickLogin(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canToggleStatusQuickLogin(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: row.status === 1 ? 'ri:forbid-line' : 'ri:check-line',
          iconClass:
            row.status === 1 ? 'bg-warning/12 text-warning' : 'bg-success/12 text-success',
          title: row.status === 1 ? '停用' : '启用',
          onClick: () => handleToggleStatusQuickLogin(row)
        })
      )
    }

    if (canDeleteQuickLogin(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteQuickLogin(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canEditQuickLogin(item?: QuickLoginItem | null) {
    return Boolean(item && hasQuickLoginEditAuth.value)
  }

  function canToggleStatusQuickLogin(item?: QuickLoginItem | null) {
    return Boolean(item && hasQuickLoginStatusAuth.value)
  }

  function canDeleteQuickLogin(item?: QuickLoginItem | null) {
    return Boolean(item && hasQuickLoginDeleteAuth.value)
  }

  async function getQuickLoginList() {
    loading.value = true
    try {
      const response = await fetchGetQuickLoginList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        type: searchForm.value.type,
        status: searchForm.value.status,
        name: searchForm.value.name
      })
      quickLoginList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载快捷登录配置失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.QuickLogins.QuickLoginSearchParams) {
    pagination.current = 1
    clearQuickLoginSelection()
    searchForm.value = {
      keyword: params.keyword,
      type: params.type,
      status: params.status as string | undefined,
      name: params.name
    }
    getQuickLoginList()
  }

  function handleReset() {
    pagination.current = 1
    clearQuickLoginSelection()
    searchForm.value = {}
    getQuickLoginList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearQuickLoginSelection()
    getQuickLoginList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearQuickLoginSelection()
    getQuickLoginList()
  }

  function handleQuickLoginSelectionChange(rows: QuickLoginItem[]) {
    selectedConfigs.value = rows
  }

  async function openDetail(row: QuickLoginItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeConfig.value = row

    try {
      const response = await fetchGetQuickLoginDetail(row.id)
      activeConfig.value = response.item
    } catch (_error) {
      ElMessage.error('加载快捷登录详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openCreateDialog() {
    if (!hasQuickLoginCreateAuth.value) {
      ElMessage.warning('您没有创建快捷登录配置的权限。')
      return
    }

    Object.assign(createForm, emptyWriteForm())
    createVisible.value = true
  }

  function openEditDialog(row?: QuickLoginItem) {
    const target = row || activeConfig.value
    if (!target) {
      return
    }

    if (!hasQuickLoginEditAuth.value) {
      ElMessage.warning('您没有编辑快捷登录配置的权限。')
      return
    }

    editQuickLoginId.value = target.id
    editSourceItem.value = target
    Object.assign(editForm, {
      type: target.type || 'qq',
      status: String(target.status || 1),
      name: target.name || '',
      url: target.url || '',
      appid: '',
      appkey: '',
      clearAppid: false,
      clearAppkey: false
    })
    editVisible.value = true
  }

  async function submitCreateQuickLogin() {
    if (!hasQuickLoginCreateAuth.value) {
      ElMessage.warning('您没有创建快捷登录配置的权限。')
      return
    }

    const payload = buildCreatePayload()
    if (!payload.type) {
      ElMessage.warning('请选择或输入快捷登录适配器类型。')
      return
    }

    creatingQuickLogin.value = true
    try {
      const response = await fetchCreateQuickLogin(payload)
      createVisible.value = false
      clearQuickLoginSelection()
      await getQuickLoginList()
      ElMessage.success(
        `快捷登录 ${displayQuickLoginText(response.created_quick_login_label, `#${response.created_quick_login_id}`)} 已创建。`
      )
    } catch (_error) {
      ElMessage.error('创建快捷登录配置失败。')
    } finally {
      creatingQuickLogin.value = false
    }
  }

  async function submitEditQuickLogin() {
    if (!hasQuickLoginEditAuth.value) {
      ElMessage.warning('您没有编辑快捷登录配置的权限。')
      return
    }

    if (!editQuickLoginId.value) {
      ElMessage.warning('当前没有可编辑的快捷登录配置。')
      return
    }

    const payload = buildEditPayload()
    if (!payload.type) {
      ElMessage.warning('请选择或输入快捷登录适配器类型。')
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdateQuickLogin(editQuickLoginId.value, payload)
      editVisible.value = false
      editSourceItem.value = response.item
      syncActiveConfig(response.item)
      clearQuickLoginSelection()
      await getQuickLoginList()
      ElMessage.success(
        `快捷登录 ${displayQuickLoginText(response.updated_quick_login_label, `#${response.updated_quick_login_id}`)} 已更新。`
      )
    } catch (_error) {
      ElMessage.error('更新快捷登录配置失败。')
    } finally {
      savingEdit.value = false
    }
  }

  async function handleToggleStatusQuickLogin(row?: QuickLoginItem) {
    const target = row || activeConfig.value
    if (!target) {
      return
    }

    if (!hasQuickLoginStatusAuth.value) {
      ElMessage.warning('您没有修改快捷登录状态的权限。')
      return
    }

    const nextStatus = target.status === 1 ? 2 : 1
    const actionLabel = nextStatus === 1 ? '启用' : '停用'

    try {
      await ElMessageBox.confirm(
        `确认${actionLabel}${displayQuickLoginText(target.name_label, `快捷登录配置 #${target.id}`)}吗？`,
        `${actionLabel}快捷登录`,
        {
          confirmButtonText: actionLabel,
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchUpdateQuickLoginStatus(target.id, {
        status: nextStatus
      })
      syncActiveConfig(response.item)
      editSourceItem.value = response.item
      clearQuickLoginSelection()
      await getQuickLoginList()
      ElMessage.success(
        `快捷登录 ${displayQuickLoginText(response.updated_quick_login_label || target.name_label, `#${target.id}`)} 状态已更新为 ${displayQuickLoginText(response.status_text || response.status_label)}。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('更新快捷登录状态失败。')
    }
  }

  async function handleDeleteQuickLogin(row?: QuickLoginItem) {
    const target = row || activeConfig.value
    if (!target) {
      return
    }

    if (!hasQuickLoginDeleteAuth.value) {
      ElMessage.warning('您没有删除快捷登录配置的权限。')
      return
    }

    try {
      const response = await fetchGetQuickLoginDeleteAudit(target.id)
      const audit = response.audit
      const title = displayQuickLoginText(target.name_label, `快捷登录配置 #${target.id}`)

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildQuickLoginDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildQuickLoginDeletePromptMessage(audit, title),
        '删除快捷登录',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteQuickLogin(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeConfig.value?.id === target.id) {
        detailVisible.value = false
        activeConfig.value = null
      }
      if (editQuickLoginId.value === target.id) {
        editVisible.value = false
        editQuickLoginId.value = null
        editSourceItem.value = null
      }

      clearQuickLoginSelection()
      await getQuickLoginList()
      ElMessage.success(
        `快捷登录配置 ${displayQuickLoginText(deleteResponse.deleted_quick_login_label, title)} 已永久删除。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除快捷登录配置失败。')
    }
  }

  async function handleBatchDeleteQuickLogins() {
    if (!hasQuickLoginBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除快捷登录配置的权限。')
      return
    }

    if (selectedConfigs.value.length === 0) {
      ElMessage.warning('请至少选择一条快捷登录配置。')
      return
    }

    const quickLoginIds = selectedConfigs.value.map((item) => item.id)

    try {
      const response = await fetchAuditQuickLoginBatchDelete({
        quick_login_ids: quickLoginIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(
          buildQuickLoginBatchDeleteBlockedMessage(audit),
          '批量删除受限',
          {
            type: 'warning',
          confirmButtonText: '知道了'
          }
        )
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildQuickLoginBatchDeletePromptMessage(audit),
        '批量删除快捷登录',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteQuickLogins({
        quick_login_ids: quickLoginIds,
        confirmation_phrase: String(value || '')
      })

      if (
        activeConfig.value &&
        deleteResponse.deleted_quick_login_ids.includes(activeConfig.value.id)
      ) {
        detailVisible.value = false
        activeConfig.value = null
      }
      if (
        editQuickLoginId.value &&
        deleteResponse.deleted_quick_login_ids.includes(editQuickLoginId.value)
      ) {
        editVisible.value = false
        editQuickLoginId.value = null
        editSourceItem.value = null
      }

      clearQuickLoginSelection()
      await getQuickLoginList()
      ElMessage.success(`已永久删除 ${deleteResponse.deleted_count} 条快捷登录配置。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除快捷登录配置失败。')
    }
  }

  function syncActiveConfig(item: QuickLoginItem | null) {
    if (!item) {
      return
    }

      if (activeConfig.value?.id === item.id) {
        activeConfig.value = item
    }

    if (editSourceItem.value?.id === item.id) {
      editSourceItem.value = item
    }
  }

  function clearQuickLoginSelection() {
    selectedConfigs.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): QuickLoginSummary {
    return {
      enabled_count: 0,
      disabled_count: 0,
      qq_count: 0,
      polymerization_count: 0,
      credential_ready_count: 0
    }
  }

  function emptyWriteForm() {
    return {
      type: 'qq',
      status: '1',
      name: '',
      url: '',
      appid: '',
      appkey: ''
    }
  }

  function emptyEditForm() {
    return {
      type: 'qq',
      status: '1',
      name: '',
      url: '',
      appid: '',
      appkey: '',
      clearAppid: false,
      clearAppkey: false
    }
  }

  function buildCreatePayload(): Api.QuickLogins.QuickLoginWritePayload {
    const payload: Api.QuickLogins.QuickLoginWritePayload = {
      type: normalizeInput(createForm.type),
      status: createForm.status,
      name: normalizeInput(createForm.name),
      url: normalizeInput(createForm.url)
    }

    const appid = normalizeInput(createForm.appid)
    if (appid !== '') {
      payload.appid = appid
    }

    const appkey = normalizeInput(createForm.appkey)
    if (appkey !== '') {
      payload.appkey = appkey
    }

    return payload
  }

  function buildEditPayload(): Api.QuickLogins.QuickLoginWritePayload {
    const payload: Api.QuickLogins.QuickLoginWritePayload = {
      type: normalizeInput(editForm.type),
      status: editForm.status,
      name: normalizeInput(editForm.name),
      url: normalizeInput(editForm.url)
    }

    if (editForm.clearAppid) {
      payload.appid = ''
    } else {
      const appid = normalizeInput(editForm.appid)
      if (appid !== '') {
        payload.appid = appid
      }
    }

    if (editForm.clearAppkey) {
      payload.appkey = ''
    } else {
      const appkey = normalizeInput(editForm.appkey)
      if (appkey !== '') {
        payload.appkey = appkey
      }
    }

    return payload
  }

  function buildQuickLoginDeleteBlockedMessage(
    audit: Api.QuickLogins.QuickLoginDeleteAudit,
    title: string
  ) {
    return [
      `${title} 当前暂不可删除。`,
      '',
      ...audit.blocking_reasons.map((item) => `- ${displayQuickLoginText(item)}`),
      '',
      ...audit.warnings.map((item) => `- ${displayQuickLoginText(item)}`)
    ].join('\n')
  }

  function buildQuickLoginDeletePromptMessage(
    audit: Api.QuickLogins.QuickLoginDeleteAudit,
    title: string
  ) {
    return [
      `${title} 将被永久删除。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${displayQuickLoginText(item)}`)
    ].join('\n')
  }

  function buildQuickLoginBatchDeleteBlockedMessage(
    audit: Api.QuickLogins.QuickLoginBatchDeleteAudit
  ) {
    const blocked = audit.items.filter((item) => !item.can_delete)
    return [
      '所选快捷登录配置当前还不能批量删除。',
      '',
      ...blocked.slice(0, 6).map((item) => {
        const label = displayQuickLoginText(item.quick_login_label, `快捷登录配置 #${item.quick_login_id}`)
        return `- ${label}: ${item.blocking_reasons.map((reason) => displayQuickLoginText(reason)).join(' ')}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${displayQuickLoginText(item)}`)
    ].join('\n')
  }

  function buildQuickLoginBatchDeletePromptMessage(
    audit: Api.QuickLogins.QuickLoginBatchDeleteAudit
  ) {
    return [
      `即将永久删除 ${audit.summary.deletable_count} 条快捷登录配置。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${displayQuickLoginText(item)}`)
    ].join('\n')
  }

  function normalizeInput(value: string | undefined) {
    return String(value || '').trim()
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

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped lang="scss">
  .quick-login-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --detail-hero-bg: linear-gradient(135deg, rgb(248 250 252 / 0.96), rgb(241 245 249 / 0.92));
    --detail-card-border: var(--el-border-color-lighter);
    --detail-title-color: #0f172a;
    --detail-text-color: #475569;
    --detail-muted-color: #64748b;
  }

  :global(html.dark .quick-login-page ){
    --detail-hero-bg: linear-gradient(135deg, rgb(30 41 59 / 0.96), rgb(15 23 42 / 0.94));
    --detail-card-border: rgb(71 85 105 / 0.42);
    --detail-title-color: #e2e8f0;
    --detail-text-color: #cbd5e1;
    --detail-muted-color: #94a3b8;
  }

  .config-cell,
  .credential-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: var(--detail-title-color);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .cell-link {
    margin: 0;
    color: var(--detail-muted-color);
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .cell-link {
    color: var(--el-color-primary);
    text-decoration: none;
  }

  .cell-link:hover {
    text-decoration: underline;
  }

  .quick-login-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid var(--detail-card-border);
    border-radius: 18px;
    background: var(--detail-hero-bg);
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--detail-title-color);
    font-size: 20px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--detail-text-color);
    line-height: 1.7;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-content: flex-start;
    justify-content: flex-end;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--detail-title-color);
    font-size: 15px;
  }

  .secret-note {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
  }

  .secret-toggle {
    margin-top: 10px;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }
  }
</style>
