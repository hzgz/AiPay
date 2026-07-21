/**
 * 鏍囩椤靛竷灞€閰嶇疆妯″潡
 *
 * 鎻愪緵涓嶅悓鏍囩椤垫牱寮忕殑楂樺害鍜岄棿璺濋厤缃?
 *
 * ## 涓昏鍔熻兘
 *
 * - 澶氱鏍囩椤垫牱寮忛厤缃紙榛樿銆佸崱鐗囥€佽胺姝岄鏍硷級
 * - 鏍囩椤垫墦寮€/鍏抽棴鐘舵€佺殑楂樺害绠＄悊
 * - 椤堕儴闂磋窛鑷姩璁＄畻
 * - 閰嶇疆鑾峰彇鍜岄粯璁ゅ€煎鐞?
 *
 * ## 浣跨敤鍦烘櫙
 *
 * - 宸ヤ綔鏍囩椤碉紙Worktab锛夊竷灞€璁＄畻
 * - 椤甸潰鍐呭鍖哄煙楂樺害璋冩暣
 * - 鏍囩椤垫樉绀?闅愯棌鏃剁殑鍔ㄧ敾
 * - 鍝嶅簲寮忓竷灞€閫傞厤
 *
 * ## 閰嶇疆椤硅鏄?
 *
 * - openTop: 鏍囩椤垫樉绀烘椂锛屽唴瀹瑰尯鍩熻窛绂婚《閮ㄧ殑璺濈
 * - closeTop: 鏍囩椤甸殣钘忔椂锛屽唴瀹瑰尯鍩熻窛绂婚《閮ㄧ殑璺濈
 * - openHeight: 鏍囩椤垫樉绀烘椂鐨勬€婚珮搴︼紙鍖呭惈鏍囩鏍忥級
 * - closeHeight: 鏍囩椤甸殣钘忔椂鐨勬€婚珮搴︼紙浠呭ご閮級
 *
 * ## 鏀寔鐨勬牱寮?
 *
 * - tab-default: 榛樿鏍囩椤垫牱寮?
 * - tab-card: 鍗＄墖寮忔爣绛鹃〉
 * - tab-google: 璋锋瓕娴忚鍣ㄩ鏍兼爣绛鹃〉
 *
 * @module utils/ui/tabs
 * @author AiPay
 */
export const TAB_CONFIG = {
  'tab-default': {
    openTop: 106,
    closeTop: 60,
    openHeight: 121,
    closeHeight: 75
  },
  'tab-card': {
    openTop: 122,
    closeTop: 78,
    openHeight: 139,
    closeHeight: 95
  },
  'tab-google': {
    openTop: 122,
    closeTop: 78,
    openHeight: 139,
    closeHeight: 95
  }
}

// 鑾峰彇褰撳墠 tab 鏍峰紡閰嶇疆锛岃缃粯璁ゅ€?
export const getTabConfig = (style: string) => {
  return TAB_CONFIG[style as keyof typeof TAB_CONFIG] || TAB_CONFIG['tab-card'] // 榛樿浣跨敤 tab-card 閰嶇疆
}

