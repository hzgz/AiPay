<template>
  <div class="risk-record-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getRiskList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">风控记录 {{ pagination.total }}</ElTag>
            <ElTag type="warning" effect="plain">涉及商户 {{ summary.merchant_count }}</ElTag>
            <ElTag type="success" effect="plain">有名称 {{ summary.named_count }}</ElTag>
            <ElTag type="info" effect="plain">有来源地址 {{ summary.source_count }}</ElTag>
            <ElTag type="danger" effect="plain">今日新增 {{ summary.today_count }}</ElTag>
            <ElButton v-if="hasRiskCreateAuth" type="primary" @click="openCreateDialog">
              新增记录
            </ElButton>
            <ElButton
              v-if="hasRiskBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedRisks.length === 0"
              @click="handleBatchDeleteRisks"
            >
              批量删除
            </ElButton>
            <ElTag v-if="selectedRisks.length > 0" type="danger" effect="plain">
              已选 {{ selectedRisks.length }}
            </ElTag>
            <ElTag type="warning" effect="plain">仅支持硬删除</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="riskList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleRiskSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="
        activeRisk
          ? `${displayAdminFixtureText(activeRisk.name_label)} / #${activeRisk.id}`
          : '风控记录详情'
      "
    >
      <div v-loading="detailLoading" class="risk-record-detail">
        <template v-if="activeRisk">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayAdminFixtureText(activeRisk.name_label) }}</h3>
              <p>{{ displayAdminFixtureText(activeRisk.merchant_display) }}</p>
              <span>{{ displayAdminFixtureUrl(activeRisk.url_preview) }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canEditRisk(activeRisk)" plain @click="openEditDialog()"
                >编辑</ElButton
              >
              <ElButton
                v-if="canDeleteRisk(activeRisk)"
                type="danger"
                plain
                @click="handleDeleteRisk()"
              >
                删除
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="商户">
                {{ displayAdminFixtureText(activeRisk.merchant_display) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户编号">
                {{ activeRisk.user_id || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商品名称">
                {{ displayAdminFixtureText(activeRisk.name_label) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="来源主机">
                {{ displayAdminFixtureText(activeRisk.url_host) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeRisk.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="更新时间">
                {{ activeRisk.update_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="记录编号">
                {{ activeRisk.id }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>商户资料</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="商户账号">
                {{ displayAdminFixtureText(activeRisk.merchant_username) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户名称">
                {{ displayAdminFixtureText(activeRisk.merchant_name) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="联系邮箱">
                {{ displayAdminFixtureText(activeRisk.merchant_email) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="联系手机">
                {{ displayAdminFixtureText(activeRisk.merchant_mobile) || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>来源快照</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="来源地址">
                <a
                  v-if="activeRisk.url_link"
                  class="cell-link"
                  :href="activeRisk.url_link"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ displayAdminFixtureUrl(activeRisk.url) }}
                </a>
                <span v-else>{{ displayAdminFixtureUrl(activeRisk.url) }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="来源主机">
                {{ displayAdminFixtureText(activeRisk.url_host) }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <ElAlert
            type="warning"
            :closable="false"
            show-icon
            title="当前阶段风控记录仍为硬删除。自动化支付风控后续仍可能再次生成相似记录。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="760px"
      destroy-on-close
      align-center
      title="新增风控记录"
    >
      <ElForm label-position="top">
        <ElFormItem label="商户编号" required>
          <ElInput
            v-model="createForm.user_id"
            maxlength="20"
            placeholder="请输入一个已存在的商户编号"
          />
        </ElFormItem>
        <ElFormItem label="商品名称">
          <ElInput v-model="createForm.name" maxlength="225" placeholder="如无商品名称，可留空" />
        </ElFormItem>
        <ElFormItem label="来源地址">
          <ElInput
            v-model="createForm.url"
            maxlength="2500"
            placeholder="https://notify.aipay.local/callback"
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="商户编号必须对应现有商户记录。商品名称和来源地址可按需留空。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton
            v-if="hasRiskCreateAuth"
            type="primary"
            :loading="creatingRisk"
            @click="submitCreateRisk"
          >
            创建记录
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="editVisible"
      width="760px"
      destroy-on-close
      align-center
      title="编辑风控记录"
    >
      <ElForm label-position="top">
        <ElFormItem label="商户编号" required>
          <ElInput
            v-model="editForm.user_id"
            maxlength="20"
            placeholder="请输入一个已存在的商户编号"
          />
        </ElFormItem>
        <ElFormItem label="商品名称">
          <ElInput
            v-model="editForm.name"
            maxlength="225"
            placeholder="留空则清空已保存的商品名称"
          />
        </ElFormItem>
        <ElFormItem label="来源链接">
          <ElInput
            v-model="editForm.url"
            maxlength="2500"
            placeholder="留空则清空已保存的来源链接"
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="保存后会直接覆盖当前风控记录，并同步更新更新时间字段。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton
            v-if="hasRiskEditAuth"
            type="primary"
            :loading="savingRisk"
            @click="submitEditRisk"
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
    fetchAuditRiskBatchDelete,
    fetchBatchDeleteRisks,
    fetchCreateRisk,
    fetchDeleteRisk,
    fetchGetRiskDeleteAudit,
    fetchGetRiskDetail,
    fetchGetRiskList,
    fetchUpdateRisk
  } from '@/api/risks'

  defineOptions({ name: 'RiskRecords' })

  type RiskItem = Api.Risks.RiskListItem
  type RiskSummary = Api.Risks.RiskSummary

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingRisk = ref(false)
  const savingRisk = ref(false)
  const riskList = ref<RiskItem[]>([])
  const selectedRisks = ref<RiskItem[]>([])
  const activeRisk = ref<RiskItem | null>(null)
  const editSourceItem = ref<RiskItem | null>(null)
  const editRiskId = ref<number | null>(null)
  const { hasAuth } = useAuth()
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<RiskSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    user_id?: string
    name?: string
    url?: string
    date_range?: string[]
  }>({})
  const createForm = reactive(emptyWriteForm())
  const editForm = reactive(emptyWriteForm())
  const hasRiskCreateAuth = computed(() => hasAuth('add'))
  const hasRiskEditAuth = computed(() => hasAuth('edit'))
  const hasRiskDeleteAuth = computed(() => hasAuth('remove'))
  const hasRiskBatchDeleteAuth = computed(() => hasAuth('batchRemove'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索风控ID、商户、商品名称、来源链接、邮箱或手机号'
      }
    },
    {
      label: '商户编号',
      key: 'user_id',
      type: 'input',
      props: {
        placeholder: '按单个商户编号筛选'
      }
    },
    {
      label: '商品名称',
      key: 'name',
      type: 'input',
      props: {
        placeholder: '按商品名称筛选'
      }
    },
    {
      label: '来源链接',
      key: 'url',
      type: 'input',
      props: {
        placeholder: '按来源链接筛选'
      }
    },
    {
      label: '创建时间',
      key: 'date_range',
      type: 'daterange',
      props: {
        type: 'daterange',
        valueFormat: 'YYYY-MM-DD',
        startPlaceholder: '开始日期',
        endPlaceholder: '结束日期',
        rangeSeparator: '至'
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<RiskItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'name_label',
      label: '风控对象',
      minWidth: 260,
      formatter: (row) =>
        h('div', { class: 'risk-object-cell' }, [
          h(
            'strong',
            { class: 'cell-title' },
            displayAdminFixtureText(row.name_label, `风控记录 #${row.id}`)
          ),
          h('p', { class: 'cell-sub' }, `编号：${row.id}`)
        ])
    },
    {
      prop: 'merchant_display',
      label: '商户',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, displayAdminFixtureText(row.merchant_display)),
          h(
            'p',
            { class: 'cell-sub' },
            `商户编号：${row.user_id || '--'}${
              row.merchant_username
                ? ` / 商户账号：${displayAdminFixtureText(row.merchant_username)}`
                : ''
            }`
          )
        ])
    },
    {
      prop: 'url_preview',
      label: '来源链接',
      minWidth: 320,
      formatter: (row) =>
        h('div', { class: 'source-cell' }, [
          row.url_link
            ? h(
                'a',
                {
                  class: 'cell-link',
                  href: row.url_link,
                  target: '_blank',
                  rel: 'noopener noreferrer'
                },
                displayAdminFixtureUrl(row.url_preview)
              )
            : h('p', { class: 'cell-sub' }, displayAdminFixtureUrl(row.url_preview)),
          h(
            'p',
            { class: 'cell-sub' },
            row.url_host ? `主机：${displayAdminFixtureText(row.url_host)}` : '未解析主机'
          )
        ])
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 180,
      formatter: (row) => row.create_time || '--'
    },
    {
      prop: 'update_time',
      label: '更新时间',
      minWidth: 180,
      formatter: (row) => row.update_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 240,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderOperationButtons(row)
    }
  ])

  onMounted(() => {
    getRiskList()
  })

  function renderOperationButtons(row: RiskItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canEditRisk(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canDeleteRisk(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteRisk(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canEditRisk(item?: RiskItem | null) {
    return Boolean(item && hasRiskEditAuth.value)
  }

  function canDeleteRisk(item?: RiskItem | null) {
    return Boolean(item && hasRiskDeleteAuth.value)
  }

  async function getRiskList() {
    loading.value = true
    try {
      const response = await fetchGetRiskList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        user_id: searchForm.value.user_id,
        name: searchForm.value.name,
        url: searchForm.value.url,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      riskList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载风控记录列表失败。')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Risks.RiskSearchParams & { date_range?: string[] }) {
    pagination.current = 1
    clearRiskSelection()
    searchForm.value = {
      keyword: params.keyword,
      user_id: params.user_id as string | undefined,
      name: params.name,
      url: params.url,
      date_range: Array.isArray(params.date_range) ? params.date_range : undefined
    }
    getRiskList()
  }

  function handleReset() {
    pagination.current = 1
    clearRiskSelection()
    searchForm.value = {}
    getRiskList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearRiskSelection()
    getRiskList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearRiskSelection()
    getRiskList()
  }

  function handleRiskSelectionChange(rows: RiskItem[]) {
    selectedRisks.value = rows
  }

  async function openDetail(row: RiskItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeRisk.value = row

    try {
      const response = await fetchGetRiskDetail(row.id)
      activeRisk.value = response.item
    } catch (_error) {
      ElMessage.error('加载风控记录详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openCreateDialog() {
    if (!hasRiskCreateAuth.value) {
      ElMessage.warning('您没有新增风控记录的权限。')
      return
    }

    Object.assign(createForm, emptyWriteForm())
    createVisible.value = true
  }

  function openEditDialog(row?: RiskItem) {
    const target = row || activeRisk.value
    if (!target) {
      return
    }

    if (!hasRiskEditAuth.value) {
      ElMessage.warning('您没有编辑风控记录的权限。')
      return
    }

    editRiskId.value = target.id
    editSourceItem.value = target
    Object.assign(editForm, {
      user_id: String(target.user_id || ''),
      name: target.name || '',
      url: target.url || ''
    })
    editVisible.value = true
  }

  async function submitCreateRisk() {
    if (!hasRiskCreateAuth.value) {
      ElMessage.warning('您没有新增风控记录的权限。')
      return
    }

    const payload = buildWritePayload(createForm)
    if (!payload) {
      return
    }

    creatingRisk.value = true
    try {
      const response = await fetchCreateRisk(payload)
      createVisible.value = false
      clearRiskSelection()
      await getRiskList()
      ElMessage.success(
        `风控记录 ${response.created_risk_label || `#${response.created_risk_id}`} 已创建。`
      )
    } catch (_error) {
      ElMessage.error('创建风控记录失败。')
    } finally {
      creatingRisk.value = false
    }
  }

  async function submitEditRisk() {
    if (!hasRiskEditAuth.value) {
      ElMessage.warning('您没有编辑风控记录的权限。')
      return
    }

    if (!editRiskId.value) {
      ElMessage.warning('当前没有可编辑的风控记录。')
      return
    }

    const payload = buildWritePayload(editForm)
    if (!payload) {
      return
    }

    savingRisk.value = true
    try {
      const response = await fetchUpdateRisk(editRiskId.value, payload)
      editVisible.value = false
      editSourceItem.value = response.item
      syncActiveRisk(response.item)
      clearRiskSelection()
      await getRiskList()
      ElMessage.success(
        `风控记录 ${response.updated_risk_label || `#${response.updated_risk_id}`} 已更新。`
      )
    } catch (_error) {
      ElMessage.error('更新风控记录失败。')
    } finally {
      savingRisk.value = false
    }
  }

  async function handleDeleteRisk(row?: RiskItem) {
    const target = row || activeRisk.value
    if (!target) {
      return
    }

    if (!hasRiskDeleteAuth.value) {
      ElMessage.warning('您没有删除风控记录的权限。')
      return
    }

    try {
      const response = await fetchGetRiskDeleteAudit(target.id)
      const audit = response.audit
      const title = displayAdminFixtureText(target.name_label, `风控记录 #${target.id}`)

      const { value } = await ElMessageBox.prompt(
        buildRiskDeletePromptMessage(audit, title),
        '删除风控记录',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteRisk(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeRisk.value?.id === target.id) {
        detailVisible.value = false
        activeRisk.value = null
      }
      if (editRiskId.value === target.id) {
        editVisible.value = false
        editRiskId.value = null
        editSourceItem.value = null
      }

      clearRiskSelection()
      await getRiskList()
      ElMessage.success(
        `风控记录 ${displayAdminFixtureText(deleteResponse.deleted_risk_label, title)} 已永久删除。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除风控记录失败。')
    }
  }

  async function handleBatchDeleteRisks() {
    if (!hasRiskBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除风控记录的权限。')
      return
    }

    if (selectedRisks.value.length === 0) {
      ElMessage.warning('请至少选择一条风控记录。')
      return
    }

    const riskIds = selectedRisks.value.map((item) => item.id)

    try {
      const response = await fetchAuditRiskBatchDelete({
        risk_ids: riskIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildRiskBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildRiskBatchDeletePromptMessage(audit),
        '批量删除风控记录',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteRisks({
        risk_ids: riskIds,
        confirmation_phrase: String(value || '')
      })

      if (activeRisk.value && deleteResponse.deleted_risk_ids.includes(activeRisk.value.id)) {
        detailVisible.value = false
        activeRisk.value = null
      }
      if (editRiskId.value && deleteResponse.deleted_risk_ids.includes(editRiskId.value)) {
        editVisible.value = false
        editRiskId.value = null
        editSourceItem.value = null
      }

      clearRiskSelection()
      await getRiskList()
      ElMessage.success(`已永久删除 ${deleteResponse.deleted_count} 条风控记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除风控记录失败。')
    }
  }

  function syncActiveRisk(item: RiskItem | null) {
    if (!item) {
      return
    }

    if (activeRisk.value?.id === item.id) {
      activeRisk.value = item
    }

    if (editSourceItem.value?.id === item.id) {
      editSourceItem.value = item
    }
  }

  function clearRiskSelection() {
    selectedRisks.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): RiskSummary {
    return {
      total_count: 0,
      merchant_count: 0,
      named_count: 0,
      source_count: 0,
      today_count: 0
    }
  }

  function emptyWriteForm() {
    return {
      user_id: '',
      name: '',
      url: ''
    }
  }

  function buildWritePayload(form: { user_id: string; name: string; url: string }) {
    const userId = normalizeInput(form.user_id)
    if (!/^\d+$/.test(userId) || Number(userId) <= 0) {
      ElMessage.warning('请输入一个有效的商户编号。')
      return null
    }

    const payload: Api.Risks.RiskWritePayload = {
      user_id: userId,
      name: normalizeInput(form.name),
      url: normalizeInput(form.url)
    }

    return payload
  }

  function buildRiskDeletePromptMessage(audit: Api.Risks.RiskDeleteAudit, title: string) {
    return [
      `${title} 将被永久删除。`,
      '',
      `商户：${displayAdminFixtureText(audit.merchant_display)}`,
      `包含来源链接：${audit.summary.source_count > 0 ? '是' : '否'}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildRiskBatchDeleteBlockedMessage(audit: Api.Risks.RiskBatchDeleteAudit) {
    const blocked = audit.items.filter((item) => !item.can_delete)
    return [
      '所选风控记录当前还不能批量删除。',
      '',
      ...blocked.slice(0, 6).map((item) => {
        const label = displayAdminFixtureText(item.risk_label, `风控记录 #${item.risk_id}`)
        return `- ${label}: ${item.blocking_reasons.join(' ')}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function buildRiskBatchDeletePromptMessage(audit: Api.Risks.RiskBatchDeleteAudit) {
    return [
      `即将永久删除 ${audit.summary.deletable_count} 条风控记录。`,
      '',
      `涉及商户数：${audit.summary.merchant_count}`,
      `包含商品名称的记录：${audit.summary.named_count}`,
      `包含来源链接的记录：${audit.summary.source_count}`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认批量删除。`,
      ...audit.warnings.map((item) => `- ${item}`)
    ].join('\n')
  }

  function normalizeInput(value: string | undefined) {
    return String(value || '').trim()
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return (
      error === 'cancel' ||
      error === 'close' ||
      (error instanceof Error && error.message === 'cancel')
    )
  }
</script>

<style scoped lang="scss">
  .risk-record-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .risk-object-cell,
  .merchant-cell,
  .source-cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .risk-record-page {
    --detail-hero-bg:
      linear-gradient(135deg, rgb(255 251 235 / 0.98), rgb(255 241 242 / 0.96)),
      radial-gradient(circle at top right, rgb(248 113 113 / 0.12), transparent 54%);
    --detail-title-color: #0f172a;
    --detail-text-color: #475569;
    --detail-muted-color: #64748b;
  }

  :global(html.dark .risk-record-page ){
    --detail-hero-bg:
      linear-gradient(135deg, rgb(31 41 55 / 0.96), rgb(15 23 42 / 0.94)),
      radial-gradient(circle at top right, rgb(248 113 113 / 0.12), transparent 54%);
    --detail-title-color: #e2e8f0;
    --detail-text-color: #cbd5e1;
    --detail-muted-color: #94a3b8;
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

  .risk-record-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid rgb(248 113 113 / 0.18);
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
    word-break: break-all;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--detail-text-color);
    line-height: 1.7;
    word-break: break-all;
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

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
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
