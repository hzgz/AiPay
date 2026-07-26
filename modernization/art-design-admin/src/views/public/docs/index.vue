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
        <div class="docs-hero__copy">
          <span class="docs-eyebrow">开发文档</span>
          <h1>{{ activeSectionMeta.title }}</h1>
          <p>{{ activeSectionMeta.description }}</p>
        </div>

        <div class="docs-hero__actions">
          <a class="docs-link docs-link--primary" :href="merchantLoginUrl">商户登录</a>
          <a class="docs-link" :href="merchantRegisterUrl">注册商户</a>
          <a class="docs-link" :href="merchantApiUrl">接口配置</a>
        </div>
      </section>

      <div v-if="error" class="docs-alert">{{ error }}</div>

      <nav class="docs-nav">
        <RouterLink
          v-for="item in sectionNavItems"
          :key="item.key"
          :to="item.to"
          :class="['docs-nav__item', { 'is-active': item.key === activeSection }]"
        >
          {{ item.title }}
        </RouterLink>
      </nav>

      <section class="docs-content">
        <article v-for="group in docGroups" :key="group.id" class="docs-group">
          <div class="docs-group__head">
            <span class="docs-eyebrow">{{ group.eyebrow }}</span>
            <h2>{{ group.title }}</h2>
            <p v-if="group.description">{{ group.description }}</p>
          </div>

          <div class="docs-rows">
            <div
              v-for="item in visibleGroupItems(group)"
              :key="`${group.id}-${item.label}`"
              class="docs-row"
            >
              <div class="docs-row__label">
                <strong>{{ item.label }}</strong>
                <small v-if="item.note">{{ item.note }}</small>
              </div>

              <div class="docs-row__value">
                <code>{{ item.value }}</code>
                <button
                  v-if="item.copyable !== false"
                  type="button"
                  @click="copyDocValue(item.copyValue || item.value)"
                >
                  复制
                </button>
              </div>
            </div>
          </div>

          <button
            v-if="showGroupToggle(group)"
            type="button"
            class="docs-group__toggle"
            @click="toggleGroup(group.id)"
          >
            {{ groupToggleLabel(group) }}
          </button>
        </article>
      </section>
    </div>
  </PublicShell>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import {
    fetchPublicDoc,
    resolvePublicBackendOrigin,
    type PublicDocPayload
  } from '@/api/publicSite'
  import PublicShell from '../shared/PublicShell.vue'
  import {
    appendPublicAffiliateQuery,
    resolvePublicAffiliateId,
    resolvePublicErrorMessage,
    scrollPublicPageToTop
  } from '../shared/publicState'

  defineOptions({ name: 'PublicDocsPage' })

  interface DocRowItem {
    label: string
    value: string
    copyValue?: string
    note?: string
    copyable?: boolean
  }

  interface DocGroupCard {
    id: string
    eyebrow: string
    title: string
    description: string
    items: DocRowItem[]
    collapseAfter?: number
  }

  type SectionKey = 'overview' | 'api' | 'result' | 'findorder'

  const route = useRoute()
  const error = ref('')
  const payload = ref<PublicDocPayload | null>(null)
  const expandedGroupMap = ref<Record<string, boolean>>({})
  const isMobileViewport = ref(false)

  const activeSection = computed<SectionKey>(() => {
    if (route.path === '/doc/api') return 'api'
    if (route.path === '/doc/result') return 'result'
    if (route.path === '/doc/findorder') return 'findorder'
    return 'overview'
  })

  const sectionNavItems = [
    { key: 'overview', title: '网页支付', to: '/doc' },
    { key: 'api', title: '接口支付', to: '/doc/api' },
    { key: 'result', title: '回调通知', to: '/doc/result' },
    { key: 'findorder', title: '订单查询', to: '/doc/findorder' }
  ] as const

  const siteName = computed(() => payload.value?.site_name || 'AiPay')
  const navs = computed(() => payload.value?.navs || [])
  const isLoggedIn = computed(() => Boolean(payload.value?.is_logged_in))
  const merchantLoginUrl = computed(() => payload.value?.merchant_login_url || '/#/merchant/login')
  const affiliateId = computed(() => resolvePublicAffiliateId(route.query.aff))
  const merchantRegisterUrl = computed(
    () =>
      appendPublicAffiliateQuery(
        payload.value?.merchant_register_url || '/#/merchant/register',
        affiliateId.value
      )
  )
  const merchantDashboardUrl = computed(() => '/#/merchant/dashboard')
  const merchantApiUrl = computed(() => '/#/merchant/api')
  const backendOrigin = computed(() => resolvePublicBackendOrigin() || '')

  const activeSectionMeta = computed(() => {
    const metaMap: Record<SectionKey, { title: string; description: string }> = {
      overview: {
        title: '网页支付',
        description: '查看提交地址与基础参数。'
      },
      api: {
        title: '接口支付',
        description: '查看接口下单与接入参数。'
      },
      result: {
        title: '回调通知',
        description: '查看回调地址与验签规则。'
      },
      findorder: {
        title: '订单查询',
        description: '查看订单查询入口。'
      }
    }

    return metaMap[activeSection.value]
  })

  const docGroups = computed<DocGroupCard[]>(() => buildDocGroups(activeSection.value))

  async function loadPage() {
    error.value = ''

    try {
      payload.value = await fetchPublicDoc(
        activeSection.value === 'overview' ? undefined : activeSection.value
      )
      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '开发文档暂时无法加载，请稍后再试。')
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

  function updateViewportState() {
    if (typeof window === 'undefined') return
    isMobileViewport.value = window.innerWidth <= 720
  }

  function isGroupExpanded(groupId: string) {
    return Boolean(expandedGroupMap.value[groupId])
  }

  function visibleGroupItems(group: DocGroupCard) {
    if (!isMobileViewport.value || !group.collapseAfter || isGroupExpanded(group.id)) {
      return group.items
    }

    return group.items.slice(0, group.collapseAfter)
  }

  function showGroupToggle(group: DocGroupCard) {
    return Boolean(
      isMobileViewport.value && group.collapseAfter && group.items.length > group.collapseAfter
    )
  }

  function toggleGroup(groupId: string) {
    expandedGroupMap.value = {
      ...expandedGroupMap.value,
      [groupId]: !expandedGroupMap.value[groupId]
    }
  }

  function groupToggleLabel(group: DocGroupCard) {
    if (isGroupExpanded(group.id)) return '收起'

    const remainingCount = group.items.length - (group.collapseAfter || 0)
    return remainingCount > 0 ? `展开 ${remainingCount} 项` : '展开'
  }

  function buildDocGroups(section: SectionKey): DocGroupCard[] {
    const backend = backendOrigin.value
    const frontend = typeof window === 'undefined' ? '' : window.location.origin

    const frontendHash = (path: string) => (frontend ? `${frontend}/#${path}` : `/#${path}`)
    const backendUrl = (path: string) => (backend ? `${backend}${path}` : path)
    const publicPath = (path: string) => (path === '/' ? '/' : `/#${path}`)
    const urlItem = (label: string, visibleValue: string, copyValue: string): DocRowItem => ({
      label,
      value: visibleValue,
      copyValue
    })

    const commonParams: DocRowItem[] = [
      { label: 'pid', value: '商户 ID', copyable: false },
      { label: 'type', value: '支付方式编码，例如 wxpay / alipay / qqpay', copyable: false },
      { label: 'out_trade_no', value: '商户订单号，必须唯一', copyable: false },
      { label: 'name', value: '商品名称或订单标题', copyable: false },
      { label: 'money', value: '订单金额，单位元，保留两位小数', copyable: false },
      { label: 'notify_url', value: '异步通知地址', copyable: false },
      { label: 'return_url', value: '同步跳转地址', copyable: false },
      { label: 'param', value: '附加参数，可选', copyable: false },
      { label: 'sign', value: '签名结果', copyable: false },
      { label: 'sign_type', value: '当前固定 MD5', copyable: false }
    ]

    if (section === 'api') {
      return [
        {
          id: 'api-endpoints',
          eyebrow: '接口地址',
          title: '下单入口',
          description: '提供网页提交、接口提交和查单地址。',
          collapseAfter: 2,
          items: [
            urlItem('接口下单', '/mapi.php', backendUrl('/mapi.php')),
            urlItem('网页提交', '/submit.php', backendUrl('/submit.php')),
            urlItem('订单查询', '/api/findorder', backendUrl('/api/findorder'))
          ]
        },
        {
          id: 'api-params',
          eyebrow: '请求参数',
          title: '下单参数',
          description: '网页支付和接口支付共用以下基础参数。',
          collapseAfter: 4,
          items: commonParams
        },
        {
          id: 'api-helper',
          eyebrow: '商户入口',
          title: '商户入口',
          description: '登录、控制台和接口配置地址。',
          collapseAfter: 2,
          items: [
            urlItem('商户登录', '/#/merchant/login', frontendHash('/merchant/login')),
            urlItem('商户控制台', '/#/merchant/dashboard', frontendHash('/merchant/dashboard')),
            urlItem('接口配置', '/#/merchant/api', frontendHash('/merchant/api'))
          ]
        }
      ]
    }

    if (section === 'result') {
      return [
        {
          id: 'result-callback',
          eyebrow: '回调地址',
          title: '回调通知',
          description: '支付完成后按商户配置地址回调。',
          collapseAfter: 2,
          items: [
            urlItem('异步通知地址', '/Notify/epay_notifyzj', backendUrl('/Notify/epay_notifyzj')),
            urlItem('同步返回地址', '/Notify/epay_returnzj', backendUrl('/Notify/epay_returnzj')),
            { label: '通知方式', value: '支持 GET 或 POST', copyable: false },
            { label: '成功状态', value: 'TRADE_SUCCESS', copyable: false }
          ]
        },
        {
          id: 'result-fields',
          eyebrow: '回调字段',
          title: '回调字段',
          description: '商户回调统一返回以下字段。',
          collapseAfter: 4,
          items: [
            { label: 'pid', value: '商户 ID', copyable: false },
            { label: 'trade_no', value: '平台订单号', copyable: false },
            { label: 'out_trade_no', value: '商户订单号', copyable: false },
            { label: 'type', value: '支付方式编码', copyable: false },
            { label: 'money', value: '订单金额', copyable: false },
            { label: 'trade_status', value: '固定返回 TRADE_SUCCESS', copyable: false },
            { label: 'name', value: '商品名称，关闭展示后可不返回', copyable: false },
            { label: 'sign', value: 'MD5 验签结果', copyable: false },
            { label: 'sign_type', value: '固定为 MD5', copyable: false }
          ]
        },
        {
          id: 'result-sign',
          eyebrow: '验签规则',
          title: '验签规则',
          description: '按参数名升序拼接密钥后计算 MD5。',
          collapseAfter: 3,
          items: [
            { label: '排序规则', value: '按参数名升序排列', copyable: false },
            { label: '忽略字段', value: 'sign、sign_type 和空值', copyable: false },
            {
              label: '拼接规则',
              value: 'key1=value1&key2=value2... 后追加商户密钥',
              copyable: false
            },
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
          title: '订单查询地址',
          description: '用于查询平台订单或商户订单当前状态。',
          items: [
            urlItem('查单接口', '/api/findorder', backendUrl('/api/findorder')),
            { label: 'order_no', value: '平台订单号或商户订单号', copyable: false },
            { label: 'type', value: '1 优先 trade_no，其它值优先 out_trade_no', copyable: false }
          ]
        },
        {
          id: 'findorder-response',
          eyebrow: '返回字段',
          title: '查单结果',
          description: '用于判断金额、状态和回调地址。',
          collapseAfter: 4,
          items: [
            { label: 'trade_no', value: '平台订单号', copyable: false },
            { label: 'out_trade_no', value: '商户订单号', copyable: false },
            { label: 'money', value: '订单金额', copyable: false },
            { label: 'truemoney', value: '实际支付金额', copyable: false },
            { label: 'status', value: '订单状态', copyable: false },
            { label: 'notify_url', value: '异步通知地址', copyable: false },
            { label: 'return_url', value: '同步跳转地址', copyable: false }
          ]
        },
        {
          id: 'findorder-console',
          eyebrow: '商户后台',
          title: '商户订单中心',
          description: '也可以直接在商户后台查看订单状态。',
          collapseAfter: 2,
          items: [
            urlItem('商户订单列表', '/#/merchant/orders', frontendHash('/merchant/orders')),
            urlItem('商户控制台', '/#/merchant/dashboard', frontendHash('/merchant/dashboard')),
            urlItem('接口配置', '/#/merchant/api', frontendHash('/merchant/api'))
          ]
        }
      ]
    }

    return [
      {
        id: 'overview-entry',
        eyebrow: '常用地址',
        title: '常用地址',
        description: '前台和商户常用地址。',
        collapseAfter: 2,
        items: [
          urlItem('前台首页', '/', frontend || '/'),
          urlItem('商户登录', publicPath('/merchant/login'), frontendHash('/merchant/login')),
          urlItem('商户注册', publicPath('/merchant/register'), merchantRegisterUrl.value),
          urlItem('支付测试', publicPath('/demo'), frontendHash('/demo'))
        ]
      },
      {
        id: 'overview-submit',
        eyebrow: '提交',
        title: '提交与回调',
        description: '下单与回调地址。',
        collapseAfter: 2,
        items: [
          urlItem('网页提交地址', '/submit.php', backendUrl('/submit.php')),
          urlItem('接口提交地址', '/mapi.php', backendUrl('/mapi.php')),
          urlItem('异步通知地址', '/Notify/epay_notifyzj', backendUrl('/Notify/epay_notifyzj')),
          urlItem('同步返回地址', '/Notify/epay_returnzj', backendUrl('/Notify/epay_returnzj'))
        ]
      },
      {
        id: 'overview-params',
        eyebrow: '请求参数',
        title: '下单参数',
        description: '下单共用字段。',
        collapseAfter: 4,
        items: commonParams
      }
    ]
  }

  onMounted(() => {
    updateViewportState()
    if (typeof window !== 'undefined') {
      window.addEventListener('resize', updateViewportState, { passive: true })
    }
  })

  onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', updateViewportState)
    }
  })

  watch(
    () => route.fullPath,
    () => {
      expandedGroupMap.value = {}
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

  .docs-eyebrow {
    display: inline-flex;
    color: var(--public-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.08em;
  }

  .docs-hero,
  .docs-nav,
  .docs-group,
  .docs-alert {
    border-top: 1px solid var(--public-border-strong);
    padding-top: 16px;
  }

  .docs-hero {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 20px;
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

  .docs-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-start;
  }

  .docs-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: var(--public-surface);
    color: var(--public-title);
    font-weight: 700;
    text-decoration: none;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .docs-link:hover {
    border-color: var(--public-border-strong);
    background: var(--public-surface-soft);
  }

  .docs-link--primary {
    background: var(--public-cta-bg);
    border-color: var(--public-cta-border);
    color: var(--public-cta-text);
  }

  .docs-alert {
    color: var(--public-warning);
    line-height: 1.8;
  }

  .docs-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .docs-nav__item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--public-border-strong);
    border-radius: 999px;
    background: var(--public-surface);
    color: var(--public-title);
    font-weight: 700;
    text-decoration: none;
  }

  .docs-nav__item.is-active {
    background: var(--public-cta-bg);
    border-color: var(--public-cta-border);
    color: var(--public-cta-text);
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
    margin: 0 0 2px;
    color: var(--public-text);
    line-height: 1.84;
  }

  .docs-group__toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    margin-top: 14px;
    padding: 0 14px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: var(--public-surface);
    color: var(--public-title);
    font-weight: 700;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .docs-group__toggle:hover {
    border-color: var(--public-border-strong);
    background: var(--public-surface-soft);
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
    border-bottom: 1px solid var(--public-border);
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
    background: var(--public-surface-soft);
    color: var(--public-title);
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
    background: var(--public-surface);
    color: var(--public-title);
    font-weight: 700;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .docs-row__value button:hover {
    border-color: var(--public-border-strong);
    background: var(--public-surface-soft);
  }

  @media (max-width: 980px) {
    .docs-row {
      grid-template-columns: 1fr;
      gap: 12px;
    }
  }

  @media (max-width: 720px) {
    .public-docs-page {
      gap: 22px;
    }

    .docs-content {
      gap: 22px;
    }

    .docs-group__head p {
      display: none;
    }

    .docs-nav {
      gap: 8px;
    }

    .docs-nav__item {
      min-height: 40px;
      padding: 0 14px;
      font-size: 0.94rem;
    }

    .docs-row__value {
      flex-direction: row;
      align-items: center;
    }

    .docs-row {
      padding: 12px 0;
    }

    .docs-row__value code {
      padding: 10px 12px;
      border-radius: 14px;
      line-height: 1.68;
    }

    .docs-link,
    .docs-group__toggle {
      width: 100%;
    }

    .docs-row__value button {
      width: auto;
      min-width: 64px;
      min-height: 36px;
      align-self: center;
    }
  }
</style>
