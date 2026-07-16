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
          <span class="docs-hero__tag">开发文档</span>
          <h1>{{ activeSectionMeta.title }}</h1>
          <p>{{ activeSectionMeta.description }}</p>
        </div>

        <div class="docs-hero__meta">
          <article>
            <small>后端服务</small>
            <strong>{{ backendOriginLabel }}</strong>
          </article>
          <article>
            <small>前端入口</small>
            <strong>{{ frontendOriginLabel }}</strong>
          </article>
          <article>
            <small>当前分栏</small>
            <strong>{{ activeSectionMeta.shortLabel }}</strong>
          </article>
        </div>
      </section>

      <div v-if="error" class="docs-alert">
        文档动态配置读取失败，当前展示为本地固定版。{{ error }}
      </div>

      <section class="docs-layout">
        <aside class="docs-sidebar">
          <div class="docs-sidebar__card">
            <span>文档目录</span>

            <RouterLink
              v-for="item in sectionNavItems"
              :key="item.key"
              :to="item.to"
              :class="{ 'is-active': item.key === activeSection }"
            >
              <strong>{{ item.title }}</strong>
              <small>{{ item.note }}</small>
            </RouterLink>
          </div>

          <div class="docs-sidebar__card">
            <span>快捷入口</span>
            <a :href="merchantLoginUrl">商户登录</a>
            <a :href="merchantRegisterUrl">注册商户</a>
            <a :href="merchantApiUrl">商户接口配置</a>
          </div>

          <div class="docs-sidebar__card">
            <span>对接提示</span>
            <p>接口地址统一走 8787，游客前台统一走 8132。</p>
            <p>公开文档只展示稳定入口，不再混用旧模板说明文案。</p>
            <p>管理员登录地址不会在游客前台展示。</p>
          </div>
        </aside>

        <div class="docs-content">
          <article v-for="group in docGroups" :key="group.id" class="doc-group">
            <div class="doc-group__head">
              <span>{{ group.eyebrow }}</span>
              <h2>{{ group.title }}</h2>
              <p>{{ group.description }}</p>
            </div>

            <div class="doc-group__rows">
              <div v-for="item in group.items" :key="`${group.id}-${item.label}`" class="doc-row">
                <div class="doc-row__label">
                  <strong>{{ item.label }}</strong>
                  <small v-if="item.note">{{ item.note }}</small>
                </div>

                <div class="doc-row__value">
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
      note: '前台与回调基础地址',
      to: '/doc'
    },
    {
      key: 'api',
      title: 'API 接口支付',
      note: 'MAPI、聚合接口与辅助入口',
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
  const backendOriginLabel = computed(() => backendOrigin.value || '未配置')
  const frontendOriginLabel = computed(() =>
    typeof window === 'undefined' ? '当前前台入口' : window.location.origin
  )

  const activeSectionMeta = computed(() => {
    const map: Record<SectionKey, { title: string; shortLabel: string; description: string }> = {
      overview: {
        title: '页面跳转支付文档',
        shortLabel: '跳转支付',
        description: '整理公开前台、支付跳转地址、基础参数和商户接入入口。'
      },
      api: {
        title: 'API 接口支付文档',
        shortLabel: 'API 支付',
        description: '集中展示 MAPI、聚合接口、订单查询与商户常用辅助地址。'
      },
      result: {
        title: '支付结果通知文档',
        shortLabel: '结果通知',
        description: '说明异步通知、同步返回、回调字段和商户验签规则。'
      },
      findorder: {
        title: '订单查询文档',
        shortLabel: '订单查询',
        description: '说明查单接口、参数格式、返回字段以及商户后台订单入口。'
      }
    }

    return map[activeSection.value]
  })

  const docGroups = computed<DocGroupCard[]>(() => buildDocGroups(activeSection.value))

  async function loadPage() {
    loading.value = true
    error.value = ''

    try {
      payload.value = await fetchPublicDoc(activeSection.value === 'overview' ? undefined : activeSection.value)
      scrollPublicPageToTop()
    } catch (err) {
      error.value = resolvePublicErrorMessage(err, '开发文档动态数据暂时不可用。')
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
      { label: 'type', value: '支付方式标识，如 wxpay / alipay / qqpay', copyable: false },
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
          description: '兼容旧接口地址，同时补充当前 Webman 聚合接口入口。',
          items: [
            { label: '兼容 MAPI', value: backendUrl('/mapi.php') },
            { label: '聚合接口', value: backendUrl('/Api/mapi') },
            { label: '支付创建', value: backendUrl('/Api/payment') },
            { label: '订单查询', value: backendUrl('/Api/findorder') }
          ]
        },
        {
          id: 'api-params',
          eyebrow: '常用参数',
          title: '下单参数说明',
          description: '公开页面展示常用参数，实际测试与调通建议在商户端完成。',
          items: commonParams
        },
        {
          id: 'api-helper',
          eyebrow: '辅助入口',
          title: '商户常用地址',
          description: '商户登录、商户后台和接口配置页全部从 8132 前台进入。',
          items: [
            { label: '商户登录', value: frontendHash('/merchant/login') },
            { label: '商户控制台', value: frontendHash('/merchant/dashboard') },
            { label: '商户接口配置', value: frontendHash('/merchant/api') }
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
          description: '当前系统商户回调 payload 统一包含以下字段。',
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
          description: '回调参数先按键名升序排序，再拼接商户密钥计算 MD5。',
          items: [
            { label: '排序规则', value: '按参数名升序排序', copyable: false },
            { label: '忽略字段', value: 'sign、sign_type 和空值', copyable: false },
            { label: '拼接规则', value: 'key1=value1&key2=value2... + 商户密钥', copyable: false },
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
            { label: 'type', value: '1 表示优先 trade_no，其他值优先 out_trade_no', copyable: false }
          ]
        },
        {
          id: 'findorder-response',
          eyebrow: '返回字段',
          title: '查单结果说明',
          description: '查单成功后，可从返回字段中判断金额、状态与回调地址。',
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
          description: '除了兼容接口，也可以直接在商户后台查看订单列表和详情。',
          items: [
            { label: '商户订单列表', value: frontendHash('/merchant/orders') },
            { label: '商户控制台', value: frontendHash('/merchant/dashboard') },
            { label: '商户接口配置', value: frontendHash('/merchant/api') }
          ]
        }
      ]
    }

    return [
      {
        id: 'overview-entry',
        eyebrow: '基础入口',
        title: '公开前台与商户入口',
        description: '8132 承载公开页面、商户登录、注册和游客访问入口。',
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
        title: '页面跳转与回调地址',
        description: '兼容旧系统的页面支付入口，同时保留当前 Webman 提交地址。',
        items: [
          { label: '兼容提交地址', value: backendUrl('/submit.php') },
          { label: 'Webman 提交地址', value: backendUrl('/Pay/submit') },
          { label: '异步通知地址', value: backendUrl('/Notify/epay_notifyzj') },
          { label: '同步返回地址', value: backendUrl('/Notify/epay_returnzj') }
        ]
      },
      {
        id: 'overview-params',
        eyebrow: '请求参数',
        title: '常用下单参数',
        description: '页面跳转与 API 支付共用的基础参数可以按以下格式准备。',
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
    gap: 22px;
  }

  .docs-hero,
  .docs-alert,
  .docs-sidebar__card,
  .doc-group {
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
  }

  .docs-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
    gap: 20px;
    padding: 30px;
  }

  .docs-hero__tag,
  .docs-sidebar__card span,
  .doc-group__head span {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(97, 115, 255, 0.12);
    color: #5467f5;
    font-size: 0.8rem;
    font-weight: 700;
  }

  .docs-hero h1 {
    margin: 18px 0 12px;
    color: #1f2937;
    font-size: clamp(2rem, 4vw, 3.3rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
  }

  .docs-hero p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.85;
    max-width: 760px;
  }

  .docs-hero__meta {
    display: grid;
    gap: 12px;
  }

  .docs-hero__meta article {
    padding: 18px;
    border-radius: 20px;
    background: #f8fbff;
  }

  .docs-hero__meta small {
    display: block;
    color: #64748b;
  }

  .docs-hero__meta strong {
    display: block;
    margin-top: 8px;
    color: #1f2937;
    line-height: 1.7;
    word-break: break-all;
  }

  .docs-alert {
    padding: 16px 18px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.75;
  }

  .docs-layout {
    display: grid;
    grid-template-columns: minmax(280px, 0.88fr) minmax(0, 1.7fr);
    gap: 20px;
  }

  .docs-sidebar {
    display: grid;
    gap: 18px;
    align-self: start;
  }

  .docs-sidebar__card {
    padding: 22px;
  }

  .docs-sidebar__card a {
    display: block;
    margin-top: 14px;
    padding: 14px 16px;
    border-radius: 18px;
    background: #f8fbff;
    color: #1f2937;
    text-decoration: none;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;
  }

  .docs-sidebar__card a.is-active,
  .docs-sidebar__card a:hover {
    transform: translateY(-1px);
    background: #edf2ff;
    box-shadow: 0 18px 34px rgba(97, 115, 255, 0.12);
  }

  .docs-sidebar__card a strong {
    display: block;
    margin-bottom: 6px;
  }

  .docs-sidebar__card a small,
  .docs-sidebar__card p {
    color: #5f6b7a;
    line-height: 1.75;
  }

  .docs-sidebar__card p {
    margin: 14px 0 0;
  }

  .docs-content {
    display: grid;
    gap: 18px;
  }

  .doc-group {
    padding: 24px;
  }

  .doc-group__head h2 {
    margin: 16px 0 10px;
    color: #1f2937;
    font-size: 1.35rem;
  }

  .doc-group__head p {
    margin: 0;
    color: #5f6b7a;
    line-height: 1.8;
  }

  .doc-group__rows {
    display: grid;
    gap: 12px;
    margin-top: 18px;
  }

  .doc-row {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    padding: 16px 18px;
    border-radius: 20px;
    background: #f8fbff;
  }

  .doc-row__label strong {
    display: block;
    color: #1f2937;
  }

  .doc-row__label small {
    display: block;
    margin-top: 6px;
    color: #64748b;
    line-height: 1.6;
  }

  .doc-row__value {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
  }

  .doc-row__value code {
    display: block;
    min-width: 0;
    flex: 1;
    overflow: hidden;
    padding: 12px 14px;
    border-radius: 14px;
    background: #0f172a;
    color: #e2e8f0;
    font-family: 'Cascadia Code', 'Consolas', monospace;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  .doc-row__value button {
    flex-shrink: 0;
    min-height: 40px;
    padding: 0 14px;
    border: 0;
    border-radius: 14px;
    background: #5467f5;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
  }

  @media (max-width: 980px) {
    .docs-hero,
    .docs-layout {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 640px) {
    .docs-hero,
    .docs-alert,
    .docs-sidebar__card,
    .doc-group {
      border-radius: 24px;
    }

    .docs-hero,
    .docs-sidebar__card,
    .doc-group {
      padding: 22px;
    }

    .doc-row {
      grid-template-columns: 1fr;
    }

    .doc-row__value {
      flex-direction: column;
      align-items: stretch;
    }

    .doc-row__value button {
      width: 100%;
    }
  }
</style>
