/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import { AppRouteRecordRaw } from '@/utils/router'
import { merchantRoutes } from './merchantRoutes'

const enablePublicAuthFlows = import.meta.env.VITE_ENABLE_PUBLIC_AUTH_FLOWS === 'true'

export const staticRoutes: AppRouteRecordRaw[] = [
  {
    path: '/',
    name: 'PublicHome',
    component: () => import('@views/public/home/index.vue'),
    meta: { title: 'AiPay', isHideTab: true, publicLanding: true }
  },
  {
    path: '/news',
    redirect: '/news/index',
    meta: { title: '公告中心', isHideTab: true, publicLanding: true }
  },
  {
    path: '/news/index',
    name: 'PublicNewsIndex',
    component: () => import('@views/public/news/index.vue'),
    meta: { title: '公告中心', isHideTab: true, publicLanding: true }
  },
  {
    path: '/news/categories/:type',
    name: 'PublicNewsCategory',
    component: () => import('@views/public/news/index.vue'),
    meta: { title: '公告分类', isHideTab: true, publicLanding: true }
  },
  {
    path: '/news/detail/:id',
    name: 'PublicNewsDetail',
    component: () => import('@views/public/newsDetail/index.vue'),
    meta: { title: '公告详情', isHideTab: true, publicLanding: true }
  },
  {
    path: '/doc/index',
    redirect: '/doc',
    meta: { title: '开发文档', isHideTab: true, publicLanding: true }
  },
  {
    path: '/doc',
    name: 'PublicDocOverview',
    component: () => import('@views/public/docs/index.vue'),
    meta: { title: '开发文档', isHideTab: true, publicLanding: true }
  },
  {
    path: '/doc/api',
    name: 'PublicDocApi',
    component: () => import('@views/public/docs/index.vue'),
    meta: { title: '接口文档', isHideTab: true, publicLanding: true }
  },
  {
    path: '/doc/result',
    name: 'PublicDocResult',
    component: () => import('@views/public/docs/index.vue'),
    meta: { title: '结果通知', isHideTab: true, publicLanding: true }
  },
  {
    path: '/doc/findorder',
    name: 'PublicDocFindOrder',
    component: () => import('@views/public/docs/index.vue'),
    meta: { title: '订单查询', isHideTab: true, publicLanding: true }
  },
  {
    path: '/demo/index',
    redirect: '/demo',
    meta: { title: '支付测试', isHideTab: true, publicLanding: true }
  },
  {
    path: '/test-pay/index',
    redirect: '/demo',
    meta: { title: '支付测试', isHideTab: true, publicLanding: true }
  },
  {
    path: '/test-pay',
    redirect: '/demo',
    meta: { title: '支付测试', isHideTab: true, publicLanding: true }
  },
  {
    path: '/demo',
    name: 'PublicDemo',
    component: () => import('@views/public/demo/index.vue'),
    meta: { title: '支付测试', isHideTab: true, publicLanding: true }
  },
  {
    path: '/auth/login',
    name: 'Login',
    component: () => import('@views/auth/login/index.vue'),
    meta: { title: 'menus.login.title', isHideTab: true }
  },
  ...(enablePublicAuthFlows
    ? [
        {
          path: '/auth/register',
          name: 'Register',
          component: () => import('@views/auth/register/index.vue'),
          meta: { title: 'menus.register.title', isHideTab: true }
        },
        {
          path: '/auth/forget-password',
          name: 'ForgetPassword',
          component: () => import('@views/auth/forgetPassword/index.vue'),
          meta: { title: 'menus.forgetPassword.title', isHideTab: true }
        }
      ]
    : []),
  {
    path: '/403',
    name: 'Exception403',
    component: () => import('@views/exception/403/index.vue'),
    meta: { title: '403', isHideTab: true }
  },
  {
    path: '/500',
    name: 'Exception500',
    component: () => import('@views/exception/500/index.vue'),
    meta: { title: '500', isHideTab: true }
  },
  {
    path: '/outside',
    component: () => import('@views/index/index.vue'),
    name: 'Outside',
    meta: { title: 'menus.outside.title' },
    children: [
      {
        path: '/outside/iframe/:path',
        name: 'Iframe',
        component: () => import('@/views/outside/Iframe.vue'),
        meta: { title: 'iframe' }
      }
    ]
  },
  ...merchantRoutes,
  {
    path: '/:pathMatch(.*)*',
    name: 'Exception404',
    component: () => import('@views/exception/404/index.vue'),
    meta: { title: '404', isHideTab: true }
  }
]
