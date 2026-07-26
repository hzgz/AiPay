<template>
  <div class="flex h-screen w-full">
    <LoginLeftView
      class="merchant-auth__left"
      brand-title="AiPay"
      hero-title="AiPay 商户登录"
      hero-subtitle="登录后即可进入商户中心，管理通道、订单、接口与账户配置"
    />

    <div class="relative flex-1 merchant-auth">
      <AuthTopBar brand-title="AiPay" :show-brand="false" />

      <div class="merchant-auth__wrap">
        <div class="merchant-auth__card">
          <h1>商户登录</h1>
          <p class="merchant-auth__desc">使用商户账号登录后可直接进入商户中心。</p>

          <ElForm ref="formRef" :model="formData" :rules="rules" @keyup.enter="handleSubmit">
            <ElFormItem prop="username">
              <ElInput
                v-model.trim="formData.username"
                class="merchant-auth__input"
                placeholder="请输入商户账号"
              />
            </ElFormItem>

            <ElFormItem prop="password">
              <ElInput
                v-model.trim="formData.password"
                class="merchant-auth__input"
                placeholder="请输入登录密码"
                type="password"
                autocomplete="current-password"
                show-password
              />
            </ElFormItem>

            <MerchantAuthSlider
              ref="sliderRef"
              v-model="sliderPassed"
              :enabled="requiresSliderVerify"
              :invalid="sliderInvalid"
            />

            <ElButton
              class="merchant-auth__submit"
              type="primary"
              :loading="loading"
              @click="handleSubmit"
            >
              进入商户中心
            </ElButton>

            <div class="merchant-auth__links">
              <ElButton text @click="openFrontHome">返回首页</ElButton>
              <div class="merchant-auth__link-group">
                <RouterLink class="merchant-auth__link" to="/merchant/forgot-password"
                  >找回密码</RouterLink
                >
                <RouterLink class="merchant-auth__link" to="/merchant/register">注册商户</RouterLink>
              </div>
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
  import { merchantLogin, MerchantApiError } from '@/api/merchant'
  import {
    createDefaultPublicSoftwareConfig,
    fetchPublicSoftwareConfig,
    type PublicSoftwareConfigPayload
  } from '@/api/publicAuth'
  import { useMerchantStore } from '@/store/modules/merchant'
  import { getMerchantFrontToken, setMerchantFrontToken } from '@/utils/merchantSession'
  import { translateMerchantText } from '../shared/text'
  import LoginLeftView from '@/components/core/views/login/LoginLeftView.vue'
  import AuthTopBar from '@/components/core/views/login/AuthTopBar.vue'
  import MerchantAuthSlider from '../shared/MerchantAuthSlider.vue'
  import { RouterLink } from 'vue-router'

  defineOptions({ name: 'MerchantLogin' })

  const router = useRouter()
  const route = useRoute()
  const merchantStore = useMerchantStore()
  const formRef = ref<FormInstance>()
  const sliderRef = ref<InstanceType<typeof MerchantAuthSlider> | null>(null)
  const config = ref<PublicSoftwareConfigPayload>(createDefaultPublicSoftwareConfig())
  const loading = ref(false)
  const sliderPassed = ref(false)
  const sliderInvalid = ref(false)

  const formData = reactive({
    username: '',
    password: ''
  })

  const requiresSliderVerify = computed(() => config.value.merchant_login_drag_verify === 1)

  const rules: FormRules = {
    username: [{ required: true, message: '请输入商户账号', trigger: 'blur' }],
    password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }]
  }

  async function handleSubmit() {
    if (!formRef.value) {
      return
    }

    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) {
      return
    }

    if (requiresSliderVerify.value && !sliderPassed.value) {
      sliderInvalid.value = true
      return
    }

    loading.value = true
    try {
      const loginPayload = await merchantLogin(formData.username, formData.password)
      merchantStore.clearSession()
      setMerchantFrontToken(String(loginPayload?.token || ''))
      await merchantStore.hydrate(true)
      ElMessage.success('登录成功，正在进入商户中心')

      const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
      await router.replace(redirect || '/merchant/dashboard')
    } catch (error) {
      const message =
        error instanceof MerchantApiError
          ? translateMerchantText(error.message, error.message)
          : '登录失败，请稍后重试'
      ElMessage.error(message)
    } finally {
      loading.value = false
      sliderInvalid.value = false
      if (requiresSliderVerify.value) {
        sliderPassed.value = false
        sliderRef.value?.reset()
      }
    }
  }

  function openFrontHome() {
    window.open('/', '_self')
  }

  onMounted(async () => {
    try {
      const response = await fetchPublicSoftwareConfig().catch(() => null)
      if (response?.data) {
        config.value = {
          ...createDefaultPublicSoftwareConfig(),
          ...response.data
        }
      }
      if (getMerchantFrontToken() !== '') {
        await merchantStore.hydrate()
      } else {
        merchantStore.clearSession()
      }
      if (merchantStore.authenticated) {
        const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
        await router.replace(redirect || '/merchant/dashboard')
      }
    } catch {
      merchantStore.clearSession()
    }
  })

  watch(sliderPassed, (value) => {
    if (value) {
      sliderInvalid.value = false
    }
  })
</script>

<style lang="scss" scoped>
  .merchant-auth {
    background:
      radial-gradient(circle at top left, rgb(245 158 11 / 10%), transparent 26%),
      radial-gradient(circle at right bottom, rgb(59 130 246 / 8%), transparent 28%),
      var(--default-bg-color);
  }

  .dark .merchant-auth {
    background:
      radial-gradient(circle at top left, rgb(245 158 11 / 12%), transparent 26%),
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
    width: min(540px, 100%);
    padding: 34px;
    background: var(--default-box-color);
    border: 1px solid var(--art-card-border);
    border-radius: calc(var(--custom-radius) / 2 + 6px);
    box-shadow: 0 18px 50px rgb(15 23 42 / 10%);
  }

  .merchant-auth__card h1 {
    margin: 0 0 10px;
    font-size: 32px;
    font-weight: 700;
  }

  .merchant-auth__desc {
    margin: 0 0 24px;
    color: var(--art-gray-600);
    line-height: 1.8;
  }

  .merchant-auth__input {
    :deep(.el-input__wrapper) {
      min-height: 46px;
      border-radius: 16px;
    }
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
    margin-top: 12px;
  }

  .merchant-auth__link-group {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: flex-end;
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

    .merchant-auth__card h1 {
      font-size: 28px;
    }

    .merchant-auth__links {
      gap: 10px;
      flex-direction: column;
      align-items: stretch;
    }

    .merchant-auth__link-group {
      justify-content: space-between;
    }
  }
</style>
