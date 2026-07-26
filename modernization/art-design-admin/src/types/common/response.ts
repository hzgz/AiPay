/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */


export interface BaseResponse<T = unknown> {
  
  code: number
  
  msg: string
  
  data: T
}

