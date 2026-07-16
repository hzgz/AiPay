import type { FastEnterConfig } from '@/types/config'

const fastEnterConfig: FastEnterConfig = {
  minWidth: 1200,
  applications: [
    {
      name: '控制台',
      description: '核心经营指标与待处理订单',
      icon: 'ri:pie-chart-line',
      iconColor: '#377dff',
      enabled: true,
      order: 1,
      routeName: 'Console'
    },
    {
      name: '经营总览',
      description: '按日查看订单、成交与趋势',
      icon: 'ri:line-chart-line',
      iconColor: '#ff3b30',
      enabled: true,
      order: 2,
      routeName: 'Business'
    },
    {
      name: '系统配置',
      description: '统一维护基础配置与业务开关',
      icon: 'ri:settings-3-line',
      iconColor: '#7A7FFF',
      enabled: true,
      order: 3,
      routeName: 'SystemConfigOverview'
    },
    {
      name: '进程管理',
      description: '查看服务进程与监控状态',
      icon: 'ri:server-line',
      iconColor: '#13DEB9',
      enabled: true,
      order: 4,
      routeName: 'SystemProcesses'
    },
    {
      name: '管理员',
      description: '维护后台账号、状态与权限',
      icon: 'ri:admin-line',
      iconColor: '#ffb100',
      enabled: true,
      order: 5,
      routeName: 'User'
    },
    {
      name: '角色权限',
      description: '管理角色分组与菜单授权',
      icon: 'ri:shield-user-line',
      iconColor: '#ff6b6b',
      enabled: true,
      order: 6,
      routeName: 'Role'
    },
    {
      name: '菜单管理',
      description: '维护路由菜单与图标展示',
      icon: 'ri:menu-line',
      iconColor: '#38C0FC',
      enabled: true,
      order: 7,
      routeName: 'Menus'
    }
  ],
  quickLinks: [
    {
      name: '控制台',
      enabled: true,
      order: 1,
      routeName: 'Console'
    },
    {
      name: '经营总览',
      enabled: true,
      order: 2,
      routeName: 'Business'
    },
    {
      name: '系统配置',
      enabled: true,
      order: 3,
      routeName: 'SystemConfigOverview'
    },
    {
      name: '进程管理',
      enabled: true,
      order: 4,
      routeName: 'SystemProcesses'
    },
    {
      name: '管理员',
      enabled: true,
      order: 5,
      routeName: 'User'
    },
    {
      name: '角色权限',
      enabled: true,
      order: 6,
      routeName: 'Role'
    }
  ]
}

export default Object.freeze(fastEnterConfig)
