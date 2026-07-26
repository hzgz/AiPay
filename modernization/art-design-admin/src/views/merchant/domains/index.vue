<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>域名管理</h1>
        <p>管理当前商户站点域名，便于审核与上线。</p>
      </div>

      <div v-if="pagination.total" class="merchant-chip-row">
        <span class="merchant-chip">待审核 {{ summary.pending_count ?? 0 }}</span>
        <span class="merchant-chip">已通过 {{ summary.approved_count ?? 0 }}</span>
        <span class="merchant-chip">最近提交 {{ summary.last_domain_time || '--' }}</span>
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
            <h2>域名列表</h2>
            <p>新增、编辑、删除都在这里处理，处理说明会直接显示。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>状态</span>
              <strong>{{ currentStatusLabel }}</strong>
            </div>
            <div v-if="filters.keyword" class="merchant-toolbar-pill">
              <span>关键词</span>
              <strong>{{ filters.keyword }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-table-toolbar">
          <div class="merchant-table-toolbar__filters merchant-table-toolbar__filters--domains">
            <ElInput
              v-model.trim="filters.keyword"
              placeholder="搜索站点名称或域名"
              clearable
              @keyup.enter="loadDomains(true)"
            >
              <template #prefix>
                <Icon icon="ri:search-line" />
              </template>
            </ElInput>

            <ElSelect
              v-model="filters.status"
              clearable
              placeholder="审核状态"
              style="width: 160px"
            >
              <ElOption label="待审核" value="0" />
              <ElOption label="已通过" value="1" />
              <ElOption label="已驳回" value="2" />
            </ElSelect>

            <ElButton type="primary" @click="loadDomains(true)">查询</ElButton>
            <ElButton plain :disabled="!hasActiveFilters" @click="resetFilters">重置</ElButton>
            <ElButton type="primary" plain @click="openCreateDialog">新增域名</ElButton>
          </div>
        </div>

        <ElTable :data="records" empty-text="暂无域名记录">
          <ElTableColumn prop="sitename" label="站点名称" min-width="180" />
          <ElTableColumn label="域名" min-width="240">
            <template #default="{ row }">
              <a :href="row.siteurl_link || '#'" class="merchant-link" target="_blank">
                {{ row.siteurl_preview || '--' }}
              </a>
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
            prop="reason_preview"
            label="处理说明"
            min-width="220"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              {{ translateMerchantText(row.reason_preview) }}
            </template>
          </ElTableColumn>
          <ElTableColumn prop="create_time" label="创建时间" min-width="180" />
          <ElTableColumn label="操作" width="140" fixed="right">
            <template #default="{ row }">
              <ElButton text type="primary" @click="openEditDialog(row)">编辑</ElButton>
              <ElButton text type="danger" @click="removeDomain(row)">删除</ElButton>
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

      <ElDialog v-model="dialogVisible" :title="editingId ? '编辑域名' : '新增域名'" width="520px">
        <ElForm ref="formRef" :model="formData" :rules="rules" label-position="top">
          <ElFormItem label="站点名称" prop="sitename">
            <ElInput v-model.trim="formData.sitename" maxlength="255" placeholder="站点名称" />
          </ElFormItem>
          <ElFormItem label="站点域名" prop="siteurl">
            <ElInput v-model.trim="formData.siteurl" placeholder="pay.你的域名.com" />
          </ElFormItem>
        </ElForm>

        <template #footer>
          <ElButton @click="dialogVisible = false">取消</ElButton>
          <ElButton type="primary" :loading="submitting" @click="submitDomain">保存域名</ElButton>
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
    createMerchantDomain,
    deleteMerchantDomain,
    fetchMerchantDomains,
    isMerchantFeatureDisabled,
    updateMerchantDomain
  } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantDomains' })

  const loading = ref(true)
  const featureMessage = ref('')
  const records = ref<Record<string, any>[]>([])
  const summary = ref<Record<string, any>>({})
  const pagination = reactive({
    current: 1,
    size: 10,
    total: 0
  })
  const filters = reactive({
    keyword: '',
    status: ''
  })

  const dialogVisible = ref(false)
  const submitting = ref(false)
  const editingId = ref<number | null>(null)
  const formRef = ref<FormInstance>()
  const formData = reactive({
    sitename: '',
    siteurl: ''
  })

  const summaryCards = computed(() => [
    {
      label: '待审核',
      value: String(summary.value.pending_count ?? 0),
      hint: '待平台审核',
      icon: 'ri:hourglass-line'
    },
    {
      label: '已通过',
      value: String(summary.value.approved_count ?? 0),
      hint: '可继续使用',
      icon: 'ri:checkbox-circle-line'
    },
    {
      label: '已驳回',
      value: String(summary.value.rejected_count ?? 0),
      hint: '按说明调整后再提交',
      icon: 'ri:close-circle-line'
    },
    {
      label: '最近提交',
      value: summary.value.last_domain_time || '--',
      hint: '最近一次提交时间',
      icon: 'ri:time-line'
    }
  ])

  const hasActiveFilters = computed(() => filters.keyword !== '' || filters.status !== '')

  const currentStatusLabel = computed(() => {
    const mapping: Record<string, string> = {
      '0': '待审核',
      '1': '已通过',
      '2': '已驳回'
    }

    return mapping[filters.status] || '全部状态'
  })

  const rules: FormRules = {
    sitename: [{ required: true, message: '请输入站点名称', trigger: 'blur' }],
    siteurl: [{ required: true, message: '请输入站点域名', trigger: 'blur' }]
  }

  async function loadDomains(resetPage = false) {
    if (resetPage) {
      pagination.current = 1
    }

    loading.value = true
    featureMessage.value = ''
    try {
      const result = await fetchMerchantDomains({
        current: pagination.current,
        size: pagination.size,
        keyword: filters.keyword,
        status: filters.status
      })

      records.value = result.records
      summary.value = result.summary
      pagination.current = result.pagination.current
      pagination.size = result.pagination.size
      pagination.total = result.pagination.total
    } catch (error) {
      if (isMerchantFeatureDisabled(error)) {
        featureMessage.value = translateMerchantText(
          error instanceof MerchantApiError ? error.message : 'merchant domain feature is disabled'
        )
      } else {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '域名数据加载失败'
        ElMessage.error(message)
      }
    } finally {
      loading.value = false
    }
  }

  function resetFilters() {
    filters.keyword = ''
    filters.status = ''
    loadDomains(true)
  }

  function openCreateDialog() {
    editingId.value = null
    formData.sitename = ''
    formData.siteurl = ''
    dialogVisible.value = true
  }

  function openEditDialog(row: Record<string, any>) {
    editingId.value = Number(row.id)
    formData.sitename = row.sitename || ''
    formData.siteurl = row.siteurl || ''
    dialogVisible.value = true
  }

  async function submitDomain() {
    if (!formRef.value) {
      return
    }

    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) {
      return
    }

    submitting.value = true
    try {
      if (editingId.value) {
        await updateMerchantDomain({
          id: editingId.value,
          sitename: formData.sitename,
          siteurl: formData.siteurl
        })
        ElMessage.success('域名已更新')
      } else {
        await createMerchantDomain({
          sitename: formData.sitename,
          siteurl: formData.siteurl
        })
        ElMessage.success('域名已提交')
      }

      dialogVisible.value = false
      await loadDomains()
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '域名保存失败'
      ElMessage.error(message)
    } finally {
      submitting.value = false
    }
  }

  async function removeDomain(row: Record<string, any>) {
    try {
      await ElMessageBox.confirm(
        `确定删除域名“${row.sitename || row.siteurl_preview}”吗？`,
        '删除确认',
        {
          type: 'warning'
        }
      )
      await deleteMerchantDomain(Number(row.id))
      ElMessage.success('域名已删除')
      await loadDomains()
    } catch (error) {
      if (error !== 'cancel' && error !== 'close') {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '域名删除失败'
        ElMessage.error(message)
      }
    }
  }

  function handlePageChange(page: number) {
    pagination.current = page
    loadDomains()
  }

  onMounted(() => {
    loadDomains()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-domain-note,
  .merchant-domain-dialog-note {
    margin-bottom: 16px;
  }

  .merchant-table-toolbar__filters--domains {
    flex: 1;
  }

  .merchant-table-toolbar__filters--domains :deep(.el-input) {
    width: 320px;
    max-width: 100%;
  }

  @media (width <= 768px) {
    .merchant-table-toolbar__filters--domains > * {
      width: 100% !important;
    }
  }
</style>
