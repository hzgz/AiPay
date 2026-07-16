<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>接口信息</h1>
        <p>查看接口地址与密钥。</p>
      </div>

      <div class="merchant-chip-row">
        <span class="merchant-chip">商户号 #{{ payload?.merchant_id || '--' }}</span>
        <span class="merchant-chip">线路 {{ gatewayLineCount }} 条</span>
        <span class="merchant-chip">接口签名 {{ payload?.signing?.algorithm || 'MD5' }}</span>
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
              <div class="merchant-stat-card__hint">{{ card.hint }}</div>
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
              <h2>网关线路</h2>
              <p>切换线路。</p>
            </div>
          </div>

          <div class="merchant-api-line-list">
            <article
              v-for="line in gatewayLines"
              :key="line.url"
              role="button"
              tabindex="0"
              class="merchant-api-line"
              :class="{ 'merchant-api-line--active': isSelectedLine(line.url) }"
              :aria-pressed="isSelectedLine(line.url)"
              @click="selectLine(line.url)"
              @keydown.enter.prevent="selectLine(line.url)"
              @keydown.space.prevent="selectLine(line.url)"
            >
              <div class="merchant-api-line__head">
                <div class="merchant-api-line__identity">
                  <div class="merchant-api-line__icon">
                    <Icon icon="ri:route-line" />
                  </div>

                  <div class="merchant-api-line__copy">
                    <strong>{{ translateMerchantText(line.name, '默认线路') }}</strong>
                    <span>{{ line.url }}</span>
                  </div>
                </div>

                <ElTag v-if="isSelectedLine(line.url)" type="primary" effect="plain">当前</ElTag>
              </div>

              <div class="merchant-api-line__primary">
                <span class="merchant-api-line__label">主网关地址</span>
                <strong>{{ line.url }}</strong>
              </div>

              <div class="merchant-api-line__meta">
                <div class="merchant-api-line__endpoint">
                  <span class="merchant-api-line__label">网页下单</span>
                  <strong>{{ line.submit_url }}</strong>
                </div>

                <div class="merchant-api-line__endpoint">
                  <span class="merchant-api-line__label">程序下单</span>
                  <strong>{{ line.mapi_url }}</strong>
                </div>
              </div>

              <div class="merchant-chip-row merchant-chip-row--compact">
                <span class="merchant-chip">网页下单 {{ line.submit_url }}</span>
                <span class="merchant-chip">程序下单 {{ line.mapi_url }}</span>
              </div>
            </article>
          </div>

          <section class="merchant-soft-panel merchant-api-qrcode-panel">
            <div class="merchant-api-qrcode-panel__head">
              <div class="merchant-api-qrcode-panel__copy">
                <strong>商户二维码</strong>
                <span>点击生成。</span>
              </div>
              <ElTag :type="payload.write_actions?.qrcode ? 'success' : 'info'" effect="plain">
                {{ payload.appkey_configured ? '可生成' : '待生成' }}
              </ElTag>
            </div>

            <div class="merchant-kv-grid merchant-kv-grid--endpoint">
              <div class="merchant-kv-item">
                <span>当前线路</span>
                <div>{{ selectedGateway?.url || '--' }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>通讯密钥</span>
                <div>{{ payload.appkey_masked || '未配置' }}</div>
              </div>
            </div>

            <div class="merchant-form-actions merchant-form-actions--start">
              <ElButton
                type="primary"
                :loading="qrcodeLoading"
                :disabled="!selectedGateway"
                @click="handleShowMerchantQrcode"
              >
                {{ payload.appkey_configured ? '显示商户二维码' : '生成并显示二维码' }}
              </ElButton>
              <ElButton
                plain
                :disabled="!qrcodePayload?.content_base64"
                @click="copyCurrentEncodedPayload"
              >
                复制当前密文
              </ElButton>
            </div>
          </section>
        </article>

        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>密钥操作</h2>
              <p>重置后生效。</p>
            </div>
          </div>

          <div class="merchant-api-key-grid">
            <section class="merchant-soft-panel merchant-api-key-panel">
              <div class="merchant-api-key-panel__head">
                <strong>商户密钥</strong>
                <ElTag :type="payload.sign_key_configured ? 'success' : 'info'" effect="plain">
                  {{ payload.sign_key_configured ? '已配置' : '未配置' }}
                </ElTag>
              </div>
              <div class="merchant-api-key-panel__value">
                {{ payload.sign_key_masked || '暂无可展示摘要' }}
              </div>
              <div class="merchant-api-key-panel__hint">{{ payload.sign_key_length || 0 }} 位</div>
            </section>

            <section class="merchant-soft-panel merchant-api-key-panel">
              <div class="merchant-api-key-panel__head">
                <strong>通讯密钥</strong>
                <ElTag :type="payload.appkey_configured ? 'success' : 'info'" effect="plain">
                  {{ payload.appkey_configured ? '已配置' : '未配置' }}
                </ElTag>
              </div>
              <div class="merchant-api-key-panel__value">
                {{ payload.appkey_masked || '暂无可展示摘要' }}
              </div>
              <div class="merchant-api-key-panel__hint">{{ payload.appkey_length || 0 }} 位</div>
            </section>
          </div>

          <div class="merchant-grid-2 merchant-api-actions">
            <ElButton type="primary" :loading="signKeyLoading" @click="handleResetSignKey">
              重置商户密钥
            </ElButton>
            <ElButton type="success" :loading="appKeyLoading" @click="handleResetAppKey">
              重置通讯密钥
            </ElButton>
          </div>
        </article>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>接口地址</h2>
            <p>下单与回调。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>超时时间</span>
              <strong>{{ payload.timeout_time ?? 0 }} 秒</strong>
            </div>
            <div class="merchant-toolbar-pill">
              <span>超时处理</span>
              <strong>{{ translateMerchantText(payload.timeout_method_label) }}</strong>
            </div>
          </div>
        </div>

        <div class="merchant-api-endpoint-grid">
          <section
            v-for="endpoint in endpointCards"
            :key="endpoint.key"
            class="merchant-soft-panel merchant-api-endpoint"
          >
            <div class="merchant-api-endpoint__head">
              <div class="merchant-api-endpoint__icon">
                <Icon :icon="endpoint.icon" />
              </div>

              <div class="merchant-api-endpoint__copy">
                <strong>{{ endpoint.title }}</strong>
              </div>
            </div>

            <div class="merchant-kv-grid merchant-kv-grid--endpoint">
              <div class="merchant-kv-item">
                <span>方式</span>
                <div>{{ endpoint.mode }}</div>
              </div>
              <div class="merchant-kv-item">
                <span>路径</span>
                <div>{{ endpoint.path || '--' }}</div>
              </div>
            </div>

            <div class="merchant-api-endpoint__url">{{ endpoint.url || '--' }}</div>
          </section>
        </div>
      </article>
    </template>

    <ElDialog
      v-model="qrcodeDialogVisible"
      title="商户对接二维码"
      width="min(560px, calc(100vw - 24px))"
      destroy-on-close
    >
      <div v-if="qrcodeLoading" class="merchant-api-dialog-loading">
        <ElSkeleton :rows="6" animated />
      </div>

      <div v-else-if="qrcodePayload" class="merchant-api-qrcode-dialog">
        <div class="merchant-api-qrcode-dialog__image">
          <img :src="qrcodePayload.qrcode_url || qrcodePayload.qrcode" alt="商户对接二维码" />
        </div>

        <div class="merchant-chip-row merchant-chip-row--compact">
          <span class="merchant-chip">线路 {{ qrcodePayload.selected_line }}</span>
          <span class="merchant-chip">商户 {{ qrcodePayload.merchant_id }}</span>
          <span class="merchant-chip"
            >密钥 {{ (qrcodePayload.key_type || 'appkey').toUpperCase() }}</span
          >
        </div>

        <div class="merchant-code-block">{{ qrcodePayload.content_base64 }}</div>

        <div class="merchant-api-qrcode-dialog__tip"> 扫码导入或复制密文 </div>
      </div>

      <div v-else class="merchant-state-card">
        <h3>暂无二维码</h3>
        <p>请重新生成二维码。</p>
      </div>

      <template #footer>
        <ElButton @click="qrcodeDialogVisible = false">关闭</ElButton>
        <ElButton
          type="primary"
          :disabled="!qrcodePayload?.content_base64"
          @click="copyCurrentEncodedPayload"
        >
          复制当前密文
        </ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    MerchantApiError,
    fetchMerchantApiInfo,
    generateMerchantApiQrcode,
    resetMerchantAppKey,
    resetMerchantSignKey
  } from '@/api/merchant'
  import { translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantApi' })

  interface GatewayLine {
    name: string
    url: string
    submit_url: string
    mapi_url: string
  }

  interface MerchantQrcodePayload {
    merchant_id: number
    selected_line: string
    key_type: string
    key_masked: string
    appkey_generated?: boolean
    content_base64: string
    content_length: number
    qrcode?: string
    qrcode_url?: string
  }

  const loading = ref(true)
  const signKeyLoading = ref(false)
  const appKeyLoading = ref(false)
  const qrcodeLoading = ref(false)
  const qrcodeDialogVisible = ref(false)
  const payload = ref<Record<string, any> | null>(null)
  const qrcodePayload = ref<MerchantQrcodePayload | null>(null)
  const selectedGatewayUrl = ref('')

  const gatewayLines = computed<GatewayLine[]>(() =>
    Array.isArray(payload.value?.gateway_lines) ? payload.value!.gateway_lines : []
  )

  const selectedGateway = computed<GatewayLine | null>(() => {
    const normalizedSelected = normalizeLineUrl(selectedGatewayUrl.value)
    return (
      gatewayLines.value.find((line) => normalizeLineUrl(line.url) === normalizedSelected) ||
      gatewayLines.value[0] ||
      null
    )
  })

  const gatewayLineCount = computed(() => gatewayLines.value.length)

  const summaryCards = computed(() => [
    {
      label: '商户密钥',
      value: payload.value?.sign_key_configured ? '已配置' : '未配置',
      hint: payload.value?.sign_key_masked || '暂无可展示摘要',
      icon: 'ri:key-2-line'
    },
    {
      label: '通讯密钥',
      value: payload.value?.appkey_configured ? '已配置' : '未配置',
      hint: payload.value?.appkey_masked || '暂无可展示摘要',
      icon: 'ri:apps-2-add-line'
    },
    {
      label: '超时时间',
      value: `${payload.value?.timeout_time ?? 0} 秒`,
      hint: translateMerchantText(payload.value?.timeout_method_label),
      icon: 'ri:timer-line'
    },
    {
      label: '二维码状态',
      value: payload.value?.appkey_configured ? '可生成' : '待生成',
      hint: payload.value?.appkey_configured
        ? selectedGateway.value?.url || '请先选择线路'
        : '生成通讯密钥后即可展示',
      icon: 'ri:qr-code-line'
    }
  ])

  const endpointCards = computed(() =>
    Object.entries(payload.value?.endpoints || {}).map(([key, endpoint]) => {
      const entry = (endpoint || {}) as Record<string, any>

      return {
        key,
        title: endpointTitle(key),
        description: translateMerchantText(entry.description),
        mode: endpointAccessMode(key, entry.method),
        path: entry.path,
        url: entry.url,
        icon: endpointIcon(key)
      }
    })
  )

  watch(
    payload,
    (nextPayload) => {
      const preferred = normalizeLineUrl(String(nextPayload?.default_gateway_url || ''))
      if (
        preferred &&
        gatewayLines.value.some((line) => normalizeLineUrl(line.url) === preferred)
      ) {
        selectedGatewayUrl.value = preferred
        return
      }

      if (gatewayLines.value.length > 0) {
        selectedGatewayUrl.value = normalizeLineUrl(gatewayLines.value[0].url)
        return
      }

      selectedGatewayUrl.value = ''
    },
    { immediate: true }
  )

  function normalizeLineUrl(url: string) {
    return String(url || '')
      .trim()
      .replace(/\/+$/, '')
  }

  function isSelectedLine(url: string) {
    return normalizeLineUrl(url) === normalizeLineUrl(selectedGatewayUrl.value)
  }

  function selectLine(url: string) {
    selectedGatewayUrl.value = normalizeLineUrl(url)
    if (normalizeLineUrl(qrcodePayload.value?.selected_line || '') !== normalizeLineUrl(url)) {
      qrcodePayload.value = null
    }
  }

  function endpointTitle(key: string) {
    const mapping: Record<string, string> = {
      submit: '网页下单地址',
      mapi: '程序下单地址',
      notify: '异步回调地址',
      return: '同步跳转地址'
    }

    return mapping[key] || key
  }

  function endpointAccessMode(key: string, method?: string) {
    const mapping: Record<string, string> = {
      submit: '浏览器跳转或表单提交',
      mapi: '服务端程序发起请求',
      notify: '支付系统异步通知',
      return: '浏览器同步跳转'
    }

    return mapping[key] || method || '--'
  }

  function endpointIcon(key: string) {
    const mapping: Record<string, string> = {
      submit: 'ri:window-line',
      mapi: 'ri:terminal-box-line',
      notify: 'ri:notification-4-line',
      return: 'ri:reply-line'
    }

    return mapping[key] || 'ri:link'
  }

  function resolveMerchantError(error: unknown, fallback: string) {
    if (error instanceof MerchantApiError) {
      return translateMerchantText(error.message, fallback)
    }

    return fallback
  }

  async function loadApiInfo() {
    loading.value = true
    try {
      payload.value = await fetchMerchantApiInfo()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '接口信息加载失败'))
    } finally {
      loading.value = false
    }
  }

  async function handleShowMerchantQrcode() {
    if (!selectedGateway.value) {
      ElMessage.warning('请先选择一条可用线路')
      return
    }

    const bootstrapAppkey = !payload.value?.appkey_configured
    if (bootstrapAppkey) {
      try {
        await ElMessageBox.confirm(
          '当前未配置通讯密钥，继续后会自动生成并展示二维码。',
          '生成通讯密钥',
          {
            type: 'warning',
            confirmButtonText: '继续生成',
            cancelButtonText: '取消'
          }
        )
      } catch {
        return
      }
    }

    qrcodeDialogVisible.value = true
    qrcodeLoading.value = true

    try {
      qrcodePayload.value = (await generateMerchantApiQrcode(selectedGateway.value.url, {
        bootstrapAppkey
      })) as MerchantQrcodePayload
      if (qrcodePayload.value?.appkey_generated) {
        ElMessage.success('已生成通讯密钥并显示二维码')
        await loadApiInfo()
      }
    } catch (error) {
      qrcodeDialogVisible.value = false
      ElMessage.error(resolveMerchantError(error, '商户二维码生成失败'))
    } finally {
      qrcodeLoading.value = false
    }
  }

  async function copyCurrentEncodedPayload() {
    const content = qrcodePayload.value?.content_base64 || ''
    if (!content) {
      ElMessage.warning('请先生成二维码')
      return
    }

    try {
      await navigator.clipboard.writeText(content)
      ElMessage.success('当前密文已复制')
    } catch {
      ElMessage.error('密文复制失败，请手动复制')
    }
  }

  async function handleResetSignKey() {
    signKeyLoading.value = true
    try {
      const data = await resetMerchantSignKey()
      qrcodePayload.value = null
      qrcodeDialogVisible.value = false
      await ElMessageBox.alert(data.key || '重置成功，但当前未返回新的商户密钥。', '新的商户密钥', {
        confirmButtonText: '我已记录'
      })
      ElMessage.success('商户密钥已重置')
      await loadApiInfo()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '商户密钥重置失败'))
    } finally {
      signKeyLoading.value = false
    }
  }

  async function handleResetAppKey() {
    appKeyLoading.value = true
    try {
      const data = await resetMerchantAppKey()
      qrcodePayload.value = null
      qrcodeDialogVisible.value = false
      await ElMessageBox.alert(data.key || '重置成功，但当前未返回新的通讯密钥。', '新的通讯密钥', {
        confirmButtonText: '我已记录'
      })
      ElMessage.success('商户通讯密钥已重置')
      await loadApiInfo()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '通讯密钥重置失败'))
    } finally {
      appKeyLoading.value = false
    }
  }

  onMounted(() => {
    loadApiInfo()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-api-line-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .merchant-api-line {
    display: flex;
    flex-direction: column;
    gap: 14px;
    width: 100%;
    padding: 18px;
    color: inherit;
    text-align: left;
    cursor: pointer;
    background: rgb(148 163 184 / 8%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 18px;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease,
      box-shadow 0.2s ease;
    appearance: none;
  }

  .merchant-api-line:hover {
    transform: translateY(-1px);
    border-color: rgb(86 119 255 / 28%);
    background: rgb(86 119 255 / 7%);
  }

  .merchant-api-line:focus-visible {
    outline: 2px solid rgb(86 119 255 / 42%);
    outline-offset: 2px;
  }

  .merchant-api-line--active {
    border-color: rgb(86 119 255 / 42%);
    background: var(--merchant-active-bg);
    box-shadow: inset 0 0 0 1px rgb(86 119 255 / 12%);
  }

  .merchant-api-line__head {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    justify-content: space-between;
    min-width: 0;
  }

  .merchant-api-line__identity {
    display: flex;
    flex: 1;
    gap: 14px;
    align-items: flex-start;
    min-width: 0;
  }

  .merchant-api-line__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    color: var(--main-color);
    background: rgb(86 119 255 / 12%);
    border-radius: 14px;
    font-size: 20px;
    flex-shrink: 0;
  }

  .merchant-api-line__copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
  }

  .merchant-api-line__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-api-line__copy span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
    word-break: break-all;
    display: none;
  }

  .merchant-api-line__primary,
  .merchant-api-line__endpoint {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    background: color-mix(in srgb, var(--merchant-panel-bg) 82%, transparent);
    border: 1px solid var(--merchant-soft-border);
    border-radius: 16px;
  }

  .merchant-api-line__meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
  }

  .merchant-api-line__label {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.2;
  }

  .merchant-api-line__primary strong,
  .merchant-api-line__endpoint strong {
    color: var(--merchant-heading-color);
    font-family:
      ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
      monospace;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.6;
    overflow-wrap: anywhere;
    word-break: normal;
  }

  .merchant-api-line > .merchant-chip-row--compact {
    display: none;
  }

  .merchant-api-qrcode-panel {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 16px;
  }

  .merchant-api-qrcode-panel__head {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    justify-content: space-between;
  }

  .merchant-api-qrcode-panel__copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .merchant-api-qrcode-panel__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
  }

  .merchant-api-qrcode-panel__copy span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-form-actions--start {
    justify-content: flex-start;
    margin-top: 0;
  }

  .merchant-api-key-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-api-key-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .merchant-api-key-panel__head {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }

  .merchant-api-key-panel__head strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
  }

  .merchant-api-key-panel__value {
    color: var(--merchant-heading-color);
    font-family: Consolas, 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.7;
    word-break: break-all;
  }

  .merchant-api-key-panel__hint {
    color: var(--merchant-muted);
    font-size: 12px;
    line-height: 1.6;
  }

  .merchant-api-actions {
    align-items: stretch;
    margin-top: 16px;
  }

  .merchant-api-endpoint-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .merchant-api-endpoint {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-kv-grid--endpoint {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .merchant-api-endpoint__head {
    display: flex;
    gap: 14px;
    align-items: center;
  }

  .merchant-api-endpoint__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 14px;
    font-size: 18px;
    flex-shrink: 0;
  }

  .merchant-api-endpoint__copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .merchant-api-endpoint__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-api-endpoint__url {
    padding: 13px 14px;
    color: var(--merchant-heading-color);
    background: rgb(15 23 42 / 4%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 14px;
    font-family: Consolas, 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.7;
    word-break: break-all;
  }

  .merchant-api-dialog-loading {
    padding: 8px 0;
  }

  .merchant-api-qrcode-dialog {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .merchant-api-qrcode-dialog__image {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgb(148 163 184 / 8%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 18px;
  }

  .merchant-api-qrcode-dialog__image img {
    width: 240px;
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    background: #fff;
  }

  .merchant-api-qrcode-dialog__tip {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  @media (width <= 960px) {
    .merchant-api-line__meta {
      grid-template-columns: 1fr;
    }

    .merchant-api-key-grid,
    .merchant-api-endpoint-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
