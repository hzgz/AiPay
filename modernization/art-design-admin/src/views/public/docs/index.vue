<template>
  <PublicShell
    :site-name="siteName"
    :navs="navs"
    :is-logged-in="isLoggedIn"
    page-label="开发文档"
    :merchant-login-url="merchantLoginUrl"
    :merchant-register-url="merchantRegisterUrl"
    :merchant-center-url="merchantDashboardUrl"
  >
    <div class="public-docs-page">
      <section class="docs-hero">
        <div>
          <span class="docs-eyebrow">开发文档</span>
          <h1>{{ activeSectionMeta.title }}</h1>
          <p>{{ activeSectionMeta.description }}</p>
        </div>

        <div class="docs-hero__meta">
          <div>
            <small>当前栏目</small>
            <strong>{{ activeSectionMeta.shortLabel }}</strong>
          </div>
          <div>
            <small>文档分组</small>
            <strong>{{ docGroups.length }}</strong>
          </div>
          <div>
            <small>配置项数</small>
            <strong>{{ docFieldCount }}</strong>
          </div>
        </div>
      </section>

      <div v-if="error" class="docs-alert">文档内容暂时无法刷新，请稍后再试。</div>

      <section class="docs-layout">
        <aside class="docs-side">
          <div class="docs-side__section">
            <span class="docs-eyebrow">文档目录</span>

            <RouterLink
              v-for="item in sectionNavItems"
              :key="item.key"
              :to="item.to"
              :class="['docs-side__nav', { 'is-active': item.key === activeSection }]"
            >
              <strong>{{ item.title }}</strong>
              <small>{{ item.note }}</small>
            </RouterLink>
          </div>

          <div class="docs-side__section">
            <span class="docs-eyebrow">常用入口</span>
            <a class="docs-side__link" :href="merchantLoginUrl">商户登录</a>
            <a class="docs-side__link" :href="merchantRegisterUrl">注册商户</a>
            <a class="docs-side__link" :href="merchantApiUrl">接口配置</a>
          </div>
        </aside>

        <div class="docs-content">
          <article v-for="group in docGroups" :key="group.id" class="docs-group">
            <div class="docs-group__head">
              <span>{{ group.eyebrow }}</span>
              <h2>{{ group.title }}</h2>
              <p>{{ group.description }}</p>
            </div>

            <div class="docs-rows">
              <div v-for="item in group.items" :key="`${group.id}-${item.label}`" class="docs-row">
                <div class="docs-row__label">
                  <strong>{{ item.label }}</strong>
                  <small v-if="item.note">{{ item.note }}</small>
                </div>

                <div class="docs-row__value">
                  <code>{{ item.value }}</code>
                  <button v-if="item.copyable !== false" type="button" @click="copyDocValue(item.value)">
                    复制
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import { fetchPublicDoc, resolvePublicBackendOrigin, type PublicDocPayload } from '@/api/public-site'
  import PublicShell from '../shared/public-shell.vue'
  import { resolvePublicErrorMessage, scrollPublicPageToTop } from '../shared/public-state'

  defineOptions({ name: 'PublicDocsPage' })

  interface DocRowItem {
    label: string
    value: string
    note?: string
    copyable?: boolean
  }

  interface DocGroupCard {
    id: string
    eyebrow: string
    title: string
    description: string
    items: DocRowItem[]
  }

  type SectionKey = 'overview' | 'api' | 'result' | 'findorder'

  const route = useRoute()
  const loading = ref(false)
  const error = ref('')
  const payload = ref<PublicDocPayload | null>(null)

  const activeSection = computed<SectionKey>(() => {
    if (route.path === '/doc/api') {
      return 'api'
    }

    if (route.path === '/doc/result') {
      return 'result'
    }

    if (route.path === '/doc/findorder') {
      return 'findorder'
    }

    return 'overview'
  })

  const sectionNavItems = [
    {
      key: 'overview',
      title: '页面跳转支付',
      note: '提交地址与基础参数',
      to: '/doc'
    },
    {
      key: 'api',
      title: 'API 接口支付',
      note: 'MAPI、聚合接口与辅助地址',
      to: '/doc/api'
    },
    {
      key: 'result',
      title: '支付结果通知',
      note: '回调字段与验签规则',
      to: '/doc/result'
    },
    {
      key: 'findorder',
      title: '订单查询',
      note: '查单参数与返回字段',
      to: '/doc/findorder'
    }
  ]

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const isLoggedIn = computed(() => Boolean(payload.value?.is_logged_in))
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const merchantRegisterUrl = computed(
    () => payload.value?.merchant_register_url || '/#/merchant/register'
  )
  const merchantDashboardUrl = computed(() => '/#/merchant/dashboard')
  const merchantApiUrl = computed(() => '/#/merchant/api')
  const backendOrigin = computed(() => resolvePublicBackendOrigin() || '')

  const activeSectionMeta = computed(() => {
    const map: Record<SectionKey, { title: string; shortLabel: string; description: string }> = {
      overview: {
        title: '页面跳转支付文档',
        shortLabel: '跳转支付',
        description: '整理网页支付入口、基础参数与回调地址。'
      },
      api: {
        title: 'API 接口支付文档',
        shortLabel: 'API 支付',
        description: '集中展示接口下单、查单与常用地址。'
      },
      result: {
        title: '支付结果通知文档',
        shortLabel: '结果通知',
        description: '说明异步通知、同步返回与商户验签规则。'
      },
      findorder: {
        title: '订单查询文档',
        shortLabel: '订单查询',
        description: '说明查单入口、参数格式与返回字段。'
      }
    }

    return map[activeSection.value]
  })

  const docGroups = computed<DocGroupCard[]>(() => buildDocGroups(activeSection.value))
  const docFieldCount = computed(() =>
    docGroups.value.reduce((total, group) => total + group.items.length, 0)
  )

  async function loadPage() {
    loading.value = true
    error.value = ''

    try {
      payload.value = await fetchPublicDoc(activeSection.value === 'overview' ? undefined : activeSection.value)
      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '开发文档内容暂时无法刷新。')
    } finally {
      loading.value = false
    }
  }

  async function copyDocValue(value: string) {
    try {
      await navigator.clipboard.writeText(value)
      ElMessage.success('已复制')
    } catch {
      ElMessage.error('复制失败')
    }
  }

  function buildDocGroups(section: SectionKey): DocGroupCard[] {
    const backend = backendOrigin.value
    const frontend = typeof window === 'undefined' ? '' : window.location.origin

    const frontendHash = (path: string) => (frontend ? `${frontend}/#${path}` : `/#${path}`)
    const backendUrl = (path: string) => (backend ? `${backend}${path}` : path)

    const commonParams: DocRowItem[] = [
      { label: 'pid', value: '商户 ID', copyable: false },
      { label: 'type', value: '支付方式标识，例如 wxpay / alipay / qqpay', copyable: false },
      { label: 'out_trade_no', value: '商户订单号，必须唯一', copyable: false },
      { label: 'name', value: '商品名称或订单名称', copyable: false },
      { label: 'money', value: '订单金额，单位元，保留两位小数', copyable: false },
      { label: 'notify_url', value: '异步通知地址', copyable: false },
      { label: 'return_url', value: '同步跳转地址', copyable: false },
      { label: 'param', value: '附加参数，可选', copyable: false },
      { label: 'sign', value: '签名值', copyable: false },
      { label: 'sign_type', value: '签名类型，当前为 MD5', copyable: false }
    ]

    if (section === 'api') {
      return [
        {
          id: 'api-endpoints',
          eyebrow: '接口地址',
          title: 'API 下单入口',
          description: '提供接口下单、支付创建与订单查询入口。',
          items: [
            { label: '兼容 MAPI', value: backendUrl('/mapi.php') },
            { label: '聚合接口', value: backendUrl('/Api/mapi') },
            { label: '支付创建', value: backendUrl('/Api/payment') },
            { label: '订单查询', value: backendUrl('/Api/findorder') }
          ]
        },
        {
          id: 'api-params',
          eyebrow: '请求参数',
          title: '下单参数说明',
          description: '页面跳转与 API 支付共用以下基础参数。',
          items: commonParams
        },
        {
          id: 'api-helper',
          eyebrow: '商户入口',
          title: '商户常用地址',
          description: '商户登录、控制台与接口配置入口。',
          items: [
            { label: '商户登录', value: frontendHash('/merchant/login') },
            { label: '商户控制台', value: frontendHash('/merchant/dashboard') },
            { label: '接口配置', value: frontendHash('/merchant/api') }
          ]
        }
      ]
    }

    if (section === 'result') {
      return [
        {
          id: 'result-callback',
          eyebrow: '回调地址',
          title: '支付结果通知',
          description: '支付完成后，系统会按兼容规则回调商户地址并附带签名参数。',
          items: [
            { label: '异步通知地址', value: backendUrl('/Notify/epay_notifyzj') },
            { label: '同步返回地址', value: backendUrl('/Notify/epay_returnzj') },
            { label: '通知方式', value: '支持 GET 或 POST', copyable: false },
            { label: '成功状态', value: 'TRADE_SUCCESS', copyable: false }
          ]
        },
        {
          id: 'result-fields',
          eyebrow: '回调字段',
          title: '回调参数列表',
          description: '当前系统的商户回调 payload 统一包含以下字段。',
          items: [
            { label: 'pid', value: '商户 ID', copyable: false },
            { label: 'trade_no', value: '平台订单号', copyable: false },
            { label: 'out_trade_no', value: '商户订单号', copyable: false },
            { label: 'type', value: '支付方式标识', copyable: false },
            { label: 'money', value: '订单金额', copyable: false },
            { label: 'trade_status', value: '固定返回 TRADE_SUCCESS', copyable: false },
            { label: 'name', value: '商品名称，开启隐藏后不返回', copyable: false },
            { label: 'sign', value: 'MD5 验签结果', copyable: false },
            { label: 'sign_type', value: '固定为 MD5', copyable: false }
          ]
        },
        {
          id: 'result-sign',
          eyebrow: '验签规则',
          title: '商户侧强烈建议校验签名',
          description: '先按参数名升序排列，再拼接商户密钥计算 MD5。',
          items: [
            { label: '排序规则', value: '按参数名升序排序', copyable: false },
            { label: '忽略字段', value: 'sign、sign_type 和空值', copyable: false },
            { label: '拼接规则', value: 'key1=value1&key2=value2... 加上商户密钥', copyable: false },
            { label: '签名算法', value: 'md5(拼接结果)', copyable: false }
          ]
        }
      ]
    }

    if (section === 'findorder') {
      return [
        {
          id: 'findorder-endpoint',
          eyebrow: '查单接口',
          title: '兼容查单地址',
          description: '保留旧系统的订单查询接口，适用于兼容式接入场景。',
          items: [
            { label: '查单接口', value: backendUrl('/Api/findorder') },
            { label: 'order_no', value: '平台订单号或商户订单号', copyable: false },
            { label: 'type', value: '1 优先 trade_no，其他值优先 out_trade_no', copyable: false }
          ]
        },
        {
          id: 'findorder-response',
          eyebrow: '返回字段',
          title: '查单结果说明',
          description: '查单成功后，可从返回字段判断金额、状态与回调地址。',
          items: [
            { label: 'trade_no', value: '平台订单号', copyable: false },
            { label: 'out_trade_no', value: '商户订单号', copyable: false },
            { label: 'money', value: '订单金额', copyable: false },
            { label: 'truemoney', value: '实际金额', copyable: false },
            { label: 'status', value: '订单状态', copyable: false },
            { label: 'notify_url', value: '异步通知地址', copyable: false },
            { label: 'return_url', value: '同步跳转地址', copyable: false }
          ]
        },
        {
          id: 'findorder-console',
          eyebrow: '后台入口',
          title: '商户订单中心',
          description: '也可直接在商户后台查看订单列表和详情。',
          items: [
            { label: '商户订单列表', value: frontendHash('/merchant/orders') },
            { label: '商户控制台', value: frontendHash('/merchant/dashboard') },
            { label: '接口配置', value: frontendHash('/merchant/api') }
          ]
        }
      ]
    }

    return [
      {
        id: 'overview-entry',
        eyebrow: '基础入口',
        title: '访客入口与商户入口',
        description: '常用公开入口与商户入口如下。',
        items: [
          { label: '前台首页', value: frontend || '/' },
          { label: '商户登录', value: frontendHash('/merchant/login') },
          { label: '商户注册', value: frontendHash('/merchant/register') },
          { label: '支付测试', value: frontendHash('/demo') }
        ]
      },
      {
        id: 'overview-submit',
        eyebrow: '跳转支付',
        title: '页面提交与回调地址',
        description: '提供网页支付提交地址与回调地址。',
        items: [
          { label: '兼容提交地址', value: backendUrl('/submit.php') },
          { label: '当前提交地址', value: backendUrl('/Pay/submit') },
          { label: '异步通知地址', value: backendUrl('/Notify/epay_notifyzj') },
          { label: '同步返回地址', value: backendUrl('/Notify/epay_returnzj') }
        ]
      },
      {
        id: 'overview-params',
        eyebrow: '请求参数',
        title: '常用下单参数',
        description: '页面跳转与 API 支付共用的基础参数如下。',
        items: commonParams
      }
    ]
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
  .public-docs-page {
    display: grid;
    gap: 28px;
  }

  .docs-eyebrow,
  .docs-group__head span {
    display: inline-flex;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .docs-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(260px, 0.7fr);
    gap: 30px;
    align-items: end;
  }

  .docs-hero h1 {
    margin: 14px 0 14px;
    color: var(--public-title);
    font-size: clamp(2.2rem, 4vw, 3.8rem);
    line-height: 1.08;
    letter-spacing: -0.05em;
  }

  .docs-hero p {
    margin: 0;
    max-width: 760px;
    color: var(--public-text);
    line-height: 1.9;
  }

  .docs-hero__meta {
    display: grid;
    gap: 14px;
  }

  .docs-hero__meta div,
  .docs-side__section,
  .docs-group,
  .docs-alert {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 14px;
  }

  .docs-hero__meta small {
    display: block;
    color: var(--public-muted);
  }

  .docs-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: var(--public-title);
    font-size: 1.02rem;
  }

  .docs-alert {
    color: #b45309;
    line-height: 1.8;
  }

  .docs-layout {
    display: grid;
    grid-template-columns: minmax(250px, 0.82fr) minmax(0, 1.8fr);
    gap: 32px;
  }

  .docs-side {
    position: sticky;
    top: 96px;
    display: grid;
    gap: 24px;
    align-self: start;
  }

  .docs-side__nav,
  .docs-side__link {
    display: block;
    padding: 14px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    color: inherit;
    text-decoration: none;
  }

  .docs-side__nav:last-child,
  .docs-side__link:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .docs-side__nav strong {
    display: block;
    color: var(--public-title);
  }

  .docs-side__nav small {
    display: block;
    margin-top: 6px;
    color: var(--public-text);
    line-height: 1.7;
  }

  .docs-side__nav.is-active {
    color: var(--public-accent);
  }

  .docs-content {
    display: grid;
    gap: 28px;
  }

  .docs-group__head h2 {
    margin: 12px 0 10px;
    color: var(--public-title);
    font-size: 1.48rem;
    line-height: 1.3;
  }

  .docs-group__head p {
    margin: 0;
    color: var(--public-text);
    line-height: 1.84;
  }

  .docs-rows {
    display: grid;
    margin-top: 18px;
  }

  .docs-row {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: 20px;
    align-items: start;
    padding: 16px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  }

  .docs-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .docs-row__label strong {
    display: block;
    color: var(--public-title);
  }

  .docs-row__label small {
    display: block;
    margin-top: 6px;
    color: var(--public-muted);
    line-height: 1.6;
  }

  .docs-row__value {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
  }

  .docs-row__value code {
    display: block;
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border-radius: 16px;
    background: #f8fafc;
    color: #1f2937;
    font-family: 'Cascadia Code', 'Consolas', monospace;
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.75;
  }

  .docs-row__value button {
    flex-shrink: 0;
    min-width: 72px;
    min-height: 40px;
    padding: 0 14px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: #fff;
    color: var(--public-title);
    font-weight: 700;
    cursor: pointer;
  }

  @media (max-width: 980px) {
    .docs-hero,
    .docs-layout {
      grid-template-columns: 1fr;
    }

    .docs-side {
      position: static;
    }
  }

  @media (max-width: 720px) {
    .docs-row {
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .docs-row__value {
      flex-direction: column;
      align-items: stretch;
    }

    .docs-row__value button {
      width: 100%;
    }
  }
</style>
