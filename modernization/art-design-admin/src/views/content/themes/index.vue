<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="themes-page art-full-height">
    <section class="themes-toolbar">
      <ElTabs v-model="activeScope" class="themes-tabs" @tab-change="loadThemes">
        <ElTabPane label="全部模板" name="all" />
        <ElTabPane
          v-for="scope in scopeOptions"
          :key="scope.value"
          :label="`${scope.label} ${scope.count}`"
          :name="scope.value"
        />
      </ElTabs>

      <div class="themes-filters">
        <ElInput
          v-model="keyword"
          clearable
          class="themes-filter themes-filter--keyword"
          placeholder="搜索模板名称、说明或目录标识"
          @keyup.enter="loadThemes"
          @clear="loadThemes"
        />
        <ElSelect v-model="status" class="themes-filter" @change="loadThemes">
          <ElOption label="全部状态" value="" />
          <ElOption label="当前启用" value="active" />
          <ElOption label="未启用" value="inactive" />
          <ElOption label="配置缺失" value="config-missing" />
        </ElSelect>
        <ElButton :loading="loading" @click="loadThemes">刷新</ElButton>
      </div>

      <div class="themes-summary">
        <ElTag effect="plain">模板总数 {{ summary.total_count }}</ElTag>
        <ElTag type="success" effect="plain">当前启用 {{ summary.active_count }}</ElTag>
        <ElTag type="warning" effect="plain">元数据完整 {{ summary.metadata_ready_count }}</ElTag>
        <ElTag type="info" effect="plain">预览就绪 {{ summary.screenshot_count }}</ElTag>
      </div>
    </section>

    <section v-loading="loading" class="themes-grid">
      <article v-for="theme in themeList" :key="themeKey(theme)" class="theme-card">
        <div class="theme-card__preview">
          <img
            v-if="theme.screenshot_path"
            :src="theme.screenshot_path"
            :alt="theme.title_label"
            class="theme-card__image"
          />
          <div v-else class="theme-card__placeholder">
            <span>{{ theme.scope_label }}</span>
            <strong>{{ theme.title_label }}</strong>
          </div>

          <div class="theme-card__badges">
            <ElTag size="small" effect="light">{{ theme.scope_label }}</ElTag>
            <ElTag
              size="small"
              :type="theme.is_active ? 'success' : theme.has_style ? 'info' : 'warning'"
              effect="light"
            >
              {{ theme.status_label }}
            </ElTag>
          </div>
        </div>

        <div class="theme-card__body">
          <div class="theme-card__head">
            <h3>{{ theme.title_label }}</h3>
            <span class="theme-card__id">{{ theme.id }}</span>
          </div>

          <p class="theme-card__description">{{ theme.description_preview }}</p>

          <div class="theme-card__meta">
            <span class="theme-card__meta-item">
              <span>版本</span>
              <strong>{{ theme.version_label }}</strong>
            </span>
            <span class="theme-card__meta-divider" aria-hidden="true">/</span>
            <span class="theme-card__meta-item">
              <span>配置</span>
              <strong>{{ theme.config_state_label }}</strong>
            </span>
          </div>

          <div class="theme-card__actions">
            <div class="theme-card__links">
              <ElButton text @click="openDetail(theme)">查看</ElButton>
              <ElButton
                v-if="theme.delete_supported && hasAuth('remove')"
                text
                type="danger"
                :loading="deletingKey === themeKey(theme)"
                @click="handleDelete(theme)"
              >
                删除
              </ElButton>
            </div>
            <ElTag v-if="theme.is_active" size="small" type="success" effect="light" round>
              当前使用
            </ElTag>
            <ElButton
              v-else
              type="primary"
              :disabled="!theme.activate_supported || !hasAuth('edit')"
              :loading="activatingKey === themeKey(theme)"
              @click="handleActivate(theme)"
            >
              启用
            </ElButton>
          </div>
        </div>
      </article>

      <ElEmpty v-if="!loading && themeList.length === 0" description="当前没有匹配的模板" />
    </section>

    <ElDrawer
      v-model="detailVisible"
      size="820px"
      destroy-on-close
      :title="detailTheme ? `${detailTheme.title_label} / ${detailTheme.scope_label}` : '模板详情'"
    >
      <div v-loading="detailLoading" class="theme-detail">
        <template v-if="detailTheme">
          <section class="theme-detail__hero">
            <div class="theme-detail__preview">
              <img
                v-if="detailTheme.screenshot_path"
                :src="detailTheme.screenshot_path"
                :alt="detailTheme.title_label"
              />
              <div v-else class="theme-card__placeholder">
                <span>{{ detailTheme.scope_label }}</span>
                <strong>{{ detailTheme.title_label }}</strong>
              </div>
            </div>

            <div class="theme-detail__copy">
              <ElSpace wrap>
                <ElTag effect="plain">{{ detailTheme.scope_label }}</ElTag>
                <ElTag :type="detailTheme.is_active ? 'success' : 'info'" effect="light">
                  {{ detailTheme.status_label }}
                </ElTag>
                <ElTag
                  :type="detailTheme.metadata_type === 'warning' ? 'warning' : 'success'"
                  effect="light"
                >
                  {{ detailTheme.metadata_label }}
                </ElTag>
              </ElSpace>

              <h3>{{ detailTheme.title_label }}</h3>
              <p>{{ detailTheme.description || '当前模板未填写说明。' }}</p>

              <div class="theme-card__actions">
                <ElButton
                  type="primary"
                  :disabled="
                    detailTheme.is_active || !detailTheme.activate_supported || !hasAuth('edit')
                  "
                  :loading="activatingKey === themeKey(detailTheme)"
                  @click="handleActivate(detailTheme)"
                >
                  {{ detailTheme.is_active ? '当前使用中' : '启用模板' }}
                </ElButton>
                <ElButton
                  v-if="detailTheme.delete_supported && hasAuth('remove')"
                  plain
                  type="danger"
                  :loading="deletingKey === themeKey(detailTheme)"
                  @click="handleDelete(detailTheme)"
                >
                  删除模板
                </ElButton>
              </div>
            </div>
          </section>

          <ElDescriptions :column="2" border class="theme-detail__descriptions">
            <ElDescriptionsItem label="模板标识">{{ detailTheme.id }}</ElDescriptionsItem>
            <ElDescriptionsItem label="模板版本">{{ detailTheme.version_label }}</ElDescriptionsItem>
            <ElDescriptionsItem label="模板目录">{{ detailTheme.relative_path }}</ElDescriptionsItem>
            <ElDescriptionsItem label="配置键">{{ detailTheme.config_key || '--' }}</ElDescriptionsItem>
            <ElDescriptionsItem label="当前配置值">
              {{ detailTheme.configured_value || '--' }}
            </ElDescriptionsItem>
            <ElDescriptionsItem label="实际生效值">
              {{ detailTheme.effective_value || '--' }}
            </ElDescriptionsItem>
          </ElDescriptions>

          <div class="theme-detail__note">
            <h4>维护说明</h4>
            <p>{{ detailTheme.readonly_note }}</p>
          </div>
        </template>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { fetchActivateTheme, fetchDeleteTheme, fetchGetThemeDeleteAudit, fetchGetThemeDetail, fetchGetThemeList } from '@/api/themes'
  import { useAuth } from '@/hooks/core/useAuth'

  defineOptions({ name: 'ContentThemes' })

  type ThemeItem = Api.Themes.ThemeListItem

  const { hasAuth } = useAuth()

  const loading = ref(false)
  const detailLoading = ref(false)
  const detailVisible = ref(false)
  const detailTheme = ref<ThemeItem | null>(null)
  const themeList = ref<ThemeItem[]>([])
  const scopeOptions = ref<Api.Themes.ThemeScopeOption[]>([])
  const activeScope = ref('all')
  const keyword = ref('')
  const status = ref('')
  const activatingKey = ref('')
  const deletingKey = ref('')
  const summary = ref<Api.Themes.ThemeSummary>({
    total_count: 0,
    scope_count: 0,
    active_count: 0,
    screenshot_count: 0,
    metadata_ready_count: 0,
    config_missing_count: 0,
    style_missing_count: 0,
    generated_at: ''
  })

  function themeKey(theme: Pick<ThemeItem, 'scope' | 'id'>) {
    return `${theme.scope}:${theme.id}`
  }

  async function loadThemes() {
    loading.value = true

    try {
      const response = await fetchGetThemeList({
        current: 1,
        size: 60,
        keyword: keyword.value.trim() || undefined,
        scope: activeScope.value === 'all' ? undefined : activeScope.value,
        status: status.value || undefined
      })

      themeList.value = response.records || []
      scopeOptions.value = response.scope_options || []
      summary.value = response.summary || summary.value
    } catch {
      ElMessage.error('模板列表加载失败，请稍后重试。')
    } finally {
      loading.value = false
    }
  }

  async function openDetail(theme: ThemeItem) {
    detailVisible.value = true
    detailLoading.value = true

    try {
      const response = await fetchGetThemeDetail(theme.scope, theme.id)
      detailTheme.value = response.item
    } catch {
      detailTheme.value = theme
      ElMessage.error('模板详情加载失败。')
    } finally {
      detailLoading.value = false
    }
  }

  async function handleActivate(theme: ThemeItem) {
    activatingKey.value = themeKey(theme)

    try {
      const response = await fetchActivateTheme(theme.scope, theme.id)
      ElMessage.success(`已启用${response.activated_scope_label}模板：${response.activated_theme_label}`)

      if (detailTheme.value && themeKey(detailTheme.value) === themeKey(theme)) {
        detailTheme.value = response.item
      }

      await loadThemes()
    } catch {
      ElMessage.error('模板启用失败，请稍后重试。')
    } finally {
      activatingKey.value = ''
    }
  }

  async function handleDelete(theme: ThemeItem) {
    deletingKey.value = themeKey(theme)

    try {
      const response = await fetchGetThemeDeleteAudit(theme.scope, theme.id)
      const audit = response.audit

      if (!audit.can_delete) {
        const reasons = audit.blocking_reasons.length
          ? audit.blocking_reasons.join('\n')
          : '当前模板暂不支持删除。'
        await ElMessageBox.alert(reasons, '当前不可删除', {
          confirmButtonText: '我知道了',
          type: 'warning'
        })
        return
      }

      const warningLines = audit.warnings.length ? `\n\n注意事项：\n${audit.warnings.join('\n')}` : ''
      const promptText =
        `请输入确认短语：${audit.confirmation_phrase}\n` +
        `目录：${audit.directory.relative_path}\n` +
        `文件数：${audit.summary.file_count}${warningLines}`

      const { value } = await ElMessageBox.prompt(promptText, '删除模板', {
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
        inputPlaceholder: audit.confirmation_phrase,
        inputValidator: (inputValue) =>
          inputValue.trim() === audit.confirmation_phrase || '确认短语不正确'
      })

      await fetchDeleteTheme(theme.scope, theme.id, {
        confirmation_phrase: value.trim()
      })

      if (detailTheme.value && themeKey(detailTheme.value) === themeKey(theme)) {
        detailVisible.value = false
        detailTheme.value = null
      }

      ElMessage.success(`模板 ${theme.title_label} 已删除。`)
      await loadThemes()
    } catch (error: any) {
      if (error === 'cancel' || error?.action === 'cancel' || error?.action === 'close') {
        return
      }

      ElMessage.error('模板删除失败，请稍后重试。')
    } finally {
      deletingKey.value = ''
    }
  }

  onMounted(() => {
    void loadThemes()
  })
