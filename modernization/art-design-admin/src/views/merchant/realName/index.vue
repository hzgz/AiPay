<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>实名认证</h1>
        <p>填写实名资料并完成扫码认证。</p>
      </div>

      <div v-if="payload" class="merchant-chip-row">
        <span class="merchant-chip"
          >功能 {{ merchantEnabledLabel(payload.status?.feature_enabled) }}</span
        >
        <span class="merchant-chip">状态 {{ translateMerchantText(payload.status?.label) }}</span>
        <span class="merchant-chip"
          >可用通道 {{ payload.verification?.available_channel_count ?? 0 }}</span
        >
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
    </div>

    <article v-else-if="featureMessage" class="merchant-card merchant-real-name-state">
      <div class="merchant-real-name-state__hero">
        <div class="merchant-real-name-state__icon">
          <Icon icon="ri:shield-user-line" />
        </div>

        <div class="merchant-real-name-state__copy">
          <h2>实名认证未开启</h2>
          <p>{{ featureMessage }}</p>
        </div>
      </div>

      <div class="merchant-grid-3">
        <section class="merchant-soft-panel merchant-real-name-state__panel">
          <strong>当前状态</strong>
          <p>当前页展示实名功能状态与开通提示。</p>
        </section>

        <section class="merchant-soft-panel merchant-real-name-state__panel">
          <strong>开放后能力</strong>
          <p>启用后可在此页填写资料并查询结果。</p>
        </section>
      </div>
    </article>

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
              <div class="merchant-stat-card__hint">{{ card.hint }}</div>
            </div>

            <div class="merchant-stat-card__symbol">
              <Icon :icon="card.icon" />
            </div>
          </div>
        </article>
      </section>

      <section class="merchant-grid-2 merchant-grid-2--top">
        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>认证总览</h2>
              <p>查看实名状态、证件脱敏信息和费用。</p>
            </div>
          </div>

          <div class="merchant-kv-grid">
            <div class="merchant-kv-item">
              <span>功能状态</span>
              <div>{{ merchantEnabledLabel(payload.status?.feature_enabled) }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>认证结果</span>
              <div>{{ translateMerchantText(payload.status?.label) }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>实名姓名</span>
              <div>{{ payload.status?.name_masked || '未提交' }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>证件号码</span>
              <div>{{ payload.status?.id_card_masked || '未提交' }}</div>
            </div>
          </div>

          <div class="merchant-real-name-overview">
            <section class="merchant-soft-panel merchant-real-name-overview__panel">
              <strong>费用承担</strong>
              <span>{{
                merchantBooleanLabel(payload.cost?.merchant_bears_cost, ['商户承担', '平台承担'])
              }}</span>
              <p>认证费用：{{ payload.cost?.amount_display || '0.00' }}</p>
            </section>

            <section class="merchant-soft-panel merchant-real-name-overview__panel">
              <strong>结果提示</strong>
              <span>{{ translateMerchantText(displayStatusText) }}</span>
              <p>{{ translateMerchantText(displayStatusDetail) }}</p>
            </section>
          </div>
        </article>

        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>发起认证</h2>
              <p>填写资料后生成二维码，再回到此页查结果。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip">默认通道 {{ activeChannel?.label || '未配置' }}</span>
            <span class="merchant-chip"
              >提交能力 {{ payload.verification?.write_allowed ? '已开启' : '未开启' }}</span
            >
            <span class="merchant-chip" v-if="qrSession?.orderNumber"
              >当前订单 {{ qrSession.orderNumber }}</span
            >
          </div>

          <div class="merchant-note merchant-real-name-note">
            {{ translateMerchantText(payload.verification?.write_message) }}
          </div>

          <div class="merchant-real-name-form">
            <div class="merchant-real-name-form__grid">
              <div class="merchant-real-name-field">
                <span>真实姓名</span>
                <ElInput
                  v-model.trim="form.name"
                  maxlength="32"
                  placeholder="请输入身份证实名姓名"
                  :disabled="verificationLocked || submitLoading"
                />
              </div>

              <div class="merchant-real-name-field">
                <span>身份证号</span>
                <ElInput
                  v-model.trim="form.idCard"
                  maxlength="32"
                  placeholder="请输入身份证号码"
                  :disabled="verificationLocked || submitLoading"
                />
              </div>
            </div>

            <div class="merchant-real-name-field">
              <span>认证通道</span>
              <ElRadioGroup
                v-model="form.channel"
                class="merchant-real-name-channel"
                :disabled="verificationLocked || submitLoading"
              >
                <ElRadioButton v-for="item in availableChannels" :key="item.id" :label="item.id">
                  {{ item.label }}
                </ElRadioButton>
              </ElRadioGroup>
            </div>

            <div v-if="activeChannel" class="merchant-soft-panel merchant-real-name-channel-info">
              <strong>{{ activeChannel.label }}</strong>
              <span>{{ translateMerchantText(activeChannel.flow) }}</span>
            </div>
          </div>

          <div class="merchant-form-actions merchant-form-actions--split">
            <span class="merchant-fine-print">
              {{
                verificationLocked
                  ? '已完成实名，当前仅可查看。'
                  : '提交后会生成二维码，请在手机端完成认证。'
              }}
            </span>

            <ElButton
              type="primary"
              :loading="submitLoading"
              :disabled="verificationLocked || !availableChannels.length"
              @click="handleSubmit"
            >
              {{ qrSession?.orderNumber ? '重新发起认证' : '开始实名认证' }}
            </ElButton>
          </div>
        </article>
      </section>

      <section class="merchant-grid-2">
        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>二维码与轮询</h2>
              <p>生成二维码后在这里查看进度。</p>
            </div>
          </div>

          <template v-if="qrSession?.qrUrl">
            <div class="merchant-real-name-progress">
              <div class="merchant-real-name-progress__qr">
                <img :src="qrSession.qrUrl" alt="实名认证二维码" />
              </div>

              <div class="merchant-real-name-progress__body">
                <div class="merchant-chip-row merchant-chip-row--compact">
                  <span class="merchant-chip"
                    >通道 {{ qrSession.channelLabel || qrSession.channel }}</span
                  >
                  <span class="merchant-chip">订单 {{ qrSession.orderNumber }}</span>
                  <span class="merchant-chip"
                    >状态 {{ translateMerchantText(displayStatusText) }}</span
                  >
                </div>

                <div class="merchant-kv-grid merchant-kv-grid--single">
                  <div class="merchant-kv-item">
                    <span>当前状态</span>
                    <div>{{ translateMerchantText(displayStatusText) }}</div>
                  </div>
                  <div class="merchant-kv-item">
                    <span>说明</span>
                    <div>{{ translateMerchantText(displayStatusDetail) }}</div>
                  </div>
                </div>

                <div v-if="qrSession.redirectUrl" class="merchant-real-name-progress__link">
                  <a :href="qrSession.redirectUrl" target="_blank" rel="noreferrer">
                    打开认证链接
                  </a>
                </div>

                <div class="merchant-form-actions merchant-form-actions--split">
                  <span class="merchant-fine-print">
                    扫码后点“查询结果”，页面停留时也会自动轮询。
                  </span>

                  <div class="merchant-real-name-progress__actions">
                    <ElButton plain :loading="pollLoading" @click="handlePoll(false)">
                      查询结果
                    </ElButton>
                    <ElButton text @click="clearSession">清除二维码</ElButton>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <div v-else class="merchant-real-name-placeholder">
            <div class="merchant-real-name-placeholder__icon">
              <Icon icon="ri:qr-scan-2-line" />
            </div>
            <strong>等待生成实名认证二维码</strong>
            <p>发起认证后，这里会显示二维码和结果。</p>
          </div>
        </article>

        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>通道清单</h2>
              <p>查看各实名通道的可用状态。</p>
            </div>
          </div>

          <ElTable :data="payload.verification?.channels || []" empty-text="暂无通道">
            <ElTableColumn prop="label" label="通道名称" min-width="160" />
            <ElTableColumn prop="flow" label="校验方式" min-width="220" />
            <ElTableColumn label="状态" width="120">
              <template #default="{ row }">
                <ElTag :type="row.available ? 'success' : 'info'" effect="plain">
                  {{ row.available ? '可用' : '未开启' }}
                </ElTag>
              </template>
            </ElTableColumn>
          </ElTable>
        </article>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage } from 'element-plus'
  import {
    MerchantApiError,
    fetchMerchantRealName,
    isMerchantFeatureDisabled,
    pollMerchantRealNameStatus,
    submitMerchantRealName
  } from '@/api/merchant'
  import { merchantBooleanLabel, merchantEnabledLabel, translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantRealName' })

  interface RealNameSession {
    orderNumber: string
    qrUrl: string
    redirectUrl: string
    channel: string
    channelLabel: string
    createdAt: number
  }

  const STORAGE_KEY = 'aipay-merchant-real-name-session'

  const loading = ref(true)
  const payload = ref<Record<string, any> | null>(null)
  const featureMessage = ref('')
  const submitLoading = ref(false)
  const pollLoading = ref(false)
  const latestStatus = ref<Record<string, any> | null>(null)
  const qrSession = ref<RealNameSession | null>(null)
  const form = reactive({
    name: '',
    idCard: '',
    channel: 'wechat'
  })

  let pollTimer: number | null = null

  const availableChannels = computed(() => {
    const channels = payload.value?.form?.channels || payload.value?.verification?.channels || []
    return Array.isArray(channels) ? channels.filter((item) => item?.available) : []
  })

  const activeChannel = computed(() => {
    return (
      availableChannels.value.find((item) => item.id === form.channel) ||
      availableChannels.value[0] ||
      null
    )
  })

  const verificationLocked = computed(() => Boolean(payload.value?.status?.verified))

  const displayStatusText = computed(() => {
    return (
      latestStatus.value?.message ||
      latestStatus.value?.status_label ||
      payload.value?.status?.label ||
      '--'
    )
  })

  const displayStatusDetail = computed(() => {
    return (
      latestStatus.value?.provider_message ||
      latestStatus.value?.message ||
      payload.value?.verification?.write_message ||
      ''
    )
  })

  const summaryCards = computed(() => [
    {
      label: '功能状态',
      value: payload.value ? merchantEnabledLabel(payload.value.status?.feature_enabled) : '--',
      hint: '是否已开放',
      icon: 'ri:shield-check-line'
    },
    {
      label: '当前结果',
      value: payload.value ? translateMerchantText(payload.value.status?.label) : '--',
      hint: '最新实名状态',
      icon: 'ri:verified-badge-line'
    },
    {
      label: '可用通道',
      value: String(payload.value?.verification?.available_channel_count ?? 0),
      hint: '当前可用通道数',
      icon: 'ri:route-line'
    },
    {
      label: '待扣费用',
      value: payload.value?.cost?.amount_display || '0.00',
      hint: merchantBooleanLabel(payload.value?.cost?.merchant_bears_cost, [
        '商户承担',
        '平台承担'
      ]),
      icon: 'ri:coins-line'
    }
  ])

  function persistSession() {
    if (typeof window === 'undefined') {
      return
    }

    if (qrSession.value) {
      window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(qrSession.value))
      return
    }

    window.sessionStorage.removeItem(STORAGE_KEY)
  }

  function restoreSession() {
    if (typeof window === 'undefined') {
      return
    }

    const raw = window.sessionStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return
    }

    try {
      const parsed = JSON.parse(raw)
      if (parsed?.orderNumber && parsed?.qrUrl) {
        qrSession.value = parsed
      }
    } catch {
      window.sessionStorage.removeItem(STORAGE_KEY)
    }
  }

  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer)
      pollTimer = null
    }
  }

  function startPolling() {
    if (
      typeof window === 'undefined' ||
      !qrSession.value?.orderNumber ||
      verificationLocked.value
    ) {
      return
    }

    stopPolling()
    pollTimer = window.setInterval(() => {
      void handlePoll(true)
    }, 5000)
  }

  function clearSession() {
    qrSession.value = null
    latestStatus.value = null
    persistSession()
    stopPolling()
  }

  function syncFormFromPayload(preserveTyped = true) {
    const defaults = payload.value?.form || {}
    if (!preserveTyped || !form.name) {
      form.name = defaults.name || ''
    }
    if (!preserveTyped || !form.idCard) {
      form.idCard = defaults.id_card || ''
    }

    const defaultChannel = defaults.default_channel || availableChannels.value[0]?.id || 'wechat'
    if (!preserveTyped || !availableChannels.value.some((item) => item.id === form.channel)) {
      form.channel = defaultChannel
    }
  }

  async function loadRealName(options: { silent?: boolean } = {}) {
    const { silent = false } = options
    if (!silent) {
      loading.value = true
    }

    featureMessage.value = ''

    try {
      payload.value = await fetchMerchantRealName()
      syncFormFromPayload(!silent)

      if (payload.value?.status?.verified) {
        clearSession()
      } else if (qrSession.value?.orderNumber) {
        persistSession()
      }
    } catch (error) {
      if (isMerchantFeatureDisabled(error)) {
        featureMessage.value = translateMerchantText(
          error instanceof MerchantApiError
            ? error.message
            : 'merchant real-name feature is disabled'
        )
      } else {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '实名认证信息加载失败'
        ElMessage.error(message)
      }
    } finally {
      if (!silent) {
        loading.value = false
      }
    }
  }

  async function handleSubmit() {
    if (!form.name) {
      ElMessage.warning('请输入真实姓名')
      return
    }

    if (!form.idCard) {
      ElMessage.warning('请输入身份证号')
      return
    }

    submitLoading.value = true
    try {
      const result = await submitMerchantRealName({
        name: form.name,
        idCard: form.idCard,
        channel: form.channel
      })

      qrSession.value = {
        orderNumber: result.orderNumber || result.order_number || '',
        qrUrl: result.qr_url || result.qrcode || '',
        redirectUrl: result.redirect_url || result.original_url || '',
        channel: result.channel || form.channel,
        channelLabel: result.channel_label || activeChannel.value?.label || form.channel,
        createdAt: Date.now()
      }
      latestStatus.value = {
        ...result,
        state: 'processing'
      }
      persistSession()

      ElMessage.success(
        translateMerchantText(
          result.message || 'merchant real-name verification started successfully'
        )
      )
      await loadRealName({ silent: true })
      startPolling()
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '实名认证发起失败'
      ElMessage.error(message)
    } finally {
      submitLoading.value = false
    }
  }

  async function handlePoll(silent = false) {
    if (!qrSession.value?.orderNumber) {
      if (!silent) {
        ElMessage.info('请先发起实名认证')
      }
      return
    }

    if (!silent) {
      pollLoading.value = true
    }

    try {
      const result = await pollMerchantRealNameStatus(qrSession.value.orderNumber)
      latestStatus.value = result
      await loadRealName({ silent: true })

      if (result.verified) {
        clearSession()
        ElMessage.success(
          translateMerchantText(
            result.message || 'merchant real-name verification completed successfully'
          )
        )
      } else if (!silent) {
        ElMessage.info(
          translateMerchantText(result.message || 'merchant real-name verification is pending')
        )
      }
    } catch (error) {
      if (!silent) {
        const message =
          error instanceof MerchantApiError
            ? translateMerchantText(error.message, error.message)
            : '实名认证状态查询失败'
        ElMessage.error(message)
      }
    } finally {
      if (!silent) {
        pollLoading.value = false
      }
    }
  }

  watch(
    () => qrSession.value,
    () => {
      persistSession()
    },
    { deep: true }
  )

  watch(
    () => payload.value?.status?.verified,
    (verified) => {
      if (verified) {
        clearSession()
      }
    }
  )

  onMounted(async () => {
    restoreSession()
    await loadRealName()
    if (qrSession.value?.orderNumber && !verificationLocked.value) {
      startPolling()
    }
  })

  onUnmounted(() => {
    stopPolling()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-real-name-state {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 24px;
  }

  .merchant-real-name-state__hero {
    display: flex;
    gap: 18px;
    align-items: center;
  }

  .merchant-real-name-state__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 20px;
    font-size: 30px;
    flex-shrink: 0;
  }

  .merchant-real-name-state__copy h2 {
    margin: 0 0 8px;
    color: var(--merchant-heading-color);
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-real-name-state__copy p,
  .merchant-real-name-state__panel p {
    margin: 0;
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.8;
  }

  .merchant-real-name-state__panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .merchant-real-name-state__panel strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
  }

  .merchant-real-name-overview {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 18px;
  }

  .merchant-real-name-overview__panel {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .merchant-real-name-overview__panel strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
  }

  .merchant-real-name-overview__panel span {
    color: var(--merchant-heading-color);
    font-size: 18px;
    font-weight: 700;
  }

  .merchant-real-name-overview__panel p {
    margin: 0;
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-real-name-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .merchant-real-name-form__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-real-name-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .merchant-real-name-field span {
    color: var(--merchant-heading-color);
    font-size: 13px;
    font-weight: 600;
  }

  .merchant-real-name-channel {
    width: 100%;
  }

  .merchant-real-name-channel :deep(.el-radio-button__inner) {
    min-width: 110px;
  }

  .merchant-real-name-channel-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .merchant-real-name-channel-info strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
  }

  .merchant-real-name-channel-info span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-real-name-note {
    margin: 14px 0 18px;
  }

  .merchant-real-name-progress {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
  }

  .merchant-real-name-progress__qr {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 220px;
    padding: 18px;
    background: rgb(148 163 184 / 8%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 22px;
  }

  .merchant-real-name-progress__qr img {
    display: block;
    width: 184px;
    height: 184px;
    object-fit: contain;
  }

  .merchant-real-name-progress__body {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-real-name-progress__link a {
    color: var(--main-color);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
  }

  .merchant-real-name-progress__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .merchant-real-name-placeholder {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
    justify-content: center;
    min-height: 280px;
    padding: 28px;
    text-align: center;
    background: rgb(148 163 184 / 6%);
    border: 1px dashed rgb(148 163 184 / 20%);
    border-radius: 22px;
  }

  .merchant-real-name-placeholder__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 24px;
    font-size: 32px;
  }

  .merchant-real-name-placeholder strong {
    color: var(--merchant-heading-color);
    font-size: 18px;
  }

  .merchant-real-name-placeholder p {
    max-width: 380px;
    margin: 0;
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.8;
  }

  @media (max-width: 1100px) {
    .merchant-real-name-progress {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 768px) {
    .merchant-real-name-form__grid,
    .merchant-real-name-overview {
      grid-template-columns: 1fr;
    }

    .merchant-real-name-state__hero {
      flex-direction: column;
      align-items: flex-start;
    }

    .merchant-real-name-progress__qr {
      min-height: 0;
    }
  }
</style>
