/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

import mitt, { type Emitter } from 'mitt'

type Events = {

  triggerFireworks: string | undefined

  openSetting: void

  openSearchDialog: void

  openChat: void

  openLockScreen: void
}

const mittBus: Emitter<Events> = mitt<Events>()

export default mittBus

