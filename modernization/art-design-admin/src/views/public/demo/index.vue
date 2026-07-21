<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    page-label="支付测试"
    :merchant-login-url="merchantLoginUrl"
  >
    <div class="public-demo-page">
      <section class="demo-hero">
        <div class="demo-hero__copy">
          <span class="demo-eyebrow">支付测试</span>
          <h1>支付测试</h1>
          <p>查看当前开放的支付方式。</p>
        </div>

        <div class="demo-hero__actions">
          <a class="demo-link demo-link--primary" :href="merchantLoginUrl">商户登录</a>
          <a class="demo-link" href="/#/doc">开发文档</a>
        </div>
      </section>

      <div v-if="error" class="demo-alert">{{ error }}</div>

      <section class="demo-summary">
        <div class="demo-summary__item">
          <small>金额</small>
          <strong>￥{{ demoMoney }}</strong>
        </div>
        <div class="demo-summary__item">
          <small>名称</small>
          <strong>{{ demoName }}</strong>
        </div>
        <div class="demo-summary__item">
          <small>开放数量</small>
          <strong>{{ availableMethods.length }}</strong>
        </div>
        <div class="demo-summary__item">
          <small>网关状态</small>
          <strong>{{ gatewayStatusLabel }}</strong>
        </div>
      </section>

      <section v-if="availableMethods.length" class="demo-methods">
        <button
          v-for="method in availableMethods"
          :key="method.id"
          type="button"
          :class="['demo-method-chip', { 'is-active': selectedMethodId === method.id }]"
          @click="selectedMethodId = method.id"
        >
          {{ method.label }}
        </button>
      </section>

      <section v-if="selectedMethod" class="demo-panel">
        <article class="demo-focus">
          <div class="demo-focus__badge">{{ selectedMethod.badge }}</div>

          <div class="demo-focus__content">
            <span class="demo-eyebrow">选中方式</span>
            <h2>{{ selectedMethod.label }}</h2>
            <p>{{ selectedMethod.description }}</p>

            <div class="demo-focus__tips">
              <span>订单编号：{{ demoOrderNo }}</span>
            </div>
          </div>
        </article>

        <aside class="demo-order">
          <span class="demo-eyebrow">订单摘要</span>

          <div class="demo-order__rows">
            <div class="demo-order__row">
              <small>金额</small>
              <strong>￥{{ demoMoney }}</strong>
            </div>
            <div class="demo-order__row">
              <small>名称</small>
              <strong>{{ demoName }}</strong>
            </div>
            <div class="demo-order__row">
              <small>订单编号</small>
              <strong>{{ demoOrderNo }}</strong>
            </div>
            <div class="demo-order__row">
              <small>状态</small>
              <strong>{{ gatewayStatusLabel }}</strong>
            </div>
          </div>
        </aside>
      </section>

      <section v-else class="demo-empty">
        <strong>当前没有对外展示的支付方式</strong>
        <p>当前暂无可展示的支付方式。</p>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import { fetchPublicDemo, type PublicDemoPayload } from '@/api/public-site'
  import PublicShell from '../shared/public-shell.vue'
  import { resolvePublicErrorMessage, scrollPublicPageToTop } from '../shared/public-state'

  defineOptions({ name: 'PublicDemoPage' })

  interface DemoMethodView {
    id: string
    label: string
    badge: string
    description: string
  }

  const route = useRoute()
  const error = ref('')
  const payload = ref<PublicDemoPayload | null>(null)
  const selectedMethodId = ref('')
  const demoOrderNo = ref('')

  const fallbackMethodMap: Record<string, DemoMethodView> = {
    wxpay: {
      id: 'wxpay',
      label: '微信支付',
      badge: 'WX',
      description: '适合微信扫码、H5 与公众号支付场景。'
    },
    alipay: {
      id: 'alipay',
      label: '支付宝',
      badge: 'AL',
      description: '适合支付宝扫码、拉起支付和收款场景。'
    },
    qqpay: {
      id: 'qqpay',
      label: 'QQ 钱包',
      badge: 'QQ',
      description: '适合 QQ 钱包扫码和收款场景。'
    }
  }

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const demoName = computed(() => payload.value?.demo_name || '支付测试')
  const demoMoney = computed(() => payload.value?.demo_money || '0.01')
  const gatewayConfigured = computed(() => Boolean(payload.value?.gateway_configured))
  const gatewayStatusLabel = computed(() => (gatewayConfigured.value ? '已配置' : '未设置'))

  const availableMethods = computed<DemoMethodView[]>(() => {
    const source = payload.value?.available_methods || Object.values(fallbackMethodMap)
    return source.map((method) => {
      const fallback = fallbackMethodMap[method.id]
      if (fallback) return fallback

      return {
        id: method.id,
        label: method.label,
        badge: method.label.slice(0, 2).toUpperCase(),
        description: method.description || '该方式当前可用。'
      }
    })
  })

  const selectedMethod = computed(
    () => availableMethods.value.find((item) => item.id === selectedMethodId.value) || null
  )

  watch(
    () => availableMethods.value,
    (methods) => {
      if (!methods.length) {
        selectedMethodId.value = ''
        return
      }

      if (!methods.some((item) => item.id === selectedMethodId.value)) {
        selectedMethodId.value = methods[0]?.id || ''
      }
    },
    { deep: true, immediate: true }
  )

  async function loadPage() {
    error.value = ''

    try {
      payload.value = await fetchPublicDemo()
      demoOrderNo.value = createDemoOrderNo()
      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '支付测试内容暂时无法加载，请稍后再试。')
      demoOrderNo.value = createDemoOrderNo()
    }
  }

  function createDemoOrderNo() {
    const now = new Date()
    const pad = (value: number) => String(value).padStart(2, '0')
    return `P${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`
  }

  watch(
    () => route.fullPath,
    () => {
      void loadPage()
    },
    { immediate: true }
  )
