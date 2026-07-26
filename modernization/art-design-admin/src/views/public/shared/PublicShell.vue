<template>
  <div class="public-shell" @click.capture="handleShellClick">
    <header class="public-header">
      <div class="public-header__inner">
        <a class="public-brand" href="/">
          <strong>{{ siteName }}</strong>
          <small>{{ pageLabel }}</small>
        </a>

        <nav class="public-nav">
          <a
            v-for="item in displayNavs"
            :key="item.key"
            :href="item.href"
            :target="item.newWindow ? '_blank' : undefined"
            :rel="item.newWindow ? 'noreferrer' : undefined"
            :class="['public-nav__item', { 'is-active': isActiveNav(item.href) }]"
          >
            {{ item.name }}
          </a>
        </nav>

        <div class="public-actions">
          <a
            v-if="isLoggedIn"
            class="public-action public-action--primary"
            :href="merchantCenterHref"
          >
            商户中心
          </a>

          <template v-else>
            <a class="public-action public-action--secondary" :href="merchantLoginHref">商户登录</a>
            <a class="public-action public-action--primary" :href="merchantRegisterHref">注册商户</a>
          </template>

          <button
            type="button"
            class="public-mobile-toggle"
            :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
            aria-label="展开导航"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </div>
    </header>

    <transition name="public-mobile-sheet">
      <div v-if="mobileMenuOpen" class="public-mobile">
        <div class="public-mobile__mask" @click="mobileMenuOpen = false"></div>

        <div class="public-mobile__panel">
          <div class="public-mobile__head">
            <div>
              <strong>{{ siteName }}</strong>
              <p>{{ pageLabel }}</p>
            </div>

            <button type="button" class="public-mobile__close" @click="mobileMenuOpen = false">
              关闭
            </button>
          </div>

          <div class="public-mobile__links">
            <a
              v-for="item in displayNavs"
              :key="`mobile-${item.key}`"
              :href="item.href"
              :target="item.newWindow ? '_blank' : undefined"
              :rel="item.newWindow ? 'noreferrer' : undefined"
              :class="['public-mobile__link', { 'is-active': isActiveNav(item.href) }]"
              @click="mobileMenuOpen = false"
            >
              {{ item.name }}
            </a>
          </div>

          <div class="public-mobile__actions">
            <a
              v-if="isLoggedIn"
              class="public-mobile__action public-mobile__action--primary"
              :href="merchantCenterHref"
              @click="mobileMenuOpen = false"
            >
              商户中心
            </a>

            <template v-else>
              <a
                class="public-mobile__action public-mobile__action--secondary"
                :href="merchantLoginHref"
                @click="mobileMenuOpen = false"
              >
                商户登录
              </a>
              <a
                class="public-mobile__action public-mobile__action--primary"
                :href="merchantRegisterHref"
                @click="mobileMenuOpen = false"
              >
                注册商户
              </a>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <main class="public-main">
      <slot></slot>
    </main>

    <footer class="public-footer">
      <div class="public-footer__inner">
        <div class="public-footer__copy">
          <strong>{{ siteName }}</strong>
          <p v-if="footerNote">{{ footerNote }}</p>
        </div>

        <div class="public-footer__links">
          <a
            v-for="item in footerNavs"
            :key="`footer-${item.key}`"
            :href="item.href"
            :target="item.newWindow ? '_blank' : undefined"
            :rel="item.newWindow ? 'noreferrer' : undefined"
          >
            {{ item.name }}
          </a>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
  import type { PublicNavItem } from '@/api/publicSite'
  import { appendPublicAffiliateQuery, resolvePublicAffiliateId } from './publicState'

  defineOptions({ name: 'PublicShell' })

  interface ShellNavItem {
    key: string
    name: string
    href: string
    newWindow: boolean
  }

  interface CoreNavRule {
    key: string
    name: string
    defaultHref: string
    match: (path: string) => boolean
  }

  const props = withDefaults(
    defineProps<{
      siteName?: string
      navs?: PublicNavItem[]
      isLoggedIn?: boolean
      pageLabel?: string
      footerNote?: string
      merchantLoginUrl?: string
      merchantRegisterUrl?: string
      merchantCenterUrl?: string
    }>(),
    {
      siteName: 'AiPay',
      navs: () => [],
      isLoggedIn: false,
      pageLabel: '首页',
      footerNote: '',
      merchantLoginUrl: '/#/merchant/login',
      merchantRegisterUrl: '/#/merchant/register',
      merchantCenterUrl: '/#/merchant/dashboard'
    }
  )

  const route = useRoute()
  const router = useRouter()
  const mobileMenuOpen = ref(false)

  const coreNavRules: CoreNavRule[] = [
    {
      key: 'home',
      name: '首页',
      defaultHref: '/',
      match: (path) => path === '/'
    },
    {
      key: 'docs',
      name: '开发文档',
      defaultHref: '/#/doc',
      match: (path) => path.startsWith('/doc')
    },
    {
      key: 'demo',
      name: '支付测试',
      defaultHref: '/#/demo',
      match: (path) => path.startsWith('/demo') || path.startsWith('/test-pay')
    },
    {
      key: 'news',
      name: '公告中心',
      defaultHref: '/#/news/index',
      match: (path) => path.startsWith('/news')
    }
  ]

  const normalizedCurrentPath = computed(() => normalizeRoutePath(route.path))
  const affiliateId = computed(() => resolvePublicAffiliateId(route.query.aff))

  const displayNavs = computed<ShellNavItem[]>(() =>
    coreNavRules.map((rule) => {
      const matched = props.navs.find((item) => rule.match(normalizeNavPath(item.url)))
      return {
        key: rule.key,
        name: rule.name,
        href: resolveAppHref(matched?.url || rule.defaultHref),
        newWindow: Boolean(matched?.new_window)
      }
    })
  )

  const footerNavs = computed(() => displayNavs.value)
  const merchantLoginHref = computed(() => resolveAppHref(props.merchantLoginUrl))
  const merchantRegisterHref = computed(() =>
    appendPublicAffiliateQuery(resolveAppHref(props.merchantRegisterUrl), affiliateId.value)
  )
  const merchantCenterHref = computed(() => resolveAppHref(props.merchantCenterUrl))

  watch(
    () => route.fullPath,
    () => {
      mobileMenuOpen.value = false
    }
  )

  watch(mobileMenuOpen, (opened) => {
    if (typeof document === 'undefined') {
      return
    }

    document.body.style.overflow = opened ? 'hidden' : ''
  })

  onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
      document.body.style.overflow = ''
    }
  })

  function resolveAppHref(url: string) {
    const raw = String(url || '').trim()
    if (!raw || raw === '/') return '/'
    if (/^https?:\/\//i.test(raw)) return raw
    if (raw.startsWith('/#/')) return raw
    if (raw.startsWith('#/')) return `/${raw}`
    if (raw.startsWith('/')) return `/#${raw}`
    return `/#/${raw.replace(/^\/+/, '')}`
  }

  function normalizeRoutePath(path: string) {
    const normalized = `/${String(path || '')
      .replace(/^\/+/, '')
      .replace(/\/+$/, '')}`

    return normalized === '/' ? normalized : normalized.replace(/\/+$/, '')
  }

  function normalizeNavPath(url: string) {
    const raw = String(url || '').trim()
    if (!raw) {
      return '/'
    }

    try {
      const parsed = /^https?:\/\//i.test(raw) ? new URL(raw) : new URL(raw, window.location.origin)
      if (parsed.hash.startsWith('#/')) {
        return normalizeRoutePath(parsed.hash.slice(1))
      }

      return normalizeRoutePath(parsed.pathname)
    } catch {
      if (raw.startsWith('#/')) return normalizeRoutePath(raw.slice(1))
      if (raw.startsWith('/#/')) return normalizeRoutePath(raw.slice(2))
      if (/^https?:\/\//i.test(raw)) return ''
      return normalizeRoutePath(raw)
    }
  }

  function isActiveNav(url: string) {
    const navPath = normalizeNavPath(url)
    const currentPath = normalizedCurrentPath.value

    if (!navPath) return false
    if (navPath === '/') return currentPath === '/'
    if (navPath.startsWith('/news')) return currentPath.startsWith('/news')
    if (navPath.startsWith('/doc')) return currentPath.startsWith('/doc')
    if (navPath.startsWith('/demo') || navPath.startsWith('/test-pay')) {
      return currentPath.startsWith('/demo') || currentPath.startsWith('/test-pay')
    }

    return currentPath === navPath || currentPath.startsWith(`${navPath}/`)
  }

  function handleShellClick(event: MouseEvent) {
    if (event.defaultPrevented || event.button !== 0) return
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

    const target = event.target as HTMLElement | null
    const anchor = target?.closest('a[href]') as HTMLAnchorElement | null
    if (!anchor) return
    if (anchor.target === '_blank' || anchor.hasAttribute('download')) return

    const routeLocation = resolveInternalRouteLocation(anchor.getAttribute('href') || '')
    if (!routeLocation) return

    event.preventDefault()
    mobileMenuOpen.value = false
    void router.push(routeLocation)
  }

  function resolveInternalRouteLocation(href: string): string | null {
    const raw = String(href || '').trim()
    if (!raw || /^(mailto:|tel:|javascript:)/i.test(raw)) {
      return null
    }

    try {
      const parsed = /^https?:\/\//i.test(raw) ? new URL(raw) : new URL(raw, window.location.origin)
      if (parsed.origin !== window.location.origin) {
        return null
      }

      if (parsed.hash.startsWith('#/')) {
        return normalizeRoutePath(parsed.hash.slice(1))
      }

      if (parsed.pathname === '/' && !parsed.hash) {
        return '/'
      }

      if (/\.[a-z0-9]+$/i.test(parsed.pathname) || parsed.pathname.startsWith('/index99-assets/')) {
        return null
      }

      return normalizeRoutePath(parsed.pathname)
    } catch {
      if (raw.startsWith('/#/')) return normalizeRoutePath(raw.slice(2))
      if (raw.startsWith('#/')) return normalizeRoutePath(raw.slice(1))
      if (raw === '/') return '/'
      if (raw.startsWith('/')) return normalizeRoutePath(raw)
      return null
    }
  }
</script>

<style scoped lang="scss">
  @font-face {
    font-family: 'MiSans';
    src: url('/index99-assets/mi-sans/MiSans-Medium.ttf') format('truetype');
    font-weight: 500;
    font-style: normal;
  }

  @font-face {
    font-family: 'MiSans';
    src: url('/index99-assets/mi-sans/MiSans-Bold.ttf') format('truetype');
    font-weight: 700;
    font-style: normal;
  }

  .public-shell {
    --public-bg: #f5f7fb;
    --public-surface: #ffffff;
    --public-surface-soft: #f8fafc;
    --public-surface-muted: rgb(255 255 255 / 0.78);
    --public-border: rgb(15 23 42 / 0.08);
    --public-border-strong: rgb(15 23 42 / 0.12);
    --public-title: #18202f;
    --public-text: #526073;
    --public-muted: #8390a3;
    --public-accent: #2850f0;
    --public-cta-bg: #18202f;
    --public-cta-border: #18202f;
    --public-cta-text: #ffffff;
    --public-warning: #b45309;
    --public-shadow: 0 20px 40px rgb(15 23 42 / 0.04);
    --public-overlay: rgb(15 23 42 / 0.24);
    --public-header-bg: rgb(245 247 251 / 0.86);
    --public-footer-bg: rgb(255 255 255 / 0.72);
    min-height: 100vh;
    background:
      linear-gradient(180deg, rgb(255 255 255 / 0.92), rgb(245 247 251) 28%),
      var(--public-bg);
    color: var(--public-title);
    font-family:
      'MiSans',
      -apple-system,
      BlinkMacSystemFont,
      'Segoe UI',
      'PingFang SC',
      'Microsoft YaHei',
      sans-serif;
  }

  :global(html.dark .public-shell) {
    --public-bg: #050816;
    --public-surface: #101624;
    --public-surface-soft: #171d2b;
    --public-surface-muted: rgb(12 18 34 / 0.88);
    --public-border: rgb(148 163 184 / 0.18);
    --public-border-strong: rgb(148 163 184 / 0.26);
    --public-title: #f8fbff;
    --public-text: rgb(226 232 240 / 0.82);
    --public-muted: rgb(148 163 184 / 0.9);
    --public-accent: #9fb2ff;
    --public-cta-bg: linear-gradient(135deg, #546dff, #7f93ff);
    --public-cta-border: rgb(144 162 255 / 0.92);
    --public-cta-text: #f8fbff;
    --public-warning: #fbbf24;
    --public-shadow: 0 24px 60px rgb(0 0 0 / 0.38);
    --public-overlay: rgb(2 6 23 / 0.56);
    --public-header-bg: rgb(5 8 22 / 0.82);
    --public-footer-bg: rgb(10 15 28 / 0.86);
    background:
      radial-gradient(circle at top right, rgb(95 121 255 / 0.18), transparent 28%),
      linear-gradient(180deg, rgb(10 15 28 / 0.96), rgb(5 8 22) 30%),
      var(--public-bg);
  }

  .public-header {
    position: sticky;
    top: 0;
    z-index: 30;
    background: var(--public-header-bg);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--public-border);
  }

  .public-header__inner,
  .public-main,
  .public-footer__inner {
    width: min(1160px, calc(100% - 32px));
    margin: 0 auto;
  }

  .public-header__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    min-height: 76px;
  }

  .public-brand {
    display: inline-flex;
    flex-direction: column;
    gap: 4px;
    color: inherit;
    text-decoration: none;
    white-space: nowrap;
  }

  .public-brand strong {
    font-size: 1.42rem;
    line-height: 1;
    letter-spacing: -0.04em;
  }

  .public-brand small {
    color: var(--public-muted);
    font-size: 0.84rem;
  }

  .public-nav {
    display: flex;
    align-items: center;
    gap: 28px;
    flex: 1;
    justify-content: center;
  }

  .public-nav__item {
    position: relative;
    padding: 6px 0;
    color: var(--public-text);
    text-decoration: none;
    transition: color 0.2s ease;
  }

  .public-nav__item::after {
    content: '';
    position: absolute;
    inset: auto 0 -2px;
    height: 2px;
    background: var(--public-accent);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.2s ease;
  }

  .public-nav__item:hover,
  .public-nav__item.is-active {
    color: var(--public-title);
  }

  .public-nav__item:hover::after,
  .public-nav__item.is-active::after {
    transform: scaleX(1);
  }

  .public-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
  }

  .public-action,
  .public-mobile__action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 700;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      color 0.2s ease;
  }

  .public-action--primary,
  .public-mobile__action--primary {
    border-color: var(--public-cta-border);
    background: var(--public-cta-bg);
    color: var(--public-cta-text);
  }

  .public-action--secondary,
  .public-mobile__action--secondary {
    border-color: var(--public-border);
    background: var(--public-surface-muted);
    color: var(--public-title);
  }

  .public-mobile-toggle {
    display: none;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--public-border);
    border-radius: 12px;
    background: var(--public-surface-muted);
    cursor: pointer;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 4px;
  }

  .public-mobile-toggle span {
    width: 16px;
    height: 2px;
    border-radius: 999px;
    background: var(--public-title);
  }

  .public-mobile {
    position: fixed;
    inset: 0;
    z-index: 40;
  }

  .public-mobile__mask {
    position: absolute;
    inset: 0;
    background: var(--public-overlay);
  }

  .public-mobile__panel {
    position: absolute;
    top: 0;
    right: 0;
    width: min(78vw, 320px);
    height: 100%;
    padding: 20px;
    background: var(--public-surface);
    box-shadow: var(--public-shadow);
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .public-mobile__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }

  .public-mobile__head strong {
    display: block;
    font-size: 1.12rem;
  }

  .public-mobile__head p {
    margin: 6px 0 0;
    color: var(--public-muted);
    font-size: 0.84rem;
  }

  .public-mobile__close {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid var(--public-border);
    border-radius: 999px;
    background: var(--public-surface);
    color: var(--public-title);
    cursor: pointer;
  }

  .public-mobile__links,
  .public-mobile__actions {
    display: grid;
    gap: 10px;
  }

  .public-mobile__link {
    display: flex;
    align-items: center;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 14px;
    background: var(--public-surface-soft);
    color: var(--public-title);
    text-decoration: none;
  }

  .public-mobile__link.is-active {
    background: rgb(40 80 240 / 0.12);
    color: var(--public-accent);
  }

  .public-mobile__actions {
    margin-top: auto;
  }

  .public-main {
    padding: 46px 0 80px;
  }

  .public-footer {
    border-top: 1px solid var(--public-border);
    background: var(--public-footer-bg);
  }

  .public-footer__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 22px 0 28px;
  }

  .public-footer__copy strong {
    display: block;
    font-size: 1rem;
  }

  .public-footer__copy p {
    margin: 6px 0 0;
    color: var(--public-text);
    font-size: 0.9rem;
    line-height: 1.7;
  }

  .public-footer__links {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 18px;
  }

  .public-footer__links a {
    color: var(--public-text);
    text-decoration: none;
    font-size: 0.92rem;
  }

  .public-mobile-sheet-enter-active,
  .public-mobile-sheet-leave-active {
    transition: opacity 0.2s ease;
  }

  .public-mobile-sheet-enter-from,
  .public-mobile-sheet-leave-to {
    opacity: 0;
  }

  @media (max-width: 960px) {
    .public-nav,
    .public-action {
      display: none;
    }

    .public-mobile-toggle {
      display: inline-flex;
    }

    .public-header__inner,
    .public-main,
    .public-footer__inner {
      width: min(100%, calc(100% - 24px));
    }

    .public-main {
      padding: 28px 0 56px;
    }
  }

  @media (max-width: 640px) {
    .public-header__inner {
      min-height: 68px;
    }

    .public-brand strong {
      font-size: 1.26rem;
    }

    .public-brand small {
      display: none;
    }

    .public-footer__inner {
      flex-direction: column;
      align-items: flex-start;
      padding: 18px 0 20px;
    }

    .public-footer__links {
      justify-content: flex-start;
    }
  }
</style>
