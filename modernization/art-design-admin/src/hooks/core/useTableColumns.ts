/**
 * useTableColumns - 琛ㄦ牸鍒楅厤缃鐞?
 *
 * 鎻愪緵鍔ㄦ€佺殑琛ㄦ牸鍒楅厤缃鐞嗚兘鍔涳紝鏀寔杩愯鏃剁伒娲绘帶鍒跺垪鐨勬樉绀恒€侀殣钘忋€佹帓搴忕瓑鎿嶄綔銆?
 * 閫氬父涓?useTable 閰嶅悎浣跨敤锛屼负琛ㄦ牸鎻愪緵瀹屾暣鐨勫垪绠＄悊鍔熻兘銆?
 *
 * ## 涓昏鍔熻兘
 *
 * 1. 鍒楁樉绀烘帶鍒?- 鍔ㄦ€佹樉绀?闅愯棌鍒楋紝鏀寔鎵归噺鎿嶄綔
 * 2. 鍒楁帓搴?- 鎷栨嫿鎴栫紪绋嬫柟寮忛噸鏂版帓鍒楀垪椤哄簭
 * 3. 鍒楅厤缃鐞?- 鏂板銆佸垹闄ゃ€佹洿鏂板垪閰嶇疆
 * 4. 鐗规畩鍒楁敮鎸?- 鑷姩澶勭悊 selection銆乪xpand銆乮ndex 绛夌壒娈婂垪
 * 5. 鐘舵€佹寔涔呭寲 - 淇濇寔鍒楃殑鏄剧ず鐘舵€侊紝鏀寔閲嶇疆鍒板垵濮嬬姸鎬?
 *
 * ## 浣跨敤绀轰緥
 *
 * ```typescript
 * const { columns, columnChecks, toggleColumn, reorderColumns } = useTableColumns(() => [
 *   { prop: 'name', label: '濮撳悕', visible: true },
 *   { prop: 'email', label: '閭', visible: true },
 *   { prop: 'status', label: '鐘舵€?, visible: false }
 * ])
 *
 * // 鍒囨崲鍒楁樉绀?
 * toggleColumn('email', false)
 *
 * // 閲嶆柊鎺掑簭
 * reorderColumns(0, 2)
 * ```
 *
 * @module useTableColumns
 * @author AiPay
 */

import { ref, computed, watch } from 'vue'
import { $t } from '@/locales'
import type { ColumnOption } from '@/types/component'

/**
 * 鐗规畩鍒楃被鍨?
 */
const SPECIAL_COLUMNS: Record<string, { prop: string; label: string }> = {
  selection: { prop: '__selection__', label: $t('table.column.selection') },
  expand: { prop: '__expand__', label: $t('table.column.expand') },
  index: { prop: '__index__', label: $t('table.column.index') }
}

/**
 * 鑾峰彇鍒楃殑鍞竴鏍囪瘑
 */
export const getColumnKey = <T>(col: ColumnOption<T>) =>
  SPECIAL_COLUMNS[col.type as keyof typeof SPECIAL_COLUMNS]?.prop ?? (col.prop as string)

/**
 * 鑾峰彇鍒楃殑鏄剧ず鐘舵€?
 * 浼樺厛浣跨敤 visible 瀛楁锛屽鏋滀笉瀛樺湪鍒欎娇鐢?checked 瀛楁
 */
export const getColumnVisibility = <T>(col: ColumnOption<T>): boolean => {
  // visible 浼樺厛绾ч珮浜?checked
  if (col.visible !== undefined) {
    return col.visible
  }
  // 濡傛灉 visible 鏈畾涔夛紝浣跨敤 checked锛岄粯璁や负 true
  return col.checked ?? true
}

/**
 * 鑾峰彇鍒楃殑妫€鏌ョ姸鎬?
 */
export const getColumnChecks = <T>(columns: ColumnOption<T>[]) =>
  columns.map((col) => {
    const special = col.type && SPECIAL_COLUMNS[col.type]
    const visibility = getColumnVisibility(col)

    if (special) {
      return { ...col, prop: special.prop, label: special.label, checked: true, visible: true }
    }
    return { ...col, checked: visibility, visible: visibility }
  })

/**
 * 鍔ㄦ€佸垪閰嶇疆鎺ュ彛
 */
