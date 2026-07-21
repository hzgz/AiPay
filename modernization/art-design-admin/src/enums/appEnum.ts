/**
 * 绯荤粺绾у埆鏋氫妇瀹氫箟妯″潡
 *
 * ## 涓昏鍔熻兘
 *
 * - 鑿滃崟绫诲瀷鏋氫妇锛堝乏渚с€侀《閮ㄣ€佹贩鍚堛€佸弻鏍忥級
 * - 涓婚绫诲瀷鏋氫妇锛堜寒鑹层€佹殫鑹层€佽嚜鍔級
 * - 鑿滃崟涓婚鏋氫妇锛堣璁°€佷寒鑹层€佹殫鑹诧級
 * - 璇█绫诲瀷鏋氫妇锛堜腑鏂囥€佽嫳鏂囷級
 * - 瀹瑰櫒瀹藉害鏋氫妇锛堝叏灞忋€佸浐瀹氾級
 * - 鑿滃崟瀹藉害鏋氫妇锛堟敹璧峰搴︼級
 *
 * @module enums/appEnum
 * @author AiPay
 */

/**
 * 鑿滃崟绫诲瀷
 */
export enum MenuTypeEnum {
  /** 宸︿晶鑿滃崟 */
  LEFT = 'left',
  /** 椤堕儴鑿滃崟 */
  TOP = 'top',
  /** 椤堕儴+宸︿晶鑿滃崟 */
  TOP_LEFT = 'top-left',
  /** 鍙屾爮鑿滃崟 */
  DUAL_MENU = 'dual-menu'
}

/**
 * 绯荤粺涓婚
 */
export enum SystemThemeEnum {
  /** 鏆楄壊涓婚 */
  DARK = 'dark',
  /** 浜壊涓婚 */
  LIGHT = 'light',
  /** 鑷姩涓婚锛堣窡闅忕郴缁燂級 */
  AUTO = 'auto'
}

/**
 * 鑿滃崟涓婚
 */
export enum MenuThemeEnum {
  /** 鏆楄壊涓婚 */
  DARK = 'dark',
  /** 浜壊涓婚 */
  LIGHT = 'light',
  /** 璁捐涓婚 */
  DESIGN = 'design'
}

/**
 * 鑿滃崟瀹藉害
 */
export enum MenuWidth {
  /** 鏀惰捣瀹藉害 */
  CLOSE = '64px'
}

/**
 * 璇█绫诲瀷
 */
export enum LanguageEnum {
  /** 涓枃 */
  ZH = 'zh',
  /** 鑻辨枃 */
  EN = 'en'
}

/**
 * 瀹瑰櫒瀹藉害
 */
export enum ContainerWidthEnum {
  /** 鍏ㄥ睆瀹藉害 */
  FULL = '100%',
  /** 鍥哄畾瀹藉害 */
  BOXED = '1200px'
}

