<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>资料维护</h1>
      </div>

      <div v-if="payload" class="merchant-chip-row">
        <span class="merchant-chip">商户 #{{ payload.profile?.id || '--' }}</span>
        <span class="merchant-chip">账户余额 {{ payload.profile?.money_display || '0.00' }}</span>
        <span class="merchant-chip">{{ translateMerchantText(payload.profile?.vip_label) }}</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <template v-else-if="payload">
      <section class="merchant-stat-grid">
        <article
          v-for="card in profileSummaryCards"
          :key="card.label"
          class="merchant-card merchant-stat-card"
        >
          <div class="merchant-stat-card__row">
            <div class="merchant-stat-card__copy">
              <div class="merchant-stat-card__label">{{ card.label }}</div>
              <div class="merchant-stat-card__value">{{ card.value }}</div>
            </div>

            <div class="merchant-stat-card__symbol">
              <Icon :icon="card.icon" />
            </div>
          </div>
        </article>
      </section>

      <section class="merchant-grid-2">
        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>商户档案</h2>
            </div>
          </div>

          <div class="merchant-profile-info-grid">
            <div
              v-for="item in baseProfileItems"
              :key="item.label"
              class="merchant-soft-panel merchant-profile-metric"
            >
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </article>

        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>会员与接口配置</h2>
            </div>
          </div>

          <div class="merchant-profile-info-grid">
            <div
              v-for="item in serviceProfileItems"
              :key="item.label"
              class="merchant-soft-panel merchant-profile-metric"
            >
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </article>
      </section>

      <article class="merchant-form-card">
        <div class="merchant-form-card__head">
          <div>
            <h2>联系方式</h2>
          </div>
        </div>

        <div class="merchant-profile-editor">
          <ElForm
            ref="formRef"
            :model="formData"
            :rules="rules"
            label-position="top"
            class="merchant-profile-editor__form"
          >
            <div class="merchant-grid-2">
              <ElFormItem label="邮箱地址" prop="email">
                <ElInput
                  v-model.trim="formData.email"
                  autocomplete="email"
                  placeholder="选填"
                />
              </ElFormItem>

              <ElFormItem label="手机号码" prop="mobile">
                <ElInput
                  v-model.trim="formData.mobile"
                  autocomplete="tel"
                  placeholder="请输入 11 位手机号"
                />
              </ElFormItem>
            </div>

            <div class="merchant-form-actions">
              <ElButton type="primary" :loading="submitting" @click="handleSubmit"
                >保存资料</ElButton
              >
            </div>
          </ElForm>

          <aside class="merchant-soft-panel merchant-profile-editor__aside">
            <strong>当前同步状态</strong>

            <div class="merchant-profile-side-list">
              <div class="merchant-profile-side-item">
                <span>登录账号</span>
                <div>{{ profileLoginName }}</div>
              </div>
              <div class="merchant-profile-side-item">
                <span>邮箱</span>
                <div>{{ profileEmailLabel }}</div>
              </div>
              <div class="merchant-profile-side-item">
                <span>手机</span>
                <div>{{ profileMobileLabel }}</div>
              </div>
              <div class="merchant-profile-side-item">
                <span>超时跳转</span>
                <div>{{ payload.api_settings?.timeout_url || '/' }}</div>
              </div>
            </div>
          </aside>
        </div>
      </article>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage } from 'element-plus'
  import { MerchantApiError, fetchMerchantProfile, updateMerchantProfile } from '@/api/merchant'
  import { useMerchantStore } from '@/store/modules/merchant'
  import {
    formatMerchantContactValue,
    formatMerchantDisplayName,
    formatMerchantIdentity,
    translateMerchantText
  } from '../shared/text'

  defineOptions({ name: 'MerchantProfile' })

  const merchantStore = useMerchantStore()
  const formRef = ref<FormInstance>()
  const loading = ref(true)
  const submitting = ref(false)
  const payload = ref<Record<string, any> | null>(null)
  const profileMerchantId = computed(() =>
    Number(payload.value?.profile?.id || merchantStore.merchantId || 0)
  )
  const profileLoginName = computed(() =>
    formatMerchantIdentity(payload.value?.profile?.username, {
      merchantId: profileMerchantId.value,
      fallback: '--',
      defaultLabel: '商户账户'
    })
  )
  const profileDisplayName = computed(() =>
    formatMerchantDisplayName(
      payload.value?.profile?.display_name,
      payload.value?.profile?.username,
      profileMerchantId.value,
      merchantStore.displayName || '商户账户'
    )
  )
  const profileEmailLabel = computed(() =>
    formatMerchantContactValue(payload.value?.profile?.email, {
      emptyLabel: '未填写',
      fallbackLabel: '邮箱已脱敏',
      maskMode: 'email'
    })
  )
  const profileMobileLabel = computed(() =>
    formatMerchantContactValue(payload.value?.profile?.mobile, {
      emptyLabel: '未填写',
      fallbackLabel: '手机已脱敏',
      maskMode: 'mobile'
    })
  )

  const profileSummaryCards = computed(() => [
    {
      label: '商户编号',
      value: profileMerchantId.value > 0 ? `#${profileMerchantId.value}` : '--',
      icon: 'ri:profile-line'
    },
    {
      label: '账户余额',
      value: payload.value?.profile?.money_display || '0.00',
      icon: 'ri:wallet-3-line'
    },
    {
      label: '当前费率',
      value: payload.value?.profile?.fee_rate || '--',
      icon: 'ri:line-chart-line'
    },
    {
      label: '会员有效期',
      value: payload.value?.profile?.vip_time || '未开通会员',
      icon: 'ri:vip-crown-2-line'
    }
  ])

  const baseProfileItems = computed(() => [
    {
      label: '登录账号',
      value: profileLoginName.value
    },
    {
      label: '显示名称',
      value: profileDisplayName.value
    },
    {
      label: '注册时间',
      value: payload.value?.profile?.create_time || '--'
    },
    {
      label: '当前余额',
      value: payload.value?.profile?.money_display || '0.00'
    }
  ])

  const serviceProfileItems = computed(() => [
    {
      label: '会员状态',
      value: translateMerchantText(payload.value?.profile?.vip_label)
    },
    {
      label: '会员到期',
      value: payload.value?.profile?.vip_time || '未开通会员'
    },
    {
      label: '超时处理方式',
      value: translateMerchantText(payload.value?.api_settings?.timeout_method_label)
    },
    {
      label: '超时时间',
      value: `${payload.value?.api_settings?.timeout_time ?? 0} 秒`
    }
  ])

  const formData = reactive({
    email: '',
    mobile: ''
  })

  const rules: FormRules = {
    email: [
      {
        validator: (_rule, value, callback) => {
          if (!value) {
            callback()
            return
          }

          ;/\S+@\S+\.\S+/.test(value) ? callback() : callback(new Error('邮箱格式不正确'))
        },
        trigger: 'blur'
      }
    ],
    mobile: [
      {
        validator: (_rule, value, callback) => {
          if (!value) {
            callback()
            return
          }

          ;/^1\d{10}$/.test(value) ? callback() : callback(new Error('手机号格式不正确'))
        },
        trigger: 'blur'
      }
    ]
  }

  function applyPayload(data: Record<string, any>) {
    payload.value = data
    merchantStore.applyProfilePayload(data)
    formData.email = data.profile?.email || ''
    formData.mobile = data.profile?.mobile || ''
  }

  async function loadProfile() {
    loading.value = true
    try {
      const data = await fetchMerchantProfile()
      applyPayload(data)
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '资料加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  async function handleSubmit() {
    if (!formRef.value) {
      return
    }

    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) {
      return
    }

    submitting.value = true
    try {
      const data = await updateMerchantProfile({
        email: formData.email,
        mobile: formData.mobile
      })
      applyPayload(data)
      ElMessage.success('商户资料已更新')
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '资料保存失败'
      ElMessage.error(message)
    } finally {
      submitting.value = false
    }
  }

  onMounted(() => {
    loadProfile()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-profile-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-profile-metric {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 140px;
  }

  .merchant-profile-metric span {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.2;
  }

  .merchant-profile-metric strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.55;
    word-break: break-word;
  }

  .merchant-profile-metric p {
    margin: 0;
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.75;
  }

  .merchant-profile-editor {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr);
    gap: 18px;
    align-items: start;
  }

  .merchant-profile-editor__aside {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-profile-editor__aside strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-profile-side-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .merchant-profile-side-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px dashed rgb(148 163 184 / 24%);
  }

  .merchant-profile-side-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
  }

  .merchant-profile-side-item span {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.6;
    flex-shrink: 0;
  }

  .merchant-profile-side-item div {
    color: var(--merchant-heading-color);
    font-size: 13px;
    font-weight: 600;
    line-height: 1.7;
    text-align: right;
    word-break: break-word;
  }
  @media (width <= 980px) {
    .merchant-profile-editor {
      grid-template-columns: 1fr;
    }
  }

  @media (width <= 1080px) {
    .merchant-profile-info-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (width <= 820px) {
    .merchant-profile-side-item {
      flex-direction: column;
    }

    .merchant-profile-side-item div {
      text-align: left;
    }
  }
</style>