export interface DynamicColumnConfig<T = any> {
  /**
   * 鏂板鍒楋紙鏀寔鍗曚釜鎴栨壒閲忥級
   * @param column 鍒楅厤缃垨鍒楅厤缃暟缁?
   * @param index 鍙€夌殑鎻掑叆浣嶇疆锛岄粯璁ゆ湯灏撅紙鎵归噺鏃朵负绗竴涓垪鐨勪綅缃級
   */
  addColumn: (column: ColumnOption<T> | ColumnOption<T>[], index?: number) => void
  /**
   * 鍒犻櫎鍒楋紙鏀寔鍗曚釜鎴栨壒閲忥級
   * @param prop 鍒楃殑鍞竴鏍囪瘑鎴栨爣璇嗘暟缁?
   */
  removeColumn: (prop: string | string[]) => void
  /**
   * 鍒囨崲鍒楁樉绀虹姸鎬侊紙鏀寔鍗曚釜鎴栨壒閲忥級
   * @param prop 鍒楃殑鍞竴鏍囪瘑鎴栨爣璇嗘暟缁?
   * @param visible 鍙€夌殑鏄剧ず鐘舵€侊紝榛樿鍙栧弽
   */
  toggleColumn: (prop: string | string[], visible?: boolean) => void

  /**
   * 鏇存柊鍒楋紙鏀寔鍗曚釜鎴栨壒閲忥級
   * @param prop 鍒楃殑鍞竴鏍囪瘑鎴栨洿鏂伴厤缃暟缁?
   * @param updates 鍒楅厤缃洿鏂帮紙褰?prop 涓哄瓧绗︿覆鏃朵娇鐢級
   */
  updateColumn: (
    prop: string | Array<{ prop: string; updates: Partial<ColumnOption<T>> }>,
    updates?: Partial<ColumnOption<T>>
  ) => void
  /**
   * 鎵归噺鏇存柊鍒楋紙鍏煎鏃х増鏈紝鎺ㄨ崘浣跨敤 updateColumn 鐨勬暟缁勬ā寮忥級
   * @param updates 鍒楁洿鏂伴厤缃?
   * @deprecated 鎺ㄨ崘浣跨敤 updateColumn 鐨勬暟缁勬ā寮?
   */
  batchUpdateColumns: (updates: Array<{ prop: string; updates: Partial<ColumnOption<T>> }>) => void
  /**
   * 閲嶆柊鎺掑簭鍒?
   * @param fromIndex 婧愮储寮?
   * @param toIndex 鐩爣绱㈠紩
   */
  reorderColumns: (fromIndex: number, toIndex: number) => void
  /**
   * 鑾峰彇鍒楅厤缃?
   * @param prop 鍒楃殑鍞竴鏍囪瘑
   * @returns 鍒楅厤缃?
   */
  getColumnConfig: (prop: string) => ColumnOption<T> | undefined
  /**
   * 鑾峰彇鎵€鏈夊垪閰嶇疆
   * @returns 鎵€鏈夊垪閰嶇疆
   */
  getAllColumns: () => ColumnOption<T>[]
  /**
   * 閲嶇疆鎵€鏈夊垪
   */
  resetColumns: () => void
}

