<template>
  <PublicShell :site-name="siteName" :navs="navs" page-label="支付测试" :merchant-login-url="merchantLoginUrl">
    <div class="public-demo-page">
      <section class="demo-hero">
        <div>
          <span class="demo-eyebrow">支付测试</span>
          <h1>公开前台仅展示支付方式和测试说明</h1>
          <p>真实下单、通道测试与回调验证请进入商户端完成。这里保留方式预览、示例金额和商户入口。</p>
        </div>

        <div class="demo-hero__meta">
          <div>
            <small>示例订单号</small>
            <strong>{{ demoOrderNo }}</strong>
          </div>
          <div>
            <small>示例金额</small>
            <strong>￥{{ demoMoney }}</strong>
          </div>
          <div>
            <small>可用方式</small>
            <strong>{{ availableMethods.length }}</strong>
          </div>
        </div>
      </section>

      <div v-if="error" class="demo-alert">
        支付测试数据暂时不可用，当前展示为默认内容。{{ error }}
      </div>

      <section class="demo-layout">
        <article class="demo-main">
          <div class="demo-main__head">
            <div>
              <span class="demo-eyebrow">方式列表</span>
              <h2>选择一个支付方式查看展示说明</h2>
            </div>

            <a class="demo-link" href="/#/doc">查看开发文档</a>
          </div>

          <div class="demo-methods">
            <button
              v-for="method in availableMethods"
              :key="method.id"
              type="button"
              :class="['demo-method', { 'is-active': selectedMethodId === method.id }]"
              @click="selectedMethodId = method.id"
            >
              <span>{{ method.badge }}</span>
              <div>
                <strong>{{ method.label }}</strong>
                <p>{{ method.description }}</p>
              </div>
            </button>

            <div v-if="!availableMethods.length" class="demo-method demo-method--empty">当前暂无公开展示的支付方式</div>
          </div>
        </article>

        <aside class="demo-side">
          <div class="demo-side__section">
            <span class="demo-eyebrow">当前方式</span>
            <strong>{{ selectedMethod?.label || '未选择' }}</strong>
            <p>{{ selectedMethod?.description || '请选择一种支付方式查看说明。' }}</p>
          </div>

          <div class="demo-side__section">
            <span class="demo-eyebrow">示例信息</span>

            <div class="demo-side__rows">
              <div class="demo-side__row">
                <small>示例金额</small>
                <strong>￥{{ demoMoney }}</strong>
              </div>
              <div class="demo-side__row">
                <small>订单名称</small>
                <strong>{{ demoName }}</strong>
              </div>
              <div class="demo-side__row">
                <small>网关状态</small>
                <strong>{{ gatewayConfigured ? '已配置' : '待配置' }}</strong>
              </div>
            </div>
          </div>

          <div class="demo-side__section">
            <span class="demo-eyebrow">下一步</span>
            <p>如需继续测试通道、真实支付和回调流程，请先登录商户端。</p>

            <div class="demo-side__actions">
              <a class="demo-button demo-button--primary" :href="merchantLoginUrl">进入商户端</a>
              <a class="demo-button demo-button--secondary" href="/#/doc">查看文档</a>
            </div>
          </div>
        </aside>
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
  const loading = ref(false)
  const error = ref('')
  const payload = ref<PublicDemoPayload | null>(null)
  const selectedMethodId = ref('')
  const demoOrderNo = ref('')

  const fallbackMethodMap: Record<string, DemoMethodView> = {
    wxpay: {
      id: 'wxpay',
      label: '微信支付',
      badge: 'WX',
      description: '适合扫码、H5、公众号和小程序等微信支付场景。'
    },
    alipay: {
      id: 'alipay',
      label: '支付宝支付',
      badge: 'AL',
      description: '适合二维码、网页拉起和生活缴费等支付宝支付场景。'
    },
    qqpay: {
      id: 'qqpay',
      label: 'QQ 支付',
      badge: 'QQ',
      description: '适合 QQ 钱包相关的扫码和收款场景。'
    }
  }

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const demoName = computed(() => payload.value?.demo_name || '支付测试订单')
  const demoMoney = computed(() => payload.value?.demo_money || '0.01')
  const gatewayConfigured = computed(() => Boolean(payload.value?.gateway_configured))

  const availableMethods = computed<DemoMethodView[]>(() => {
    const source = payload.value?.available_methods || Object.values(fallbackMethodMap)
    return source.map((method) => fallbackMethodMap[method.id] || {
      id: method.id,
      label: method.label,
      badge: method.label.slice(0, 2).toUpperCase(),
      description: method.description || '该方式已启用，可在商户端继续测试。'
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
    loading.value = true
    error.value = ''

    try {
      payload.value = await fetchPublicDemo()
      demoOrderNo.value = createDemoOrderNo()
      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '支付测试动态数据暂时不可用。')
      demoOrderNo.value = createDemoOrderNo()
    } finally {
      loading.value = false
    }
  }

  function createDemoOrderNo() {
    const now = new Date()
    const pad = (value: number) => String(value).padStart(2, '0')

    return `T${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`
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
    text-transform: uppercase;
  }

  .demo-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(260px, 0.7fr);
    gap: 30px;
    align-items: end;
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

  .demo-hero__meta,
  .demo-side__rows {
    display: grid;
    gap: 14px;
  }

  .demo-hero__meta div,
  .demo-main,
  .demo-side__section,
  .demo-alert {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 14px;
  }

  .demo-hero__meta small,
  .demo-side__row small {
    display: block;
    color: var(--public-muted);
  }

  .demo-hero__meta strong,
  .demo-side__row strong,
  .demo-side__section > strong {
    display: block;
    margin-top: 8px;
    color: var(--public-title);
    line-height: 1.7;
    word-break: break-all;
  }

  .demo-alert {
    color: #b45309;
    line-height: 1.8;
  }

  .demo-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.82fr);
    gap: 32px;
  }

  .demo-main__head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
  }

  .demo-main__head h2 {
    margin: 10px 0 0;
    color: var(--public-title);
    font-size: 1.72rem;
    line-height: 1.25;
    letter-spacing: -0.04em;
  }

  .demo-link,
  .demo-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
  }

  .demo-link,
  .demo-button--secondary {
    border-color: var(--public-border);
    background: #fff;
    color: var(--public-title);
  }

  .demo-button--primary {
    background: #18202f;
    color: #fff;
  }

  .demo-methods {
    display: grid;
  }

  .demo-method {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
    padding: 18px 0;
    border: 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    background: transparent;
    text-align: left;
    cursor: pointer;
  }

  .demo-method:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .demo-method span {
    display: inline-flex;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #f3f5f8;
    color: var(--public-title);
    font-size: 0.86rem;
    font-weight: 700;
  }

  .demo-method strong {
    display: block;
    color: var(--public-title);
    font-size: 1.02rem;
  }

  .demo-method p {
    margin: 8px 0 0;
    color: var(--public-text);
    line-height: 1.82;
  }

  .demo-method.is-active span {
    background: #18202f;
    color: #fff;
  }

  .demo-method--empty {
    grid-template-columns: 1fr;
    color: var(--public-text);
    cursor: default;
  }

  .demo-side {
    display: grid;
    gap: 24px;
    align-self: start;
  }

  .demo-side__section p {
    margin: 10px 0 0;
    color: var(--public-text);
    line-height: 1.82;
  }

  .demo-side__row {
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  }

  .demo-side__row:last-child {
    padding-bottom: 0;
    border-bottom: 0;
  }

  .demo-side__actions {
    display: grid;
    gap: 10px;
    margin-top: 14px;
  }

  @media (max-width: 980px) {
    .demo-hero,
    .demo-layout {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 720px) {
    .demo-main__head {
      flex-direction: column;
      align-items: stretch;
    }

    .demo-link,
    .demo-button {
      width: 100%;
    }

    .demo-method {
      grid-template-columns: 1fr;
    }
  }
</style>
