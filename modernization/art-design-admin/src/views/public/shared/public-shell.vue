<template>
  <div class="public-shell">
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
          <p>{{ footerNote }}</p>
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
  import type { PublicNavItem } from '@/api/public-site'

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
      footerNote: '可商用聚合支付与商户接入平台。',
      merchantLoginUrl: '/#/merchant/login',
      merchantRegisterUrl: '/#/merchant/register',
      merchantCenterUrl: '/#/merchant/dashboard'
    }
  )

  const route = useRoute()
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
  const merchantRegisterHref = computed(() => resolveAppHref(props.merchantRegisterUrl))
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
    if (!raw || raw === '/') {
      return '/'
    }

    if (/^https?:\/\//i.test(raw)) {
      return raw
    }

    if (raw.startsWith('/#/')) {
      return raw
    }

    if (raw.startsWith('#/')) {
      return `/${raw}`
    }

    if (raw.startsWith('/')) {
      return `/#${raw}`
    }

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
      if (raw.startsWith('#/')) {
        return normalizeRoutePath(raw.slice(1))
      }

      if (raw.startsWith('/#/')) {
        return normalizeRoutePath(raw.slice(2))
      }

      if (/^https?:\/\//i.test(raw)) {
        return ''
      }

      return normalizeRoutePath(raw)
    }
  }

  function isActiveNav(url: string) {
    const navPath = normalizeNavPath(url)
    const currentPath = normalizedCurrentPath.value

    if (!navPath) {
      return false
    }

    if (navPath === '/') {
      return currentPath === '/'
    }

    if (navPath.startsWith('/news')) {
      return currentPath.startsWith('/news')
    }

    if (navPath.startsWith('/doc')) {
      return currentPath.startsWith('/doc')
    }

    if (navPath.startsWith('/demo') || navPath.startsWith('/test-pay')) {
      return currentPath.startsWith('/demo') || currentPath.startsWith('/test-pay')
    }

    return currentPath === navPath || currentPath.startsWith(`${navPath}/`)
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
    --public-border: rgba(15, 23, 42, 0.08);
    --public-border-strong: rgba(15, 23, 42, 0.12);
    --public-title: #18202f;
    --public-text: #526073;
    --public-muted: #8390a3;
    --public-accent: #2850f0;
    --public-shadow: 0 20px 40px rgba(15, 23, 42, 0.04);
    min-height: 100vh;
    background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(245, 247, 251, 1) 28%),
      #f5f7fb;
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

  .public-header {
    position: sticky;
    top: 0;
    z-index: 30;
    background: rgba(245, 247, 251, 0.86);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
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
    color: #364152;
    text-decoration: none;
    transition: color 0.2s ease;
  }

  .public-nav__item::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -2px;
    height: 2px;
    background: var(--public-title);
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
    background: #18202f;
    color: #fff;
  }

  .public-action--secondary,
  .public-mobile__action--secondary {
    border-color: var(--public-border);
    background: rgba(255, 255, 255, 0.78);
    color: var(--public-title);
  }

  .public-mobile-toggle {
    display: none;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--public-border);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.88);
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
    background: rgba(15, 23, 42, 0.24);
  }

  .public-mobile__panel {
    position: absolute;
    top: 0;
    right: 0;
    width: min(78vw, 320px);
    height: 100%;
    padding: 20px;
    background: #fff;
    box-shadow: -20px 0 40px rgba(15, 23, 42, 0.08);
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
    background: #fff;
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
    background: #eef2ff;
    color: var(--public-accent);
  }

  .public-mobile__actions {
    margin-top: auto;
  }

  .public-main {
    padding: 46px 0 80px;
  }

  .public-footer {
    border-top: 1px solid rgba(15, 23, 42, 0.06);
    background: rgba(255, 255, 255, 0.72);
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
    color: #4b5563;
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
