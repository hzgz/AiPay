<template>
  <div class="domain-page art-full-height">
    <ArtSearchBar
      v-model="searchForm"
      :items="searchItems"
      :showExpand="false"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="getDomainList">
        <template #left>
          <ElSpace wrap>
            <ElTag effect="plain">域名 {{ pagination.total }}</ElTag>
            <ElTag type="warning" effect="plain">待审核 {{ summary.pending_count }}</ElTag>
            <ElTag type="success" effect="plain">已通过 {{ summary.approved_count }}</ElTag>
            <ElTag type="danger" effect="plain">已驳回 {{ summary.rejected_count }}</ElTag>
            <ElTag type="info" effect="plain">回收站 {{ summary.deleted_count }}</ElTag>
            <ElButton
              plain
              :type="isRecycleView ? 'primary' : 'info'"
              @click="toggleRecycleView"
            >
              {{ isRecycleView ? '返回正常列表' : '回收站' }}
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasDomainCreateAuth"
              type="primary"
              @click="openCreateDialog"
            >
              新建域名
            </ElButton>
            <ElButton
              v-if="!isRecycleView && hasDomainBatchDeleteAuth"
              plain
              type="danger"
              :disabled="selectedDomains.length === 0"
              @click="handleBatchDeleteDomains"
            >
              批量删除
            </ElButton>
            <ElButton
              v-if="isRecycleView && hasDomainRecycleAuth"
              plain
              type="success"
              :disabled="selectedDomains.length === 0"
              @click="handleBatchRestoreDomains"
            >
              批量恢复
            </ElButton>
            <ElTag v-if="selectedDomains.length > 0" type="danger" effect="plain">
              已选 {{ selectedDomains.length }}
            </ElTag>
            <ElTag type="info" effect="plain">
              {{ isRecycleView ? '回收站视图' : '审核视图' }}
            </ElTag>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        :loading="loading"
        :data="domainList"
        :columns="columns"
        :pagination="pagination"
        row-key="id"
        reserve-selection
        @selection-change="handleDomainSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      size="760px"
      destroy-on-close
      :title="activeDomain ? `${activeDomain.sitename || activeDomain.siteurl} / #${activeDomain.id}` : '域名详情'"
    >
      <div v-loading="detailLoading" class="domain-detail">
        <template v-if="activeDomain">
          <div class="detail-hero">
            <div class="detail-hero-copy">
              <h3>{{ activeDomain.sitename || activeDomain.siteurl || `域名 #${activeDomain.id}` }}</h3>
              <p>{{ activeDomain.siteurl || '--' }}</p>
              <span>查看该域名记录的归属商户、审核状态与回收站状态。</span>
            </div>
            <div class="detail-hero-actions">
              <ElButton
                v-if="canApproveDomain(activeDomain)"
                type="success"
                @click="handleApproveDomain()"
              >
                通过
              </ElButton>
              <ElButton
                v-if="canRejectDomain(activeDomain)"
                type="danger"
                plain
                @click="handleRejectDomain()"
              >
                驳回
              </ElButton>
              <ElButton
                v-if="canEditDomain(activeDomain)"
                plain
                @click="openEditDialog()"
              >
                编辑
              </ElButton>
              <ElButton
                v-if="canDeleteDomain(activeDomain)"
                type="danger"
                plain
                @click="handleDeleteDomain()"
              >
                删除
              </ElButton>
              <ElButton
                v-if="canRestoreDomain(activeDomain)"
                type="success"
                plain
                @click="handleRestoreDomain()"
              >
                恢复域名
              </ElButton>
            </div>
          </div>

          <div class="drawer-section">
            <ElDescriptions :column="2" border>
              <ElDescriptionsItem label="网站名称">
                {{ activeDomain.sitename || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="状态">
                {{ displayDomainStatus(activeDomain) }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="域名地址">
                <a
                  v-if="activeDomain.siteurl_link"
                  class="cell-link"
                  :href="activeDomain.siteurl_link"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ activeDomain.siteurl }}
                </a>
                <span v-else>{{ activeDomain.siteurl || '--' }}</span>
              </ElDescriptionsItem>
              <ElDescriptionsItem label="商户">
                {{ activeDomain.merchant_display }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="创建时间">
                {{ activeDomain.create_time || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="删除时间">
                {{ activeDomain.delete_time || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>商户资料</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="商户编号">
                {{ activeDomain.user_id || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="用户名">
                {{ activeDomain.merchant_username || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="联系人">
                {{ activeDomain.merchant_name || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="Email">
                {{ activeDomain.merchant_email || '--' }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="手机号">
                {{ activeDomain.merchant_mobile || '--' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <div class="drawer-section">
            <h4>审核备注</h4>
            <ElDescriptions :column="1" border>
              <ElDescriptionsItem label="记录 ID">
                {{ activeDomain.id }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="原始状态">
                {{ activeDomain.status }}
              </ElDescriptionsItem>
              <ElDescriptionsItem label="驳回原因">
                {{ activeDomain.reason || '暂无驳回原因' }}
              </ElDescriptionsItem>
            </ElDescriptions>
          </div>

          <ElAlert
            type="info"
            :closable="false"
            show-icon
            title="当前页面已支持新建、编辑、通过、驳回、删除到回收站、批量删除到回收站及回收站恢复。修改域名地址后会重新触发审核规则。"
          />
        </template>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      width="560px"
      destroy-on-close
      align-center
      title="新建域名"
    >
      <ElForm label-position="top">
        <div class="domain-form-grid">
          <ElFormItem label="商户编号">
            <ElInput
              v-model="createForm.user_id"
              maxlength="20"
              placeholder="请输入该域名所属的商户编号"
            />
          </ElFormItem>
          <ElFormItem label="网站名称">
            <ElInput
              v-model="createForm.sitename"
              maxlength="255"
              placeholder="请输入站点显示名称"
            />
          </ElFormItem>
        </div>
        <ElFormItem label="域名地址">
          <ElInput
            v-model="createForm.siteurl"
            maxlength="255"
            placeholder="pay.你的域名.com 或 https://pay.你的域名.com"
          />
        </ElFormItem>
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="系统会去掉 http/https 和结尾斜杠。命中白名单或全局自动审核时可自动通过，命中黑名单时会直接拦截。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="createVisible = false">取消</ElButton>
          <ElButton
            v-if="hasDomainCreateAuth"
            type="primary"
            :loading="creatingDomain"
            @click="submitCreateDomain"
          >
            新建域名
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="editVisible"
      width="560px"
      destroy-on-close
      align-center
      title="编辑域名"
    >
      <ElForm label-position="top">
        <div class="domain-form-grid">
          <ElFormItem label="商户编号">
            <ElInput
              v-model="editForm.user_id"
              maxlength="20"
              placeholder="请输入该域名所属的商户编号"
            />
          </ElFormItem>
          <ElFormItem label="网站名称">
            <ElInput
              v-model="editForm.sitename"
              maxlength="255"
              placeholder="请输入站点显示名称"
            />
          </ElFormItem>
        </div>
        <ElFormItem label="域名地址">
          <ElInput
            v-model="editForm.siteurl"
            maxlength="255"
            placeholder="pay.你的域名.com 或 https://pay.你的域名.com"
          />
        </ElFormItem>
        <ElAlert
          type="warning"
          :closable="false"
          show-icon
          title="修改域名地址后会重新执行审核规则；根据当前白名单和自动审核设置，修改后的地址可能重新进入待审核，或再次被自动通过。"
        />
      </ElForm>

      <template #footer>
        <div class="dialog-footer">
          <ElButton @click="editVisible = false">取消</ElButton>
          <ElButton
            v-if="hasDomainEditAuth"
            type="primary"
            :loading="savingEdit"
            @click="submitEditDomain"
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
  import {
    fetchAuditDomainBatchDelete,
    fetchApproveDomain,
    fetchBatchDeleteDomains,
    fetchBatchRestoreDomains,
    fetchCreateDomain,
    fetchDeleteDomain,
    fetchGetDomainDeleteAudit,
    fetchGetDomainDetail,
    fetchGetDomainList,
    fetchRejectDomain,
    fetchRestoreDomain,
    fetchUpdateDomain
  } from '@/api/domains'

  defineOptions({ name: 'SystemDomains' })

  type DomainItem = Api.Domains.DomainListItem
  type DomainSummary = Api.Domains.DomainSummary

  const tableRef = ref<{ elTableRef?: { clearSelection?: () => void } } | null>(null)
  const loading = ref(false)
  const detailVisible = ref(false)
  const detailLoading = ref(false)
  const createVisible = ref(false)
  const editVisible = ref(false)
  const creatingDomain = ref(false)
  const savingEdit = ref(false)
  const domainList = ref<DomainItem[]>([])
  const selectedDomains = ref<DomainItem[]>([])
  const activeDomain = ref<DomainItem | null>(null)
  const editDomainId = ref<number | null>(null)
  const { hasAuth } = useAuth()
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })
  const summary = reactive<DomainSummary>(emptySummary())
  const searchForm = ref<{
    keyword?: string
    user_id?: string
    sitename?: string
    siteurl?: string
    status?: string
  }>({})
  const createForm = reactive({
    user_id: '',
    sitename: '',
    siteurl: ''
  })
  const editForm = reactive({
    user_id: '',
    sitename: '',
    siteurl: ''
  })
  const hasDomainCreateAuth = computed(() => hasAuth('add') || hasAuth('index'))
  const hasDomainEditAuth = computed(() => hasAuth('edit') || hasAuth('index'))
  const hasDomainAuditAuth = computed(() => hasAuth('status') || hasAuth('index'))
  const hasDomainDeleteAuth = computed(() => hasAuth('remove') || hasAuth('index'))
  const hasDomainBatchDeleteAuth = computed(() => hasAuth('batchRemove') || hasAuth('index'))
  const hasDomainRecycleAuth = computed(() => hasAuth('recycle') || hasAuth('index'))

  const isRecycleView = computed(() => {
    const status = String(searchForm.value.status || '')
    return status === '-1' || status.toLowerCase() === 'deleted'
  })

  const searchItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        placeholder: '搜索域名编号、商户、站点名称、域名地址或驳回原因'
      }
    },
    {
      label: '商户编号',
      key: 'user_id',
      type: 'input',
      props: {
        placeholder: '按商户编号筛选'
      }
    },
    {
      label: '网站名称',
      key: 'sitename',
      type: 'input',
      props: {
        placeholder: '按站点名称筛选'
      }
    },
    {
      label: '域名地址',
      key: 'siteurl',
      type: 'input',
      props: {
        placeholder: '按域名地址筛选'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        options: [
          { label: '待审核', value: '0' },
          { label: '已通过', value: '1' },
          { label: '已驳回', value: '2' },
          { label: '回收站', value: '-1' }
        ]
      }
    }
  ])

  const { columnChecks, columns } = useTableColumns<DomainItem>(() => [
    { type: 'selection', width: 54, fixed: 'left' as const },
    { type: 'globalIndex', width: 70, label: '序号' },
    {
      prop: 'sitename',
      label: '站点信息',
      minWidth: 260,
      formatter: (row) =>
        h('div', { class: 'domain-cell' }, [
          h('strong', { class: 'cell-title' }, row.sitename || `域名 #${row.id}`),
          row.siteurl_link
            ? h(
                'a',
                {
                  class: 'cell-link',
                  href: row.siteurl_link,
                  target: '_blank',
                  rel: 'noopener noreferrer'
                },
                row.siteurl
              )
            : h('p', { class: 'cell-sub' }, row.siteurl || '--')
        ])
    },
    {
      prop: 'merchant_display',
      label: '商户',
      minWidth: 220,
      formatter: (row) =>
        h('div', { class: 'merchant-cell' }, [
          h('strong', { class: 'cell-title' }, row.merchant_display),
          h('p', { class: 'cell-sub' }, `商户编号：${row.user_id || '--'}`)
        ])
    },
    {
      prop: 'status_label',
      label: '状态',
      width: 120,
      align: 'center' as const,
      formatter: (row) =>
        h(ElTag, { type: tagType(row.status_type), effect: 'light' }, () => displayDomainStatus(row))
    },
    {
      prop: 'reason_preview',
      label: '驳回原因',
      minWidth: 220,
      formatter: (row) => row.reason_preview
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
      width: 300,
      align: 'center' as const,
      fixed: 'right' as const,
      formatter: (row) => renderDomainOperationButtons(row)
    }
  ])

  onMounted(() => {
    getDomainList()
  })

  function displayDomainStatus(domain?: Partial<DomainItem> | null, fallback = '--') {
    const value = String(domain?.status_text || domain?.status_label || '').trim()
    if (value === '') {
      return fallback
    }

    switch (value.toLowerCase()) {
      case 'approved':
        return '已通过'
      case 'pending':
        return '待审核'
      case 'rejected':
        return '已驳回'
      case 'recycled':
        return '回收站'
      default:
        return value
    }
  }

  function renderDomainOperationButtons(row: DomainItem) {
    const actions = [
      h(ArtButtonTable, {
        type: 'view',
        title: '详情',
        onClick: () => openDetail(row)
      })
    ]

    if (canApproveDomain(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:check-line',
          iconClass: 'bg-success/12 text-success',
          title: '通过',
          onClick: () => handleApproveDomain(row)
        })
      )
    }

    if (canRejectDomain(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:close-line',
          iconClass: 'bg-danger/12 text-danger',
          title: '驳回',
          onClick: () => handleRejectDomain(row)
        })
      )
    }

    if (canEditDomain(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:pencil-line',
          iconClass: 'bg-primary/12 text-primary',
          title: '编辑',
          onClick: () => openEditDialog(row)
        })
      )
    }

    if (canDeleteDomain(row)) {
      actions.push(
        h(ArtButtonTable, {
          type: 'delete',
          title: '删除',
          onClick: () => handleDeleteDomain(row)
        })
      )
    }

    if (canRestoreDomain(row)) {
      actions.push(
        h(ArtButtonTable, {
          icon: 'ri:restart-line',
          iconClass: 'bg-success/12 text-success',
          title: '恢复',
          onClick: () => handleRestoreDomain(row)
        })
      )
    }

    return h('div', { class: 'table-actions' }, actions)
  }

  function canApproveDomain(domain?: DomainItem | null) {
    return Boolean(domain && hasDomainAuditAuth.value && !domain.is_deleted && domain.status !== 1)
  }

  function canRejectDomain(domain?: DomainItem | null) {
    return Boolean(domain && hasDomainAuditAuth.value && !domain.is_deleted && domain.status !== 2)
  }

  function canEditDomain(domain?: DomainItem | null) {
    return Boolean(domain && hasDomainEditAuth.value && !domain.is_deleted)
  }

  function canDeleteDomain(domain?: DomainItem | null) {
    return Boolean(domain && hasDomainDeleteAuth.value && !domain.is_deleted)
  }

  function canRestoreDomain(domain?: DomainItem | null) {
    return Boolean(domain && hasDomainRecycleAuth.value && domain.is_deleted)
  }

  async function getDomainList() {
    loading.value = true
    try {
      const response = await fetchGetDomainList({
        current: pagination.current,
        size: pagination.size,
        keyword: searchForm.value.keyword,
        user_id: searchForm.value.user_id,
        sitename: searchForm.value.sitename,
        siteurl: searchForm.value.siteurl,
        status: searchForm.value.status
      })
      domainList.value = response.records
      pagination.current = response.current
      pagination.size = response.size
      pagination.total = response.total
      Object.assign(summary, response.summary || emptySummary())
    } catch (_error) {
      ElMessage.error('域名列表加载失败')
    } finally {
      loading.value = false
    }
  }

  function handleSearch(params: Api.Domains.DomainSearchParams) {
    pagination.current = 1
    clearDomainSelection()
    searchForm.value = {
      keyword: params.keyword,
      user_id: params.user_id as string | undefined,
      sitename: params.sitename,
      siteurl: params.siteurl,
      status: params.status as string | undefined
    }
    getDomainList()
  }

  function handleReset() {
    pagination.current = 1
    clearDomainSelection()
    searchForm.value = {}
    getDomainList()
  }

  function toggleRecycleView() {
    pagination.current = 1
    clearDomainSelection()
    searchForm.value = {
      ...searchForm.value,
      status: isRecycleView.value ? undefined : '-1'
    }
    getDomainList()
  }

  function handleSizeChange(size: number) {
    pagination.size = size
    pagination.current = 1
    getDomainList()
  }

  function handleCurrentChange(current: number) {
    pagination.current = current
    getDomainList()
  }

  function handleDomainSelectionChange(rows: DomainItem[]) {
    selectedDomains.value = rows
  }

  function openCreateDialog() {
    if (!hasDomainCreateAuth.value) {
      ElMessage.warning('你没有新建域名的权限')
      return
    }

    resetWriteForm(createForm)
    createVisible.value = true
  }

  function openEditDialog(row?: DomainItem) {
    const target = row || activeDomain.value
    if (!target) {
      return
    }

    if (!hasDomainEditAuth.value) {
      ElMessage.warning('你没有编辑域名的权限')
      return
    }

    if (target.is_deleted) {
      ElMessage.warning('请先恢复该域名后再编辑')
      return
    }

    editDomainId.value = target.id
    syncWriteForm(editForm, target)
    editVisible.value = true
  }

  async function openDetail(row: DomainItem) {
    detailVisible.value = true
    detailLoading.value = true
    activeDomain.value = row

    try {
      const response = await fetchGetDomainDetail(row.id)
      activeDomain.value = response.item
    } catch (_error) {
      ElMessage.error('域名详情加载失败')
    } finally {
      detailLoading.value = false
    }
  }

  async function submitCreateDomain() {
    if (!hasDomainCreateAuth.value) {
      ElMessage.warning('你没有新建域名的权限')
      return
    }

    const payload = buildWritePayload(createForm)
    if (!payload) {
      return
    }

    creatingDomain.value = true
    try {
      const response = await fetchCreateDomain(payload)
      createVisible.value = false
      resetWriteForm(createForm)
      clearDomainSelection()
      await getDomainList()
      ElMessage.success(
        `域名 ${response.created_domain_label || payload.sitename || payload.siteurl} 已创建，当前状态：${displayDomainStatus(response.item)}。`
      )
    } finally {
      creatingDomain.value = false
    }
  }

  async function submitEditDomain() {
    if (!editDomainId.value || !hasDomainEditAuth.value) {
      if (!hasDomainEditAuth.value) {
        ElMessage.warning('你没有编辑域名的权限')
      }
      return
    }

    const payload = buildWritePayload(editForm)
    if (!payload) {
      return
    }

    savingEdit.value = true
    try {
      const response = await fetchUpdateDomain(editDomainId.value, payload)
      editVisible.value = false
      syncActiveDomain(response.item)
      clearDomainSelection()
      await getDomainList()
      ElMessage.success(
        `域名 ${response.updated_domain_label || payload.sitename || payload.siteurl} 已更新，当前状态：${displayDomainStatus(response.item)}。`
      )
    } finally {
      savingEdit.value = false
    }
  }

  async function handleApproveDomain(row?: DomainItem) {
    const target = row || activeDomain.value
    if (!target) {
      return
    }

    if (!hasDomainAuditAuth.value) {
      ElMessage.warning('你没有审核域名的权限')
      return
    }

    if (target.is_deleted) {
      ElMessage.warning('请先恢复该域名后再通过')
      return
    }

    if (!canApproveDomain(target)) {
      ElMessage.warning('该域名已处于通过状态')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认通过 ${target.sitename || target.siteurl || `域名 #${target.id}`}，并清空已保存的驳回原因吗？`,
        '通过域名',
        {
          confirmButtonText: '确认通过',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchApproveDomain(target.id)
      syncActiveDomain(response.item)
      clearDomainSelection()
      await getDomainList()

      ElMessage.success(
        `域名 ${response.approved_domain_label || target.sitename || target.siteurl || `#${target.id}`} 已通过。`
      )
      showApprovalNotificationFeedback(response.notification)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleRejectDomain(row?: DomainItem) {
    const target = row || activeDomain.value
    if (!target) {
      return
    }

    if (!hasDomainAuditAuth.value) {
      ElMessage.warning('你没有审核域名的权限')
      return
    }

    if (target.is_deleted) {
      ElMessage.warning('请先恢复该域名后再驳回')
      return
    }

    if (!canRejectDomain(target)) {
      ElMessage.warning('该域名已处于驳回状态')
      return
    }

    try {
      const { value } = await ElMessageBox.prompt(
        `请输入 ${target.sitename || target.siteurl || `域名 #${target.id}`} 的驳回原因。`,
        '驳回域名',
        {
          confirmButtonText: '确认驳回',
          cancelButtonText: '取消',
          type: 'warning',
          inputPlaceholder: '请输入驳回原因',
          inputValidator: (value) =>
            String(value || '').trim() !== '' ? true : '请输入驳回原因'
        }
      )

      const response = await fetchRejectDomain(target.id, {
        reason: String(value || '').trim()
      })

      syncActiveDomain(response.item)
      clearDomainSelection()
      await getDomainList()

      ElMessage.success(
        `域名 ${response.rejected_domain_label || target.sitename || target.siteurl || `#${target.id}`} 已驳回。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleDeleteDomain(row?: DomainItem) {
    const target = row || activeDomain.value
    if (!target) {
      return
    }

    if (!hasDomainDeleteAuth.value) {
      ElMessage.warning('你没有删除域名的权限')
      return
    }

    if (target.is_deleted) {
      ElMessage.warning('该域名已在回收站中')
      return
    }

    try {
      const response = await fetchGetDomainDeleteAudit(target.id)
      const audit = response.audit
      const title = target.sitename || target.siteurl || `域名 #${target.id}`

      if (!audit.can_delete) {
        await ElMessageBox.alert(buildDomainDeleteBlockedMessage(audit, title), '删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildDomainDeletePromptMessage(audit, title),
        '删除域名',
        {
          confirmButtonText: '确认删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchDeleteDomain(target.id, {
        confirmation_phrase: String(value || '')
      })

      if (activeDomain.value?.id === target.id) {
        detailVisible.value = false
        activeDomain.value = null
      }

      clearDomainSelection()
      await getDomainList()
      ElMessage.success(
        `域名 ${deleteResponse.deleted_domain_label || title} 已移入回收站，影响 ${deleteResponse.audit.summary.delete_row_count} 条记录。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleRestoreDomain(row?: DomainItem) {
    const target = row || activeDomain.value
    if (!target) {
      return
    }

    if (!hasDomainRecycleAuth.value) {
      ElMessage.warning('你没有恢复域名的权限')
      return
    }

    if (!target.is_deleted) {
      ElMessage.warning('该域名当前已是正常状态')
      return
    }

    try {
      await ElMessageBox.confirm(
        `确认将 ${target.sitename || target.siteurl || `域名 #${target.id}`} 恢复到正常域名列表吗？`,
        '恢复域名',
        {
          confirmButtonText: '确认恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchRestoreDomain(target.id)
      syncActiveDomain(response.item)
      clearDomainSelection()
      await getDomainList()

      if (isRecycleView.value && activeDomain.value?.id === target.id) {
        detailVisible.value = false
        activeDomain.value = null
      }

      ElMessage.success(`域名 ${response.restored_domain_label || target.sitename || target.siteurl} 已恢复。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchDeleteDomains() {
    if (!hasDomainBatchDeleteAuth.value) {
      ElMessage.warning('你没有批量删除域名的权限')
      return
    }

    const activeSelection = selectedDomains.value.filter((item) => !item.is_deleted)
    if (activeSelection.length === 0) {
      ElMessage.warning('请至少选择一个正常域名')
      return
    }

    const domainIds = activeSelection.map((item) => item.id)

    try {
      const response = await fetchAuditDomainBatchDelete({
        domain_ids: domainIds
      })
      const audit = response.audit

      if (!audit.can_delete_all) {
        await ElMessageBox.alert(buildDomainBatchDeleteBlockedMessage(audit), '批量删除受限', {
          type: 'warning',
          confirmButtonText: '知道了'
        })
        return
      }

      const { value } = await ElMessageBox.prompt(
        buildDomainBatchDeletePromptMessage(audit),
        '批量删除域名',
        {
          confirmButtonText: '确认批量删除',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: audit.confirmation_phrase,
          inputPattern: new RegExp(`^${escapeRegExp(audit.confirmation_phrase)}$`),
          inputErrorMessage: `请输入 ${audit.confirmation_phrase} 后继续。`
        }
      )

      const deleteResponse = await fetchBatchDeleteDomains({
        domain_ids: domainIds,
        confirmation_phrase: String(value || '')
      })

      if (activeDomain.value && deleteResponse.deleted_domain_ids.includes(activeDomain.value.id)) {
        detailVisible.value = false
        activeDomain.value = null
      }

      clearDomainSelection()
      await getDomainList()
      ElMessage.success(
        `已将 ${deleteResponse.deleted_count} 条域名记录移入回收站，影响 ${deleteResponse.audit.summary.delete_row_count} 条记录。`
      )
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  async function handleBatchRestoreDomains() {
    if (!hasDomainRecycleAuth.value) {
      ElMessage.warning('你没有批量恢复域名的权限')
      return
    }

    const recycleSelection = selectedDomains.value.filter((item) => item.is_deleted)
    if (recycleSelection.length === 0) {
      ElMessage.warning('请至少选择一个回收站域名')
      return
    }

    const domainIds = recycleSelection.map((item) => item.id)

    try {
      await ElMessageBox.confirm(
        `确认将 ${domainIds.length} 条域名记录恢复到正常列表吗？`,
        '批量恢复域名',
        {
          confirmButtonText: '确认批量恢复',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )

      const response = await fetchBatchRestoreDomains({
        domain_ids: domainIds
      })

      clearDomainSelection()
      await getDomainList()

      if (activeDomain.value && response.restored_domain_ids.includes(activeDomain.value.id)) {
        detailVisible.value = false
        activeDomain.value = null
      }

      ElMessage.success(`已恢复 ${response.restored_count} 条域名记录。`)
    } catch (error) {
      if (isDialogCancel(error)) {
        return
      }

      throw error
    }
  }

  function buildWritePayload(form: { user_id: string; sitename: string; siteurl: string }) {
    form.user_id = form.user_id.trim()
    form.sitename = form.sitename.trim()
    form.siteurl = form.siteurl.trim()

    if (!form.user_id) {
      ElMessage.warning('请输入商户编号')
      return null
    }

    if (!/^\d+$/.test(form.user_id)) {
      ElMessage.warning('商户编号必须为正整数')
      return null
    }

    if (!form.sitename) {
      ElMessage.warning('请输入网站名称')
      return null
    }

    if (!form.siteurl) {
      ElMessage.warning('请输入域名地址')
      return null
    }

    return {
      user_id: form.user_id,
      sitename: form.sitename,
      siteurl: form.siteurl
    }
  }

  function resetWriteForm(form: { user_id: string; sitename: string; siteurl: string }) {
    form.user_id = ''
    form.sitename = ''
    form.siteurl = ''
  }

  function syncWriteForm(form: { user_id: string; sitename: string; siteurl: string }, item: DomainItem) {
    form.user_id = String(item.user_id || '')
    form.sitename = item.sitename || ''
    form.siteurl = item.siteurl || ''
  }

  function showApprovalNotificationFeedback(notification: Api.Domains.DomainApprovalNotification) {
    if (!notification || notification.status === 'sent') {
      return
    }

    const message =
      notification.message || '域名通过后，通知邮件发送失败。'

    if (notification.status === 'failed') {
      ElMessage.warning(`域名已通过，但通知邮件发送失败：${message}`)
      return
    }

    if (notification.status === 'skipped') {
      ElMessage.info(`域名已通过，本次跳过通知邮件发送：${message}`)
    }
  }

  function syncActiveDomain(item: DomainItem) {
    if (activeDomain.value?.id === item.id) {
      activeDomain.value = item
    }
  }

  function buildDomainDeleteBlockedMessage(audit: Api.Domains.DomainDeleteAudit, title: string) {
    return [
      `${title} 当前无法移入回收站。`,
      ...audit.blocking_reasons,
      ...audit.warnings
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildDomainDeletePromptMessage(audit: Api.Domains.DomainDeleteAudit, title: string) {
    return [
      `确认将 ${title} 移入回收站吗？`,
      audit.merchant_display ? `商户：${audit.merchant_display}` : null,
      audit.siteurl ? `域名地址：${audit.siteurl}` : null,
      ...audit.warnings,
      `请输入 ${audit.confirmation_phrase} 继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildDomainBatchDeleteBlockedMessage(audit: Api.Domains.DomainBatchDeleteAudit) {
    return [
      '当前所选域名暂时无法移入回收站。',
      ...audit.warnings,
      ...audit.items.flatMap((item) =>
        item.can_delete || item.blocking_reasons.length === 0
          ? []
          : [`#${item.domain_id || '--'} ${item.domain_label || item.siteurl || '未知域名'}：${item.blocking_reasons.join('；')}`]
      )
    ]
      .filter(Boolean)
      .join('\n')
  }

  function buildDomainBatchDeletePromptMessage(audit: Api.Domains.DomainBatchDeleteAudit) {
    return [
      `确认将已选的 ${audit.summary.deletable_count} 条域名记录移入回收站吗？`,
      `现存记录：${audit.summary.existing_count}`,
      `影响行数：${audit.summary.delete_row_count}`,
      ...audit.warnings,
      `请输入 ${audit.confirmation_phrase} 继续。`
    ]
      .filter(Boolean)
      .join('\n')
  }

  function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  function clearDomainSelection() {
    selectedDomains.value = []
    tableRef.value?.elTableRef?.clearSelection?.()
  }

  function emptySummary(): DomainSummary {
    return {
      pending_count: 0,
      approved_count: 0,
      rejected_count: 0,
      deleted_count: 0
    }
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

  function isDialogCancel(error: unknown) {
    return error === 'cancel' || error === 'close'
  }
</script>

<style scoped lang="scss">
  .domain-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .domain-cell,
  .merchant-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .cell-title {
    color: var(--el-text-color-primary);
    font-size: 14px;
    word-break: break-all;
  }

  .cell-sub,
  .cell-link {
    margin: 0;
    color: var(--el-text-color-secondary);
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

  .domain-detail {
    min-height: 240px;
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 0 20px;
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 22px;
    word-break: break-all;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.6;
  }

  .detail-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
  }

  .domain-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  .drawer-section {
    margin-bottom: 24px;
  }

  .drawer-section h4 {
    margin: 0 0 12px;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .domain-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
