<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="flex h-screen w-full">
    <LoginLeftView
      class="merchant-auth__left"
      brand-title="AiPay"
      hero-title="AiPay 商户入驻"
      hero-subtitle="完成开户注册后即可接入支付、配置通道并开始收款"
    />

    <div class="relative flex-1 merchant-auth">
      <AuthTopBar brand-title="AiPay" :show-brand="false" />

      <div class="merchant-auth__wrap">
        <div class="merchant-auth__card">
          <div class="merchant-auth__head">
            <h1>商户注册</h1>
          </div>

          <el-alert
            v-if="pendingPayment"
            type="warning"
            show-icon
            :closable="false"
            class="merchant-auth__alert"
            title="注册已生成待支付订单"
            :description="pendingPaymentDescription"
          />

          <el-alert
            v-if="successMessage"
            type="success"
            show-icon
            :closable="false"
            class="merchant-auth__alert"
            :title="successMessage"
          />

          <ElForm ref="formRef" :model="formData" :rules="rules" label-position="top">
            <ElFormItem label="商户账号" prop="username">
              <ElInput
                v-model.trim="formData.username"
                class="merchant-auth__input"
                placeholder="请输入商户账号"
              />
            </ElFormItem>

            <ElFormItem v-if="requiresEmail" label="邮箱地址" prop="email">
              <ElInput
                v-model.trim="formData.email"
                class="merchant-auth__input"
                placeholder="请输入注册邮箱"
              />
            </ElFormItem>

            <ElFormItem v-if="requiresMobile" label="手机号码" prop="mobile">
              <ElInput
                v-model.trim="formData.mobile"
                class="merchant-auth__input"
                placeholder="请输入手机号码"
              />
            </ElFormItem>

            <ElFormItem v-if="requiresTelegram" label="电报会话标识" prop="tg_chat_id">
              <ElInput
                v-model.trim="formData.tg_chat_id"
                class="merchant-auth__input"
                placeholder="请输入电报会话标识"
              />
            </ElFormItem>

            <ElFormItem label="登录密码" prop="password">
              <ElInput
                v-model.trim="formData.password"
                class="merchant-auth__input"
                type="password"
                show-password
                autocomplete="new-password"
                placeholder="请输入登录密码"
              />
            </ElFormItem>

            <ElFormItem label="确认密码" prop="password2">
              <ElInput
                v-model.trim="formData.password2"
                class="merchant-auth__input"
                type="password"
                show-password
                autocomplete="new-password"
                placeholder="请再次输入登录密码"
              />
            </ElFormItem>

            <ElFormItem v-if="requiresVerifyCode" label="验证码" prop="captcha">
              <div class="merchant-auth__code-row">
                <ElInput
                  v-model.trim="formData.captcha"
                  class="merchant-auth__input"
                  placeholder="请输入收到的验证码"
                />
                <ElButton
                  class="merchant-auth__code-btn"
                  :disabled="codeSending || countdown > 0"
                  @click="handleSendCode"
                >
                  {{
                    countdown > 0 ? `${countdown}s 后重试` : codeSending ? '发送中' : '发送验证码'
                  }}
                </ElButton>
              </div>
            </ElFormItem>

            <ElFormItem v-if="requiresImageCaptcha" label="图形验证码" prop="ordinary_captcha">
              <div class="merchant-auth__captcha-row">
                <ElInput
                  v-model.trim="formData.ordinary_captcha"
                  class="merchant-auth__input"
                  placeholder="请输入图形验证码"
                />
                <button type="button" class="merchant-auth__captcha-card" @click="refreshCaptcha">
                  <img v-if="captchaUrl" :src="captchaUrl" alt="图形验证码" />
                  <span>{{ captchaUrl ? '看不清？点击刷新' : '加载验证码' }}</span>
                </button>
              </div>
            </ElFormItem>

            <MerchantAuthSlider
              v-model="sliderPassed"
              :enabled="requiresSliderVerify"
              :invalid="sliderInvalid"
            />

            <div class="merchant-auth__summary">
              <span>注册方式：{{ registerTypeLabel }}</span>
              <span>校验方式：{{ captchaTypeLabel }}</span>
            </div>

            <ElButton
              class="merchant-auth__submit"
              type="primary"
              :loading="submitting"
              @click="handleSubmit"
            >
              创建商户账号
            </ElButton>

            <div class="merchant-auth__links">
              <ElButton text @click="openFrontHome">返回首页</ElButton>
              <RouterLink class="merchant-auth__link" to="/merchant/login"
                >已有账号，去登录</RouterLink
              >
            </div>
          </ElForm>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage } from 'element-plus'
  import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
  import { RouterLink } from 'vue-router'
  import {
    buildPublicCaptchaUrl,
    createDefaultPublicSoftwareConfig,
    fetchPublicSoftwareConfig,
    sendPublicRegisterCode,
    submitPublicRegister,
    type PublicRegisterPendingPaymentPayload,
    type PublicSoftwareConfigPayload
  } from '@/api/publicAuth'
  import LoginLeftView from '@/components/core/views/login/LoginLeftView.vue'
  import AuthTopBar from '@/components/core/views/login/AuthTopBar.vue'
  import MerchantAuthSlider from '../shared/MerchantAuthSlider.vue'

  defineOptions({ name: 'MerchantRegister' })

  const router = useRouter()
  const route = useRoute()
  const formRef = ref<FormInstance>()
  const config = ref<PublicSoftwareConfigPayload>(createDefaultPublicSoftwareConfig())
  const submitting = ref(false)
  const codeSending = ref(false)
  const countdown = ref(0)
  const captchaUrl = ref('')
  const sliderPassed = ref(false)
  const sliderInvalid = ref(false)
  const successMessage = ref('')
  const pendingPayment = ref<PublicRegisterPendingPaymentPayload | null>(null)
  let countdownTimer: ReturnType<typeof setInterval> | null = null

  const formData = reactive({
    username: '',
    password: '',
    password2: '',
    email: '',
    mobile: '',
    tg_chat_id: '',
    captcha: '',
    ordinary_captcha: ''
  })

  const requiresMobile = computed(() => config.value.register_type === 1)
  const requiresEmail = computed(() => config.value.register_type === 2)
  const requiresTelegram = computed(() => config.value.register_type === 3)
  const requiresVerifyCode = computed(() => [1, 2, 3].includes(config.value.register_type))
  const requiresImageCaptcha = computed(() => config.value.captcha_type !== 0)
  const requiresSliderVerify = computed(() => config.value.merchant_register_drag_verify === 1)

  const registerTypeLabel = computed(() => {
    return (
      {
        0: '账号密码',
        1: '手机验证码',
        2: '邮箱验证码',
        3: '电报验证'
      }[config.value.register_type] || '账号密码'
    )
  })

  const captchaTypeLabel = computed(() => {
    return config.value.captcha_type === 0 ? '未启用图形验证码' : '已启用图形验证码'
  })

  const pendingPaymentDescription = computed(() => {
    if (!pendingPayment.value) {
      return ''
    }

    const methods = Array.isArray(pendingPayment.value.paytype)
      ? pendingPayment.value.paytype
          .map((item) =>
            String(
              (item as { showname?: string })?.showname || (item as { name?: string })?.name || ''
            )
          )
          .filter(Boolean)
          .join(' / ')
      : ''

    return `待支付金额 ${pendingPayment.value.need || '--'}，订单号 ${pendingPayment.value.trade_no || '--'}${
      methods ? `，可用方式：${methods}` : ''
    }。`
  })

  const affiliateId = computed(() => {
    const raw = route.query.aff
    if (typeof raw === 'string') {
      return raw.trim()
    }

    if (Array.isArray(raw)) {
      return String(raw[0] || '').trim()
    }

    return ''
  })

  const rules = computed<FormRules>(() => ({
    username: [{ required: true, message: '请输入商户账号', trigger: 'blur' }],
    password: [
      { required: true, message: '请输入登录密码', trigger: 'blur' },
      { min: 6, message: '登录密码至少 6 位', trigger: 'blur' }
    ],
    password2: [
      { required: true, message: '请再次输入登录密码', trigger: 'blur' },
      {
        validator: (_rule, value: string, callback) => {
          if (value !== formData.password) {
            callback(new Error('两次输入的密码不一致'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ],
    email: requiresEmail.value
      ? [
          { required: true, message: '请输入注册邮箱', trigger: 'blur' },
          { type: 'email', message: '邮箱格式不正确', trigger: ['blur', 'change'] }
        ]
      : [],
    mobile: requiresMobile.value
      ? [
          { required: true, message: '请输入手机号码', trigger: 'blur' },
          { pattern: /^1\d{10}$/, message: '手机号码格式不正确', trigger: ['blur', 'change'] }
        ]
      : [],
    tg_chat_id: requiresTelegram.value
      ? [{ required: true, message: '请输入电报会话标识', trigger: 'blur' }]
      : [],
    captcha: requiresVerifyCode.value
      ? [{ required: true, message: '请输入验证码', trigger: 'blur' }]
      : [],
    ordinary_captcha: requiresImageCaptcha.value
      ? [{ required: true, message: '请输入图形验证码', trigger: 'blur' }]
      : []
  }))

  async function loadConfig() {
    try {
      const response = await fetchPublicSoftwareConfig()
      config.value = {
        ...createDefaultPublicSoftwareConfig(),
        ...(response.data || {})
      }
      if (requiresImageCaptcha.value) {
        refreshCaptcha()
      }
    } catch (error) {
      console.error('[MerchantRegister] Failed to load public register config', error)
      config.value = createDefaultPublicSoftwareConfig()
    }
  }

  function refreshCaptcha() {
    captchaUrl.value = buildPublicCaptchaUrl()
  }

  function startCountdown() {
    clearCountdown()
    countdown.value = 60
    countdownTimer = setInterval(() => {
      if (countdown.value <= 1) {
        clearCountdown()
        return
      }
      countdown.value -= 1
    }, 1000)
  }

  function clearCountdown() {
    if (countdownTimer) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
    countdown.value = 0
  }

  async function handleSendCode() {
    if (!requiresVerifyCode.value) {
      return
    }

    if (requiresSliderVerify.value && !sliderPassed.value) {
      sliderInvalid.value = true
      return
    }

    const fields = [
      requiresEmail.value ? 'email' : null,
      requiresMobile.value ? 'mobile' : null,
      requiresTelegram.value ? 'tg_chat_id' : null,
      requiresImageCaptcha.value ? 'ordinary_captcha' : null
    ].filter(Boolean) as string[]

    const valid = await formRef.value?.validateField(fields).catch(() => false)
    if (valid === false) {
      return
    }

    codeSending.value = true
    try {
      await sendPublicRegisterCode({
        email: formData.email || undefined,
        mobile: formData.mobile || undefined,
        tg_chat_id: formData.tg_chat_id || undefined
      })
      ElMessage.success('验证码已发送')
      startCountdown()
    } catch (error: any) {
      console.error('[MerchantRegister] Failed to send verification code', error)
      ElMessage.error(error?.message || '验证码发送失败')
      if (requiresImageCaptcha.value) {
        refreshCaptcha()
      }
    } finally {
      codeSending.value = false
    }
  }

  async function handleSubmit() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) {
      return
    }

    if (requiresSliderVerify.value && !sliderPassed.value) {
      sliderInvalid.value = true
      return
    }

    submitting.value = true
    successMessage.value = ''
    pendingPayment.value = null

    try {
      const response = await submitPublicRegister({
        username: formData.username,
        password: formData.password,
        password2: formData.password2,
        email: formData.email || undefined,
        mobile: formData.mobile || undefined,
        tg_chat_id: formData.tg_chat_id || undefined,
        captcha: formData.captcha || undefined,
        ordinary_captcha: formData.ordinary_captcha || undefined,
        ...(affiliateId.value ? { superior_id: affiliateId.value } : {})
      })

      if (response.code === 888) {
        pendingPayment.value = (response.data || {}) as PublicRegisterPendingPaymentPayload
        ElMessage.warning('当前注册需要先完成支付')
        return
      }

      successMessage.value = '注册成功，正在跳转到商户登录页。'
      ElMessage.success('注册成功')
      window.setTimeout(() => {
        router.replace('/merchant/login')
      }, 900)
    } catch (error: any) {
      console.error('[MerchantRegister] Registration failed', error)
      ElMessage.error(error?.message || '注册失败，请稍后重试')
      if (requiresImageCaptcha.value) {
        refreshCaptcha()
      }
    } finally {
      submitting.value = false
    }
  }

  function openFrontHome() {
    window.open('/', '_self')
  }

  watch(requiresImageCaptcha, (value) => {
    if (value && !captchaUrl.value) {
      refreshCaptcha()
    }
  })

  watch(sliderPassed, (value) => {
    if (value) {
      sliderInvalid.value = false
    }
  })

  onMounted(loadConfig)
  onBeforeUnmount(clearCountdown)
</script>

<style lang="scss" scoped>
  .merchant-auth {
    background:
      radial-gradient(circle at top left, rgb(16 185 129 / 10%), transparent 26%),
      radial-gradient(circle at right bottom, rgb(59 130 246 / 8%), transparent 28%),
      var(--default-bg-color);
  }

  .dark .merchant-auth {
    background:
      radial-gradient(circle at top left, rgb(16 185 129 / 12%), transparent 26%),
      radial-gradient(circle at right bottom, rgb(59 130 246 / 10%), transparent 28%),
      var(--default-bg-color);
  }

  .merchant-auth__wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 60px);
    padding: 24px;
  }

  .merchant-auth__left {
    flex: 0 0 40%;
    min-width: 420px;
  }

  .merchant-auth__card {
    width: min(620px, 100%);
    padding: 34px;
    background: var(--default-box-color);
    border: 1px solid var(--art-card-border);
    border-radius: calc(var(--custom-radius) / 2 + 6px);
    box-shadow: 0 18px 50px rgb(15 23 42 / 10%);
  }

  .merchant-auth__head h1 {
    margin: 0 0 10px;
    font-size: 32px;
    font-weight: 700;
  }

  .merchant-auth__desc {
    margin: 0 0 24px;
    color: var(--art-gray-600);
    line-height: 1.8;
  }

  .merchant-auth__alert {
    margin-bottom: 18px;
  }

  .merchant-auth__input {
    :deep(.el-input__wrapper) {
      min-height: 46px;
      border-radius: 16px;
    }
  }

  .merchant-auth__code-row,
  .merchant-auth__captcha-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    align-items: stretch;
  }

  .merchant-auth__code-btn,
  .merchant-auth__captcha-card {
    min-width: 140px;
    border-radius: 16px;
  }

  .merchant-auth__captcha-card {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    border: 1px solid var(--art-card-border);
    background: rgba(248, 251, 255, 0.95);
    color: var(--art-gray-700);
    cursor: pointer;
  }

  .merchant-auth__captcha-card img {
    width: 100%;
    height: 52px;
    object-fit: cover;
    border-radius: 10px;
  }

  .merchant-auth__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 4px 0 12px;
    color: var(--art-gray-600);
    font-size: 13px;
  }

  .merchant-auth__summary span {
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(15, 118, 110, 0.08);
    color: #0f766e;
  }

  .merchant-auth__submit {
    width: 100%;
    min-height: 48px;
    margin-top: 4px;
    border-radius: 16px;
    font-size: 15px;
    font-weight: 700;
  }

  .merchant-auth__links {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
  }

  .merchant-auth__link {
    color: var(--el-color-primary);
    text-decoration: none;
    font-weight: 600;
  }

  @media (width <= 1180px) {
    .merchant-auth__left {
      display: none;
    }

    .merchant-auth__wrap {
      min-height: 100vh;
      padding: 80px 16px 24px;
    }
  }

  @media (width <= 640px) {
    .merchant-auth__card {
      padding: 26px 22px;
      border-radius: 24px;
    }

    .merchant-auth__head h1 {
      font-size: 28px;
    }

    .merchant-auth__code-row,
    .merchant-auth__captcha-row,
    .merchant-auth__links {
      grid-template-columns: 1fr;
      flex-direction: column;
      align-items: stretch;
    }

    .merchant-auth__code-btn,
    .merchant-auth__captcha-card {
      min-width: 0;
    }
  }
</style>
