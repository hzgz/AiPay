export function resolveMenuIcon(
  icon: null | string | undefined,
  ...hints: Array<null | string | undefined>
) {
  const normalizedIcon = String(icon || '').trim()
  if (normalizedIcon) {
    return normalizedIcon
  }

  const normalizedHints = hints
    .map((value) => String(value || '').trim().toLowerCase())
    .filter(Boolean)
    .join(' ')

  if (
    /(dashboard|console|analysis|overview|home|经营总览|支付控制台|概览|商城总览|首页)/.test(
      normalizedHints
    )
  ) {
    return 'ri:dashboard-horizontal-line'
  }

  if (/(payment|payments|pay|支付|收款|通道|轮询|channel|pool|plugin|插件)/.test(normalizedHints)) {
    return 'ri:bank-card-line'
  }

  if (/(finance|财务|资金|money|wallet|cdk|card|recharge|充值)/.test(normalizedHints)) {
    return 'ri:wallet-3-line'
  }

  if (/(risk|风控|audit|审计|shield)/.test(normalizedHints)) {
    return 'ri:shield-check-line'
  }

  if (/(ticket|工单|service|客服)/.test(normalizedHints)) {
    return 'ri:customer-service-2-line'
  }

  if (/(content|主题|公告|导航|theme|news|nav|layout)/.test(normalizedHints)) {
    return 'ri:layout-grid-line'
  }

  if (/(merchant|商户|store|vip|会员|domain|域名)/.test(normalizedHints)) {
    return 'ri:store-2-line'
  }

  if (/(order|订单|list|record|日志)/.test(normalizedHints)) {
    return 'ri:file-list-3-line'
  }

  if (/(system|管理|admin|role|menu|config|user|账号|权限)/.test(normalizedHints)) {
    return 'ri:settings-3-line'
  }

  return 'ri:apps-2-line'
}
