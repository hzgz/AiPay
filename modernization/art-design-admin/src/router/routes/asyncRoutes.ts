/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

// 权限文档：https://www.artd.pro/docs/zh/guide/in-depth/permission.html
import { AppRouteRecord } from '@/types/router'
import { routeModules } from '../modules'

/**
 * 动态路由（需要权限才能访问的路由）
 * 用于渲染菜单以及根据菜单权限动态加载路由，如果没有权限无法访问
 */
export const asyncRoutes: AppRouteRecord[] = routeModules
