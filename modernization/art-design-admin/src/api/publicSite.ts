import { publicCompatRequest, resolvePublicBackendOrigin } from './publicClient'

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
  site_name: string
  active_theme_id: string
  active_theme_title: string
  is_logged_in: boolean
  merchant_login_url: string
  merchant_register_url?: string
  news_index_url: string
  doc_url: string
  demo_url?: string
  news_sections: PublicNewsSection[]
  navs: PublicNavItem[]
}

export interface PublicNewsListPayload {
  site_name: string
  is_logged_in: boolean
  current: number
  size: number
  total: number
  records: PublicNewsSummary[]
  navs: PublicNavItem[]
}

export interface PublicNewsDetailPayload {
  site_name: string
  is_logged_in: boolean
  article: PublicNewsSummary & {
    content_html: string
  }
  navs: PublicNavItem[]
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
  is_logged_in: boolean
  navs: PublicNavItem[]
  merchant_login_url: string
  merchant_register_url?: string
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
  site_name: string
  demo_name: string
  demo_money: string
  gateway_configured: boolean
  available_methods: PublicDemoMethod[]
  navs: PublicNavItem[]
  merchant_login_url: string
}

export function fetchPublicDemo() {
  return publicCompatRequest<PublicDemoPayload>({
    url: '/api/public/demo',
    method: 'GET'
  })
}

export { resolvePublicBackendOrigin }
