/**
 * useTableHeight - 琛ㄦ牸楂樺害鑷姩璁＄畻
 *
 * 鑷姩璁＄畻琛ㄦ牸瀹瑰櫒鐨勬渶浣抽珮搴︼紝纭繚琛ㄦ牸鍦ㄤ笉鍚屽竷灞€鍦烘櫙涓嬮兘鑳芥纭樉绀恒€?
 * 鏍规嵁琛ㄦ牸澶撮儴銆佸垎椤靛櫒绛夊厓绱犵殑楂樺害鍔ㄦ€佽皟鏁村鍣ㄩ珮搴︼紝閬垮厤鍑虹幇婊氬姩鏉℃垨甯冨眬閿欎贡銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鍔ㄦ€侀珮搴﹁绠?- 鏍规嵁琛ㄦ牸澶撮儴銆佸垎椤靛櫒楂樺害鑷姩璁＄畻瀹瑰櫒楂樺害
 * 2. 鍝嶅簲寮忔洿鏂?- 閰嶇疆鍙樺寲鏃惰嚜鍔ㄩ噸鏂拌绠楅珮搴?
 * 3. 鐏垫椿閰嶇疆 - 鏀寔鑷畾涔夊悇閮ㄥ垎楂樺害鍜岄棿璺?
 * 4. 鏅鸿兘閫傞厤 - 鏃犻澶栧厓绱犳椂鑷姩浣跨敤 100% 楂樺害
 *
 * @module useTableHeight
 * @author AiPay
 */

import { computed, type Ref } from 'vue'

/**
 * 琛ㄦ牸楂樺害璁＄畻鍣ㄩ厤缃帴鍙?
 */
interface TableHeightOptions {
  /** 鏄惁鏄剧ず琛ㄦ牸澶撮儴 */
  showTableHeader: Ref<boolean>
  /** 鍒嗛〉鍣ㄩ珮搴?*/
  paginationHeight: Ref<number>
  /** 琛ㄦ牸澶撮儴楂樺害 */
  tableHeaderHeight: Ref<number>
  /** 鍒嗛〉鍣ㄩ棿璺?*/
  paginationSpacing: Ref<number>
}

/**
 * 琛ㄦ牸楂樺害璁＄畻鍣ㄧ被
 */
class TableHeightCalculator {
  // 甯搁噺閰嶇疆
  private static readonly DEFAULT_TABLE_HEADER_HEIGHT = 44
  private static readonly TABLE_HEADER_SPACING = 12

  constructor(private options: TableHeightOptions) {}

  /**
   * 璁＄畻瀹瑰櫒楂樺害
   */
  calculate(): { height: string } {
    const offset = this.calculateOffset()
    return {
      height: offset === 0 ? '100%' : `calc(100% - ${offset}px)`
    }
  }

  /**
   * 璁＄畻鍋忕Щ閲?
   */
  private calculateOffset(): number {
    if (!this.options.showTableHeader.value) {
      return this.calculatePaginationOffset()
    }

    const headerHeight = this.getHeaderHeight()
    const paginationOffset = this.calculatePaginationOffset()

    return headerHeight + paginationOffset + TableHeightCalculator.TABLE_HEADER_SPACING
  }

  /**
   * 鑾峰彇琛ㄦ牸澶撮儴楂樺害
   */
  private getHeaderHeight(): number {
    return this.options.tableHeaderHeight.value || TableHeightCalculator.DEFAULT_TABLE_HEADER_HEIGHT
  }

  /**
   * 璁＄畻鍒嗛〉鍣ㄥ亸绉婚噺
   */
  private calculatePaginationOffset(): number {
    const { paginationHeight, paginationSpacing } = this.options
    return paginationHeight.value === 0 ? 0 : paginationHeight.value + paginationSpacing.value
  }
}

/**
 * 琛ㄦ牸楂樺害璁＄畻 Hook
 *
 * 鎻愪緵琛ㄦ牸瀹瑰櫒楂樺害鐨勮嚜鍔ㄨ绠楀姛鑳斤紝鏀寔锛?
 * - 琛ㄦ牸澶撮儴楂樺害
 * - 鍒嗛〉鍣ㄩ珮搴?
 * - 鍔ㄦ€侀棿璺濊绠?
 *
 * @param options 閰嶇疆閫夐」
 * @returns 瀹瑰櫒楂樺害璁＄畻缁撴灉
 */
export function useTableHeight(options: TableHeightOptions) {
  const containerHeight = computed(() => {
    const calculator = new TableHeightCalculator(options)
    return calculator.calculate()
  })

  return {
    /** 瀹瑰櫒楂樺害鏍峰紡瀵硅薄 */
    containerHeight
  }
}