export function useTableColumns<T = any>(
  columnsFactory: () => ColumnOption<T>[]
): {
  columns: any
  columnChecks: any
} & DynamicColumnConfig<T> {
  const dynamicColumns = ref<ColumnOption<T>[]>(columnsFactory())
  const columnChecks = ref<ColumnOption<T>[]>(getColumnChecks(dynamicColumns.value))

  // 褰?dynamicColumns 鍙樺姩鏃讹紝閲嶆柊鐢熸垚 columnChecks 涓斾繚鐣欏凡瀛樺湪鐨勬樉绀虹姸鎬?
  watch(
    dynamicColumns,
    (newCols) => {
      const visibilityMap = new Map(
        columnChecks.value.map((c) => [getColumnKey(c), getColumnVisibility(c)])
      )
      const newChecks = getColumnChecks(newCols).map((c) => {
        const key = getColumnKey(c)
        const visibility = visibilityMap.has(key) ? visibilityMap.get(key) : getColumnVisibility(c)
        return {
          ...c,
          checked: visibility,
          visible: visibility
        }
      })
      columnChecks.value = newChecks
    },
    { deep: true }
  )

  // 褰撳墠鏄剧ず鍒楋紙鍩轰簬 columnChecks 鐨?checked 鎴?visible锛?
  const columns = computed(() => {
    const colMap = new Map(dynamicColumns.value.map((c) => [getColumnKey(c), c]))
    return columnChecks.value
      .filter((c) => getColumnVisibility(c))
      .map((c) => colMap.get(getColumnKey(c)))
      .filter(Boolean) as ColumnOption<T>[]
  })

  // 鏀寔 updater 杩斿洖鏂版暟缁勬垨鐩存帴鍦ㄤ紶鍏ユ暟缁勪笂 mutate
  const setDynamicColumns = (updater: (cols: ColumnOption<T>[]) => void | ColumnOption<T>[]) => {
    const copy = [...dynamicColumns.value]
    const result = updater(copy)
    dynamicColumns.value = Array.isArray(result) ? result : copy
  }

  return {
    columns,
    columnChecks,

    /**
     * 鏂板鍒楋紙鏀寔鍗曚釜鎴栨壒閲忥級
     */
    addColumn: (column: ColumnOption<T> | ColumnOption<T>[], index?: number) =>
      setDynamicColumns((cols) => {
        const next = [...cols]
        const columnsToAdd = Array.isArray(column) ? column : [column]
        const insertIndex =
          typeof index === 'number' && index >= 0 && index <= next.length ? index : next.length

        // 鎵归噺鎻掑叆
        next.splice(insertIndex, 0, ...columnsToAdd)
        return next
      }),

    /**
     * 鍒犻櫎鍒楋紙鏀寔鍗曚釜鎴栨壒閲忥級
     */
    removeColumn: (prop: string | string[]) =>
      setDynamicColumns((cols) => {
        const propsToRemove = Array.isArray(prop) ? prop : [prop]
        return cols.filter((c) => !propsToRemove.includes(getColumnKey(c)))
      }),

    /**
     * 鏇存柊鍒楋紙鏀寔鍗曚釜鎴栨壒閲忥級
     */
    updateColumn: (
      prop: string | Array<{ prop: string; updates: Partial<ColumnOption<T>> }>,
      updates?: Partial<ColumnOption<T>>
    ) => {
      // 鎵归噺妯″紡锛歱rop 鏄暟缁?
      if (Array.isArray(prop)) {
        setDynamicColumns((cols) => {
          const map = new Map(prop.map((u) => [u.prop, u.updates]))
          return cols.map((c) => {
            const key = getColumnKey(c)
            const upd = map.get(key)
            return upd ? { ...c, ...upd } : c
          })
        })
      }
      // 鍗曚釜妯″紡锛歱rop 鏄瓧绗︿覆
      else if (updates) {
        setDynamicColumns((cols) =>
          cols.map((c) => (getColumnKey(c) === prop ? { ...c, ...updates } : c))
        )
      }
    },

    /**
     * 鍒囨崲鍒楁樉绀虹姸鎬侊紙鏀寔鍗曚釜鎴栨壒閲忥級
     */
    toggleColumn: (prop: string | string[], visible?: boolean) => {
      const propsToToggle = Array.isArray(prop) ? prop : [prop]
      const next = [...columnChecks.value]

      propsToToggle.forEach((p) => {
        const i = next.findIndex((c) => getColumnKey(c) === p)
        if (i > -1) {
          const currentVisibility = getColumnVisibility(next[i])
          const newVisibility = visible ?? !currentVisibility
          // 鍚屾椂鏇存柊 checked 鍜?visible 浠ヤ繚鎸佸吋瀹规€?
          next[i] = { ...next[i], checked: newVisibility, visible: newVisibility }
        }
      })

      columnChecks.value = next
    },

    /**
     * 閲嶇疆鎵€鏈夊垪
     */
    resetColumns: () => {
      dynamicColumns.value = columnsFactory()
    },

    /**
     * 鎵归噺鏇存柊鍒楋紙鍏煎鏃х増鏈級
     * @deprecated 鎺ㄨ崘浣跨敤 updateColumn 鐨勬暟缁勬ā寮?
     */
    batchUpdateColumns: (updates) =>
      setDynamicColumns((cols) => {
        const map = new Map(updates.map((u) => [u.prop, u.updates]))
        return cols.map((c) => {
          const key = getColumnKey(c)
          const upd = map.get(key)
          return upd ? { ...c, ...upd } : c
        })
      }),

    /**
     * 閲嶆柊鎺掑簭鍒?
     */
    reorderColumns: (fromIndex: number, toIndex: number) =>
      setDynamicColumns((cols) => {
        if (
          fromIndex < 0 ||
          fromIndex >= cols.length ||
          toIndex < 0 ||
          toIndex >= cols.length ||
          fromIndex === toIndex
        ) {
          return cols
        }
        const next = [...cols]
        const [moved] = next.splice(fromIndex, 1)
        next.splice(toIndex, 0, moved)
        return next
      }),

    /**
     * 鑾峰彇鍒楅厤缃?
     */
    getColumnConfig: (prop: string) => dynamicColumns.value.find((c) => getColumnKey(c) === prop),

    /**
     * 鑾峰彇鎵€鏈夊垪閰嶇疆
     */
    getAllColumns: () => [...dynamicColumns.value]
  }
}

