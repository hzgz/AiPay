export interface MerchantNavItem {
  key: string
  title: string
  description: string
  path: string
  icon: string
}

export interface MerchantNavSection {
  title: string
  items: MerchantNavItem[]
}

export const merchantNavSections: MerchantNavSection[] = [
  {
    title: '工作台',
    items: [
      {
        key: 'dashboard',
        title: '概览看板',
        description: '查看账户余额、经营数据和常用快捷入口',
        path: '/merchant/dashboard',
        icon: 'ri:dashboard-horizontal-line'
      }
    ]
  },
  {
    title: '账户中心',
    items: [
      {
        key: 'profile',
        title: '资料维护',
        description: '维护邮箱、手机号和基础档案信息',
        path: '/merchant/profile',
        icon: 'ri:profile-line'
      },
      {
        key: 'notifications',
        title: '通知设置',
        description: '管理订单提醒、余额提醒和语音播报配置',
        path: '/merchant/notifications',
        icon: 'ri:notification-4-line'
      },
      {
        key: 'connections',
        title: '绑定中心',
        description: '查看第三方登录与通知渠道绑定状态',
        path: '/merchant/connections',
        icon: 'ri:links-line'
      },
      {
        key: 'security',
        title: '安全中心',
        description: '修改密码，查看验证器、实名和近期登录状态',
        path: '/merchant/security',
        icon: 'ri:shield-keyhole-line'
      },
      {
        key: 'real-name',
        title: '实名认证',
        description: '查看实名状态、认证通道和费用说明',
        path: '/merchant/real-name',
        icon: 'ri:verified-badge-line'
      },
      {
        key: 'api',
        title: '接口信息',
        description: '查看网关地址、签名密钥和应用密钥配置',
        path: '/merchant/api',
        icon: 'ri:code-box-line'
      }
    ]
  },
  {
    title: '经营中心',
    items: [
      {
        key: 'orders',
        title: '订单中心',
        description: '查看交易订单、金额、状态和通道信息',
        path: '/merchant/orders',
        icon: 'ri:file-list-3-line'
      },
      {
        key: 'money-logs',
        title: '资金日志',
        description: '查看收入支出、余额变动和记账备注',
        path: '/merchant/money-logs',
        icon: 'ri:exchange-dollar-line'
      },
      {
        key: 'recharges',
        title: '充值中心',
        description: '创建充值订单、兑换卡券并查看充值状态',
        path: '/merchant/recharges',
        icon: 'ri:wallet-3-line'
      },
      {
        key: 'vip',
        title: '会员套餐',
        description: '查看会员资费、费率和当前套餐状态',
        path: '/merchant/vip',
        icon: 'ri:vip-crown-2-line'
      },
      {
        key: 'channels',
        title: '通道管理',
        description: '维护当前商户自有的支付通道，并直接新增、停用或清理专属配置',
        path: '/merchant/channels',
        icon: 'ri:route-line'
      },
      {
        key: 'pools',
        title: '轮询池',
        description: '统一维护轮询池、排序权重和通道分配，按池管理商户收款轮询策略',
        path: '/merchant/pools',
        icon: 'ri:repeat-2-line'
      }
    ]
  },
  {
    title: '服务支持',
    items: [
      {
        key: 'tickets',
        title: '工单中心',
        description: '提交工单、跟进状态并查看分类',
        path: '/merchant/tickets',
        icon: 'ri:customer-service-2-line'
      },
      {
        key: 'affiliate',
        title: '推广返佣',
        description: '查看邀请数据、返佣统计和推广链接概览',
        path: '/merchant/affiliate',
        icon: 'ri:share-forward-line'
      },
      {
        key: 'domains',
        title: '域名管理',
        description: '维护站点域名、审核状态和删除回收',
        path: '/merchant/domains',
        icon: 'ri:global-line'
      },
      {
        key: 'login-logs',
        title: '登录日志',
        description: '查看前台访问记录、来源地址和请求轨迹',
        path: '/merchant/login-logs',
        icon: 'ri:history-line'
      }
    ]
  }
]

export const merchantNavItems = merchantNavSections.flatMap((section) => section.items)

export function findMerchantNavItem(path: string) {
  return merchantNavItems.find((item) => item.path === path)
}
