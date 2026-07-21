import { AppRouteRecordRaw } from '@/utils/router'

export const merchantRoutes: AppRouteRecordRaw[] = [
  {
    path: '/merchant/login',
    name: 'MerchantLogin',
    component: () => import('@views/merchant/login/index.vue'),
    meta: {
      title: '商户登录',
      isHideTab: true,
      merchantPublic: true
    }
  },
  {
    path: '/merchant/register',
    name: 'MerchantRegister',
    component: () => import('@views/merchant/register/index.vue'),
    meta: {
      title: '商户注册',
      isHideTab: true,
      merchantPublic: true
    }
  },
  {
    path: '/merchant/forgot-password',
    name: 'MerchantForgotPassword',
    component: () => import('@views/merchant/forgot-password/index.vue'),
    meta: {
      title: '找回密码',
      isHideTab: true,
      merchantPublic: true
    }
  },
  {
    path: '/merchant',
    component: () => import('@views/merchant/layout/index.vue'),
    name: 'MerchantLayout',
    redirect: '/merchant/dashboard',
    meta: {
      title: '商户中心',
      isHideTab: true
    },
    children: [
      {
        path: 'dashboard',
        name: 'MerchantDashboard',
        component: () => import('@views/merchant/dashboard/index.vue'),
        meta: { title: '概览看板', isHideTab: true }
      },
      {
        path: 'profile',
        name: 'MerchantProfile',
        component: () => import('@views/merchant/profile/index.vue'),
        meta: { title: '资料维护', isHideTab: true }
      },
      {
        path: 'notifications',
        name: 'MerchantNotifications',
        component: () => import('@views/merchant/notifications/index.vue'),
        meta: { title: '通知设置', isHideTab: true }
      },
      {
        path: 'connections',
        name: 'MerchantConnections',
        component: () => import('@views/merchant/connections/index.vue'),
        meta: { title: '绑定中心', isHideTab: true }
      },
      {
        path: 'security',
        name: 'MerchantSecurity',
        component: () => import('@views/merchant/security/index.vue'),
        meta: { title: '安全中心', isHideTab: true }
      },
      {
        path: 'real-name',
        name: 'MerchantRealName',
        component: () => import('@views/merchant/real-name/index.vue'),
        meta: { title: '实名认证', isHideTab: true }
      },
      {
        path: 'affiliate',
        name: 'MerchantAffiliate',
        component: () => import('@views/merchant/affiliate/index.vue'),
        meta: { title: '推广返佣', isHideTab: true }
      },
      {
        path: 'orders',
        name: 'MerchantOrders',
        component: () => import('@views/merchant/orders/index.vue'),
        meta: { title: '订单中心', isHideTab: true }
      },
      {
        path: 'money-logs',
        name: 'MerchantMoneyLogs',
        component: () => import('@views/merchant/money-logs/index.vue'),
        meta: { title: '资金日志', isHideTab: true }
      },
      {
        path: 'recharges',
        name: 'MerchantRecharges',
        component: () => import('@views/merchant/recharges/index.vue'),
        meta: { title: '充值中心', isHideTab: true }
      },
      {
        path: 'recharge',
        redirect: '/merchant/recharges',
        meta: { title: '充值中心', isHideTab: true }
      },
      {
        path: 'vip',
        name: 'MerchantVip',
        component: () => import('@views/merchant/vip/index.vue'),
        meta: { title: '会员套餐', isHideTab: true }
      },
      {
        path: 'api',
        name: 'MerchantApi',
        component: () => import('@views/merchant/api/index.vue'),
        meta: { title: '接口信息', isHideTab: true }
      },
      {
        path: 'channels',
        name: 'MerchantChannels',
        component: () => import('@views/merchant/channels/index.vue'),
        meta: { title: '通道管理', isHideTab: true }
      },
      {
        path: 'pools',
        name: 'MerchantPools',
        component: () => import('@views/merchant/pools/index.vue'),
        meta: { title: '轮询池', isHideTab: true }
      },
      {
        path: 'tickets',
        name: 'MerchantTickets',
        component: () => import('@views/merchant/tickets/index.vue'),
        meta: { title: '工单中心', isHideTab: true }
      },
      {
        path: 'domains',
        name: 'MerchantDomains',
        component: () => import('@views/merchant/domains/index.vue'),
        meta: { title: '域名管理', isHideTab: true }
      },
      {
        path: 'login-logs',
        name: 'MerchantLoginLogs',
        component: () => import('@views/merchant/login-logs/index.vue'),
        meta: { title: '登录日志', isHideTab: true }
      }
    ]
  }
]
