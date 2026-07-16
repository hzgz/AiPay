<!-- 登录页面 -->
<template>
  <div class="flex w-full h-screen">
    <LoginLeftView />

    <div class="relative flex-1">
      <AuthTopBar />

      <div class="auth-right-wrap">
        <div class="form">
          <h3 class="title">{{ $t('login.title') }}</h3>
          <p class="sub-title">{{ $t('login.subTitle') }}</p>
          <ElForm
            ref="formRef"
            :model="formData"
            :rules="rules"
            :key="formKey"
            @keyup.enter="handleSubmit"
            style="margin-top: 25px"
          >
            <ElFormItem prop="username">
              <ElInput
                class="custom-height"
                :placeholder="$t('login.placeholder.username')"
                v-model.trim="formData.username"
              />
            </ElFormItem>
            <ElFormItem prop="password">
              <ElInput
                class="custom-height"
                :placeholder="$t('login.placeholder.password')"
                v-model.trim="formData.password"
                type="password"
                autocomplete="off"
                show-password
              />
            </ElFormItem>

            <div class="login-admin-hint">
              {{ adminHint }}
            </div>

            <div v-if="!isAuditBypassEnabled" class="relative pb-5 mt-6">
              <div
                class="relative z-[2] overflow-hidden select-none rounded-lg border border-transparent tad-300"
                :class="{ '!border-[#FF4E4F]': !isPassing && isClickPass }"
              >
                <ArtDragVerify
                  ref="dragVerify"
                  v-model:value="isPassing"
                  :text="$t('login.sliderText')"
                  textColor="var(--art-gray-700)"
                  :successText="$t('login.sliderSuccessText')"
                  progressBarBg="var(--main-color)"
                  :background="isDark ? '#26272F' : '#F1F1F4'"
                  handlerBg="var(--default-box-color)"
                />
              </div>
              <p
                class="absolute top-0 z-[1] px-px mt-2 text-xs text-[#f56c6c] tad-300"
                :class="{ 'translate-y-10': !isPassing && isClickPass }"
              >
                {{ $t('login.placeholder.slider') }}
              </p>
            </div>

            <div
              v-else
              class="mt-6 rounded-lg border border-dashed border-[var(--el-color-success)] bg-[var(--el-color-success-light-9)] px-4 py-3 text-sm text-[var(--el-color-success)]"
            >
              本地调试已启用，当前已跳过滑块验证。
            </div>

            <div class="flex-cb mt-2 text-sm">
              <ElCheckbox v-model="formData.rememberPassword">
                {{ $t('login.rememberPwd') }}
              </ElCheckbox>
              <RouterLink
                v-if="enablePublicAuthFlows"
                class="text-theme"
                :to="{ name: 'ForgetPassword' }"
              >
                {{ $t('login.forgetPwd') }}
              </RouterLink>
            </div>

            <div style="margin-top: 30px">
              <ElButton
                class="w-full"
                type="primary"
                @click="handleSubmit"
                :loading="loading"
                v-ripple
              >
                {{ $t('login.btnText') }}
              </ElButton>
            </div>

            <div v-if="enablePublicAuthFlows" class="mt-5 text-sm text-gray-600">
              <span>{{ $t('login.noAccount') }}</span>
              <RouterLink class="text-theme" :to="{ name: 'Register' }">
                {{ $t('login.register') }}
              </RouterLink>
            </div>
          </ElForm>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import AppConfig from '@/config'
  import { useUserStore } from '@/store/modules/user'
  import { useI18n } from 'vue-i18n'
  import { HttpError } from '@/utils/http/error'
  import { fetchLogin } from '@/api/auth'
  import { ElNotification, type FormInstance, type FormRules } from 'element-plus'
  import { useSettingStore } from '@/store/modules/setting'

  defineOptions({ name: 'Login' })

  const settingStore = useSettingStore()
  const { isDark } = storeToRefs(settingStore)
  const { t, locale } = useI18n()
  const formKey = ref(0)

  watch(locale, () => {
    formKey.value++
  })

  const dragVerify = ref()

  const userStore = useUserStore()
  const router = useRouter()
  const route = useRoute()
  const adminDefaultEntry = '/dashboard/console'
  const isPassing = ref(false)
  const isClickPass = ref(false)
  const enablePublicAuthFlows = import.meta.env.VITE_ENABLE_PUBLIC_AUTH_FLOWS === 'true'
  const allowLocalAuditBypass =
    import.meta.env.DEV && import.meta.env.VITE_ENABLE_LOCAL_AUDIT_BYPASS === 'true'
  const isLocalAuditHost =
    typeof window !== 'undefined' &&
    ['127.0.0.1', 'localhost'].includes(window.location.hostname)
  const isAuditBypassEnabled = computed(
    () =>
      allowLocalAuditBypass &&
      isLocalAuditHost &&
      ['1', 'true'].includes(String(route.query.audit || ''))
  )
  const adminHint = computed(() => '请输入管理员账号和密码登录后台。')

  const systemName = AppConfig.systemInfo.name
  const formRef = ref<FormInstance>()

  const formData = reactive({
    username: '',
    password: '',
    rememberPassword: true
  })

  const rules = computed<FormRules>(() => ({
    username: [{ required: true, message: t('login.placeholder.username'), trigger: 'blur' }],
    password: [{ required: true, message: t('login.placeholder.password'), trigger: 'blur' }]
  }))

  const loading = ref(false)

  onMounted(() => {
    formData.username = ''
    formData.password = ''
    if (isAuditBypassEnabled.value) {
      isPassing.value = true
    }
  })

  const handleSubmit = async () => {
    if (!formRef.value) return

    try {
      const valid = await formRef.value.validate()
      if (!valid) return

      if (!isPassing.value && !isAuditBypassEnabled.value) {
        isClickPass.value = true
        return
      }

      loading.value = true

      const { username, password } = formData
      const { token, refreshToken } = await fetchLogin({
        userName: username,
        password
      })

      if (!token) {
        throw new Error('Login failed - no token received')
      }

      userStore.setToken(token, refreshToken)
      userStore.setLoginStatus(true)
      showLoginSuccessNotice()

      const redirect = typeof route.query.redirect === 'string' ? route.query.redirect.trim() : ''
      const resolvedRedirect = redirect ? router.resolve(redirect) : null
      const shouldUseRedirect =
        redirect !== '' &&
        redirect !== '/' &&
        !redirect.startsWith('/auth') &&
        !redirect.startsWith('/merchant') &&
        !resolvedRedirect?.matched.some((record) => record.meta?.publicLanding)

      router.push(shouldUseRedirect ? redirect : adminDefaultEntry)
    } catch (error) {
      if (!(error instanceof HttpError)) {
        console.error('[Login] Unexpected error:', error)
      }
    } finally {
      loading.value = false
      if (isAuditBypassEnabled.value) {
        isPassing.value = true
      } else {
        resetDragVerify()
      }
    }
  }

  const resetDragVerify = () => {
    dragVerify.value.reset()
  }

  const showLoginSuccessNotice = () => {
    setTimeout(() => {
      ElNotification({
        title: t('login.success.title'),
        type: 'success',
        duration: 2500,
        zIndex: 10000,
        message: `${t('login.success.message')}, ${systemName}!`
      })
    }, 1000)
  }
</script>

<style scoped>
  @import './style.css';
</style>

<style lang="scss" scoped>
  :deep(.custom-height) {
    height: var(--el-component-custom-height) !important;
  }

  :deep(.el-select__wrapper) {
    height: var(--el-component-custom-height) !important;
  }

  .login-admin-hint {
    margin-top: -6px;
    color: var(--art-gray-500);
    font-size: 12px;
    line-height: 1.7;
  }
</style>
