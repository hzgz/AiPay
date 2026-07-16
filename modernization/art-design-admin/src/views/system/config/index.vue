<template>
  <div class="system-config-page art-full-height">
    <ElCard class="config-toolbar-card" shadow="never">
      <div class="config-toolbar">
        <div class="config-toolbar-copy">
          <p class="toolbar-eyebrow">系统配置</p>
          <h2 class="toolbar-title">系统配置中心</h2>
          <p class="toolbar-desc">{{ resolveToolbarDescription() }}</p>

          <div class="toolbar-stat-row">
            <span class="toolbar-stat-chip">可编辑 {{ summary.editable_key_count }} 项</span>
            <span class="toolbar-stat-chip">已配置 {{ summary.editable_filled_count }} 项</span>
            <span class="toolbar-stat-chip">当前展示 {{ totalVisibleFieldCount }} 项</span>
          </div>
        </div>

        <div class="config-toolbar-actions">
          <ElInput
            v-model.trim="keyword"
            clearable
            class="config-toolbar-search"
            placeholder="搜索配置项名称或说明"
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
              <p class="config-editor-desc">{{ resolveGroupDescription(activeForm.description) }}</p>
            </div>

            <div class="config-editor-actions">
              <ElTag effect="plain">{{ activeForm.visibleFields.length }} 项展示中</ElTag>
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
                  <strong>{{ field.label || field.key }}</strong>
                  <ElTag
                    v-if="isFieldFilled(activeForm.key, field)"
                    size="small"
                    type="success"
                    effect="plain"
                  >
                    已配置
                  </ElTag>
                  <ElTag v-else size="small" effect="plain">待配置</ElTag>
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
                    :label="option.label || option.value"
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
  import { fetchGetSystemConfigSummary, fetchUpdateSystemConfigGroup } from '@/api/config'
  import { useAuth } from '@/hooks'

  type ConfigField = Api.Configs.ConfigItem
  type ConfigForm = Api.Configs.EditableForm

  interface DisplayForm extends ConfigForm {
    icon: string
    visibleFields: ConfigField[]
  }

  const CONFIG_HELP_TEXT_EXACT_MAP: Record<string, string> = {
    '在前台与后台展示的主品牌名称。': '前后台统一品牌名。',
    '在后台与系统标识区域展示的产品名称。': '后台产品名称。',
    '用于页面标题和系统简短说明。': '页面标题。',
    '显示在前台页面与控制台摘要中。': '前台简介。',
    '启用邮件发送后用于接收系统通知；留空则不向该邮箱投递。': '接收系统通知的邮箱。',
    '配置后显示在前台页脚。': '前台页脚展示。',
    '支持内部路径或完整图片 URL。': '支持站内路径或图片链接。',
    '选择使用本地背景资源，或改为自定义背景接口。': '选择本地背景或自定义接口。',
    '支持相对路径或完整资源链接。': '支持相对路径或完整链接。',
    '切换接口页使用默认模板还是自定义模板内容。': '切换默认或自定义模板。',
    '从当前模板目录中选择已存在的主题，避免手填目录名。': '从模板目录直接选择。',
    '仅在接口模板切换为“自定义模板”后生效，支持直接填写 HTML 内容。': '切到自定义模板后生效，支持 HTML。',
    '前台首页弹窗文案，支持 HTML 内容。': '支持 HTML。',
    '前台入口页弹窗文案，支持 HTML 内容。': '支持 HTML。',
    '前台注册页弹窗文案，支持 HTML 内容。': '支持 HTML。',
    '前台隐私政策内容，支持 HTML 内容。': '支持 HTML。',
    '前台用户协议内容，支持 HTML 内容。': '支持 HTML。',
    '显示在商户域名管理相关流程附近。': '显示在商户域名流程附近。',
    '显示在商户或域名审核场景附近。': '显示在审核场景附近。',
    '显示在支付通道或账户引导相关区域。': '显示在支付通道引导区域。',
    '开启后显示举报弹窗及相关文案配置。': '开启后显示举报弹窗。',
    '支持 HTML 内容，用于展示举报与风控说明。': '支持 HTML。',
    '请填写完整可访问地址。': '请填写完整可访问地址。',
    '启用后，商户端通道管理可发起测试支付。': '开启后商户可发起测试。',
    '开启后允许商户按既定规则自定义订单号。': '开启后允许商户自定义订单号。',
    '商户测试支付时默认使用的金额。': '测试支付默认金额。',
    '商户测试支付或演示收银台中显示的收款人名称。': '测试支付显示的收款人名称。',
    '支持换行、英文逗号或中文逗号分隔，决定测试支付可选的支付方式。': '支持逗号或换行分隔。',
    '用于测试支付或演示收银台的易支付商户号。': '测试支付使用的商户号。',
    '默认隐藏显示，点击查看后可编辑演示支付密钥。': '点击查看后可编辑。',
    '用于演示支付跳转或接口请求的易支付网关地址。': '测试支付使用的网关地址。',
    '单笔订单允许的最小支付金额。': '单笔最小支付金额。',
    '单笔订单允许的最大支付金额。': '单笔最大支付金额。',
    '订单未支付时自动失效的秒数。': '订单未支付自动失效秒数。',
    '开启后，对外网关地址将改为这里维护的自定义 API 线路。': '开启后使用自定义 API 线路。',
    '支持填写一条或多条自定义网关地址，使用换行或逗号分隔；如需沿用当前站点根路径，可填写 / 。': '支持逗号或换行分隔，填 / 表示当前站点。',
    '同一来源每天最多请求多少次验证码，用于防止短信或邮箱验证码被刷。': '限制验证码每日请求次数。',
    '支付账号连续多少分钟未上报即判定为掉线，1 表示 1 分钟。': '连续未上报多少分钟判定掉线。',
    '控制前台订单记录列表默认展示条数，建议不要设置过大。': '控制前台订单默认展示条数。',
    '决定二维码图片由本地生成，还是调用外部接口生成。': '选择本地生码或外部生码。',
    '支付账号图片入库时，决定使用外部接口解码还是本地解码。': '选择外部解码或本地解码。',
    '基础校验沿用通讯密钥与 token 校验；安全签名要求软件端同时提交时间戳、随机串和签名，正式商用建议优先使用安全签名。': '正式商用建议优先启用安全签名。',
    '强签名模式允许的最大时间漂移，单位秒，默认 300 秒。': '强签名允许的最大时间漂移秒数。',
    '填写外部卡密购买页地址，用户购买卡密后再回本站充值消费。': '填写外部卡密购买页地址。',
    '支持英文逗号、中文逗号或换行分隔，多项内容会统一整理后保存。': '支持逗号或换行分隔。',
    '开启后，前台可跳转到外部卡密购买页，用户购买卡密后回站充值。': '开启后允许跳转外部卡密购买页。',
    '控制订单日志页是否显示补单按钮。': '控制订单日志是否显示补单按钮。',
    '允许新商户通过前台入口注册。': '允许前台注册商户。',
    '商户注册完成前必须先支付注册费用。': '注册前需先支付费用。',
    '启用付费注册后收取的金额。': '付费注册金额。',
    '商户充值流程允许的最小金额。': '商户充值最小金额。',
    '商户充值流程允许的最大金额。': '商户充值最大金额。',
    '启用商户域名提交与审核流程。': '启用商户域名提审流程。',
    '商户每日最多可提交的域名数量，0 表示不限制。': '商户每日可提交域名数量，0 为不限。',
    '命中白名单的域名可直接放行，支持换行或逗号分隔。': '白名单域名直接放行，支持逗号或换行分隔。',
    '命中黑名单的域名将被拒绝提交，支持换行或逗号分隔。': '黑名单域名直接拒绝，支持逗号或换行分隔。',
    '新注册商户需人工审核后才能完全可用。': '新商户注册后需人工审核。',
    '允许商户使用工单/支持模块。': '允许商户使用工单模块。',
    '启用实名认证功能。': '启用实名认证。',
    '决定实名认证使用人脸核验方案，还是使用支付宝身份授权方案。': '选择实名认证方案。',
    '决定实名认证所需费用由平台承担，还是由商户自行承担。': '选择实名费用承担方。',
    '当实名费用由商户承担时，按这里设置的金额进行扣费。': '商户承担实名费用时的扣费金额。',
    '启用实名认证后，要求受保护的商户操作必须先完成实名认证。': '开启后受保护操作需先完成实名。',
    '开启后，商户邀请下级注册并消费时可按返佣规则获得收益。': '开启后支持推广返佣。',
    '决定上级返佣是在商户充值时结算，还是在购买会员时结算。': '选择返佣结算时机。',
    '请输入 0 到 1 之间的小数，例如 0.10 表示返佣 10%。': '填写 0 到 1 之间的小数。',
    '开启后，新注册商户会从指定起始 ID 开始递增分配编号。': '开启后按指定起始 ID 递增。',
    '只允许填写数字，设置后新商户编号会从这个起始值开始递增。': '仅填数字。',
    '开启后，新注册商户会自动获赠一笔账户余额。': '开启后注册送余额。',
    '注册成功后自动发放到商户账户余额中的金额。': '注册赠送余额金额。',
    '开启后，新注册商户会自动获赠指定会员套餐。': '开启后注册送会员套餐。',
    '从会员套餐列表中选择一个作为注册赠送套餐。': '选择注册赠送的会员套餐。',
    '开启后，系统会在会员即将到期前按设定天数发送提醒。': '开启后在会员到期前发送提醒。',
    '会员到期前多少天开始提醒，通常设置为 1 到 7 天。': '会员到期前提醒天数。',
    '控制前台页面是否展示赞助位模块。': '控制前台是否显示赞助位。',
    '开启后，商户端个人设置页允许申请注销账户。': '开启后允许商户申请注销账户。',
    '控制登录、注册、找回密码等流程使用的验证码能力。': '控制登录注册找回密码的验证码能力。',
    '决定商户端登录时采用账号密码、短信、邮箱、社交或 TG 验证。': '选择商户登录方式。',
    '决定新商户注册时使用账号密码、短信、邮箱或 TG 验证。': '选择商户注册方式。',
    '决定忘记密码时可通过短信、邮箱或 TG 完成找回。': '选择找回密码方式。',
    '用于下单或商品风控拦截，支持换行或逗号分隔多个关键词。': '用于下单或商品风控拦截，支持逗号或换行分隔。',
    '默认隐藏显示，展开后可编辑当前敏感配置。': '',
    '请先在“快捷登录管理”中创建 QQ 配置，再在这里绑定前台入口。': '先在快捷登录管理中创建 QQ 配置。',
    '请先在“快捷登录管理”中创建微信配置，再在这里绑定前台入口。': '先在快捷登录管理中创建微信配置。',
    '允许系统通知走邮件通道。': '允许系统通知走邮件通道。',
    '邮件服务连接时使用的传输加密方式。': '邮件服务传输加密方式。',
    '短信验证码开启后，系统会按这里选择的短信通道发送验证码。': '短信验证码发送所用通道。',
    '允许系统通知走 Telegram 通道。': '允许系统通知走 Telegram 通道。',
    '允许系统通知走 WxPusher 通道。': '允许系统通知走 WxPusher 通道。',
    '启用 Telegram 投递后发送充值通知。': 'Telegram 发送充值通知。',
    '启用 Telegram 投递后发送注册通知。': 'Telegram 发送注册通知。',
    '启用 Telegram 投递后发送工单通知。': 'Telegram 发送工单通知。',
    '启用 Telegram 投递后发送 VIP 通知。': 'Telegram 发送会员通知。',
    '支持 [code] 占位符。': '支持 [code] 占位符。',
    '支持 [login_uid]、[login_ip] 和 [login_time] 占位符。': '支持登录通知占位符。',
    '商户注册完成后使用。': '商户注册完成后使用。',
    '支持订单通知占位符。': '支持订单通知占位符。',
    '用于提醒商户账户余额不足。': '用于提醒商户余额不足。',
    '支持 [account_id]、[account_type]、[account_code] 和 [lose_time] 占位符。': '支持账号掉线通知占位符。',
    '用于 VIP 到期提醒。': '用于会员到期提醒。',
    '引导管理员或商户在使用 Telegram 通知前先绑定机器人。': '提示先绑定 Telegram 机器人。',
    '本地上传、支付账号图片与媒体库会按这里选择的存储方式执行。': '选择上传和素材的存储方式。',
    '仅支持非负数值。': '仅填数字。',
    '维护模式开启后，决定展示默认维护页还是自定义维护页内容。': '维护模式下选择默认或自定义页面。'
  }

  const CONFIG_GROUP_DESCRIPTION_EXACT_MAP: Record<string, string> = {
    '按分组维护站点、模板、支付、商户、安全、通知与系统运行配置。':
      '统一维护站点、模板、支付与系统配置。',
    '站点名称、页面标题、Logo、图标以及首页基础展示配置。': '站点名称、标题与首页基础配置。',
    '前台公告、弹窗、协议、公示文案与主题模板设置。': '公告、弹窗、协议与主题配置。',
    '订单金额、通道测试支付、演示支付与二维码相关配置。': '订单金额、测试支付与二维码配置。',
    '商户注册、实名、域名、工单、分销与充值限制配置。': '商户注册、实名、域名与分销配置。',
    '验证码、安全校验与风控提示等常用配置。': '验证码、安全校验与风控配置。',
    '邮件、Telegram、WxPusher 开关与常用通知模板。': '邮件与消息通知配置。',
    '文件策略、上传大小以及存储接入基础配置。': '上传策略与存储接入配置。',
    '停站、维护页和数据清理相关配置。': '停站、维护页与数据清理配置。'
  }

  const SUPPRESSED_HELP_TEXTS = new Set([
    '前后台统一品牌名。',
    '后台产品名称。',
    '页面标题。',
    '前台简介。',
    '前台页脚展示。'
  ])

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

  const loading = ref(false)
  const savingGroupKey = ref('')
  const keyword = ref('')
  const activeGroupKey = ref('')
  const response = ref<Api.Configs.SummaryResponse | null>(null)
  const groupModels = reactive<Record<string, Record<string, string | boolean>>>({})

  const canSaveConfig = computed(() => {
    return hasAuth('groupUpdate') || hasAuth('update') || hasAuth('index')
  })

  const summary = computed(() => response.value?.summary ?? emptySummary)
  const baseForms = computed(() => response.value?.editable_forms ?? [])

  const displayForms = computed<DisplayForm[]>(() => {
    const search = keyword.value.trim().toLowerCase()

    return baseForms.value
      .map((form) => {
        const formText = `${form.title} ${form.description} ${form.key}`.toLowerCase()
        const matchGroup = !search || formText.includes(search)
        const visibleFields = matchGroup
          ? form.fields
          : form.fields.filter((field) => {
              const fieldText = `${field.label} ${field.key} ${field.help_text}`.toLowerCase()
              return fieldText.includes(search)
            })

        return {
          ...form,
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
    loadConfig()
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

    forms.forEach((form) => {
      groupModels[form.key] = Object.fromEntries(
        form.fields.map((field) => [field.key, normalizeFieldValue(field)])
      )
    })
  }

  function ensureGroupModel(groupKey: string) {
    if (!groupModels[groupKey]) {
      groupModels[groupKey] = {}
    }

    return groupModels[groupKey]
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
  }

  function writeBooleanValue(groupKey: string, fieldKey: string, value: boolean | string | number) {
    ensureGroupModel(groupKey)[fieldKey] = value === true || value === 'true' || value === '1'
  }

  function resolveFieldPlaceholder(field: ConfigField) {
    const placeholder = String(field.placeholder || '').trim()
    if (placeholder) {
      return placeholder
    }

    if (field.editor === 'select') {
      return '请选择'
    }

    if (field.editor === 'textarea') {
      return '请输入配置内容'
    }

    return '请输入配置值'
  }

  function compactConfigText(value: string) {
    const raw = String(value || '').trim()
    if (!raw) {
      return ''
    }

    const exact = CONFIG_HELP_TEXT_EXACT_MAP[raw]
    if (typeof exact === 'string') {
      return exact
    }

    return raw
      .replace(/支持相对路径或完整资源链接。/g, '支持相对路径或完整链接。')
      .replace(/支持内部路径或完整图片 URL。/g, '支持站内路径或图片链接。')
      .replace(/支持英文逗号、中文逗号或换行分隔，多项内容会统一整理后保存。/g, '支持逗号或换行分隔。')
      .replace(/支持换行、英文逗号或中文逗号分隔/g, '支持逗号或换行分隔')
      .replace(/，支持 HTML 内容。/g, '，支持 HTML。')
      .replace(/支持 HTML 内容。/g, '支持 HTML。')
  }

  function resolveToolbarDescription() {
    return '统一维护站点、支付与系统配置。'
  }

  function resolveGroupDescription(description: string) {
    const normalized = String(description || '').trim()
    return CONFIG_GROUP_DESCRIPTION_EXACT_MAP[normalized] || compactConfigText(normalized)
  }

  function resolveFieldHelpText(field: ConfigField) {
    const helpText = compactConfigText(String(field.help_text || ''))
    const normalized = helpText.trim()
    if (SUPPRESSED_HELP_TEXTS.has(normalized)) {
      return ''
    }

    return normalized
  }

  function resolveTextareaRows(field: ConfigField) {
    if (field.type === 'html') {
      return 7
    }

    if ((field.max_length || 0) >= 1000) {
      return 6
    }

    return 4
  }

  function isTextareaField(field: ConfigField) {
    return field.editor === 'textarea'
  }

  function isFieldFilled(groupKey: string, field: ConfigField) {
    if (field.editor === 'switch') {
      return true
    }

    return readStringValue(groupKey, field.key).trim().length > 0
  }

  function countFilledFields(form: DisplayForm) {
    return form.visibleFields.filter((field) => isFieldFilled(form.key, field)).length
  }

  function resolveAssetUrl(groupKey: string, field: ConfigField) {
    if (!ASSET_FIELDS.has(field.key)) {
      return ''
    }

    const rawValue = readStringValue(groupKey, field.key).trim()
    if (!rawValue) {
      return ''
    }

    if (/^https?:\/\//i.test(rawValue)) {
      return rawValue
    }

    if (rawValue.startsWith('//')) {
      return `${window.location.protocol}${rawValue}`
    }

    if (rawValue.startsWith('/')) {
      return rawValue
    }

    return `/${rawValue.replace(/^\/+/, '')}`
  }

  function buildGroupPayload(groupKey: string) {
    const form = baseForms.value.find((item) => item.key === groupKey)
    const model = ensureGroupModel(groupKey)
    const payload: Record<string, string | boolean> = {}

    form?.fields.forEach((field) => {
      const currentValue = model[field.key]
      payload[field.key] =
        field.editor === 'switch' ? Boolean(currentValue) : String(currentValue ?? '')
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
    if (!groupKey || !canSaveConfig.value) {
      return
    }

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
    if (!activeForm.value) {
      return
    }

    await saveGroup(activeForm.value.key)
  }

  function resetSearch() {
    keyword.value = ''
  }
</script>

<style scoped lang="scss">
  .system-config-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .config-toolbar-card,
  .config-sidebar-card,
  .config-editor-card {
    border: 1px solid var(--el-border-color-light);
  }

  .config-toolbar-card {
    background: linear-gradient(180deg, rgb(248 250 252 / 1), rgb(255 255 255 / 1));
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
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
  }

  .toolbar-title {
    margin: 0;
    color: #0f172a;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
  }

  .toolbar-desc {
    margin: 0;
    color: #64748b;
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
    border: 1px solid rgb(226 232 240 / 0.92);
    border-radius: 999px;
    background: rgb(241 245 249 / 0.95);
    color: #475569;
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
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(248 250 252 / 1));
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
    border-color: rgb(59 130 246 / 0.1);
    background: rgb(59 130 246 / 0.05);
  }

  .config-sidebar-item.active {
    border-color: rgb(59 130 246 / 0.18);
    background:
      radial-gradient(circle at top right, rgb(59 130 246 / 0.1), transparent 35%),
      rgb(59 130 246 / 0.07);
  }

  .config-sidebar-item__icon {
    display: inline-flex;
    width: 18px;
    justify-content: center;
    flex-shrink: 0;
    color: #64748b;
    font-size: 18px;
  }

  .config-sidebar-item.active .config-sidebar-item__icon {
    color: #2563eb;
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
    color: #0f172a;
    font-size: 14px;
    font-weight: 600;
  }

  .config-sidebar-item.active .config-sidebar-item__copy strong {
    color: #2563eb;
  }

  .config-sidebar-item__copy small {
    color: #94a3b8;
    font-size: 12px;
    white-space: nowrap;
  }

  .config-editor-card {
    background: linear-gradient(180deg, rgb(255 255 255 / 1), rgb(250 250 250 / 1));
  }

  .config-editor-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px 16px;
    border-bottom: 1px solid rgb(226 232 240 / 0.88);
  }

  .config-editor-copy {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  .config-editor-eyebrow {
    margin: 0;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .config-editor-title {
    margin: 0;
    color: #0f172a;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.2;
  }

  .config-editor-desc {
    max-width: 760px;
    margin: 0;
    color: #64748b;
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
    border-bottom: 1px solid rgb(226 232 240 / 0.82);
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
    color: #0f172a;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
  }

  .config-row__help {
    margin: 0;
    color: #64748b;
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
  }

  .config-row__control :deep(.el-input__wrapper),
  .config-row__control :deep(.el-select__wrapper) {
    min-height: 40px;
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
    border: 1px dashed rgb(203 213 225 / 1);
    border-radius: 14px;
    background: rgb(248 250 252 / 0.9);
  }

  .config-asset-preview__thumb {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgb(226 232 240 / 0.92);
  }

  .config-asset-preview__fallback {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 20px;
    background: rgb(241 245 249 / 1);
  }

  .config-asset-preview__meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .config-asset-preview__meta span {
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
  }

  .config-asset-preview__meta a {
    color: #2563eb;
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
    border: 1px dashed rgb(203 213 225 / 1);
    background: rgb(255 255 255 / 0.8);
  }

  @media (width <= 1080px) {
    .config-shell {
      grid-template-columns: 1fr;
    }

    .config-sidebar-card {
      position: static;
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
