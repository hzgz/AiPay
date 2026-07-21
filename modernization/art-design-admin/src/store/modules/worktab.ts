/**
 * 宸ヤ綔鏍囩椤电姸鎬佺鐞嗘ā鍧?
 *
 * 鎻愪緵澶氭爣绛鹃〉鍔熻兘鐨勫畬鏁寸姸鎬佺鐞?
 *
 * ## 涓昏鍔熻兘
 *
 * - 鏍囩椤垫墦寮€鍜屽叧闂?
 * - 鏍囩椤靛浐瀹氬拰鍙栨秷鍥哄畾
 * - 鎵归噺鍏抽棴锛堝乏渚с€佸彸渚с€佸叾浠栥€佸叏閮級
 * - 鏍囩椤电紦瀛樼鐞嗭紙KeepAlive锛?
 * - 鏍囩椤垫爣棰樿嚜瀹氫箟
 * - 鏍囩椤佃矾鐢遍獙璇?
 * - 鍔ㄦ€佽矾鐢卞弬鏁板鐞?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 澶氭爣绛鹃〉瀵艰埅
 * - 椤甸潰缂撳瓨鎺у埗
 * - 鏍囩椤靛彸閿彍鍗?
 * - 鍥哄畾甯哥敤椤甸潰
 * - 鎵归噺鍏抽棴鏍囩
 *
 * ## 鏍稿績鐗规€?
 *
 * - 鏅鸿兘鏍囩椤靛鐢紙鍚岃矾鐢卞悕绉板鐢級
 * - 鍥哄畾鏍囩椤典繚鎶わ紙涓嶅彲鍏抽棴锛?
 * - KeepAlive 缂撳瓨鎺掗櫎绠＄悊
 * - 璺敱鏈夋晥鎬ч獙璇?
 * - 棣栭〉鑷姩淇濈暀
 *
 * ## 鎸佷箙鍖?
 * - 浣跨敤 localStorage 瀛樺偍
 * - 瀛樺偍閿細sys-v{version}-worktab
 * - 鍒锋柊椤甸潰淇濇寔鏍囩鐘舵€?
 *
 * @module store/modules/worktab
 * @author AiPay
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { router } from '@/router'
import { LocationQueryRaw, Router } from 'vue-router'
import { WorkTab } from '@/types'
import { useCommon } from '@/hooks/core/useCommon'

interface WorktabState {
  current: Partial<WorkTab>
  opened: WorkTab[]
  keepAliveExclude: string[]
}

/**
 * 宸ヤ綔鍙版爣绛鹃〉绠＄悊 Store
 */