</script>

<style scoped lang="scss">
  .public-demo-page {
    display: grid;
    gap: 28px;
  }

  .demo-eyebrow {
    display: inline-flex;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
  }

  .demo-hero,
  .demo-summary,
  .demo-methods,
  .demo-panel,
  .demo-empty,
  .demo-alert {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 16px;
  }

  .demo-hero {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 20px;
  }

  .demo-hero h1 {
    margin: 14px 0 14px;
    color: var(--public-title);
    font-size: clamp(2.2rem, 4vw, 3.9rem);
    line-height: 1.08;
    letter-spacing: -0.05em;
  }

  .demo-hero p {
    margin: 0;
    max-width: 760px;
    color: var(--public-text);
    line-height: 1.9;
  }

  .demo-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-start;
  }

  .demo-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: #fff;
    color: var(--public-title);
    font-weight: 700;
    text-decoration: none;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .demo-link:hover {
    border-color: rgba(24, 32, 47, 0.16);
    background: #f8fafc;
  }

  .demo-link--primary {
    background: #18202f;
    border-color: #18202f;
    color: #fff;
  }

  .demo-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }

  .demo-summary__item small,
  .demo-order__row small {
    display: block;
    color: var(--public-muted);
  }

  .demo-summary__item strong,
  .demo-order__row strong {
    display: block;
    margin-top: 8px;
    color: var(--public-title);
    line-height: 1.7;
    word-break: break-all;
  }

  .demo-methods {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .demo-method-chip {
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 999px;
    background: #fff;
    color: var(--public-title);
    font-weight: 700;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .demo-method-chip.is-active {
    background: #18202f;
    border-color: #18202f;
    color: #fff;
  }

  .demo-panel {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.8fr);
    gap: 32px;
  }

  .demo-focus {
    display: grid;
    grid-template-columns: 88px minmax(0, 1fr);
    gap: 20px;
    align-items: start;
  }

  .demo-focus__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 88px;
    height: 88px;
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.08), rgba(15, 23, 42, 0.16));
    color: var(--public-title);
    font-size: 1.32rem;
    font-weight: 800;
    letter-spacing: 0.08em;
  }

  .demo-focus__content h2 {
    margin: 12px 0 10px;
    color: var(--public-title);
    font-size: 1.72rem;
    line-height: 1.25;
    letter-spacing: -0.04em;
  }

  .demo-focus__content p,
  .demo-empty p {
    margin: 0;
    color: var(--public-text);
    line-height: 1.84;
  }

  .demo-focus__tips {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
  }

  .demo-focus__tips span,
  .detail-pill {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    padding: 0 14px;
    border-radius: 999px;
    background: #f8fafc;
    color: #445167;
    font-size: 0.94rem;
  }

  .demo-order {
    display: grid;
    gap: 18px;
  }

  .demo-order__rows {
    display: grid;
  }

  .demo-order__row {
    padding: 14px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  }

  .demo-order__row:last-child {
    padding-bottom: 0;
    border-bottom: 0;
  }

  .demo-empty strong {
    display: block;
    margin-bottom: 10px;
    color: var(--public-title);
  }

  .demo-alert {
    color: #b45309;
    line-height: 1.8;
  }

  @media (max-width: 980px) {
    .demo-summary,
    .demo-panel {
      grid-template-columns: 1fr 1fr;
    }
  }

  @media (max-width: 720px) {
    .public-demo-page {
      gap: 22px;
    }

    .demo-summary {
      grid-template-columns: 1fr 1fr;
    }

    .demo-panel,
    .demo-focus {
      grid-template-columns: 1fr;
    }

    .demo-hero__actions {
      width: 100%;
    }

    .demo-methods {
      flex-wrap: nowrap;
      overflow-x: auto;
      padding-bottom: 4px;
      scrollbar-width: none;
    }

    .demo-methods::-webkit-scrollbar {
      display: none;
    }

    .demo-method-chip {
      flex: 0 0 auto;
      white-space: nowrap;
    }

    .demo-link {
      flex: 1;
    }

    .demo-focus__badge {
      width: 72px;
      height: 72px;
      border-radius: 22px;
    }
  }

  @media (max-width: 560px) {
    .demo-summary {
      grid-template-columns: 1fr;
    }

    .demo-link {
      width: 100%;
      flex: initial;
    }
  }
</style>
