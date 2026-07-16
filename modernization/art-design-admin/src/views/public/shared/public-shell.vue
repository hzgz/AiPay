<template>
  <div class="index99-shell">
    <div class="index99-shell__glow index99-shell__glow--left"></div>
    <div class="index99-shell__glow index99-shell__glow--right"></div>

    <header class="index99-header">
      <div class="index99-header__inner">
        <a class="index99-brand" href="/">
          <strong>{{ siteName }}</strong>
          <small>{{ pageLabel }}</small>
        </a>

        <nav class="index99-nav">
          <a
            v-for="item in displayNavs"
            :key="item.key"
            :href="item.href"
            :target="item.newWindow ? '_blank' : undefined"
            :rel="item.newWindow ? 'noreferrer' : undefined"
            :class="['index99-nav__item', { 'is-active': isActiveNav(item.href) }]"
          >
            {{ item.name }}
          </a>
        </nav>

        <div class="index99-actions">
          <span class="index99-accent-pill">
            <Icon icon="ri:palette-line" />
            <em>index99</em>
          </span>

          <a
            v-if="isLoggedIn"
            class="index99-auth index99-auth--primary"
            :href="merchantCenterHref"
          >
            <Icon icon="ri:user-3-line" />
            <span>商户中心</span>
          </a>

          <a v-else class="index99-auth index99-auth--primary" :href="merchantLoginHref">
            <Icon icon="ri:user-3-line" />
            <span>登录 / 注册</span>
          </a>

          <button
            type="button"
            class="index99-mobile-toggle"
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

    <transition name="index99-mobile-sheet">
      <div v-if="mobileMenuOpen" class="index99-mobile">
        <div class="index99-mobile__mask" @click="mobileMenuOpen = false"></div>

        <div class="index99-mobile__panel">
          <div class="index99-mobile__head">
            <div>
              <strong>{{ siteName }}</strong>
              <p>{{ pageLabel }}</p>
            </div>

            <button type="button" class="index99-mobile__close" @click="mobileMenuOpen = false">
              ×
            </button>
          </div>

          <div class="index99-mobile__links">
            <a
              v-for="item in displayNavs"
              :key="`mobile-${item.key}`"
              :href="item.href"
              :target="item.newWindow ? '_blank' : undefined"
              :rel="item.newWindow ? 'noreferrer' : undefined"
              :class="['index99-mobile__link', { 'is-active': isActiveNav(item.href) }]"
              @click="mobileMenuOpen = false"
            >
              {{ item.name }}
            </a>
          </div>

          <div class="index99-mobile__actions">
            <a
              v-if="isLoggedIn"
              class="index99-mobile__auth index99-mobile__auth--primary"
              :href="merchantCenterHref"
              @click="mobileMenuOpen = false"
            >
              商户中心
            </a>

            <template v-else>
              <a
                class="index99-mobile__auth index99-mobile__auth--ghost"
                :href="merchantLoginHref"
                @click="mobileMenuOpen = false"
              >
                商户登录
              </a>

              <a
                class="index99-mobile__auth index99-mobile__auth--primary"
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

    <main class="index99-main">
      <slot></slot>
    </main>

    <footer class="index99-footer">
      <div class="index99-footer__inner">
        <div>
          <strong>{{ siteName }}</strong>
          <p>{{ footerNote }}</p>
        </div>

        <div class="index99-footer__links">
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
  import { Icon } from '@iconify/vue'
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
      pageLabel: '聚合支付前台',
      footerNote: '8132 统一承载游客首页、开发文档、公告中心、支付测试与商户入口。',
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
    document.body.style.overflow = opened ? 'hidden' : ''
  })

  onBeforeUnmount(() => {
    document.body.style.overflow = ''
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

  .index99-shell {
    position: relative;
    min-height: 100vh;
    overflow-x: hidden;
    background: linear-gradient(90deg, #edf4ff 0%, #f6eef4 100%);
    color: #1f2937;
    font-family:
      'MiSans',
      -apple-system,
      BlinkMacSystemFont,
      'Segoe UI',
      'PingFang SC',
      'Microsoft YaHei',
      sans-serif;
  }

  .index99-shell__glow {
    position: fixed;
    inset: auto;
    z-index: 0;
    pointer-events: none;
    border-radius: 999px;
    filter: blur(48px);
    opacity: 0.45;
  }

  .index99-shell__glow--left {
    top: 90px;
    left: -80px;
    width: 280px;
    height: 280px;
    background: rgba(103, 80, 255, 0.14);
  }

  .index99-shell__glow--right {
    top: 180px;
    right: -120px;
    width: 320px;
    height: 320px;
    background: rgba(59, 130, 246, 0.12);
  }

  .index99-header {
    position: sticky;
    top: 0;
    z-index: 30;
    padding: 10px 16px 0;
  }

  .index99-header__inner {
    display: flex;
    align-items: center;
    gap: 26px;
    justify-content: space-between;
    width: min(1360px, 100%);
    margin: 0 auto;
    padding: 18px 36px;
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.56);
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.06);
    backdrop-filter: blur(16px);
  }

  .index99-brand {
    display: inline-flex;
    flex-direction: column;
    gap: 4px;
    text-decoration: none;
    color: inherit;
    white-space: nowrap;
  }

  .index99-brand strong {
    font-size: 2.1rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.04em;
    color: #4b5563;
  }

  .index99-brand small {
    color: #7c8aa5;
    font-size: 0.8rem;
    line-height: 1;
  }

  .index99-nav {
    display: flex;
    align-items: center;
    gap: 34px;
    flex: 1;
  }

  .index99-nav__item {
    position: relative;
    color: #334155;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s ease;
  }

  .index99-nav__item::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -8px;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(90deg, #4f7cff, #7367ff);
    opacity: 0;
    transform: scaleX(0.2);
    transition:
      opacity 0.2s ease,
      transform 0.2s ease;
  }

  .index99-nav__item:hover,
  .index99-nav__item.is-active {
    color: #2563eb;
  }

  .index99-nav__item:hover::after,
  .index99-nav__item.is-active::after {
    opacity: 1;
    transform: scaleX(1);
  }

  .index99-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
  }

  .index99-accent-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.88);
    color: #6173ff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    font-size: 1rem;
    font-weight: 700;
  }

  .index99-accent-pill em {
    font-style: normal;
    color: #4b5563;
    font-size: 0.88rem;
  }

  .index99-auth {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 20px;
    border-radius: 12px;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 700;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;
  }

  .index99-auth--primary {
    background: linear-gradient(135deg, #6173ff 0%, #5b6cff 35%, #6f67ff 100%);
    color: #fff;
    box-shadow: 0 12px 26px rgba(97, 115, 255, 0.3);
  }

  .index99-auth--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 34px rgba(97, 115, 255, 0.34);
  }

  .index99-mobile-toggle {
    display: none;
    width: 42px;
    height: 42px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 5px;
    border: 0;
    cursor: pointer;
    border-radius: 12px;
    background: linear-gradient(135deg, #6173ff 0%, #6f67ff 100%);
    box-shadow: 0 12px 24px rgba(97, 115, 255, 0.22);
  }

  .index99-mobile-toggle span {
    width: 18px;
    height: 2px;
    border-radius: 999px;
    background: #fff;
  }

  .index99-mobile {
    position: fixed;
    inset: 0;
    z-index: 40;
  }

  .index99-mobile__mask {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.34);
    backdrop-filter: blur(3px);
  }

  .index99-mobile__panel {
    position: absolute;
    top: 0;
    right: 0;
    width: min(78vw, 300px);
    height: 100%;
    padding: 22px 18px 20px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: -12px 0 30px rgba(15, 23, 42, 0.12);
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .index99-mobile__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .index99-mobile__head strong {
    display: block;
    font-size: 1.25rem;
    color: #4b5563;
  }

  .index99-mobile__head p {
    margin: 4px 0 0;
    color: #7c8aa5;
    font-size: 0.82rem;
  }

  .index99-mobile__close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 1.4rem;
    cursor: pointer;
  }

  .index99-mobile__links {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .index99-mobile__link,
  .index99-mobile__auth {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 16px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
  }

  .index99-mobile__link {
    background: #f8fafc;
    color: #334155;
  }

  .index99-mobile__link.is-active {
    background: #eef2ff;
    color: #4f46e5;
  }

  .index99-mobile__actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: auto;
  }

  .index99-mobile__auth--ghost {
    border: 1px solid #dbe4ff;
    background: #fff;
    color: #334155;
  }

  .index99-mobile__auth--primary {
    background: linear-gradient(135deg, #6173ff 0%, #6f67ff 100%);
    color: #fff;
  }

  .index99-main {
    position: relative;
    z-index: 1;
    width: min(1360px, calc(100% - 32px));
    margin: 0 auto;
    padding: 42px 0 80px;
  }

  .index99-footer {
    position: relative;
    z-index: 1;
    padding: 0 16px 28px;
  }

  .index99-footer__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: min(1360px, 100%);
    margin: 0 auto;
    padding: 20px 28px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.58);
    border: 1px solid rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(14px);
  }

  .index99-footer strong {
    display: block;
    font-size: 1rem;
    color: #334155;
  }

  .index99-footer p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 0.9rem;
  }

  .index99-footer__links {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 18px;
  }

  .index99-footer__links a {
    color: #475569;
    text-decoration: none;
    font-size: 0.92rem;
  }

  .index99-mobile-sheet-enter-active,
  .index99-mobile-sheet-leave-active {
    transition: opacity 0.24s ease;
  }

  .index99-mobile-sheet-enter-from,
  .index99-mobile-sheet-leave-to {
    opacity: 0;
  }

  @media (max-width: 1120px) {
    .index99-header__inner {
      padding: 16px 24px;
    }

    .index99-nav {
      gap: 22px;
    }
  }

  @media (max-width: 960px) {
    .index99-nav,
    .index99-accent-pill,
    .index99-auth {
      display: none;
    }

    .index99-mobile-toggle {
      display: inline-flex;
    }

    .index99-header__inner {
      padding: 16px 18px;
    }

    .index99-brand strong {
      font-size: 1.9rem;
    }
  }

  @media (max-width: 640px) {
    .index99-header {
      padding: 10px 12px 0;
    }

    .index99-main {
      width: min(100%, calc(100% - 24px));
      padding: 26px 0 56px;
    }

    .index99-footer {
      padding: 0 12px 18px;
    }

    .index99-footer__inner {
      flex-direction: column;
      align-items: flex-start;
      padding: 18px 16px;
    }

    .index99-footer__links {
      justify-content: flex-start;
    }
  }
</style>