export const useWorktabStore = defineStore(
  'worktabStore',
  () => {
    // 鐘舵€佸畾涔?
    const current = ref<Partial<WorkTab>>({})
    const opened = ref<WorkTab[]>([])
    const keepAliveExclude = ref<string[]>([])

    // 璁＄畻灞炴€?
    const hasOpenedTabs = computed(() => opened.value.length > 0)
    const hasMultipleTabs = computed(() => opened.value.length > 1)
    const currentTabIndex = computed(() =>
      current.value.path ? opened.value.findIndex((tab) => tab.path === current.value.path) : -1
    )

    /**
     * 鏌ユ壘鏍囩椤电储寮?
     */
    const findTabIndex = (path: string): number => {
      return opened.value.findIndex((tab) => tab.path === path)
    }

    /**
     * 鑾峰彇鏍囩椤?
     */
    const getTab = (path: string): WorkTab | undefined => {
      return opened.value.find((tab) => tab.path === path)
    }

    /**
     * 妫€鏌ユ爣绛鹃〉鏄惁鍙叧闂?     */
    const isTabClosable = (tab: WorkTab): boolean => {
      return !tab.fixedTab
    }

    /**
     * 瀹夊叏鐨勮矾鐢辫烦杞?
     */
    const safeRouterPush = (tab: Partial<WorkTab>): void => {
      if (!tab.path) {
        console.warn('尝试跳转到无效路径的标签页')
        return
      }

      try {
        router.push({
          path: tab.path,
          query: tab.query as LocationQueryRaw
        })
      } catch (error) {
        console.error('璺敱璺宠浆澶辫触:', error)
      }
    }

    /**
     * 鎵撳紑鎴栨縺娲讳竴涓€夐」鍗?
     */
    const openTab = (tab: WorkTab): void => {
      if (!tab.path) {
        console.warn('灏濊瘯鎵撳紑鏃犳晥鐨勬爣绛鹃〉')
        return
      }

      // 浠?keepAlive 鎺掗櫎鍒楄〃涓Щ闄?
      if (tab.name) {
        removeKeepAliveExclude(tab.name)
      }

      // 鍏堟牴鎹矾鐢卞悕绉版煡鎵撅紙搴斿鍔ㄦ€佽矾鐢卞弬鏁板鑷寸殑澶氬紑闂锛夛紝鎵句笉鍒板啀鏍规嵁璺緞鏌ユ壘
      let existingIndex = -1
      if (tab.name) {
        existingIndex = opened.value.findIndex((t) => t.name === tab.name)
      }
      if (existingIndex === -1) {
        existingIndex = findTabIndex(tab.path)
      }

      if (existingIndex === -1) {
        // 鏂板鏍囩椤?
        const insertIndex = tab.fixedTab ? findFixedTabInsertIndex() : opened.value.length
        const newTab = { ...tab }

        if (tab.fixedTab) {
          opened.value.splice(insertIndex, 0, newTab)
        } else {
          opened.value.push(newTab)
        }

        current.value = newTab
      } else {
        // 鏇存柊鐜版湁鏍囩椤碉紙褰撳姩鎬佽矾鐢卞弬鏁版垨鏌ヨ鍙樻洿鏃讹紝澶嶇敤鍚屼竴鏍囩锛?
        const existingTab = opened.value[existingIndex]

        opened.value[existingIndex] = {
          ...existingTab,
          path: tab.path,
          params: tab.params,
          query: tab.query,
          title: tab.title || existingTab.title,
          fixedTab: tab.fixedTab ?? existingTab.fixedTab,
          keepAlive: tab.keepAlive ?? existingTab.keepAlive,
          name: tab.name || existingTab.name,
          icon: tab.icon || existingTab.icon
        }

        current.value = opened.value[existingIndex]
      }
    }

    /**
     * 鏌ユ壘鍥哄畾鏍囩椤电殑鎻掑叆浣嶇疆
     */
    const findFixedTabInsertIndex = (): number => {
      let insertIndex = 0
      for (let i = 0; i < opened.value.length; i++) {
        if (opened.value[i].fixedTab) {
          insertIndex = i + 1
        } else {
          break
        }
      }
      return insertIndex
    }

    /**
     * 鍏抽棴鎸囧畾鐨勯€夐」鍗?
     */
    const removeTab = (path: string): void => {
      const targetTab = getTab(path)
      const targetIndex = findTabIndex(path)

      if (targetIndex === -1) {
        console.warn(`灏濊瘯鍏抽棴涓嶅瓨鍦ㄧ殑鏍囩椤? ${path}`)
        return
      }

      if (targetTab && !isTabClosable(targetTab)) {
        console.warn(`灏濊瘯鍏抽棴鍥哄畾鏍囩椤? ${path}`)
        return
      }

      // 浠庢爣绛鹃〉鍒楄〃涓Щ闄?
      opened.value.splice(targetIndex, 1)

      // 澶勭悊缂撳瓨鎺掗櫎
      if (targetTab?.name) {
        addKeepAliveExclude(targetTab)
      }

      const { homePath } = useCommon()

      // 濡傛灉鍏抽棴鍚庢棤鏍囩椤碉紝璺宠浆棣栭〉
      if (!hasOpenedTabs.value) {
        if (path !== homePath.value) {
          current.value = {}
          safeRouterPush({ path: homePath.value })
        }
        return
      }

      // 濡傛灉鍏抽棴鐨勬槸褰撳墠婵€娲绘爣绛撅紝闇€瑕佹縺娲诲叾浠栨爣绛?
      if (current.value.path === path) {
        const newIndex = targetIndex >= opened.value.length ? opened.value.length - 1 : targetIndex
        current.value = opened.value[newIndex]
        safeRouterPush(current.value)
      }
    }

    /**
     * 鍏抽棴宸︿晶閫夐」鍗?
     */
    const removeLeft = (path: string): void => {
      const targetIndex = findTabIndex(path)

      if (targetIndex === -1) {
        console.warn(`灏濊瘯鍏抽棴宸︿晶鏍囩椤碉紝浣嗙洰鏍囨爣绛鹃〉涓嶅瓨鍦? ${path}`)
        return
      }

      // 鑾峰彇宸︿晶鍙叧闂殑鏍囩椤?
      const leftTabs = opened.value.slice(0, targetIndex)
      const closableLeftTabs = leftTabs.filter(isTabClosable)

      if (closableLeftTabs.length === 0) {
        console.warn('左侧没有可关闭的标签页')
        return
      }

      // 鏍囪涓虹紦瀛樻帓闄?
      markTabsToRemove(closableLeftTabs)

      // 绉婚櫎宸︿晶鍙叧闂殑鏍囩椤?
      opened.value = opened.value.filter(
        (tab, index) => index >= targetIndex || !isTabClosable(tab)
      )

      // 纭繚褰撳墠鏍囩鏄縺娲荤姸鎬?
      const targetTab = getTab(path)
      if (targetTab) {
        current.value = targetTab
      }
    }

    /**
     * 鍏抽棴鍙充晶閫夐」鍗?
     */
    const removeRight = (path: string): void => {
      const targetIndex = findTabIndex(path)

      if (targetIndex === -1) {
        console.warn(`灏濊瘯鍏抽棴鍙充晶鏍囩椤碉紝浣嗙洰鏍囨爣绛鹃〉涓嶅瓨鍦? ${path}`)
        return
      }

      // 鑾峰彇鍙充晶鍙叧闂殑鏍囩椤?
      const rightTabs = opened.value.slice(targetIndex + 1)
      const closableRightTabs = rightTabs.filter(isTabClosable)

      if (closableRightTabs.length === 0) {
        console.warn('右侧没有可关闭的标签页')
        return
      }

      // 鏍囪涓虹紦瀛樻帓闄?
      markTabsToRemove(closableRightTabs)

      // 绉婚櫎鍙充晶鍙叧闂殑鏍囩椤?
      opened.value = opened.value.filter(
        (tab, index) => index <= targetIndex || !isTabClosable(tab)
      )

      // 纭繚褰撳墠鏍囩鏄縺娲荤姸鎬?
      const targetTab = getTab(path)
      if (targetTab) {
        current.value = targetTab
      }
    }

    /**
     * 鍏抽棴鍏朵粬閫夐」鍗?
     */
    const removeOthers = (path: string): void => {
      const targetTab = getTab(path)

      if (!targetTab) {
        console.warn(`灏濊瘯鍏抽棴鍏朵粬鏍囩椤碉紝浣嗙洰鏍囨爣绛鹃〉涓嶅瓨鍦? ${path}`)
        return
      }

      // 鑾峰彇鍏朵粬鍙叧闂殑鏍囩椤?
      const otherTabs = opened.value.filter((tab) => tab.path !== path)
      const closableTabs = otherTabs.filter(isTabClosable)

      if (closableTabs.length === 0) {
        console.warn('没有其他可关闭的标签页')
        return
      }

      // 鏍囪涓虹紦瀛樻帓闄?
      markTabsToRemove(closableTabs)

      // 鍙繚鐣欏綋鍓嶆爣绛惧拰鍥哄畾鏍囩
      opened.value = opened.value.filter((tab) => tab.path === path || !isTabClosable(tab))

      // 纭繚褰撳墠鏍囩鏄縺娲荤姸鎬?
      current.value = targetTab
    }

    /**
     * 鍏抽棴鎵€鏈夊彲鍏抽棴鐨勬爣绛鹃〉
     */
    const removeAll = (): void => {
      const { homePath } = useCommon()
      const hasFixedTabs = opened.value.some((tab) => tab.fixedTab)

      // 鑾峰彇鍙叧闂殑鏍囩椤?
      const closableTabs = opened.value.filter((tab) => {
        if (!isTabClosable(tab)) return false
        // 濡傛灉鏈夊浐瀹氭爣绛撅紝鍒欐墍鏈夊彲鍏抽棴鐨勯兘鍙互鍏抽棴锛涘惁鍒欎繚鐣欓椤?
        return hasFixedTabs || tab.path !== homePath.value
      })

      if (closableTabs.length === 0) {
        console.warn('没有可关闭的标签页')
        return
      }

      // 鏍囪涓虹紦瀛樻帓闄?
      markTabsToRemove(closableTabs)

      // 淇濈暀涓嶅彲鍏抽棴鐨勬爣绛鹃〉鍜岄椤碉紙褰撴病鏈夊浐瀹氭爣绛炬椂锛?
      opened.value = opened.value.filter((tab) => {
        return !isTabClosable(tab) || (!hasFixedTabs && tab.path === homePath.value)
      })

      // 澶勭悊婵€娲荤姸鎬?
      if (!hasOpenedTabs.value) {
        current.value = {}
        safeRouterPush({ path: homePath.value })
        return
      }

      // 閫夋嫨婵€娲荤殑鏍囩椤碉細浼樺厛棣栭〉锛屽叾娆＄涓€涓彲鐢ㄦ爣绛?
      const homeTab = opened.value.find((tab) => tab.path === homePath.value)
      const targetTab = homeTab || opened.value[0]

      current.value = targetTab
      safeRouterPush(targetTab)
    }

    /**
     * 灏嗘寚瀹氶€夐」鍗℃坊鍔犲埌 keepAlive 鎺掗櫎鍒楄〃涓?
     */
    const addKeepAliveExclude = (tab: WorkTab): void => {
      if (!tab.keepAlive || !tab.name) return

      if (!keepAliveExclude.value.includes(tab.name)) {
        keepAliveExclude.value.push(tab.name)
      }
    }

    /**
     * 浠?keepAlive 鎺掗櫎鍒楄〃涓Щ闄ゆ寚瀹氱粍浠跺悕绉?
     */
    const removeKeepAliveExclude = (name: string): void => {
      if (!name) return

      keepAliveExclude.value = keepAliveExclude.value.filter((item) => item !== name)
    }

    /**
     * 灏嗕紶鍏ョ殑涓€缁勯€夐」鍗＄殑缁勪欢鍚嶇О鏍囪涓烘帓闄ょ紦瀛?
     */
    const markTabsToRemove = (tabs: WorkTab[]): void => {
      tabs.forEach((tab) => {
        if (tab.name) {
          addKeepAliveExclude(tab)
        }
      })
    }

    /**
     * 鍒囨崲鎸囧畾鏍囩椤电殑鍥哄畾鐘舵€?
     */
    const toggleFixedTab = (path: string): void => {
      const targetIndex = findTabIndex(path)

      if (targetIndex === -1) {
        console.warn(`灏濊瘯鍒囨崲涓嶅瓨鍦ㄦ爣绛鹃〉鐨勫浐瀹氱姸鎬? ${path}`)
        return
      }

      const tab = { ...opened.value[targetIndex] }
      tab.fixedTab = !tab.fixedTab

      // 绉婚櫎鍘熶綅缃?
      opened.value.splice(targetIndex, 1)

      if (tab.fixedTab) {
        // 鍥哄畾鏍囩鎻掑叆鍒版墍鏈夊浐瀹氭爣绛剧殑鏈熬
        const firstNonFixedIndex = opened.value.findIndex((t) => !t.fixedTab)
        const insertIndex = firstNonFixedIndex === -1 ? opened.value.length : firstNonFixedIndex
        opened.value.splice(insertIndex, 0, tab)
      } else {
        // 闈炲浐瀹氭爣绛炬彃鍏ュ埌鎵€鏈夊浐瀹氭爣绛惧悗
        const fixedCount = opened.value.filter((t) => t.fixedTab).length
        opened.value.splice(fixedCount, 0, tab)
      }

      // 鏇存柊褰撳墠鏍囩寮曠敤
      if (current.value.path === path) {
        current.value = tab
      }
    }

    /**
     * 楠岃瘉宸ヤ綔鍙版爣绛鹃〉鐨勮矾鐢辨湁鏁堟€?
     */
    const validateWorktabs = (routerInstance: Router): void => {
      try {
        // 动态路由校验：优先通过路由 name 判断，其次使用 resolve 匹配参数化路径。
        const isTabRouteValid = (tab: Partial<WorkTab>): boolean => {
          try {
            if (tab.name) {
              const routes = routerInstance.getRoutes()
              if (routes.some((r) => r.name === tab.name)) return true
            }
            if (tab.path) {
              const resolved = routerInstance.resolve({
                path: tab.path,
                query: (tab.query as LocationQueryRaw) || undefined
              })
              return resolved.matched.length > 0
            }
            return false
          } catch {
            return false
          }
        }

        // 杩囨护鍑烘湁鏁堢殑鏍囩椤?
        const validTabs = opened.value.filter((tab) => isTabRouteValid(tab))

        if (validTabs.length !== opened.value.length) {
          console.warn('鍙戠幇鏃犳晥鐨勬爣绛鹃〉璺敱锛屽凡鑷姩娓呯悊')
          opened.value = validTabs
        }

        // 楠岃瘉褰撳墠婵€娲绘爣绛剧殑鏈夋晥鎬?
        const isCurrentValid = current.value && isTabRouteValid(current.value)

        if (!isCurrentValid && validTabs.length > 0) {
          console.warn('当前激活标签无效，已自动切换')
          current.value = validTabs[0]
        } else if (!isCurrentValid) {
          current.value = {}
        }
      } catch (error) {
        console.error('楠岃瘉宸ヤ綔鍙版爣绛鹃〉澶辫触:', error)
      }
    }

    /**
     * 娓呯┖鎵€鏈夌姸鎬侊紙鐢ㄤ簬鐧诲嚭绛夊満鏅級
     */
    const clearAll = (): void => {
      current.value = {}
      opened.value = []
      keepAliveExclude.value = []
    }

    /**
     * 鑾峰彇鐘舵€佸揩鐓э紙鐢ㄤ簬鎸佷箙鍖栧瓨鍌級
     */
    const getStateSnapshot = (): WorktabState => {
      return {
        current: { ...current.value },
        opened: [...opened.value],
        keepAliveExclude: [...keepAliveExclude.value]
      }
    }

    /**
     * 鑾峰彇鏍囩椤垫爣棰?
     */
    const getTabTitle = (path: string): WorkTab | undefined => {
      const tab = getTab(path)
      return tab
    }

    /**
     * 鏇存柊鏍囩椤垫爣棰?
     */
    const updateTabTitle = (path: string, title: string): void => {
      const tab = getTab(path)
      if (tab) {
        tab.customTitle = title
      }
    }

    /**
     * 閲嶇疆鏍囩椤垫爣棰?
     */
    const resetTabTitle = (path: string): void => {
      const tab = getTab(path)
      if (tab) {
        tab.customTitle = ''
      }
    }

    return {
      // 鐘舵€?
      current,
      opened,
      keepAliveExclude,

      // 璁＄畻灞炴€?
      hasOpenedTabs,
      hasMultipleTabs,
      currentTabIndex,

      // 鏂规硶
      openTab,
      removeTab,
      removeLeft,
      removeRight,
      removeOthers,
      removeAll,
      toggleFixedTab,
      validateWorktabs,
      clearAll,
      getStateSnapshot,

      // 宸ュ叿鏂规硶
      findTabIndex,
      getTab,
      isTabClosable,
      addKeepAliveExclude,
      removeKeepAliveExclude,
      markTabsToRemove,
      getTabTitle,
      updateTabTitle,
      resetTabTitle
    }
  },
  {
    persist: {
      key: 'worktab',
      storage: localStorage
    }
  }
)

