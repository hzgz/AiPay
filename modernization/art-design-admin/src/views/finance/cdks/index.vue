<template>
  <div class="cdk-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getCdkList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">卡密 {{ pagination.total }}</ElTag>
            <ElTag type="warning" effect="plain">未使用 {{ summary.unused_count }}</ElTag>
            <ElTag type="info" effect="plain">已使用 {{ summary.used_count }}</ElTag>
            <ElTag type="success" effect="plain">余额卡 {{ summary.balance_card_count }}</ElTag>
            <ElTag type="primary" effect="plain">会员卡 {{ summary.vip_card_count }}</ElTag>
            <ElTag effect="plain">总面额 {{ formatAmount(summary.total_face_amount, 2) }}</ElTag>
            <ElTag effect="plain">已掩码卡密 {{ summary.code_ready_count }}</ElTag>
            <ElButton v-if="hasCdkCreateAuth" type="primary" @click="openCreateDialog">生成卡密</ElButton>
            <ElButton
              v-if="hasCdkBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedCdks.length === 0"
              @click="handleBatchDeleteCdks"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="hasCdkBatchDeleteAuth"
              plain
              type="warning"
              :loading="cleanupUsedLoading"
              @click="handleCleanupUsedCdks"
            >
              清理已使用
            </ElButton>
            <ElTag v-if="hasCdkBatchDeleteAuth && selectedCdks.length > 0" type="danger" effect="plain">
              已选 {{ selectedCdks.length }}
            </ElTag>
            <ElTag type="info" effect="plain">卡密维护</ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="cdkList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleCdkSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeCdk ? `${displayCdkType(activeCdk.type_label)} / #${activeCdk.id}` : '卡密详情'"
    >
      <div v-loading="detailLoading" class="cdk-detail">
        <template v-if="activeCdk">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ displayCdkType(activeCdk.type_label) }}</h3>
              <p>{{ displayCdkStatus(activeCdk.status_label) }} / {{ displayCdkValue(activeCdk.value_label) }}</p>
              <span>{{ displayAdminFixtureText(activeCdk.delete_guard_reason, '--') }}</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton v-if="canDeleteCdk(activeCdk)" type="danger" plain @click="handleDeleteCdk()">删除</ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="脱敏卡密">
                <span class="code-text">
                  {{ displayCdkCode(activeCdk.code_masked, activeCdk.has_code) }}
                </span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态">
                {{ displayCdkStatus(activeCdk.status_label) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="类型">
                {{ displayCdkType(activeCdk.type_label) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="面值">
                {{ displayCdkValue(activeCdk.value_label) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="卡密长度">
                {{ activeCdk.code_length || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeCdk.create_time || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>卡密元数据</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="卡密编号">
                {{ activeCdk.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始类型">
                {{ displayAdminFixtureText(activeCdk.type, '--') }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始值 / 会员套餐编号">
                {{ displayAdminFixtureText(activeCdk.value, '--') }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="会员套餐">
                {{
                  displayAdminFixtureText(
                    activeCdk.vip_name || (activeCdk.vip_id ? `会员 #${activeCdk.vip_id}` : '--'),
                    '--'
                  )
                }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="面额">
                {{ activeCdk.face_amount === null ? '--' : formatAmount(activeCdk.face_amount, 2) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始状态">
                {{ activeCdk.status }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <ElAlert
            type="warning"
            :closable="false"
            show-icon
            title="原始卡密仅会在创建后展示一次，后续列表和详情都会保持脱敏显示。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="760px"
      destroy-on-close
      align-center
      title="生成卡密"
    >
      <ElForm label-position="top">
        <ElFormItem label="卡密类型">
          <ElSelect v-model="createForm.type" placeholder="请选择卡密类型">
            <ElOption label="余额充值卡" value="1" />
            <ElOption label="会员兑换卡" value="2" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="生成数量">
          <ElInput
            v-model="createForm.count"
            maxlength="3"
            placeholder="请输入 1 到 200 之间的数量"
          />
        </ElFormItem>
        <ElFormItem v-if="createForm.type === '1'" label="余额面额">
          <ElInput
            v-model="createForm.amount"
            maxlength="16"
            placeholder="请输入每张卡的余额面额"
          />
        </ElFormItem>
        <ElFormItem v-else label="会员套餐">
          <ElSelect
            v-model="createForm.vip_id"
            filterable
            placeholder="请选择每张卡可兑换的会员套餐"
          >
            <ElOption
              v-for="vip in vipOptions"
              :key="vip.id"
              :label="vip.name"
              :value="String(vip.id)"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="可选前缀">
          <ElInput
            v-model="createForm.prefix"
            maxlength="20"
            placeholder="可选前缀，仅支持字母、数字、下划线和短横线"
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="原始卡密仅会在创建结果弹窗中返回一次，请在关闭前完成保存。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton v-if="hasCdkCreateAuth" type="primary" :loading="creatingCdks" @click="submitCreateCdks">
            生成卡密
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="createResultVisible"
      width="820px"
      destroy-on-close
      align-center
      title="创建结果"
    >
      <div v-if="createResult" class="result-panel">
          <ElDescriptions :column="2" border>
            <ElDescriptionsItem label="生成数量">
              {{ createResult.created_count }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="卡密类型">
              {{ displayCdkType(createResult.created_type_label) }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="面值">
              {{ displayCdkValue(createResult.value_label) }}
            </ElDescriptionsItem>
          <ElDescriptionsItem label="前缀">
            {{ createResult.prefix || '--' }}
          </ElDescriptionsItem>
        </ElDescriptions>

        <div class="drawer-section">
          <h4>原始卡密</h4>
          <ElInput
            :model-value="createResult.generated_codes.join('\n')"
            type="textarea"
            :rows="10"
            readonly
          />
        </div>

        <ElAlert
          type="warning"
          :closable="false"
          show-icon
          title="这是唯一一次展示原始卡密的返回结果，关闭弹窗后列表和详情都只会显示脱敏值。"
        />
      </div>

      <template #footer>
        <div class="dialog-footer">
          <ElButton type="primary" @click="createResultVisible = false">关闭</ElButton>
        </div>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useAuth } from '@/hooks'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import {
    fetchAuditCdkBatchDelete,
    fetchBatchDeleteCdks,
    fetchCleanupUsedCdks,
    fetchCreateCdks,
    fetchDeleteCdk,
    fetchGetCdkCleanupUsedAudit,
    fetchGetCdkDeleteAudit,
    fetchGetCdkDetail,
    fetchGetCdkList
  } from '@/api/cdks'
  import { fetchGetVipList } from '@/api/vips'
  import {
    displayAdminFixtureText,
    displayAdminMaskedPreview
  } from '@/utils/adminFixtureText'

  defineOptions({ name: 'FinanceCdks' })

  type CdkItem = Api.Cdks.CdkListItem
  type CdkSummary = Api.Cdks.CdkSummary
  type CdkCreateResponse = Api.Cdks.CdkCreateResponse
  type VipItem = Api.Vips.VipListItem

  const { hasAuth } = useAuth()
  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const createResultVisible = ref(false)
  const creatingCdks = ref(false)
  const cleanupUsedLoading = ref(false)
  const cdkList = ref<CdkItem[]>([])
  const selectedCdks = ref<CdkItem[]>([])
  const activeCdk = ref<CdkItem | null>(null)
  const vipOptions = ref<VipItem[]>([])
  const createResult = ref<CdkCreateResponse | null>(null)
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<CdkSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    type?: string
    status?: string
    date_range?: string[]
  }>({})
  const createForm = reactive(emptyCreateForm())
  const hasCdkCreateAuth = computed(() => hasAuth('add'))
  const hasCdkDeleteAuth = computed(() => hasAuth('remove'))
  const hasCdkBatchDeleteAuth = computed(() => hasAuth('batchRemove'))

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '按卡密编号、卡密、面额或会员套餐搜索'
      }
    },
    {
      label: '卡密类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '全部类型',
        options: [
          { label: '余额充值卡', value: '1' },
          { label: '会员兑换卡', value: '2' }
        ]
      }
    },
    {
      label: '使用状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '全部状态',
        options: [
          { label: '未使用', value: '0' },
          { label: '已使用', value: '1' }
        ]
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

  const { columnChecks, columns } = useTableColumns<CdkItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'code_masked',
      label: '卡密',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'code-cell' }, [
          h('strong', { class: 'code-text' }, displayCdkCode(row.code_masked, row.has_code)),
          h('p', { class: 'cell-sub' }, row.has_code ? `长度 ${row.code_length}` : '未存储卡密')
        ])
    },
    {
      prop: 'type_label',
      label: '类型',
      minWidth: 160,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.type_tag), effect: 'light' }, () => displayCdkType(row.type_label))
    },
    {
      prop: 'value_label',
      label: '面值',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'value-cell' }, [
          h('strong', { class: 'cell-title' }, displayCdkValue(row.value_label)),
          h('p', { class: 'cell-sub' }, row.type === 2 ? `会员套餐编号：${row.vip_id || '--'}` : '余额卡')
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      minWidth: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayCdkStatus(row.status_label))
    },
    {
      prop: 'create_time',
      label: '创建时间',
      minWidth: 170,
      formatter: (row) => row.create_time || '--'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 170,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderCdkOperationButtons(row)
    }
  ])

  onMounted(() => {
    getCdkList()
    loadVipOptions()
  })

  function renderCdkOperationButtons(row: CdkItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canDeleteCdk(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteCdk(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  async function getCdkList() {
    loading.value = true
    try {
      const response = await fetchGetCdkList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        type: searchForm.value.type,
        status: searchForm.value.status,
        start_date: searchForm.value.date_range?.[0],
        end_date: searchForm.value.date_range?.[1]
      })
      cdkList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('加载卡密列表失败。')
    } finally {
      loading.value = false
    }
  }

  async function loadVipOptions() {
    try {
      const response = await fetchGetVipList({
        current: 1,
        size: 100
      })
      vipOptions.value = response.records.filter((item) => !item.deleted)
    } catch (_error) {
      vipOptions.value = []
    }
  }

  function handleSearch(params: Record<string, unknown>) {
    pagination.current = 1
    clearCdkSelection()
    searchForm.value = {
      keyword: params.keyword as string | undefined,
      type: params.type as string | undefined,
      status: params.status as string | undefined,
      date_range: Array.isArray(params.date_range) ? (params.date_range as string[]) : undefined
    }
    getCdkList()
  }

  function handleReset() {
    pagination.current = 1
    clearCdkSelection()
    searchForm.value = {}
    getCdkList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    clearCdkSelection()
    getCdkList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    clearCdkSelection()
    getCdkList()
  }

  function handleCdkSelectionChange(rows: CdkItem[]) {
    selectedCdks.value = rows
  }

  async function openDetail(row: CdkItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeCdk.value = row

    try {
      const response = await fetchGetCdkDetail(row.id)
      activeCdk.value = response.item
    } catch (_error) {
      ElMessage.error('加载卡密详情失败。')
    } finally {
      detailLoading.value = false
    }
  }

  function openCreateDialog() {
    if (!hasCdkCreateAuth.value) {
      ElMessage.warning('您没有生成卡密的权限。')
      return
    }

    Object.assign(createForm, emptyCreateForm())
    createVisible.value = true
  }

  async function submitCreateCdks() {
    if (!hasCdkCreateAuth.value) {
      ElMessage.warning('您没有生成卡密的权限。')
      return
    }

    const payload = buildCreatePayload()
    if (!payload) {
      return
    }

    creatingCdks.value = true
    try {
      const response = await fetchCreateCdks(payload)
      createVisible.value = false
      createResult.value = response
      createResultVisible.value = true
      clearCdkSelection()
      await getCdkList()
      ElMessage.success(`已生成 ${response.created_count} 条卡密记录。`)
    } catch (_error) {
      ElMessage.error('生成卡密失败。')
    } finally {
      creatingCdks.value = false
    }
  }

  async function handleDeleteCdk(row?: CdkItem) {
    if (!hasCdkDeleteAuth.value) {
      ElMessage.warning('您没有删除卡密的权限。')
      return
    }

    const target = row || activeCdk.value
    if (!target) {
      return
    }

    try {
      const response = await fetchGetCdkDeleteAudit(target.id)
      const audit = response.audit
      const title = target.type_label ? `${displayCdkType(target.type_label)} / #${target.id}` : `卡密 #${target.id}`

      const { value } = await ElMessageBox.prompt(
        buildCdkDeletePromptMessage(audit, title),
        '删除卡密',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchDeleteCdk(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeCdk.value?.id === target.id) {
        detailVisible.value = false
        activeCdk.value = null
      }

      clearCdkSelection()
      await getCdkList()
      ElMessage.success(
        `卡密 ${displayAdminFixtureText(deleteResponse.deleted_cdk_label || title, title)} 已永久删除。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('删除卡密失败。')
    }
  }

  async function handleBatchDeleteCdks() {
    if (!hasCdkBatchDeleteAuth.value) {
      ElMessage.warning('您没有批量删除卡密的权限。')
      return
    }

    if (selectedCdks.value.length === 0) {
      ElMessage.warning('请至少选择一条卡密记录。')
      return
    }

    const cdkIds = selectedCdks.value.map((item) => item.id)

    try {
      const response = await fetchAuditCdkBatchDelete({
        cdk_ids: cdkIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildCdkBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildCdkBatchDeletePromptMessage(audit),
        '批量删除卡密',
        {
          confirmButtonText: '批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteCdks({
        cdk_ids: cdkIds,
        confirmation_phrase: String(value || '')
      })

      if (activeCdk.value && deleteResponse.deleted_cdk_ids.includes(activeCdk.value.id)) {
        detailVisible.value = false
        activeCdk.value = null
      }

      clearCdkSelection()
      await getCdkList()
      ElMessage.success(`已永久删除 ${deleteResponse.deleted_count} 条卡密记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('批量删除卡密失败。')
    }
  }

  async function handleCleanupUsedCdks() {
    if (!hasCdkBatchDeleteAuth.value) {
      ElMessage.warning('您没有清理已使用卡密的权限。')
      return
    }

    cleanupUsedLoading.value = true
    try {
      const response = await fetchGetCdkCleanupUsedAudit()
      const audit = response.audit

      if (!audit.can_cleanup) {
        await ElMessageBox.alert(audit.warnings.join('\n'), '暂无可清理项', {
          type: 'info',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildCleanupUsedPromptMessage(audit),
        '清理已使用卡密',
        {
          confirmButtonText: '清理',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 以继续。`
        }
      )

      const cleanupResponse = await fetchCleanupUsedCdks({
        confirmation_phrase: String(value || '')
      })

      if (activeCdk.value?.is_used) {
        detailVisible.value = false
        activeCdk.value = null
      }

      clearCdkSelection()
      await getCdkList()
      ElMessage.success(`已清理 ${cleanupResponse.deleted_count} 条已使用卡密记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      ElMessage.error('清理已使用卡密失败。')
    } finally {
      cleanupUsedLoading.value = false
    }
  }

  function clearCdkSelection() {
    selectedCdks.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function canDeleteCdk(item?: CdkItem | null) {
    return Boolean(item && hasCdkDeleteAuth.value)
  }

  function emptySummary(): CdkSummary {
    return {
      unused_count: 0,
      used_count: 0,
      balance_card_count: 0,
      vip_card_count: 0,
      total_face_amount: 0,
      code_ready_count: 0
    }
  }

  function emptyCreateForm() {
    return {
      type: '1',
      count: '1',
      amount: '',
      vip_id: '',
      prefix: ''
    }
  }

  function buildCreatePayload(): Api.Cdks.CdkCreatePayload | null {
    const count = normalizeInput(createForm.count)
    if (!count) {
      ElMessage.warning('请输入生成数量。')
      return null
    }

    const payload: Api.Cdks.CdkCreatePayload = {
      type: createForm.type,
      count
    }

    const prefix = normalizeInput(createForm.prefix)
    if (prefix) {
      payload.prefix = prefix
    }

    if (createForm.type === '1') {
      const amount = normalizeInput(createForm.amount)
      if (!amount) {
        ElMessage.warning('请输入余额面额。')
        return null
      }
      payload.amount = amount
      return payload
    }

    const vipId = normalizeInput(createForm.vip_id)
    if (!vipId) {
      ElMessage.warning('请选择会员套餐。')
      return null
    }

    payload.vip_id = vipId
    return payload
  }

  function buildCdkDeletePromptMessage(audit: Api.Cdks.CdkDeleteAudit, title: string) {
    return [
      `${title} 将被永久删除。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${displayAdminFixtureText(item, item)}`)
    ].join('\n')
  }

  function buildCdkBatchDeleteBlockedMessage(audit: Api.Cdks.CdkBatchDeleteAudit) {
    const blocked = audit.items.filter((item) => !item.can_delete)
    return [
      '当前选中的卡密记录暂时无法批量删除。',
      '',
      ...blocked.slice(0, 6).map((item) => {
        const label = displayAdminFixtureText(item.cdk_label || `卡密 #${item.cdk_id}`, `卡密 #${item.cdk_id}`)
        const reason =
          item.blocking_reasons
            .map((entry) => displayAdminFixtureText(entry, entry))
            .join(' ') || '请刷新选择后重试。'
        return `- ${label}：${reason}`
      }),
      '',
      ...audit.warnings.map((item) => `- ${displayAdminFixtureText(item, item)}`)
    ].join('\n')
  }

  function buildCdkBatchDeletePromptMessage(audit: Api.Cdks.CdkBatchDeleteAudit) {
    return [
      `即将永久删除 ${audit.summary.deletable_count} 条卡密记录。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认永久删除。`,
      ...audit.warnings.map((item) => `- ${displayAdminFixtureText(item, item)}`)
    ].join('\n')
  }

  function buildCleanupUsedPromptMessage(audit: Api.Cdks.CdkCleanupUsedAudit) {
    return [
      `即将永久清理 ${audit.summary.used_count} 条已使用卡密。`,
      '',
      `请输入 ${audit.confirmation_phrase} 以确认清理。`,
      ...audit.warnings.map((item) => `- ${displayAdminFixtureText(item, item)}`)
    ].join('\n')
  }

  function normalizeInput(value: string | undefined) {
    return String(value || '').trim()
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

  function displayCdkType(value: string | null | undefined) {
    if (value === 'Balance Recharge Card') return '余额充值卡'
    if (value === 'VIP Exchange Card') return '会员兑换卡'
    return displayAdminFixtureText(value, '--')
  }

  function displayCdkStatus(value: string | null | undefined) {
    if (value === 'Unused') return '未使用'
    if (value === 'Used') return '已使用'
    return displayAdminFixtureText(value, '--')
  }

  function displayCdkValue(value: null | number | string | undefined) {
    return displayAdminFixtureText(value, '--')
  }

  function displayCdkCode(value: null | number | string | undefined, hasCode: boolean) {
    return displayAdminMaskedPreview(value, hasCode ? '已脱敏卡密' : '--', '已脱敏卡密')
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped lang="scss">
  .cdk-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .code-cell,
  .value-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .code-text {
    font-family:
      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
      monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
    word-break: break-all;
  }

  .cell-title {
    color: #0f172a;
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
    word-break: break-all;
  }

  .cdk-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 18px;
    background: linear-gradient(135deg, rgb(248 250 252 / 0.96), rgb(241 245 249 / 0.92));
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: #0f172a;
    font-size: 20px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: #475569;
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
    color: #0f172a;
    font-size: 15px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  .result-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
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
