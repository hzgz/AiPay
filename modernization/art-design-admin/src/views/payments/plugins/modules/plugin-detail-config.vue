<template>
  <section class="drawer-section">
    <template v-if="legacyProfile">
      <div class="config-card">
        <div class="config-head">
          <div class="capability-list">
            <ElTag v-for="item in detail.manifest.capabilities" :key="item" effect="plain">
              {{ capabilityDisplayLabel(item) }}
            </ElTag>
          </div>
        </div>

        <div class="legacy-profile-toolbar">
          <div class="plugin-config-notice__copy">
            <strong>{{ pluginWorkspaceLabel(legacyProfile) }}</strong>
            <p>{{ legacyProfile.summary }}</p>
          </div>
          <ElButton
            v-if="pluginWorkspaceButtonLabel(legacyProfile)"
            plain
            @click="emit('openWorkspace', legacyProfile, detail.manifest.code)"
          >
            {{ pluginWorkspaceButtonLabel(legacyProfile) }}
          </ElButton>
        </div>

        <div class="legacy-profile-list">
          <section
            v-for="field in legacyProfile.fields"
            :key="`${legacyProfile.code}-${field.key}`"
            class="legacy-profile-row"
          >
            <div class="legacy-profile-row__head">
              <strong>{{ field.label }}</strong>
              <div class="capability-list">
                <ElTag v-if="field.required" type="danger" effect="plain">必填</ElTag>
                <ElTag v-if="field.secret" type="warning" effect="plain">敏感</ElTag>
              </div>
            </div>
            <p v-if="field.hint" class="legacy-profile-row__hint">{{ field.hint }}</p>
          </section>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="config-card">
        <div class="config-head">
          <div class="capability-list">
            <ElTag v-for="item in detail.manifest.capabilities" :key="item" effect="plain">
              {{ capabilityDisplayLabel(item) }}
            </ElTag>
          </div>
          <div class="audit-tags">
            <ElTag effect="plain">字段 {{ detail.config_summary.total_fields }}</ElTag>
            <ElTag type="success" effect="plain">
              已配置 {{ detail.config_summary.configured_fields }}
            </ElTag>
            <ElTag
              :type="detail.config_summary.missing_required_fields > 0 ? 'danger' : 'info'"
              effect="plain"
            >
              缺少必填 {{ detail.config_summary.missing_required_fields }}
            </ElTag>
          </div>
        </div>

        <div v-if="!detail.state.installed" class="plugin-config-notice">
          <div class="plugin-config-notice__copy">
            <strong>插件未安装</strong>
            <p>安装后即可在这里填写接入字段。</p>
          </div>
          <ElTag effect="plain" type="info">未安装</ElTag>
        </div>

        <template v-else>
          <div
            v-if="!detail.state_audit.config_table_exists"
            class="plugin-config-notice plugin-config-notice--danger"
          >
            <div class="plugin-config-notice__copy">
              <strong>配置表缺失</strong>
              <p>请先修复插件，再回到这里填写接入字段。</p>
            </div>
            <ElTag effect="plain" type="danger">需修复</ElTag>
          </div>

          <div v-else class="plugin-config-stack">
            <section
              v-for="section in configSections"
              :key="section.key"
              class="plugin-config-section"
            >
              <div class="plugin-config-section__head">
                <div>
                  <h4>{{ section.title }}</h4>
                </div>
                <ElTag effect="plain">{{ section.fields.length }} 项</ElTag>
              </div>

              <ElForm label-position="top" class="config-form">
                <ElRow :gutter="16">
                  <ElCol v-for="field in section.fields" :key="field.field" :xs="24" :md="24">
                    <ElFormItem
                      :label="normalizePluginCopy(field.label)"
                      :required="field.required"
                    >
                      <ElInput
                        :model-value="configForm[field.field] ?? ''"
                        :type="inputTypeForField(field)"
                        :rows="field.type === 'textarea' ? 4 : undefined"
                        :show-password="field.type === 'password'"
                        :placeholder="placeholderForField(field) || undefined"
                        :disabled="!hasPluginSaveConfigAuth"
                        clearable
                        @update:model-value="updateField(field.field, $event)"
                      />
                    </ElFormItem>
                  </ElCol>
                </ElRow>
              </ElForm>
            </section>
          </div>

          <div class="plan-actions">
            <ElButton
              v-if="hasPluginSaveConfigAuth"
              type="primary"
              :loading="configSaving"
              :disabled="!canSaveConfig"
              @click="emit('save', detail.manifest.code)"
            >
              保存配置
            </ElButton>
            <ElTag effect="plain" type="info"
              >必填 {{ detail.config_summary.required_fields }} 项</ElTag
            >
          </div>
        </template>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
  import type { PaymentPluginLegacyProfile } from '@/views/payments/shared/paymentPluginDisplay'
  import {
    capabilityDisplayLabel,
    inputTypeForField,
    normalizePluginCopy,
    placeholderForField,
    pluginWorkspaceButtonLabel,
    pluginWorkspaceLabel,
    type PluginConfigSection
  } from '@/views/payments/shared/paymentPluginDisplay'

  type PaymentPluginDetail = Api.SystemManage.PaymentPluginDetail

  interface Props {
    detail: PaymentPluginDetail
    legacyProfile: PaymentPluginLegacyProfile | null
    configSections: PluginConfigSection[]
    configForm: Record<string, string>
    hasPluginSaveConfigAuth: boolean
    configSaving: boolean
    canSaveConfig: boolean
  }

  defineProps<Props>()

  const emit = defineEmits<{
    (e: 'openWorkspace', profile: PaymentPluginLegacyProfile, code: string): void
    (e: 'save', code: string): void
    (e: 'updateConfigField', payload: { field: string; value: string }): void
  }>()

  const updateField = (field: string, value: string | number) => {
    emit('updateConfigField', {
      field,
      value: String(value ?? '')
    })
  }
