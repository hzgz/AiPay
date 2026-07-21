/**
 * 琛ㄦ牸鐘舵€佺鐞嗘ā鍧?
 *
 * 鎻愪緵琛ㄦ牸鏄剧ず閰嶇疆鐨勭姸鎬佺鐞?
 *
 * ## 涓昏鍔熻兘
 *
 * - 琛ㄦ牸灏哄閰嶇疆锛堢揣鍑戙€侀粯璁ゃ€佸鏉撅級
 * - 鏂戦┈绾规樉绀哄紑鍏?
 * - 杈规鏄剧ず寮€鍏?
 * - 琛ㄥご鑳屾櫙鏄剧ず寮€鍏?
 * - 鍏ㄥ睆妯″紡寮€鍏?
 *
 * ## 浣跨敤鍦烘櫙
 * - 琛ㄦ牸缁勪欢鏍峰紡閰嶇疆
 * - 鐢ㄦ埛琛ㄦ牸鍋忓ソ璁剧疆
 * - 琛ㄦ牸宸ュ叿鏍忓姛鑳芥帶鍒?
 *
 * ## 鎸佷箙鍖?
 *
 * - 浣跨敤 localStorage 瀛樺偍
 * - 瀛樺偍閿細sys-v{version}-table
 * - 鐢ㄦ埛閰嶇疆璺ㄩ〉闈繚鎸?
 *
 * @module store/modules/table
 * @author AiPay
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { TableSizeEnum } from '@/enums/formEnum'

// 琛ㄦ牸
export const useTableStore = defineStore(
  'tableStore',
  () => {
    // 琛ㄦ牸澶у皬
    const tableSize = ref(TableSizeEnum.DEFAULT)
    // 鏂戦┈绾?
    const isZebra = ref(false)
    // 杈规
    const isBorder = ref(false)
    // 琛ㄥご鑳屾櫙
    const isHeaderBackground = ref(false)

    // 鏄惁鍏ㄥ睆
    const isFullScreen = ref(false)

    /**
     * 璁剧疆琛ㄦ牸澶у皬
     * @param size 琛ㄦ牸澶у皬鏋氫妇鍊?
     */
    const setTableSize = (size: TableSizeEnum) => (tableSize.value = size)

    /**
     * 璁剧疆鏂戦┈绾规樉绀虹姸鎬?
     * @param value 鏄惁鏄剧ず鏂戦┈绾?
     */
    const setIsZebra = (value: boolean) => (isZebra.value = value)

    /**
     * 璁剧疆琛ㄦ牸杈规鏄剧ず鐘舵€?
     * @param value 鏄惁鏄剧ず杈规
     */
    const setIsBorder = (value: boolean) => (isBorder.value = value)

    /**
     * 璁剧疆琛ㄥご鑳屾櫙鏄剧ず鐘舵€?
     * @param value 鏄惁鏄剧ず琛ㄥご鑳屾櫙
     */
    const setIsHeaderBackground = (value: boolean) => (isHeaderBackground.value = value)

    /**
     * 璁剧疆鏄惁鍏ㄥ睆
     * @param value 鏄惁鍏ㄥ睆
     */
    const setIsFullScreen = (value: boolean) => (isFullScreen.value = value)

    return {
      tableSize,
      isZebra,
      isBorder,
      isHeaderBackground,
      setTableSize,
      setIsZebra,
      setIsBorder,
      setIsHeaderBackground,
      isFullScreen,
      setIsFullScreen
    }
  },
  {
    persist: {
      key: 'table',
      storage: localStorage
    }
  }
)

