import { publicCompatRequest, resolvePublicBackendOrigin } from './public-client'

export interface PublicNavItem {
  id: number
  name: string
  url: string
  new_window?: boolean
  is_target?: number
  sort: number
}

export interface PublicNewsSummary {
  id: number
  type: number
  type_label: string
  title: string
  create_time?: string | null
  date_label: string
  excerpt: string
  color?: string | null
}

export interface PublicNewsSection {
  type: number
  type_label: string
  items: PublicNewsSummary[]
  count: number
  path: string
}

export interface PublicHomePayload {
  mode: string
  site_name: string
  home_url?: string | null
  is_logged_in: boolean
  affiliate_id: string
  merchant_login_url: string
  merchant_register_url?: string
  public_home_url: string
  news_index_url: string
  doc_url: string
  demo_url?: string
  legacy_url: string
  news_sections: PublicNewsSection[]
  navs: PublicNavItem[]
  summary: {
    nav_count: number
    news_count: number
    doc_routes: number
  }
}

export interface PublicNewsListPayload {
  site_name: string
  is_logged_in: boolean
  mode: 'index' | 'categories'
  type: number
  type_label: string
  current: number
  size: number
  total: number
  records: PublicNewsSummary[]
  navs: PublicNavItem[]
  merchant_register_url?: string
  demo_url?: string
  summary: {
    total_count: number
    type: number
    type_label: string
  }
}

export interface PublicNewsDetailPayload {
  status_code: number
  site_name: string
  is_logged_in: boolean
  article: PublicNewsSummary & {
    content_html: string
  }
  navs: PublicNavItem[]
  merchant_register_url?: string
  demo_url?: string
}

export interface PublicDocItem {
  label: string
  value: string
}

export interface PublicDocGroup {
  id: string
  title: string
  description: string
  items: PublicDocItem[]
}

export interface PublicDocPayload {
  site_name: string
  section: string
  section_label: string
  section_description: string
  is_logged_in: boolean
  navs: PublicNavItem[]
  merchant_login_url: string
  merchant_register_url?: string
  public_home_url: string
  demo_url?: string
  docs: PublicDocGroup[]
}

export function fetchPublicHome() {
  return publicCompatRequest<PublicHomePayload>({
    url: '/api/public/home',
    method: 'GET'
  })
}

export interface PublicListQuery {
  current?: number
  size?: number
}

export function fetchPublicNewsIndex(type?: number, params: PublicListQuery = {}) {
  return publicCompatRequest<PublicNewsListPayload>({
    url: type ? `/api/public/news/index/${type}` : '/api/public/news/index',
    method: 'GET',
    params
  })
}

export function fetchPublicNewsCategory(type: number, params: PublicListQuery = {}) {
  return publicCompatRequest<PublicNewsListPayload>({
    url: `/api/public/news/categories/${type}`,
    method: 'GET',
    params
  })
}

export function fetchPublicNewsDetail(id: number) {
  return publicCompatRequest<PublicNewsDetailPayload>({
    url: `/api/public/news/detail/${id}`,
    method: 'GET'
  })
}

export function fetchPublicDoc(section?: string) {
  return publicCompatRequest<PublicDocPayload>({
    url: section ? `/api/public/doc/${section}` : '/api/public/doc',
    method: 'GET'
  })
}

export interface PublicDemoMethod {
  id: string
  label: string
  description: string
}

export interface PublicDemoPayload {
  mode: string
  site_name: string
  demo_name: string
  demo_money: string
  demo_theme: string
  gateway_configured: boolean
  gateway_host: string
  available_methods: PublicDemoMethod[]
  navs: PublicNavItem[]
  public_home_url: string
  merchant_login_url: string
  admin_url: string
  supports_write: boolean
  summary: {
    available_method_count: number
    gateway_configured: boolean
    nav_count: number
    read_only: boolean
    production_enabled: boolean
  }
  route_policy?: Record<string, any>
  write_actions?: Record<string, boolean>
  migration_guard?: Record<string, any>
}

export function fetchPublicDemo() {
  return publicCompatRequest<PublicDemoPayload>({
    url: '/api/public/demo',
    method: 'GET'
  })
}

export { resolvePublicBackendOrigin }
