<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>通知设置</h1>
      </div>

      <div v-if="payload" class="merchant-chip-row">
        <span class="merchant-chip">可用渠道 {{ availableChannelCount }} 项</span>
        <span class="merchant-chip">已启用 {{ configuredSettingCount }} 项</span>
        <span class="merchant-chip">{{ voiceStatusLabel }}</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <template v-else-if="payload">
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
            </div>

            <div class="merchant-stat-card__symbol">
              <Icon :icon="card.icon" />
            </div>
          </div>
        </article>
      </section>

      <ElForm label-position="top" class="merchant-notification-form">
        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>提醒渠道</h2>
            </div>
          </div>

          <div class="merchant-notification-grid">
            <div v-for="setting in settings" :key="setting.id" class="merchant-notification-card">
              <div class="merchant-notification-card__head">
                <div class="merchant-notification-card__icon">
                  <Icon :icon="notificationIcon(setting.id)" />
                </div>

                <div class="merchant-notification-card__copy">
                  <strong>{{ labelMap[setting.id] || translateMerchantText(setting.name) }}</strong>
                </div>

                <ElTag :type="formData[setting.id] === 'close' ? 'info' : 'success'" effect="plain">
                  {{ formData[setting.id] === 'close' ? '未开启' : '已开启' }}
                </ElTag>
              </div>

              <ElSelect v-model="formData[setting.id]" class="w-full">
                <ElOption
                  v-for="channel in setting.channels"
                  :key="channel.id"
                  :label="channelLabel(channel.id)"
                  :value="channel.id"
                  :disabled="!channel.available && channel.id !== 'close'"
                />
              </ElSelect>
            </div>
          </div>
        </article>

        <section class="merchant-grid-2">
          <article class="merchant-form-card">
            <div class="merchant-form-card__head">
              <div>
                <h2>余额与公告</h2>
              </div>
            </div>

            <div class="merchant-grid-2">
              <ElFormItem label="余额提醒阈值">
                <ElInput v-model.trim="formData.money_tips" placeholder="50.00" />
              </ElFormItem>

              <ElFormItem label="控制台公告">
                <ElInput v-model.trim="formData.console_notice" placeholder="选填" />
              </ElFormItem>
            </div>
          </article>

          <article class="merchant-form-card">
            <div class="merchant-form-card__head">
              <div>
                <h2>语音播报</h2>
              </div>
            </div>

            <div class="merchant-notification-voice">
              <div class="merchant-notification-voice__copy">
                <strong>{{ voiceStatusLabel }}</strong>
              </div>

              <ElSwitch v-model="formData.is_voice_tips" />
            </div>

            <ElFormItem label="语音模板">
              <ElInput v-model.trim="formData.voice_tips" placeholder="到账【交易金额】" />
            </ElFormItem>
          </article>
        </section>

        <div class="merchant-form-actions merchant-notification-actions">
          <ElButton type="primary" :loading="submitting" @click="handleSubmit">
            保存通知设置
          </ElButton>
        </div>
      </ElForm>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import {
    MerchantApiError,
    fetchMerchantNotifications,
    updateMerchantNotifications
  } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantNotifications' })

  const loading = ref(true)
  const submitting = ref(false)
  const payload = ref<Record<string, any> | null>(null)
  const settings = ref<Array<Record<string, any>>>([])

  const formData = reactive<Record<string, any>>({
    order_tips: 'close',
    lose_tips: 'close',
    login_tips: 'close',
    is_money_tips: 'close',
    money_tips: '0',
    console_notice: '',
    is_voice_tips: false,
    voice_tips: ''
  })

  const labelMap: Record<string, string> = {
    order_tips: '新订单通知',
    lose_tips: '通道掉线提醒',
    login_tips: '账户登录提醒',
    is_money_tips: '余额不足提醒'
  }

  const voiceTokenAlias = '【交易金额】'
  const voiceTokenRaw = '[money]'

  const availableChannelCount = computed(
    () =>
      (payload.value?.channels || []).filter((item: Record<string, any>) => item.available).length
  )

  const configuredSettingCount = computed(
    () =>
      settings.value.filter((setting) => formData[setting.id] && formData[setting.id] !== 'close')
        .length
  )

  const voiceStatusLabel = computed(() =>
    formData.is_voice_tips ? '语音播报已开启' : '语音播报已关闭'
  )

  const summaryCards = computed(() => [
    {
      label: '可用渠道',
      value: String(availableChannelCount.value),
      icon: 'ri:broadcast-line'
    },
    {
      label: '已启用场景',
      value: String(configuredSettingCount.value),
      icon: 'ri:notification-badge-line'
    },
    {
      label: '语音播报',
      value: formData.is_voice_tips ? '已开启' : '已关闭',
      icon: 'ri:volume-up-line'
    },
    {
      label: '余额阈值',
      value: formatThreshold(formData.money_tips),
      icon: 'ri:alarm-warning-line'
    }
  ])

  function notificationIcon(settingId: string) {
    const mapping: Record<string, string> = {
      order_tips: 'ri:file-list-3-line',
      lose_tips: 'ri:signal-tower-line',
      login_tips: 'ri:shield-user-line',
      is_money_tips: 'ri:wallet-3-line'
    }

    return mapping[settingId] || 'ri:notification-4-line'
  }

  function channelLabel(channelId: string) {
    const mapping: Record<string, string> = {
      close: '关闭',
      email: '邮箱',
      wxpusher: '微信推送',
      tg: '电报'
    }

    return mapping[channelId] || translateMerchantText(channelId)
  }

  function formatThreshold(value: unknown) {
    const raw = String(value ?? '').trim()
    return raw === '' ? '0.00 元' : `${raw} 元`
  }

  function toVoiceTemplateAlias(value: unknown) {
    return String(value || '').replaceAll(voiceTokenRaw, voiceTokenAlias)
  }

  function toVoiceTemplateRaw(value: unknown) {
    return String(value || '').replaceAll(voiceTokenAlias, voiceTokenRaw)
  }

  function applyPayload(data: Record<string, any>) {
    payload.value = data
    settings.value = Array.isArray(data.settings) ? data.settings : []

    settings.value.forEach((setting) => {
      formData[setting.id] = setting.selected || 'close'
    })
    formData.money_tips = data.low_balance_threshold || '0'
    formData.console_notice = data.console_notice || ''
    formData.is_voice_tips = Boolean(data.voice_tips?.enabled)
    formData.voice_tips = toVoiceTemplateAlias(data.voice_tips?.template || '')
  }

  async function loadNotifications() {
    loading.value = true
    try {
      const data = await fetchMerchantNotifications()
      applyPayload(data)
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '通知配置加载失败'
      ElMessage.error(message)
    } finally {
      loading.value = false
    }
  }

  async function handleSubmit() {
    submitting.value = true
    try {
      const data = await updateMerchantNotifications({
        order_tips: formData.order_tips,
        lose_tips: formData.lose_tips,
        login_tips: formData.login_tips,
        is_money_tips: formData.is_money_tips,
        money_tips: formData.money_tips,
        console_notice: formData.console_notice,
        is_voice_tips: formData.is_voice_tips,
        voice_tips: toVoiceTemplateRaw(formData.voice_tips)
      })
      applyPayload(data)
      ElMessage.success('通知设置已保存')
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '通知设置保存失败'
      ElMessage.error(message)
    } finally {
      submitting.value = false
    }
  }

  onMounted(() => {
    loadNotifications()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-notification-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .merchant-notification-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-notification-card {
    padding: 18px;
    background: rgb(148 163 184 / 8%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 18px;
  }

  .merchant-notification-card__head {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 14px;
  }

  .merchant-notification-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 14px;
    font-size: 20px;
    flex-shrink: 0;
  }

  .merchant-notification-card__copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .merchant-notification-card__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.3;
  }

  .merchant-notification-card__copy span,
  .merchant-notification-card__hint {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-notification-card__hint {
    margin: 12px 0 0;
  }

  .merchant-notification-voice {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 6px;
  }

  .merchant-notification-voice__copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .merchant-notification-voice__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-notification-actions {
    margin-top: 0;
  }

  @media (width <= 820px) {
    .merchant-notification-grid {
      grid-template-columns: 1fr;
    }

    .merchant-notification-card__head,
    .merchant-notification-voice {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
