

export interface BaseResponse<T = unknown> {
  
  code: number
  
  msg: string
  
  data: T
}

