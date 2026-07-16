<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    page-label="支付测试"
    :merchant-login-url="merchantLoginUrl"
  >
    <div class="public-demo-page">
      <section class="demo-hero">
        <div>
          <span class="demo-hero__tag">支付测试</span>
          <h1>公开支付方式展示</h1>
          <p>游客页只展示支付方式和示例金额，真实下单、通道测试和回调验证请进入商户端完成。</p>
        </div>

        <div class="demo-hero__meta">
          <article>
            <small>示例订单号</small>
            <strong>{{ demoOrderNo }}</strong>
          </article>
          <article>
            <small>参考金额</small>
            <strong>¥{{ demoMoney }}</strong>
          </article>
          <article>
            <small>可用方式</small>
            <strong>{{ availableMethods.length }}</strong>
          </article>
        </div>
      </section>

      <div v-if="error" class="demo-alert">
        支付测试动态配置读取失败，当前展示为本地固定版。{{ error }}
      </div>

      <section class="demo-layout">
        <article class="demo-main">
          <div class="demo-steps">
            <div class="demo-step">
              <span>1</span>
              <strong>选择方式</strong>
            </div>
            <div class="demo-step is-active">
              <span>2</span>
              <strong>查看说明</strong>
            </div>
            <div class="demo-step">
              <span>3</span>
              <strong>进入商户端测试</strong>
            </div>
          </div>

          <div class="demo-amount-card">
            <small>示例金额</small>
            <strong>¥{{ demoMoney }}</strong>
            <p>{{ demoName }}</p>
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
              <strong>{{ method.label }}</strong>
              <p>{{ method.description }}</p>
            </button>
          </div>

          <div class="demo-actions">
            <a class="demo-btn demo-btn--primary" :href="merchantLoginUrl">进入商户端</a>
            <a class="demo-btn demo-btn--ghost" href="/#/doc">查看开发文档</a>
          </div>
        </article>

        <aside class="demo-side">
          <div class="demo-side__card">
            <span>当前方式</span>
            <strong>{{ selectedMethod?.label || '未选择' }}</strong>
            <p>{{ selectedMethod?.description || '请选择一种支付方式查看说明。' }}</p>
          </div>

          <div class="demo-side__card">
            <span>使用说明</span>
            <ul>
              <li>游客页不直接创建订单。</li>
              <li>真实通道测试请前往商户端操作。</li>
              <li>支付回调和通知处理由 8787 后端负责。</li>
            </ul>
          </div>

          <div class="demo-side__card">
            <span>环境状态</span>
            <div class="demo-side__facts">
              <div>
                <small>网关状态</small>
                <strong>{{ gatewayConfigured ? '已配置' : '待配置' }}</strong>
              </div>
              <div>
                <small>公开写入</small>
                <strong>{{ supportsWrite ? '开启' : '关闭' }}</strong>
              </div>
              <div>
                <small>支付后端</small>
                <strong>{{ gatewayHostLabel }}</strong>
              </div>
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
      description: '适合扫码、H5、公众号与小程序等微信支付场景。'
    },
    alipay: {
      id: 'alipay',
      label: '支付宝支付',
      badge: 'AL',
      description: '适合二维码、网页拉起和生活缴费等支付宝场景。'
    },
    qqpay: {
      id: 'qqpay',
      label: 'QQ 支付',
      badge: 'QQ',
      description: '适合 QQ 钱包相关扫码和收款场景。'
    }
  }

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const demoName = computed(() => payload.value?.demo_name || '支付测试订单')
  const demoMoney = computed(() => payload.value?.demo_money || '0.01')
  const gatewayConfigured = computed(() => Boolean(payload.value?.gateway_configured))
  const supportsWrite = computed(() => Boolean(payload.value?.supports_write))
  const gatewayHostLabel = computed(() => payload.value?.gateway_host || '8787 后端接口')

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
    gap: 22px;
  }

  .demo-hero,
  .demo-alert,
  .demo-main,
  .demo-side__card,
  .demo-method {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
  }

  .demo-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
    gap: 20px;
    padding: 30px;
  }

  .demo-hero__tag,
  .demo-side__card span {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(97, 115, 255, 0.12);
    color: #5467f5;
    font-size: 0.8rem;
    font-weight: 700;
  }

  .demo-hero h1 {
    margin: 18px 0 12px;
    color: #1f2937;
    font-size: clamp(2rem, 4vw, 3.2rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
  }

  .demo-hero p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .demo-hero__meta {
    display: grid;
    gap: 12px;
  }

  .demo-hero__meta article {
    padding: 18px;
    border-radius: 20px;
    background: #f8fbff;
  }

  .demo-hero__meta small {
    display: block;
    color: #64748b;
  }

  .demo-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: #1f2937;
    line-height: 1.7;
    word-break: break-all;
  }

  .demo-alert {
    padding: 16px 18px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.75;
  }

  .demo-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.82fr);
    gap: 20px;
  }

  .demo-main,
  .demo-side__card {
    padding: 24px;
  }

  .demo-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .demo-step {
    display: grid;
    justify-items: center;
    gap: 8px;
    padding: 16px;
    border-radius: 18px;
    background: #f8fbff;
    color: #64748b;
    text-align: center;
  }

  .demo-step span {
    display: inline-flex;
    width: 32px;
    height: 32px;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #dbeafe;
    color: #5467f5;
    font-weight: 800;
  }

  .demo-step.is-active {
    background: linear-gradient(135deg, #eff6ff, #ecfeff);
    color: #1f2937;
  }

  .demo-amount-card {
    margin-top: 18px;
    padding: 24px;
    border-radius: 22px;
    background: linear-gradient(135deg, #eff6ff, #f8fbff);
  }

  .demo-amount-card small {
    display: block;
    color: #64748b;
  }

  .demo-amount-card strong {
    display: block;
    margin-top: 10px;
    color: #1f2937;
    font-size: 2rem;
  }

  .demo-amount-card p {
    margin: 8px 0 0;
    color: #5f6b7a;
  }

  .demo-methods {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
  }

  .demo-method {
    padding: 20px;
    text-align: left;
    cursor: pointer;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;
  }

  .demo-method:hover,
  .demo-method.is-active {
    transform: translateY(-2px);
    background: #f8fbff;
    box-shadow: 0 18px 36px rgba(97, 115, 255, 0.12);
  }

  .demo-method span {
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(97, 115, 255, 0.12);
    color: #5467f5;
    font-size: 0.8rem;
    font-weight: 700;
  }

  .demo-method strong {
    display: block;
    margin-top: 14px;
    color: #1f2937;
    font-size: 1.08rem;
  }

  .demo-method p {
    margin: 10px 0 0;
    color: #5f6b7a;
    line-height: 1.75;
  }

  .demo-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
  }

  .demo-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
  }

  .demo-btn--primary {
    background: #5467f5;
    color: #fff;
  }

  .demo-btn--ghost {
    background: #eff6ff;
    color: #5467f5;
  }

  .demo-side {
    display: grid;
    gap: 18px;
    align-self: start;
  }

  .demo-side__card strong {
    display: block;
    margin: 14px 0 0;
    color: #1f2937;
    font-size: 1.08rem;
  }

  .demo-side__card p,
  .demo-side__card ul {
    margin: 14px 0 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .demo-side__card ul {
    padding-left: 18px;
  }

  .demo-side__facts {
    display: grid;
    gap: 12px;
    margin-top: 14px;
  }

  .demo-side__facts div {
    padding: 14px 16px;
    border-radius: 18px;
    background: #f8fbff;
  }

  .demo-side__facts small {
    display: block;
    color: #64748b;
  }

  .demo-side__facts strong {
    margin-top: 8px;
  }

  @media (max-width: 980px) {
    .demo-hero,
    .demo-layout,
    .demo-methods {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 640px) {
    .demo-hero,
    .demo-alert,
    .demo-main,
    .demo-side__card,
    .demo-method {
      border-radius: 24px;
    }

    .demo-hero,
    .demo-main,
    .demo-side__card {
      padding: 22px;
    }

    .demo-steps,
    .demo-actions {
      grid-template-columns: 1fr;
      flex-direction: column;
    }

    .demo-btn {
      width: 100%;
    }
  }
</style>