</script>

<style scoped lang="scss">
  .themes-page {
    display: grid;
    gap: 16px;
    padding: 2px;
  }

  .themes-toolbar,
  .theme-card {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 24px;
    background:
      radial-gradient(circle at top right, rgb(14 165 233 / 0.08), transparent 24%),
      linear-gradient(180deg, rgb(255 255 255 / 0.98), rgb(248 250 252 / 0.98));
    box-shadow: 0 16px 36px rgb(15 23 42 / 0.05);
  }

  :global(html.dark .themes-toolbar),
  :global(html.dark .theme-card) {
    border-color: rgb(71 85 105 / 0.42);
    background:
      radial-gradient(circle at top right, rgb(56 189 248 / 0.12), transparent 28%),
      linear-gradient(180deg, rgb(15 23 42 / 0.94), rgb(8 15 28 / 0.96));
    box-shadow: 0 18px 40px rgb(2 6 23 / 0.26);
  }

  .themes-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
      'tabs tabs'
      'filters summary';
    align-items: end;
    gap: 14px 18px;
    padding: 16px 18px 18px;
  }

  .themes-tabs {
    grid-area: tabs;
    min-width: 0;
  }

  .themes-tabs :deep(.el-tabs__header) {
    margin: 0;
  }

  .themes-filters {
    grid-area: filters;
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }

  .themes-summary {
    grid-area: summary;
    display: flex;
    flex-wrap: nowrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding-top: 0;
  }

  .themes-filter {
    width: 180px;
    flex: 0 0 180px;
  }

  .themes-filter--keyword {
    flex: 1 1 420px;
    width: auto;
    min-width: 320px;
  }

  .themes-summary :deep(.el-tag) {
    white-space: nowrap;
  }

  .themes-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    align-items: start;
  }

  .theme-card {
    overflow: hidden;
    border-radius: 12px;
    box-shadow: 0 8px 18px rgb(15 23 42 / 0.04);
  }

  .theme-card__preview {
    position: relative;
    aspect-ratio: 16 / 8.4;
    overflow: hidden;
    background: linear-gradient(135deg, rgb(226 232 240), rgb(248 250 252));
  }

  :global(html.dark .theme-card__preview) {
    background: linear-gradient(135deg, rgb(30 41 59), rgb(15 23 42));
  }

  .theme-card__image,
  .theme-detail__preview img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .theme-card__placeholder {
    display: grid;
    place-items: center;
    align-content: center;
    width: 100%;
    height: 100%;
    gap: 5px;
    color: var(--el-text-color-primary);
    text-align: center;
    padding: 14px;
  }

  .theme-card__placeholder span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .theme-card__placeholder strong {
    font-size: 15px;
    line-height: 1.25;
  }

  .theme-card__badges {
    position: absolute;
    top: 8px;
    left: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
  }

  .theme-card__body {
    display: grid;
    gap: 5px;
    padding: 8px 9px 9px;
  }

  .theme-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
  }

  .theme-card__head h3,
  .theme-detail__copy h3,
  .theme-detail__copy p {
    margin: 0;
    color: var(--el-text-color-primary);
  }

  .theme-card__head h3 {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    font-size: 12px;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .theme-card__description {
    margin: 0;
    color: var(--el-text-color-regular);
    font-size: 10px;
    line-height: 1.4;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
  }

  .theme-detail__copy h3 {
    font-size: 22px;
    line-height: 1.24;
  }

  .theme-detail__copy p {
    margin: 10px 0 0;
    color: var(--el-text-color-regular);
    line-height: 1.8;
  }

  .theme-card__id {
    flex-shrink: 0;
    max-width: 84px;
    overflow: hidden;
    padding: 3px 6px;
    border-radius: 999px;
    background: rgb(148 163 184 / 0.12);
    color: var(--el-text-color-secondary);
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .theme-card__meta {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 5px;
    min-width: 0;
  }

  .theme-card__meta-item {
    min-width: 0;
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
    flex: 0 1 auto;
  }

  .theme-card__meta-divider {
    color: var(--el-text-color-placeholder);
    font-size: 10px;
    line-height: 1;
  }

  .theme-card__meta-item span {
    display: inline;
    color: var(--el-text-color-secondary);
    font-size: 9px;
    line-height: 1.2;
  }

  .theme-card__meta-item strong {
    display: inline;
    margin-top: 0;
    color: var(--el-text-color-primary);
    font-size: 9px;
    line-height: 1.3;
    white-space: nowrap;
  }

  .theme-card__actions {
    display: flex;
    justify-content: space-between;
    gap: 6px;
    align-items: center;
  }

  .theme-card__actions :deep(.el-button) {
    min-height: 24px;
    padding: 4px 9px;
    border-radius: 8px;
    font-size: 10px;
  }

  .theme-card__badges :deep(.el-tag) {
    height: 20px;
    padding: 0 6px;
    border-radius: 8px;
    font-size: 10px;
    line-height: 18px;
  }

  .theme-card__links {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .theme-card__actions :deep(.el-button--text) {
    padding-left: 0;
    padding-right: 0;
  }

  .theme-card__actions :deep(.el-tag) {
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    font-size: 10px;
    line-height: 20px;
  }

  .theme-detail {
    display: grid;
    gap: 20px;
  }

  .theme-detail__hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
    gap: 20px;
  }

  .theme-detail__preview {
    overflow: hidden;
    min-height: 240px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 24px;
    background: linear-gradient(135deg, rgb(226 232 240), rgb(248 250 252));
  }

  :global(html.dark .theme-detail__preview) {
    border-color: rgb(71 85 105 / 0.42);
    background: linear-gradient(135deg, rgb(30 41 59), rgb(15 23 42));
  }

  .theme-detail__copy {
    display: grid;
    align-content: start;
    gap: 14px;
  }

  .theme-detail__note {
    padding: 18px 20px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 22px;
    background: var(--el-bg-color-page);
  }

  .theme-detail__note h4 {
    margin: 0 0 10px;
    color: var(--el-text-color-primary);
  }

  .theme-detail__note p {
    margin: 0;
    color: var(--el-text-color-regular);
    line-height: 1.85;
  }

  @media (max-width: 1080px) {
    .themes-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 1180px) {
    .themes-toolbar {
      grid-template-columns: 1fr;
      grid-template-areas:
        'tabs'
        'filters'
        'summary';
    }

    .themes-filters,
    .themes-summary {
      flex-wrap: wrap;
    }

    .themes-summary {
      justify-content: flex-start;
      padding-top: 2px;
    }
  }

  @media (max-width: 900px) {
    .themes-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .themes-summary {
      justify-content: flex-start;
    }

    .theme-detail__hero {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 720px) {
    .themes-toolbar {
      padding: 12px;
    }

    .themes-filter,
    .themes-filter--keyword {
      width: 100%;
      min-width: 0;
      flex-basis: 100%;
    }

    .theme-card__body {
      padding: 10px;
    }

    .theme-card__head {
      gap: 8px;
    }

    .theme-card__meta,
    .theme-card__actions {
      flex-wrap: wrap;
    }
  }

  @media (max-width: 560px) {
    .themes-grid {
      grid-template-columns: 1fr;
    }

    .theme-card__meta-item {
      flex-basis: 100%;
    }

    .theme-card__meta-item strong {
      max-width: 100%;
    }
  }
</style>
