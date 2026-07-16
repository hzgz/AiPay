import { AppRouteRecord } from '@/types/router'
import { dashboardRoutes } from './dashboard'
import { systemRoutes } from './system'
import { exceptionRoutes } from './exception'

export const routeModules: AppRouteRecord[] = [
  dashboardRoutes,
  systemRoutes,
  exceptionRoutes
]
