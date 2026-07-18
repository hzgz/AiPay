<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>工单中心</h1>
        <p>集中提交工单、查看处理进度和管理工单记录，保持商户与平台沟通链路清晰可追踪。</p>
      </div>

      <div v-if="categories.length || pagination.total" class="merchant-chip-row">
        <span class="merchant-chip">工单分类 {{ categories.length }} 个</span>
        <span class="merchant-chip">最近提交 {{ summary.last_ticket_time || '--' }}</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <div v-else-if="featureMessage" class="merchant-panel merchant-state-card">
      <h3>功能未开启</h3>
      <p>{{ featureMessage }}</p>
    </div>

    <template v-else>
      <section class="merchant-stat-grid">
        <article
          v-for="card in summaryCards"
          :key="card.label"
          class="merchant-card merchant-stat-card"
        >
          <div class="merchant-stat-card__row">
            <div class="merchant-stat-card__copy">
              <div class="merchant-stat-card__label">{{ card.label }}</div>
              <div class="merchant-stat-card__value">{{ card.value }}</div>
              <div class="merchant-stat-card__hint">{{ card.hint }}</div>
            </div>

            <div class="merchant-stat-card__symbol">
              <Icon :icon="card.icon" />
            </div>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>工单列表</h2>
            <p>支持按标题、内容、分类和状态筛选，工单创建与删除能力已在当前商户后台开放。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>状态</span>
              <strong>{{ currentStatusLabel }}</strong>
            </div>
            <div class="merchant-toolbar-pill">
              <span>分类</span>
              <strong>{{ currentCategoryLabel }}</strong>
            </div>
            <div v-if="filters.keyword" class="merchant-toolbar-pill">
              <span>关键词</span>
              <strong>{{ filters.keyword }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--tickets">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索工单标题或问题内容"
              clearable
              @keyup.enter="loadTickets(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElSelect
              v-model="filters.status"
              clearable
              placeholder="工单状态"
              style="width: 160px"
            >
              <ElOption label="新建" value="0" />
              <ElOption label="处理中" value="1" />
              <ElOption label="已解决" value="2" />
              <ElOption label="已关闭" value="3" />
            </ElSelect>

            <ElSelect v-model="filters.type" clearable placeholder="工单分类" style="width: 180px">
              <ElOption
                v-for="category in categories"
                :key="category.id"
                :label="translateMerchantText(category.name)"
                :value="String(category.id)"
              />
            </ElSelect>

            <ElButton type="primary" @click="loadTickets(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
            <ElButton type="primary" plain @click="openCreateDialog">新建工单</ElButton>
          </div>

        </div>

        <ElTable :data="records" empty-text="暂无工单记录">
          <ElTableColumn
            prop="ticket_label"
            label="工单标题"
            min-width="220"
            show-overflow-tooltip
          />
          <ElTableColumn prop="type_name" label="分类" min-width="150">
            <template #default="{ row }">
              {{ translateMerchantText(row.type_name) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="status_label" label="状态" width="120">
            <template #default="{ row }">
              <ElTag :type="row.status_type" effect="plain">
                {{ translateMerchantText(row.status_label) }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn
            prop="content_preview"
            label="问题内容"
            min-width="220"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              {{ translateMerchantText(row.content_preview) }}
            </template>
          </ElTableColumn>
          <ElTableColumn
            prop="reply_preview"
            label="回复摘要"
            min-width="220"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              {{ translateMerchantText(row.reply_preview) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="create_time" label="创建时间" min-width="180" />
          <ElTableColumn label="操作" width="100" fixed="right">
            <template #default="{ row }">
              <ElButton text type="danger" @click="removeTicket(row)">删除</ElButton>
            </template>
          </ElTableColumn>
        </ElTable>

        <div class="merchant-pagination">
          <ElPagination
            background
            layout="prev, pager, next"
            :current-page="pagination.current"
            :page-size="pagination.size"
            :total="pagination.total"
            @current-change="handlePageChange"
          />
        </div>
      </article>

      <ElDialog v-model="dialogVisible" title="新建工单" width="520px">
        <ElForm ref="formRef" :model="formData" :rules="rules" label-position="top">
          <ElFormItem label="工单标题" prop="title">
            <ElInput
              v-model.trim="formData.title"
              maxlength="255"
              placeholder="问题标题"
            />
          </ElFormItem>
          <ElFormItem label="工单分类" prop="type">
            <ElSelect v-model="formData.type" placeholder="请选择工单分类" class="w-full">
              <ElOption
                v-for="category in categories"
                :key="category.id"
                :label="translateMerchantText(category.name)"
                :value="category.id"
              />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="问题描述" prop="content">
            <ElInput
              v-model.trim="formData.content"
              type="textarea"
              :rows="5"
              placeholder="描述问题"
            />
          </ElFormItem>
        </ElForm>

        <template #footer>
          <ElButton @click="dialogVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="submitting" @click="submitTicket">提交工单</ElButton>
        </template>
      </ElDialog>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    MerchantApiError,
    createMerchantTicket,
    deleteMerchantTicket,
    fetchMerchantTickets,
    isMerchantFeatureDisabled
  } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantTickets' })

  const loading = ref(true)
  const featureMessage = ref('')
  const records = ref<Record<string, any>[]>([])
  const categories = ref<Record<string, any>[]>([])
  const summary = ref<Record<string, any>>({})
  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })
  const filters = reactive({
    keyword: '',
    status: '',
    type: ''
  })

  const dialogVisible = ref(false)
  const submitting = ref(false)
  const formRef = ref<FormInstance>()
  const formData = reactive({
    title: '',
    type: undefined as number | undefined,
    content: ''
  })

  const summaryCards = computed(() => [
    {
      label: '新建工单',
      value: String(summary.value.new_count ?? 0),
      hint: '等待平台处理的新建工单数量',
      icon: 'ri:mail-open-line'
    },
    {
      label: '处理中',
      value: String(summary.value.processing_count ?? 0),
      hint: '管理员正在跟进的工单数量',
      icon: 'ri:loader-4-line'
    },
    {
      label: '已解决',
      value: String(summary.value.resolved_count ?? 0),
      hint: '已经处理完成的工单数量',
      icon: 'ri:checkbox-circle-line'
    },
    {
      label: '最近工单',
      value: summary.value.last_ticket_time || '--',
      hint: '最近一次提交工单的时间',
      icon: 'ri:time-line'
    }
  ])

  const hasActiveFilters = computed(
    () => filters.keyword !== '' || filters.status !== '' || filters.type !== ''
  )

  const currentStatusLabel = computed(() => {
    const mapping: Record<string, string> = {
      '0': '新建',
      '1': '处理中',
      '2': '已解决',
      '3': '已关闭'
    }

    return mapping[filters.status] || '全部状态'
  })

  const currentCategoryLabel = computed(() => {
    const current = categories.value.find((item) => String(item.id) === filters.type)
    return current ? translateMerchantText(current.name) : '全部分类'
  })

  const rules: FormRules = {
    title: [{ required: true, message: '请输入工单标题', trigger: 'blur' }],
    type: [{ required: true, message: '请选择工单分类', trigger: 'change' }],
    content: [{ required: true, message: '请输入问题描述', trigger: 'blur' }]
  }

  async function loadTickets(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    featureMessage.value = ''
    try {
      const result = await fetchMerchantTickets({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword,
        status: filters.status,
        type: filters.type
      })

      records.value = result.records
      categories.value = result.categories
      summary.value = result.summary
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
    } catch (error) {
      if (isMerchantFeatureDisabled(error)) {
        featureMessage.value = translateMerchantText(
          error instanceof MerchantApiError ? error.message : 'merchant ticket feature is disabled'
        )
      } else {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '工单数据加载失败'
        ElMessage.error(message)
      }
    } finally {
      loading.value = false
    }
  }

  function resetFilters() {
    filters.keyword = ''
    filters.status = ''
    filters.type = ''
    loadTickets(true)
  }

  function openCreateDialog() {
    formData.title = ''
    formData.type = undefined
    formData.content = ''
    dialogVisible.value = true
  }

  async function submitTicket() {
    if (!formRef.value) {
      return
    }

    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) {
      return
    }

    submitting.value = true
    try {
      await createMerchantTicket({
        title: formData.title,
        type: formData.type,
        content: formData.content
      })
      ElMessage.success('工单创建成功')
      dialogVisible.value = false
      await loadTickets(true)
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '工单创建失败'
      ElMessage.error(message)
    } finally {
      submitting.value = false
    }
  }

  async function removeTicket(row: Record<string, any>) {
    try {
      await ElMessageBox.confirm(`确定删除工单“${row.ticket_label || row.id}”吗？`, '删除确认', {
        type: 'warning'
      })
      await deleteMerchantTicket(Number(row.id))
      ElMessage.success('工单删除成功')
      await loadTickets()
    } catch (error) {
      if (error !== 'cancel' && error !== 'close') {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '工单删除失败'
        ElMessage.error(message)
      }
    }
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadTickets()
  }

  onMounted(() => {
    loadTickets()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-ticket-note,
  .merchant-ticket-dialog-note {
    margin-bottom: 16px;
  }

  .merchant-table-toolbar__filters--tickets {
    flex: 1;
  }

  .merchant-table-toolbar__filters--tickets :deep(.el-input) {
    width: 320px;
    max-width: 100%;
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--tickets > * {
      width: 100% !important;
    }
  }
</style>
