<template>
  <div
    class="system-config-page art-full-height"
    :class="{ 'system-config-page--dark': isDark }"
  >
    <ElCard class="config-toolbar-card" shadow="never">
      <div class="config-toolbar">
        <div class="config-toolbar-copy">
          <p class="toolbar-eyebrow">系统配置</p>
          <h2 class="toolbar-title">配置总览</h2>
          <p class="toolbar-desc">{{ resolveToolbarDescription() }}</p>

          <div class="toolbar-stat-row">
            <span class="toolbar-stat-chip">可编辑 {{ summary.editable_key_count }} 项</span>
            <span class="toolbar-stat-chip">已配置 {{ summary.editable_filled_count }} 项</span>
            <span class="toolbar-stat-chip">当前显示 {{ totalVisibleFieldCount }} 项</span>
          </div>
        </div>

        <div class="config-toolbar-actions">
          <ElInput
            v-model.trim="keyword"
            clearable
            class="config-toolbar-search"
            placeholder="搜索配置项名称"
          />
          <ElButton size="small" plain @click="resetSearch">清空</ElButton>
          <ElButton size="small" plain :loading="loading" @click="loadConfig">刷新</ElButton>
          <ElButton
            size="small"
            type="primary"
            :disabled="!activeForm || !canSaveConfig"
            :loading="savingGroupKey === activeForm?.key"
            @click="saveActiveGroup"
          >
            保存当前分组
          </ElButton>
        </div>
      </div>
    </ElCard>

    <template v-if="displayForms.length && activeForm">
      <div class="config-shell">
        <ElCard class="config-sidebar-card" shadow="never">
          <div class="config-sidebar-list">
            <button
              v-for="form in displayForms"
              :key="form.key"
              type="button"
              class="config-sidebar-item"
              :class="{ active: form.key === activeGroupKey }"
              @click="activeGroupKey = form.key"
            >
              <span class="config-sidebar-item__icon">
                <Icon :icon="form.icon" />
              </span>

              <span class="config-sidebar-item__copy">
                <strong>{{ form.title }}</strong>
                <small>{{ form.visibleFields.length }} 项</small>
              </span>
            </button>
          </div>
        </ElCard>

        <ElCard class="config-editor-card" shadow="never">
          <div class="config-editor-head">
            <div class="config-editor-copy">
              <p class="config-editor-eyebrow">当前分组</p>
              <h3 class="config-editor-title">{{ activeForm.title }}</h3>
              <p class="config-editor-desc">{{ activeForm.description }}</p>
            </div>

            <div class="config-editor-actions">
              <ElTag effect="plain">{{ activeForm.visibleFields.length }} 项显示中</ElTag>
              <ElTag type="success" effect="plain">
                {{ countFilledFields(activeForm) }} 项已配置
              </ElTag>
              <ElButton
                size="small"
                type="primary"
                :disabled="!canSaveConfig"
                :loading="savingGroupKey === activeForm.key"
                @click="saveGroup(activeForm.key)"
              >
                保存当前分组
              </ElButton>
            </div>
          </div>

          <div class="config-form-list">
            <section
              v-for="field in activeForm.visibleFields"
              :key="field.key"
              class="config-row"
              :class="{ 'config-row--stacked': isTextareaField(field) }"
            >
              <div class="config-row__meta">
                <div class="config-row__title">
                  <strong>{{ resolveFieldLabel(field) }}</strong>
                  <ElTag
                    size="small"
                    :type="resolveFieldTagType(activeForm.key, field)"
                    effect="plain"
                  >
                    {{ resolveFieldTagText(activeForm.key, field) }}
                  </ElTag>
                </div>

                <p v-if="resolveFieldHelpText(field)" class="config-row__help">
                  {{ resolveFieldHelpText(field) }}
                </p>
              </div>

              <div class="config-row__control">
                <ElSwitch
                  v-if="field.editor === 'switch'"
                  :model-value="readBooleanValue(activeForm.key, field.key)"
                  :disabled="!canSaveConfig"
                  inline-prompt
                  active-text="开"
                  inactive-text="关"
                  @update:model-value="writeBooleanValue(activeForm.key, field.key, $event)"
                />

                <ElSelect
                  v-else-if="field.editor === 'select'"
                  :model-value="readStringValue(activeForm.key, field.key)"
                  :disabled="!canSaveConfig"
                  :placeholder="resolveFieldPlaceholder(field)"
                  @update:model-value="
                    writeStringValue(activeForm.key, field.key, String($event ?? ''))
                  "
                >
                  <ElOption
                    v-for="option in field.options || []"
                    :key="`${field.key}-${option.value}`"
                    :label="
                      resolveOptionLabel(field.key, option.value, option.label || option.value)
                    "
                    :value="option.value"
                  />
                </ElSelect>

                <ElInput
                  v-else-if="field.editor === 'textarea'"
                  :model-value="readStringValue(activeForm.key, field.key)"
                  :disabled="!canSaveConfig"
                  type="textarea"
                  :rows="resolveTextareaRows(field)"
                  :maxlength="field.max_length || undefined"
                  :placeholder="resolveFieldPlaceholder(field)"
                  @update:model-value="writeStringValue(activeForm.key, field.key, $event)"
                />

                <ElInput
                  v-else-if="field.editor === 'password'"
                  :model-value="readStringValue(activeForm.key, field.key)"
                  :disabled="!canSaveConfig"
                  type="password"
                  show-password
                  :maxlength="field.max_length || undefined"
                  :placeholder="resolveFieldPlaceholder(field)"
                  @update:model-value="writeStringValue(activeForm.key, field.key, $event)"
                />

                <ElInput
                  v-else
                  :model-value="readStringValue(activeForm.key, field.key)"
                  :disabled="!canSaveConfig"
                  :maxlength="field.max_length || undefined"
                  :placeholder="resolveFieldPlaceholder(field)"
                  @update:model-value="writeStringValue(activeForm.key, field.key, $event)"
                />

                <div v-if="resolveAssetUrl(activeForm.key, field)" class="config-asset-preview">
                  <ElImage
                    class="config-asset-preview__thumb"
                    :src="resolveAssetUrl(activeForm.key, field)"
                    fit="cover"
                    :preview-src-list="[resolveAssetUrl(activeForm.key, field)]"
                    preview-teleported
                  >
                    <template #error>
                      <div class="config-asset-preview__fallback">
                        <Icon icon="ri:image-line" />
                      </div>
                    </template>
                    <template #placeholder>
                      <div class="config-asset-preview__fallback">
                        <Icon icon="ri:image-line" />
                      </div>
                    </template>
                  </ElImage>

                  <div class="config-asset-preview__meta">
                    <span>图片预览</span>
                    <a
                      :href="resolveAssetUrl(activeForm.key, field)"
                      target="_blank"
                      rel="noreferrer"
                    >
                      打开原图
                    </a>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="config-editor-footer">
            <ElButton
              size="small"
              type="primary"
              :disabled="!canSaveConfig"
              :loading="savingGroupKey === activeForm.key"
              @click="saveGroup(activeForm.key)"
            >
              保存当前分组
            </ElButton>
            <ElButton size="small" plain :loading="loading" @click="loadConfig">刷新数据</ElButton>
          </div>
        </ElCard>
      </div>
    </template>

    <ElEmpty v-else class="config-empty" description="当前筛选条件下没有可编辑的配置项。" />
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { storeToRefs } from 'pinia'
  import { fetchGetSystemConfigSummary, fetchUpdateSystemConfigGroup } from '@/api/config'
  import { useAuth } from '@/hooks'
  import { useSettingStore } from '@/store/modules/setting'
  import { displayAdminFixtureText } from '@/utils/adminFixtureText'

  type ConfigField = Api.Configs.ConfigItem
  type ConfigForm = Api.Configs.EditableForm

  interface DisplayForm extends ConfigForm {
    icon: string
    visibleFields: ConfigField[]
  }

  const GROUP_COPY_MAP: Record<string, { title: string; description: string }> = {
    basic_display: {
      title: '基础展示',
      description: '站点名称、标题、标志和首页入口配置。'
    },
    template_content: {
      title: '模板内容',
      description: '公告、协议、弹窗和公共页面设置。'
    },
    transaction_rules: {
      title: '交易规则',
      description: '订单金额、支付测试和线路配置。'
    },
    merchant_access: {
      title: '商户准入',
      description: '注册、实名、域名、返佣与充值限制。'
    },
    security_auth: {
      title: '安全验证',
      description: '验证码、安全校验和风控提示。'
    },
    notifications: {
      title: '通知提醒',
      description: '邮件、短信、电报和通知模板配置。'
    },
    storage_integrations: {
      title: '存储集成',
      description: '上传、压缩和对象存储配置。'
    },
    maintenance: {
      title: '维护设置',
      description: '停站、维护页和清理设置。'
    }
  }

  const FIELD_LABEL_MAP: Record<string, string> = {
    sitename: '站点名称',
    software_name: '软件名称',
    title: '页面标题',
    desc: '站点简介',
    key: '站点关键字',
    adminMail: '管理员邮箱',
    icp: 'ICP备案',
    logo: '站点标志',
    favicon: '浏览器图标',
    bgtype: '背景类型',
    bg: '全站背景',
    api_bg: '接口页背景',
    apiTemp: '接口页模板',
    home_temp: '首页模板',
    home_url: '首页入口地址',
    diyApiTemp: '自定义接口页模板',
    is_notice: '公告开关',
    demo_theme: '支付测试页模板',
    doc_theme: '文档页模板',
    home_popup: '首页弹窗',
    index_popup: '入口弹窗',
    news_theme: '公告中心模板',
    reg_popup: '注册弹窗',
    privacy: '隐私政策',
    user_agreement: '用户协议',
    domain_notice: '域名提示',
    sh_notice: '首页公告',
    td_notice: '支付页公告',
    user_theme: '商户中心模板',
    is_channelPay: '商户通道测试支付',
    isDiy_orderNo: '自定义订单号开关',
    diy_orderNo: '自定义订单号规则',
    demopay_money: '支付测试默认金额',
    demopay_name: '支付测试收款人名称',
    diy_demoPay: '支付测试可用方式',
    epayid_demo: '支付测试商户号',
    epaykey_demo: '支付测试密钥',
    epayurl_demo: '支付测试网关地址',
    min_orderprice: '单笔最小金额',
    max_orderprice: '单笔最大金额',
    timeout: '订单超时时间',
    is_pay_money: '金额校验',
    is_pay_api: '自定义线路',
    pay_api: '线路地址',
    daily_limit: '验证码日请求上限',
    disconnect_minute: '账号掉线判定分钟',
    orderDisplay: '订单默认条数',
    create_qrCode: '生码方式',
    qr_codeType: '解码方式',
    software_callback_sign_mode: '软件回调签名模式',
    software_callback_sign_window: '软件回调签名时效',
    is_reg: '允许前台注册',
    paid_reg: '付费注册',
    paid_reg_price: '注册费用',
    min_recharge: '商户充值最小金额',
    max_recharge: '商户充值最大金额',
    is_domain: '商户域名提审',
    domainNum: '每日提交域名数量',
    domain_white: '域名白名单',
    domain_black: '域名黑名单',
    is_examine: '新商户人工审核',
    isTicket: '工单支持',
    isRealName: '实名认证',
    realNameType: '实名认证方式',
    realNameBear: '实名费用承担方',
    bearMoney: '实名费用',
    forceRealName: '强制实名保护',
    is_aff: '推广返佣',
    aff_type: '返佣模式',
    aff_percentage: '返佣比例',
    is_diyUserId: '自定义商户编号',
    diy_userId: '起始商户编号',
    is_reg_give_price: '注册赠送余额',
    reg_give_price: '赠送余额金额',
    is_reg_give_vip: '注册赠送会员',
    reg_give_vip: '赠送会员套餐',
    is_vip_expire: '会员到期提醒',
    vip_expire: '提前提醒天数',
    is_paypage_realname: '支付页实名展示',
    is_sponsor: '赞助位展示',
    is_logOff: '允许注销账户',
    isAdminSecurity: '后台安全验证',
    isSecurity: '安全绑定',
    isSecurityForce: '强制安全绑定',
    isSecurityLogin: '登录安全验证',
    code_switch: '短信验证码',
    'captcha-type': '验证码服务',
    'logincode-type': '登录方式',
    'regcode-type': '注册方式',
    'retrieve-type': '找回密码方式',
    merchant_login_drag_verify: '商户登录滑动验证',
    merchant_register_drag_verify: '商户注册滑动验证',
    merchant_retrieve_drag_verify: '找回密码滑动验证',
    smstype: '短信服务商',
    shield_tips: '风控提示',
    shield_key: '风控密钥',
    email_switch: '邮件通知',
    'smtp-host': '邮件服务器',
    'smtp-port': '邮件端口',
    'smtp-user': '发信账号',
    'smtp-pass': '发信密码',
    SmtpSecure: '邮件加密方式',
    'alisms-accessKeyId': '阿里云短信访问密钥编号',
    'alisms-Secret': '阿里云短信访问密钥',
    'alisms-SignName': '阿里云短信签名',
    'alisms-LoginCodeId': '阿里云登录模板编号',
    'alisms-RegCodeId': '阿里云注册模板编号',
    'tensms-AppId': '腾讯云短信应用编号',
    'tensms-accessKeyId': '腾讯云短信访问密钥编号',
    'tensms-Secret': '腾讯云短信访问密钥',
    'tensms-SignName': '腾讯云短信签名',
    'tensms-LoginCodeId': '腾讯云登录模板编号',
    'tensms-RegCodeId': '腾讯云注册模板编号',
    'smsbao-user': '短信宝账号',
    'smsbao-pass': '短信宝密码',
    'smsbao-api': '短信宝接口地址',
    'smsbao-SignName': '短信宝签名',
    tg_switch: '电报通知',
    tg_admin_id: '电报管理员编号',
    tg_bot_token: '电报机器人令牌',
    wxpusher_switch: '微信推送通知',
    wxpusher_appToken: '微信推送应用令牌',
    tg_notice_recharge: '电报充值通知',
    tg_notice_register: '电报注册通知',
    tg_notice_ticket: '电报工单通知',
    tg_notice_vip: '电报会员通知',
    diy_codeTemp: '验证码模板',
    diy_loginTips: '登录通知模板',
    diy_regTips: '注册通知模板',
    diy_orderTips: '订单通知模板',
    diy_moneyTips: '余额提醒模板',
    diy_loseTips: '掉线通知模板',
    diy_vipTemp: '会员通知模板',
    tg_bind_tips: '电报绑定提示',
    'file-type': '文件存储方式',
    imageSize: '图片压缩大小',
    'file-endpoint': '对象存储服务地址',
    'file-accessKeyId': '对象存储访问密钥编号',
    'file-accessKeySecret': '对象存储访问密钥',
    'file-OssName': '对象存储存储桶',
    'qiniu-Domain': '七牛云访问域名',
    'qiniu-Bucket': '七牛云存储桶',
    'qiniu-AK': '七牛云访问密钥编号',
    'qiniu-SK': '七牛云访问密钥',
    isMtce: '维护模式',
    is_weboff: '前台停站',
    mtceType: '维护页类型',
    diyMtceHtml: '自定义维护页',
    is_dataClear: '自动数据清理',
    dataClearDays: '数据保留天数',
    diy_task_key: '计划任务密钥',
    diy_dataClear: '清理目标'
  }

  const FIELD_HELP_MAP: Record<string, string> = {
    home_url: '启用外链首页时填写完整地址。',
    diyApiTemp: '仅在启用自定义接口页时生效。',
    domain_notice: '显示在商户域名提审入口附近。',
    is_channelPay: '开启后，商户可在通道列表发起“测试”。',
    diy_demoPay: '每行一个支付方式编码，例如 wxpay、alipay、qqpay。',
    epayurl_demo: '填写网关地址，留空时按当前站点处理。',
    pay_api: '多条线路可用换行或逗号分隔，填 / 表示当前站点。',
    domain_white: '命中白名单的域名可直接放行。',
    domain_black: '命中黑名单的域名将直接拒绝。',
    diy_userId: '新商户编号会从这里开始递增。',
    tg_bind_tips: '显示在商户端电报绑定入口。',
    diy_task_key: '供计划任务或清理接口调用。',
    diy_dataClear: '填写需要清理的表或目录，一行一个。',
    create_qrCode: '建议优先使用稳定的外部生码服务。',
    qr_codeType: '选择本地或远程解析方式。',
    software_callback_sign_mode: '正式商用建议启用强签名模式。',
    software_callback_sign_window: '单位秒，默认建议 300。',
    imageSize: '仅在启用本地图片压缩时生效。',
    merchant_login_drag_verify: '开启后，商户登录提交前必须先完成滑动验证。',
    merchant_register_drag_verify: '开启后，商户注册发送验证码和提交注册前都需要先完成滑动验证。',
    merchant_retrieve_drag_verify: '开启后，商户找回密码发送验证码和重置密码前都需要先完成滑动验证。'
  }

  const OPTION_LABEL_MAP: Record<string, Record<string, string>> = {
    SmtpSecure: {
      ssl: 'SSL/TLS 加密',
      tls: 'STARTTLS 加密'
    },
    smstype: {
      aliyun: '阿里云',
      qcloud: '腾讯云',
      smsbao: '短信宝'
    },
    software_callback_sign_mode: {
      compat: '基础校验',
      strict: '安全签名'
    },
    apiTemp: {
      default: '标准模板',
      diyApiTemp: '自定义模板'
    },
    mtceType: {
      default: '标准模板',
      diyMtceHtml: '自定义模板'
    }
  }

  const SUSPICIOUS_TEXT_CHAR_REGEX =
    /[\u5a34\u7481\u7eef\u7f01\u9225\u934f\u935f\u93c0\u95ab\ufffd]/gu

  const GROUP_ICONS: Record<string, string> = {
    basic_display: 'ri:global-line',
    template_content: 'ri:layout-4-line',
    transaction_rules: 'ri:bank-card-line',
    merchant_access: 'ri:store-2-line',
    security_auth: 'ri:shield-keyhole-line',
    notifications: 'ri:notification-2-line',
    storage_integrations: 'ri:database-2-line',
    maintenance: 'ri:tools-line'
  }

  const HTML_FIELDS = new Set([
    'diyApiTemp',
    'home_popup',
    'index_popup',
    'reg_popup',
    'privacy',
    'user_agreement',
    'diyMtceHtml'
  ])

  const LIST_TEXT_FIELDS = new Set([
    'diy_demoPay',
    'pay_api',
    'domain_white',
    'domain_black',
    'diy_dataClear'
  ])

  const SENSITIVE_FIELDS = new Set([
    'epaykey_demo',
    'shield_key',
    'smtp-pass',
    'alisms-Secret',
    'tensms-Secret',
    'smsbao-pass',
    'tg_bot_token',
    'wxpusher_appToken',
    'file-accessKeySecret',
    'qiniu-SK',
    'diy_task_key'
  ])

  const ASSET_FIELDS = new Set([
    'logo',
    'favicon',
    'bg',
    'api_bg',
    'diy_userAvatar',
    'securityIcon'
  ])

  const emptySummary: Api.Configs.Summary = {
    total_keys: 0,
    filled_keys: 0,
    empty_keys: 0,
    masked_keys: 0,
    matched_keys: 0,
    group_count: 0,
    editable_group_count: 0,
    editable_key_count: 0,
    editable_filled_count: 0,
    generated_at: ''
  }

  const { hasAuth } = useAuth()
  const settingStore = useSettingStore()
  const { isDark } = storeToRefs(settingStore)

  const loading = ref(false)
  const savingGroupKey = ref('')
  const keyword = ref('')
  const activeGroupKey = ref('')
  const response = ref<Api.Configs.SummaryResponse | null>(null)
  const groupModels = reactive<Record<string, Record<string, string | boolean>>>({})
  const groupDirtyFields = reactive<Record<string, Record<string, boolean>>>({})

  const canSaveConfig = computed(() => hasAuth('groupUpdate') || hasAuth('update'))
  const summary = computed(() => response.value?.summary ?? emptySummary)
  const baseForms = computed(() => response.value?.editable_forms ?? [])

  const displayForms = computed<DisplayForm[]>(() => {
    const search = keyword.value.trim().toLowerCase()

    return baseForms.value
      .map((form) => {
        const resolvedTitle = resolveFormTitle(form)
        const resolvedDescription = resolveFormDescription(form)
        const formText = `${resolvedTitle} ${resolvedDescription} ${form.key}`.toLowerCase()
        const matchGroup = !search || formText.includes(search)
        const visibleFields = matchGroup
          ? form.fields
          : form.fields.filter((field) => {
              const fieldText =
                `${resolveFieldLabel(field)} ${resolveFieldHelpText(field)} ${field.key}`.toLowerCase()
              return fieldText.includes(search)
            })

        return {
          ...form,
          title: resolvedTitle,
          description: resolvedDescription,
          icon: GROUP_ICONS[form.key] || 'ri:settings-3-line',
          visibleFields
        }
      })
      .filter((form) => form.visibleFields.length > 0)
  })

  const activeForm = computed(() => {
    return (
      displayForms.value.find((form) => form.key === activeGroupKey.value) ||
      displayForms.value[0] ||
      null
    )
  })

  const totalVisibleFieldCount = computed(() => {
    return displayForms.value.reduce((count, form) => count + form.visibleFields.length, 0)
  })

  watch(
    displayForms,
    (forms) => {
      if (!forms.length) {
        activeGroupKey.value = ''
        return
      }

      if (!forms.some((form) => form.key === activeGroupKey.value)) {
        activeGroupKey.value = forms[0].key
      }
    },
    { immediate: true }
  )

  onMounted(() => {
    void loadConfig()
  })

  function normalizeSwitchValue(value: unknown) {
    return ['1', 'true', 'yes', 'on'].includes(
      String(value ?? '')
        .trim()
        .toLowerCase()
    )
  }

  function normalizeFieldValue(field: ConfigField): string | boolean {
    const rawValue = field.editable_value ?? field.value ?? ''
    return field.editor === 'switch' ? normalizeSwitchValue(rawValue) : String(rawValue)
  }

  function syncGroupModels(forms: ConfigForm[]) {
    const activeKeys = new Set(forms.map((form) => form.key))

    Object.keys(groupModels).forEach((key) => {
      if (!activeKeys.has(key)) {
        delete groupModels[key]
      }
    })

    Object.keys(groupDirtyFields).forEach((key) => {
      if (!activeKeys.has(key)) {
        delete groupDirtyFields[key]
      }
    })

    forms.forEach((form) => {
      groupModels[form.key] = Object.fromEntries(
        form.fields.map((field) => [field.key, normalizeFieldValue(field)])
      )
      groupDirtyFields[form.key] = {}
    })
  }

  function ensureGroupModel(groupKey: string) {
    if (!groupModels[groupKey]) {
      groupModels[groupKey] = {}
    }

    return groupModels[groupKey]
  }

  function ensureGroupDirtyState(groupKey: string) {
    if (!groupDirtyFields[groupKey]) {
      groupDirtyFields[groupKey] = {}
    }

    return groupDirtyFields[groupKey]
  }

  function markGroupFieldDirty(groupKey: string, fieldKey: string) {
    ensureGroupDirtyState(groupKey)[fieldKey] = true
  }

  function readStringValue(groupKey: string, fieldKey: string) {
    const value = ensureGroupModel(groupKey)[fieldKey]
    return typeof value === 'string' ? value : String(value ?? '')
  }

  function readBooleanValue(groupKey: string, fieldKey: string) {
    return Boolean(ensureGroupModel(groupKey)[fieldKey])
  }

  function writeStringValue(groupKey: string, fieldKey: string, value: string) {
    ensureGroupModel(groupKey)[fieldKey] = String(value ?? '')
    markGroupFieldDirty(groupKey, fieldKey)
  }

  function writeBooleanValue(groupKey: string, fieldKey: string, value: boolean | string | number) {
    ensureGroupModel(groupKey)[fieldKey] = value === true || value === 'true' || value === '1'
    markGroupFieldDirty(groupKey, fieldKey)
  }

  function resolveToolbarDescription() {
    return '集中维护站点、支付和系统设置。'
  }

  function resolveFormTitle(form: ConfigForm) {
    return GROUP_COPY_MAP[form.key]?.title || safeDisplayText(form.title) || form.key
  }

  function resolveFormDescription(form: ConfigForm) {
    return GROUP_COPY_MAP[form.key]?.description || safeDisplayText(form.description)
  }

  function resolveFieldLabel(field: ConfigField) {
    return (
      FIELD_LABEL_MAP[field.key] || safeDisplayText(field.label) || humanizeConfigKey(field.key)
    )
  }

  function resolveFieldHelpText(field: ConfigField) {
    return FIELD_HELP_MAP[field.key] || safeDisplayText(field.help_text) || ''
  }

  function resolveOptionLabel(fieldKey: string, optionValue: string, optionLabel: string) {
    const mapped = OPTION_LABEL_MAP[fieldKey]?.[String(optionValue)]
    if (mapped) return mapped

    const normalized = safeDisplayText(optionLabel)
    if (normalized) return normalized

    const fieldLabel = resolveFieldLabel({ key: fieldKey } as ConfigField)
    return `${fieldLabel}：${optionValue}`
  }

  function resolveFieldPlaceholder(field: ConfigField) {
    const backendPlaceholder = safeDisplayText(field.placeholder)

    if (field.editor === 'select') return backendPlaceholder || '请选择'
    if (backendPlaceholder) return backendPlaceholder
    if (HTML_FIELDS.has(field.key) || field.type === 'html') return '请输入富文本内容'
    if (LIST_TEXT_FIELDS.has(field.key)) return '每行一项，或使用英文逗号分隔'
    if (ASSET_FIELDS.has(field.key)) return '请输入图片地址'
    if (SENSITIVE_FIELDS.has(field.key) || field.editor === 'password') return '请输入密钥或密码'
    if (field.editor === 'textarea') return '请输入内容'
    return '请输入配置值'
  }

  function resolveTextareaRows(field: ConfigField) {
    if (HTML_FIELDS.has(field.key) || field.type === 'html') return 8
    if (LIST_TEXT_FIELDS.has(field.key)) return 5
    if ((field.max_length || 0) >= 1000) return 6
    return 4
  }

  function isTextareaField(field: ConfigField) {
    return field.editor === 'textarea'
  }

  function isSwitchEnabled(groupKey: string, field: ConfigField) {
    return field.editor === 'switch' && readBooleanValue(groupKey, field.key)
  }

  function isFieldFilled(groupKey: string, field: ConfigField) {
    if (field.editor === 'switch') return isSwitchEnabled(groupKey, field)
    return readStringValue(groupKey, field.key).trim().length > 0
  }

  function resolveFieldTagText(groupKey: string, field: ConfigField) {
    if (field.editor === 'switch') {
      return isSwitchEnabled(groupKey, field) ? '已开启' : '已关闭'
    }

    return isFieldFilled(groupKey, field) ? '已配置' : '未设置'
  }

  function resolveFieldTagType(groupKey: string, field: ConfigField) {
    if (field.editor === 'switch') {
      return isSwitchEnabled(groupKey, field) ? 'success' : 'info'
    }

    return isFieldFilled(groupKey, field) ? 'success' : 'info'
  }

  function countFilledFields(form: DisplayForm) {
    return form.visibleFields.filter((field) => isFieldFilled(form.key, field)).length
  }

  function resolveAssetUrl(groupKey: string, field: ConfigField) {
    if (!ASSET_FIELDS.has(field.key)) return ''

    const rawValue = readStringValue(groupKey, field.key).trim()
    if (!rawValue) return ''
    if (/^https?:\/\//i.test(rawValue)) return rawValue
    if (rawValue.startsWith('//')) return `${window.location.protocol}${rawValue}`
    if (rawValue.startsWith('/')) return rawValue
    return `/${rawValue.replace(/^\/+/, '')}`
  }

  function buildGroupPayload(groupKey: string) {
    const form = baseForms.value.find((item) => item.key === groupKey)
    const model = ensureGroupModel(groupKey)
    const dirtyState = ensureGroupDirtyState(groupKey)
    const payload: Record<string, string | boolean> = {}

    form?.fields.forEach((field) => {
      if (!dirtyState[field.key]) {
        return
      }

      const currentValue = model[field.key]
      const normalizedValue =
        field.editor === 'switch' ? Boolean(currentValue) : String(currentValue ?? '')

      payload[field.key] = normalizedValue
    })

    return payload
  }

  async function loadConfig() {
    loading.value = true

    try {
      const result = await fetchGetSystemConfigSummary({})
      response.value = result
      syncGroupModels(result.editable_forms || [])
    } finally {
      loading.value = false
    }
  }

  async function saveGroup(groupKey: string) {
    if (!groupKey || !canSaveConfig.value) return

    savingGroupKey.value = groupKey

    try {
      await fetchUpdateSystemConfigGroup({
        group: groupKey,
        values: buildGroupPayload(groupKey)
      })
      await loadConfig()
    } finally {
      savingGroupKey.value = ''
    }
  }

  async function saveActiveGroup() {
    if (!activeForm.value) return
    await saveGroup(activeForm.value.key)
  }

  function resetSearch() {
    keyword.value = ''
  }

  function safeDisplayText(value: unknown) {
    const raw = String(value ?? '').trim()
    if (!raw) return ''

    const normalized = displayAdminFixtureText(raw, raw).replace(/\s+/g, ' ').trim()
    if (!normalized || looksLikeBrokenText(normalized)) {
      return ''
    }

    return normalized
  }

  function looksLikeBrokenText(value: string) {
    if (value.includes('\uFFFD')) {
      return true
    }

    return (value.match(SUSPICIOUS_TEXT_CHAR_REGEX)?.length ?? 0) >= 2
  }

  function humanizeConfigKey(value: string) {
    return String(value || '')
      .replace(/[-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
  }
</script>

<style scoped lang="scss">
  .system-config-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    --config-card-border: var(--el-border-color-light);
    --config-panel-shadow: 0 20px 48px rgb(15 23 42 / 0.04);
    --config-toolbar-bg: linear-gradient(180deg, rgb(248 250 252 / 1), rgb(255 255 255 / 1));
    --config-sidebar-bg: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 1));
    --config-editor-bg: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(250 250 250 / 1));
    --config-title-color: #0f172a;
    --config-text-color: #475569;
    --config-muted-color: #94a3b8;
    --config-accent-color: #2563eb;
    --config-chip-border: rgb(226 232 240 / 0.92);
    --config-chip-bg: rgb(241 245 249 / 0.95);
    --config-chip-text: #475569;
    --config-sidebar-hover-border: rgb(59 130 246 / 0.1);
    --config-sidebar-hover-bg: rgb(59 130 246 / 0.05);
    --config-sidebar-active-border: rgb(59 130 246 / 0.18);
    --config-sidebar-active-bg:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.1), transparent 35%),
      rgb(59 130 246 / 0.07);
    --config-section-border: rgb(226 232 240 / 0.88);
    --config-input-bg: rgb(255 255 255 / 0.96);
    --config-input-border: rgb(226 232 240 / 0.95);
    --config-input-text: #0f172a;
    --config-input-placeholder: #94a3b8;
    --config-preview-border: rgb(203 213 225 / 1);
    --config-preview-bg: rgb(248 250 252 / 0.9);
    --config-thumb-bg: #fff;
    --config-thumb-border: rgb(226 232 240 / 0.92);
    --config-fallback-bg: rgb(241 245 249 / 1);
    --config-empty-bg: rgb(255 255 255 / 0.8);
  }

  .system-config-page--dark {
    --config-card-border: rgb(71 85 105 / 0.42);
    --config-panel-shadow: 0 24px 56px rgb(2 6 23 / 0.28);
    --config-toolbar-bg: linear-gradient(180deg, rgb(15 23 42 / 0.96), rgb(17 24 39 / 0.9));
    --config-sidebar-bg: linear-gradient(180deg, rgb(15 23 42 / 0.94), rgb(2 6 23 / 0.9));
    --config-editor-bg: linear-gradient(180deg, rgb(17 24 39 / 0.95), rgb(15 23 42 / 0.92));
    --config-title-color: #e5edf8;
    --config-text-color: #cbd5e1;
    --config-muted-color: #94a3b8;
    --config-chip-border: rgb(71 85 105 / 0.52);
    --config-chip-bg: rgb(30 41 59 / 0.84);
    --config-chip-text: #dbe7f5;
    --config-sidebar-hover-border: rgb(96 165 250 / 0.24);
    --config-sidebar-hover-bg: rgb(59 130 246 / 0.14);
    --config-sidebar-active-border: rgb(96 165 250 / 0.34);
    --config-sidebar-active-bg:
      radial-gradient(circle at top right, rgb(96 165 250 / 0.18), transparent 38%),
      rgb(30 64 175 / 0.22);
    --config-section-border: rgb(71 85 105 / 0.42);
    --config-input-bg: rgb(15 23 42 / 0.84);
    --config-input-border: rgb(71 85 105 / 0.74);
    --config-input-text: #e2e8f0;
    --config-input-placeholder: #64748b;
    --config-preview-border: rgb(71 85 105 / 0.56);
    --config-preview-bg: rgb(15 23 42 / 0.72);
    --config-thumb-bg: rgb(15 23 42 / 0.92);
    --config-thumb-border: rgb(71 85 105 / 0.54);
    --config-fallback-bg: rgb(30 41 59 / 0.92);
    --config-empty-bg: rgb(15 23 42 / 0.58);
  }

  .config-toolbar-card,
  .config-sidebar-card,
  .config-editor-card {
    border: 1px solid var(--config-card-border);
    box-shadow: var(--config-panel-shadow);
  }

  .config-toolbar-card {
    background: var(--config-toolbar-bg);
  }

  .config-toolbar-card :deep(.el-card__body) {
    padding: 16px 18px;
  }

  .config-sidebar-card :deep(.el-card__body) {
    padding: 12px;
  }

  .config-editor-card :deep(.el-card__body) {
    padding: 0;
  }

  .config-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .config-toolbar-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .toolbar-eyebrow {
    margin: 0;
    color: var(--config-accent-color);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
  }

  .toolbar-title {
    margin: 0;
    color: var(--config-title-color);
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
  }

  .toolbar-desc {
    margin: 0;
    color: var(--config-text-color);
    font-size: 13px;
    line-height: 1.7;
  }

  .toolbar-stat-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .toolbar-stat-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border: 1px solid var(--config-chip-border);
    border-radius: 999px;
    background: var(--config-chip-bg);
    color: var(--config-chip-text);
    font-size: 12px;
    line-height: 1;
  }

  .config-toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
  }

  .config-toolbar-search {
    width: 300px;
  }

  .config-shell {
    display: grid;
    grid-template-columns: 240px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
  }

  .config-sidebar-card {
    position: sticky;
    top: 0;
    background: var(--config-sidebar-bg);
  }

  .config-sidebar-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .config-sidebar-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: var(--el-component-custom-height);
    padding: 0 12px;
    border: 1px solid transparent;
    border-radius: 12px;
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition:
      background-color 180ms ease,
      border-color 180ms ease,
      color 180ms ease;
  }

  .config-sidebar-item:hover {
    border-color: var(--config-sidebar-hover-border);
    background: var(--config-sidebar-hover-bg);
  }

  .config-sidebar-item.active {
    border-color: var(--config-sidebar-active-border);
    background: var(--config-sidebar-active-bg);
  }

  .config-sidebar-item__icon {
    display: inline-flex;
    width: 18px;
    justify-content: center;
    flex-shrink: 0;
    color: var(--config-text-color);
    font-size: 18px;
  }

  .config-sidebar-item.active .config-sidebar-item__icon {
    color: var(--config-accent-color);
  }

  .config-sidebar-item__copy {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
    flex: 1;
  }

  .config-sidebar-item__copy strong {
    color: var(--config-title-color);
    font-size: 14px;
    font-weight: 600;
  }

  .config-sidebar-item.active .config-sidebar-item__copy strong {
    color: var(--config-accent-color);
  }

  .config-sidebar-item__copy small {
    color: var(--config-muted-color);
    font-size: 12px;
    white-space: nowrap;
  }

  .config-editor-card {
    background: var(--config-editor-bg);
  }

  .config-editor-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px 16px;
    border-bottom: 1px solid var(--config-section-border);
  }

  .config-editor-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  .config-editor-eyebrow {
    margin: 0;
    color: var(--config-accent-color);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .config-editor-title {
    margin: 0;
    color: var(--config-title-color);
    font-size: 26px;
    font-weight: 700;
    line-height: 1.2;
  }

  .config-editor-desc {
    max-width: 760px;
    margin: 0;
    color: var(--config-text-color);
    font-size: 13px;
    line-height: 1.7;
  }

  .config-editor-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
  }

  .config-editor-actions :deep(.el-tag) {
    height: 26px;
    padding: 0 9px;
    border-radius: 999px;
    font-size: 12px;
  }

  .config-form-list {
    display: flex;
    flex-direction: column;
  }

  .config-row {
    display: grid;
    grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
    gap: 18px;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--config-section-border);
  }

  .config-row--stacked {
    align-items: start;
  }

  .config-row__meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
  }

  .config-row__title {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }

  .config-row__title strong {
    color: var(--config-title-color);
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
  }

  .config-row__help {
    margin: 0;
    color: var(--config-text-color);
    font-size: 12px;
    line-height: 1.7;
  }

  .config-row__control {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
  }

  .config-row__control :deep(.el-input),
  .config-row__control :deep(.el-select) {
    width: 100%;
  }

  .config-row__control :deep(.el-input__wrapper),
  .config-row__control :deep(.el-select__wrapper),
  .config-row__control :deep(.el-textarea__inner) {
    border-radius: 12px;
    background: var(--config-input-bg);
    color: var(--config-input-text);
    box-shadow: 0 0 0 1px var(--config-input-border) inset;
  }

  .config-row__control :deep(.el-input__wrapper),
  .config-row__control :deep(.el-select__wrapper) {
    min-height: 40px;
  }

  .config-row__control :deep(.el-input__inner),
  .config-row__control :deep(.el-textarea__inner),
  .config-row__control :deep(.el-select__selected-item),
  .config-row__control :deep(.el-select__placeholder) {
    color: var(--config-input-text);
  }

  .config-row__control :deep(.el-input__inner::placeholder),
  .config-row__control :deep(.el-textarea__inner::placeholder) {
    color: var(--config-input-placeholder);
  }

  .config-row__control :deep(.el-input__wrapper.is-focus),
  .config-row__control :deep(.el-select__wrapper.is-focused),
  .config-row__control :deep(.el-textarea__inner:focus) {
    box-shadow:
      0 0 0 1px var(--config-accent-color) inset,
      0 0 0 3px rgb(37 99 235 / 0.08);
  }

  .config-row__control :deep(.el-textarea__inner) {
    min-height: 104px;
    line-height: 1.65;
  }

  .config-row__control :deep(.el-switch) {
    align-self: flex-start;
  }

  .config-asset-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border: 1px dashed var(--config-preview-border);
    border-radius: 14px;
    background: var(--config-preview-bg);
  }

  .config-asset-preview__thumb {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    border-radius: 12px;
    overflow: hidden;
    background: var(--config-thumb-bg);
    border: 1px solid var(--config-thumb-border);
  }

  .config-asset-preview__fallback {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    color: var(--config-muted-color);
    font-size: 20px;
    background: var(--config-fallback-bg);
  }

  .config-asset-preview__meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .config-asset-preview__meta span {
    color: var(--config-title-color);
    font-size: 13px;
    font-weight: 600;
  }

  .config-asset-preview__meta a {
    color: var(--config-accent-color);
    font-size: 12px;
    text-decoration: none;
  }

  .config-editor-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 20px 18px;
  }

  .config-empty {
    padding: 24px 0;
    border-radius: 18px;
    border: 1px dashed var(--config-preview-border);
    background: var(--config-empty-bg);
  }

  .config-empty :deep(.el-empty__description),
  .config-empty :deep(.el-empty__description p) {
    color: var(--config-text-color);
  }

  @media (width <= 1080px) {
    .config-shell {
      grid-template-columns: 1fr;
    }

    .config-sidebar-card {
      position: static;
    }

    .config-sidebar-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }

    .config-editor-head {
      flex-direction: column;
    }
  }

  @media (width <= 768px) {
    .config-toolbar {
      flex-direction: column;
      align-items: stretch;
    }

    .config-toolbar-actions {
      justify-content: flex-start;
    }

    .config-toolbar-search {
      width: 100%;
    }

    .config-row {
      grid-template-columns: 1fr;
      gap: 14px;
    }

    .config-editor-footer {
      justify-content: flex-start;
      flex-wrap: wrap;
    }
  }
</style>