</script>

<style scoped lang="scss">
  .drawer-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
  }

  .config-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 14px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(249 250 251 / 0.92));
  }

  .config-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
  }

  .config-head .capability-list {
    min-width: 0;
  }

  .legacy-profile-toolbar,
  .plugin-config-notice {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    padding: 14px 16px;
    border: 1px solid rgb(59 130 246 / 0.16);
    border-radius: 16px;
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.12), transparent 34%),
      linear-gradient(180deg, rgb(239 246 255 / 0.92), rgb(255 255 255 / 1));
  }

  .legacy-profile-toolbar__label,
  .plugin-config-notice__copy strong {
    color: #0f172a;
    font-size: 14px;
    font-weight: 600;
  }

  .plugin-config-notice__copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .plugin-config-notice__copy p {
    margin: 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.6;
  }

  .plugin-config-notice--danger {
    border-color: rgb(248 113 113 / 0.18);
    background:
      radial-gradient(circle at top right, rgb(248 113 113 / 0.14), transparent 34%),
      linear-gradient(180deg, rgb(254 242 242 / 0.94), rgb(255 255 255 / 1));
  }

  .plugin-config-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .plugin-config-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 14px;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.94));
  }

  .plugin-config-section__head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
  }

  .plugin-config-section__head h4 {
    margin: 0;
    color: #0f172a;
    font-size: 14px;
    font-weight: 700;
  }

  .plugin-config-section .config-form {
    margin-top: 0;
  }

  .legacy-profile-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .legacy-profile-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 13px 14px;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 16px;
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 0.94));
  }

  .legacy-profile-row__head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: flex-start;
  }

  .legacy-profile-row__head strong {
    color: #111827;
    line-height: 1.4;
  }

  .legacy-profile-row__hint {
    margin: 0;
    color: #475569;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.6;
    word-break: break-word;
  }

  .capability-list,
  .audit-tags,
  .plan-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .audit-tags {
    justify-content: flex-end;
  }

  .plan-actions {
    gap: 10px;
  }

  @media (width <= 991px) {
    .config-head {
      grid-template-columns: 1fr;
    }

    .legacy-profile-toolbar,
    .plugin-config-notice,
    .plugin-config-section__head {
      flex-direction: column;
      align-items: flex-start;
    }

    .audit-tags {
      justify-content: flex-start;
    }
  }
</style>
