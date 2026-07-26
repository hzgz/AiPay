<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="register-closed-page">
    <div class="register-closed-card">
      <ElResult
        icon="warning"
        title="管理员注册入口未启用"
        sub-title="管理员账号由系统安装或后台维护创建，请使用现有管理员账号登录。"
      >
        <template #extra>
          <div class="register-closed-actions">
            <ElButton type="primary" @click="goLogin">返回管理员登录</ElButton>
            <ElButton plain @click="goHome">返回首页</ElButton>
          </div>
          <p class="register-closed-tip">页面将在 {{ countdown }} 秒后自动跳转到管理员登录页。</p>
        </template>
      </ElResult>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ElButton, ElResult } from 'element-plus'

  defineOptions({ name: 'Register' })

  const router = useRouter()
  const countdown = ref(3)
  let redirectTimer: ReturnType<typeof setInterval> | null = null

  function goLogin() {
    router.replace({ name: 'Login' })
  }

  function goHome() {
    router.replace('/')
  }

  onMounted(() => {
    redirectTimer = setInterval(() => {
      if (countdown.value <= 1) {
        if (redirectTimer) {
          clearInterval(redirectTimer)
          redirectTimer = null
        }
        goLogin()
        return
      }

      countdown.value -= 1
    }, 1000)
  })

  onBeforeUnmount(() => {
    if (redirectTimer) {
      clearInterval(redirectTimer)
      redirectTimer = null
    }
  })
</script>

<style scoped>
  .register-closed-page {
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    padding: 32px 20px;
    background:
      radial-gradient(circle at top, rgb(59 130 246 / 0.14), transparent 36%),
      linear-gradient(180deg, #f8fafc 0%, #eef4ff 100%);
  }

  .register-closed-card {
    width: min(100%, 620px);
    border: 1px solid rgb(226 232 240 / 0.95);
    border-radius: 28px;
    background: rgb(255 255 255 / 0.96);
    box-shadow: 0 26px 80px rgb(15 23 42 / 0.12);
  }

  .register-closed-card :deep(.el-result) {
    padding: 52px 36px 40px;
  }

  .register-closed-card :deep(.el-result__title) {
    font-size: 28px;
    font-weight: 700;
  }

  .register-closed-card :deep(.el-result__subtitle) {
    max-width: 420px;
    margin: 0 auto;
    color: rgb(71 85 105);
    font-size: 14px;
    line-height: 1.75;
  }

  .register-closed-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .register-closed-actions :deep(.el-button) {
    min-width: 144px;
    height: 40px;
    border-radius: 12px;
  }

  .register-closed-tip {
    margin: 14px 0 0;
    color: rgb(100 116 139);
    font-size: 13px;
    text-align: center;
  }

  @media (max-width: 640px) {
    .register-closed-card :deep(.el-result) {
      padding: 40px 20px 28px;
    }

    .register-closed-card :deep(.el-result__title) {
      font-size: 24px;
    }

    .register-closed-actions {
      flex-direction: column;
    }

    .register-closed-actions :deep(.el-button) {
      width: 100%;
    }
  }
</style>
