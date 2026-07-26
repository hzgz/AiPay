<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>安全中心</h1>
        <p>查看账号安全状态并修改登录密码。</p>
      </div>

      <div v-if="payload" class="merchant-chip-row">
        <span class="merchant-chip"
          >安全中心 {{ merchantEnabledLabel(payload.security_center?.enabled) }}</span
        >
        <span class="merchant-chip"
          >谷歌验证 {{ translateMerchantText(payload.google_auth?.status_label) }}</span
        >
        <span class="merchant-chip">近期登录 {{ (payload.recent_logs || []).length }} 条</span>
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="10" animated />
    </div>

    <template v-else-if="payload">
      <section class="merchant-stat-grid">
        <article
          v-for="card in summaryCards"
          :key="card.label"
          class="merchant-card merchant-stat-card"
        >
          <div class="merchant-stat-card__row">
            <div class="merchant-stat-card__copy">
              <div class="merchant-stat-card__label">{{ card.label }}</div>
              <div class="merchant-stat-card__value">{{ card.value }}</div>
              <div class="merchant-stat-card__hint">{{ card.hint }}</div>
            </div>

            <div class="merchant-stat-card__symbol">
              <Icon :icon="card.icon" />
            </div>
          </div>
        </article>
      </section>

      <section class="merchant-grid-2 merchant-grid-2--top">
        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>安全策略概览</h2>
              <p>查看安全开关、登录校验和实名状态。</p>
            </div>
          </div>

          <div class="merchant-kv-grid">
            <div class="merchant-kv-item">
              <span>安全中心</span>
              <div>{{ merchantEnabledLabel(payload.security_center?.enabled) }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>强制绑定</span>
              <div>{{
                merchantBooleanLabel(payload.security_center?.force_bind, ['开启', '关闭'])
              }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>登录校验</span>
              <div>{{
                payload.security_center?.login_verification_required ? '已开启' : '已关闭'
              }}</div>
            </div>
            <div class="merchant-kv-item">
              <span>验证方式</span>
              <div>{{ translateMerchantText(payload.security_center?.provider_name) }}</div>
            </div>
          </div>

          <div class="merchant-security-stack">
            <section class="merchant-soft-panel merchant-security-panel">
              <div class="merchant-security-panel__head">
                <div class="merchant-security-panel__icon">
                  <Icon icon="ri:shield-keyhole-line" />
                </div>

                <div class="merchant-security-panel__copy">
                  <strong>谷歌验证器</strong>
                  <span>{{ translateMerchantText(payload.google_auth?.status_label) }}</span>
                </div>
              </div>

              <div class="merchant-security-panel__meta">
                <div class="merchant-kv-item">
                  <span>密钥摘要</span>
                  <div>{{ payload.google_auth?.secret_masked || '未绑定' }}</div>
                </div>
                <div class="merchant-kv-item">
                  <span>登录二次校验</span>
                  <div>{{
                    payload.google_auth?.verification_required_at_login
                      ? '系统要求启用'
                      : '当前未要求'
                  }}</div>
                </div>
              </div>

              <p class="merchant-fine-print">
                {{ translateMerchantText(payload.google_auth?.write_message) }}
              </p>
            </section>

            <section class="merchant-soft-panel merchant-security-panel">
              <div class="merchant-security-panel__head">
                <div class="merchant-security-panel__icon merchant-security-panel__icon--teal">
                  <Icon icon="ri:verified-badge-line" />
                </div>

                <div class="merchant-security-panel__copy">
                  <strong>实名认证</strong>
                  <span>{{ translateMerchantText(payload.real_name?.status_label) }}</span>
                </div>
              </div>

              <div class="merchant-security-panel__meta">
                <div class="merchant-kv-item">
                  <span>功能状态</span>
                  <div>{{ merchantEnabledLabel(payload.real_name?.feature_enabled) }}</div>
                </div>
                <div class="merchant-kv-item">
                  <span>证件信息</span>
                  <div>{{ payload.real_name?.id_card_masked || '未实名' }}</div>
                </div>
              </div>
            </section>
          </div>
        </article>

        <div class="merchant-security-side">
          <article class="merchant-form-card">
            <div class="merchant-form-card__head">
              <div>
                <h2>谷歌验证管理</h2>
                <p>{{ googleManagementDescription }}</p>
              </div>
            </div>

            <div class="merchant-chip-row">
              <span class="merchant-chip"
                >当前状态 {{ translateMerchantText(payload.google_auth?.status_label) }}</span
              >
              <span class="merchant-chip"
                >登录校验
                {{
                  payload.google_auth?.verification_required_at_login ? '系统要求启用' : '暂未要求'
                }}</span
              >
            </div>

            <div class="merchant-note merchant-security-note">
              {{ translateMerchantText(payload.google_auth?.write_message) }}
            </div>

            <div class="merchant-google-grid">
              <section class="merchant-soft-panel merchant-google-card">
                <div class="merchant-google-card__head">
                  <strong>绑定二维码</strong>
                  <ElButton
                    v-if="googleWriteEnabled && !payload.google_auth?.bound"
                    size="small"
                    plain
                    :loading="googleQrLoading"
                    @click="handleGenerateGoogleQr"
                  >
                    {{ googleSetup ? '重新生成' : '获取二维码' }}
                  </ElButton>
                </div>

                <template v-if="!googleWriteEnabled">
                  <div class="merchant-google-placeholder">
                    当前页展示谷歌验证状态，二维码开通需按平台安全策略处理。
                  </div>
                </template>

                <template v-else-if="payload.google_auth?.bound">
                  <div class="merchant-google-placeholder"> 已绑定，如需更换设备请先解绑。 </div>
                </template>

                <template v-else-if="googleSetup">
                  <div class="merchant-google-qr">
                    <img :src="googleSetup.setup_qrcode_url" alt="谷歌验证二维码" />
                  </div>

                  <div class="merchant-kv-grid merchant-kv-grid--compact">
                    <div class="merchant-kv-item">
                      <span>绑定账号</span>
                      <div>{{ googleSetup.setup_account || '--' }}</div>
                    </div>
                    <div class="merchant-kv-item">
                      <span>签发标识</span>
                      <div>{{ googleSetup.setup_issuer || '--' }}</div>
                    </div>
                  </div>

                  <div class="merchant-google-secret">
                    <span>手动输入密钥</span>
                    <code>{{ googleSetup.setup_secret || '--' }}</code>
                  </div>
                </template>

                <template v-else>
                  <div class="merchant-google-placeholder"> 先获取二维码再扫码绑定。 </div>
                </template>
              </section>

              <section class="merchant-soft-panel merchant-google-card">
                <div class="merchant-google-card__head">
                  <strong>{{ payload.google_auth?.bound ? '解绑验证' : '绑定验证' }}</strong>
                </div>

                <template v-if="!googleWriteEnabled">
                  <div class="merchant-google-placeholder">
                    当前绑定与解绑由平台安全策略统一管理。
                  </div>
                </template>

                <template v-else-if="payload.google_auth?.bound">
                  <ElInput
                    v-model.trim="unbindCode"
                    maxlength="6"
                    placeholder="输入当前谷歌验证码后解绑"
                  />

                  <div class="merchant-form-actions merchant-form-actions--end">
                    <ElButton
                      type="danger"
                      plain
                      :loading="googleUnbindLoading"
                      @click="handleUnbindGoogleAuth"
                    >
                      解绑
                    </ElButton>
                  </div>
                </template>

                <template v-else>
                  <ElInput
                    v-model.trim="bindCode"
                    maxlength="6"
                    placeholder="请输入验证器中显示的 6 位验证码"
                  />

                  <div class="merchant-form-actions merchant-form-actions--split">
                    <span class="merchant-fine-print">扫码后输入 6 位验证码完成绑定。</span>
                    <ElButton
                      type="primary"
                      :loading="googleBindLoading"
                      @click="handleBindGoogleAuth"
                    >
                      确认绑定
                    </ElButton>
                  </div>
                </template>
              </section>
            </div>
          </article>

          <article class="merchant-form-card">
            <div class="merchant-form-card__head">
              <div>
                <h2>修改登录密码</h2>
                <p>保存后需重新登录。</p>
              </div>
            </div>

            <div class="merchant-chip-row">
              <span class="merchant-chip">密码策略：不少于 6 位</span>
              <span class="merchant-chip">保存后立即生效</span>
            </div>

            <ElForm ref="formRef" :model="formData" :rules="rules" label-position="top">
              <ElFormItem label="新密码" prop="newpwd">
                <ElInput
                  v-model.trim="formData.newpwd"
                  type="password"
                  show-password
                  placeholder="请输入新的登录密码"
                  autocomplete="new-password"
                />
              </ElFormItem>

              <ElFormItem label="确认新密码" prop="renewpwd">
                <ElInput
                  v-model.trim="formData.renewpwd"
                  type="password"
                  show-password
                  placeholder="请再次输入新的登录密码"
                  autocomplete="new-password"
                />
              </ElFormItem>

              <div class="merchant-form-actions merchant-form-actions--split">
                <span class="merchant-fine-print">密码保存后将跳转回商户登录页。</span>
                <ElButton type="primary" :loading="submitting" @click="handleSubmit">
                  保存新密码
                </ElButton>
              </div>
            </ElForm>
          </article>

          <article class="merchant-form-card merchant-danger-card">
            <div class="merchant-form-card__head">
              <div>
                <h2>账号注销</h2>
                <p>注销后会清理商户配置、通道、订单和日志，且不可恢复。</p>
              </div>
            </div>

            <div class="merchant-chip-row">
              <span class="merchant-chip"
                >功能状态 {{ accountCancellationEnabled ? '已开启' : '未开启' }}</span
              >
              <span class="merchant-chip"
                >提交状态 {{ accountCancellationAllowed ? '可提交' : '未开启' }}</span
              >
            </div>

            <div class="merchant-note merchant-security-note merchant-security-note--danger">
              {{
                translateMerchantText(accountCancellation.write_message || '当前账号暂不支持注销。')
              }}
            </div>

            <div class="merchant-kv-grid merchant-kv-grid--compact">
              <div class="merchant-kv-item">
                <span>预计清理数据</span>
                <div>{{ accountCancellation.summary?.delete_row_count ?? 0 }} 条</div>
              </div>
              <div class="merchant-kv-item">
                <span>涉及模块</span>
                <div>{{ accountCancellation.summary?.non_empty_target_count ?? 0 }} 项</div>
              </div>
              <div class="merchant-kv-item">
                <span>风险项</span>
                <div>{{ accountCancellation.summary?.blocking_reference_count ?? 0 }} 项</div>
              </div>
              <div class="merchant-kv-item">
                <span>当前余额</span>
                <div>{{ accountCancellation.balance_amount || '0.00' }} 元</div>
              </div>
            </div>

            <div v-if="accountCancellationNoticeList.length" class="merchant-warning-list">
              <div
                v-for="notice in accountCancellationNoticeList"
                :key="notice"
                class="merchant-warning-list__item"
              >
                {{ translateMerchantText(notice) }}
              </div>
            </div>

            <div v-if="accountCancellation.confirmation_phrase" class="merchant-confirm-block">
              <span>输入确认口令后再注销</span>
              <code>{{ accountCancellation.confirmation_phrase }}</code>
            </div>

            <ElInput
              v-model.trim="accountCancellationConfirmation"
              :disabled="!accountCancellationEnabled"
              placeholder="请输入确认口令"
              autocomplete="off"
            />

            <div class="merchant-form-actions merchant-form-actions--split">
              <span class="merchant-fine-print">提交后会退出当前登录。</span>
              <ElButton
                type="danger"
                :disabled="!accountCancellationEnabled"
                :loading="accountCancellationSubmitting"
                @click="handleCancelAccount"
              >
                注销账户
              </ElButton>
            </div>
          </article>
        </div>
      </section>

      <article class="merchant-card">
        <div class="merchant-card__head">
          <div>
            <h2>近期登录记录</h2>
            <p>最近 5 条访问记录。</p>
          </div>

          <div class="merchant-toolbar-pills">
            <div class="merchant-toolbar-pill">
              <span>记录数</span>
              <strong>{{ (payload.recent_logs || []).length }}</strong>
            </div>
            <div class="merchant-toolbar-pill">
              <span>谷歌验证</span>
              <strong>{{ translateMerchantText(payload.google_auth?.status_label) }}</strong>
            </div>
          </div>
        </div>

        <ElTable :data="payload.recent_logs || []" empty-text="暂无登录记录">
          <ElTableColumn prop="path" label="访问路径" min-width="220" show-overflow-tooltip />
          <ElTableColumn prop="ip" label="来源地址" width="160" />
          <ElTableColumn prop="create_time" label="访问时间" width="190" />
        </ElTable>
      </article>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    MerchantApiError,
    bindMerchantGoogleAuth,
    cancelMerchantAccount,
    fetchMerchantGoogleAuthQrCode,
    fetchMerchantSecurity,
    unbindMerchantGoogleAuth,
    updateMerchantPassword
  } from '@/api/merchant'
  import { useMerchantStore } from '@/store/modules/merchant'
  import { merchantBooleanLabel, merchantEnabledLabel, translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantSecurity' })

  const router = useRouter()
  const merchantStore = useMerchantStore()
  const formRef = ref<FormInstance>()
  const loading = ref(true)
  const submitting = ref(false)
  const googleQrLoading = ref(false)
  const googleBindLoading = ref(false)
  const googleUnbindLoading = ref(false)
  const accountCancellationSubmitting = ref(false)
  const payload = ref<Record<string, any> | null>(null)
  const bindCode = ref('')
  const unbindCode = ref('')
  const accountCancellationConfirmation = ref('')

  const formData = reactive({
    newpwd: '',
    renewpwd: ''
  })

  const googleSetup = computed(() => {
    const googleAuth = payload.value?.google_auth
    if (
      !googleAuth ||
      googleAuth.bound ||
      !googleAuth.setup_pending ||
      !googleAuth.setup_qrcode_url
    ) {
      return null
    }

    return googleAuth
  })

  const googleWriteEnabled = computed(() =>
    Boolean(
      payload.value?.write_actions?.google_bind || payload.value?.write_actions?.google_unbind
    )
  )

  const googleManagementDescription = computed(() =>
    googleWriteEnabled.value ? '可生成二维码并完成绑定或解绑。' : '当前页提供谷歌验证状态查看。'
  )

  const accountCancellation = computed<Record<string, any>>(
    () => payload.value?.account_cancellation || {}
  )
  const accountCancellationEnabled = computed(() =>
    Boolean(accountCancellation.value?.feature_enabled)
  )
  const accountCancellationAllowed = computed(() =>
    accountCancellationEnabled.value && Boolean(payload.value?.write_actions?.account_cancellation)
  )
  const accountCancellationNoticeList = computed<string[]>(() => {
    const notices = [
      ...((accountCancellation.value?.warnings as string[]) || []),
      ...((accountCancellation.value?.blocking_reasons as string[]) || [])
    ]

    return notices.filter((item) => Boolean(String(item || '').trim()))
  })

  const summaryCards = computed(() => [
    {
      label: '安全中心',
      value: payload.value ? merchantEnabledLabel(payload.value.security_center?.enabled) : '--',
      hint: '当前安全总开关',
      icon: 'ri:shield-check-line'
    },
    {
      label: '登录校验',
      value: payload.value?.security_center?.login_verification_required ? '已开启' : '已关闭',
      hint: '登录是否二次校验',
      icon: 'ri:lock-password-line'
    },
    {
      label: '谷歌验证器',
      value: translateMerchantText(payload.value?.google_auth?.status_label),
      hint: '当前绑定状态',
      icon: 'ri:google-line'
    },
    {
      label: '实名认证',
      value: translateMerchantText(payload.value?.real_name?.status_label),
      hint: '当前实名状态',
      icon: 'ri:passport-line'
    }
  ])

  const rules: FormRules = {
    newpwd: [
      { required: true, message: '请输入新密码', trigger: 'blur' },
      { min: 6, message: '密码至少 6 位', trigger: 'blur' }
    ],
    renewpwd: [
      { required: true, message: '请再次输入新密码', trigger: 'blur' },
      {
        validator: (_rule, value, callback) => {
          if (value === formData.newpwd) {
            callback()
            return
          }

          callback(new Error('两次输入的密码不一致'))
        },
        trigger: 'blur'
      }
    ]
  }

  function resolveMerchantError(error: unknown, fallback: string) {
    return error instanceof MerchantApiError
      ? translateMerchantText(error.message, error.message)
      : fallback
  }

  function ensureGoogleCode(value: string) {
    if (!/^\d{6}$/.test(value)) {
      ElMessage.warning('请输入 6 位谷歌验证码')
      return false
    }

    return true
  }

  async function loadSecurity() {
    loading.value = true
    try {
      payload.value = await fetchMerchantSecurity()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '安全信息加载失败'))
    } finally {
      loading.value = false
    }
  }

  async function handleGenerateGoogleQr() {
    googleQrLoading.value = true
    try {
      const data = await fetchMerchantGoogleAuthQrCode()
      ElMessage.success(
        translateMerchantText('google auth qr code generated successfully', '谷歌验证二维码已生成')
      )
      payload.value = {
        ...(payload.value || {}),
        google_auth: {
          ...(payload.value?.google_auth || {}),
          ...(data || {})
        }
      }
      await loadSecurity()
      bindCode.value = ''
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '二维码生成失败'))
    } finally {
      googleQrLoading.value = false
    }
  }

  async function handleBindGoogleAuth() {
    if (!ensureGoogleCode(bindCode.value)) {
      return
    }

    googleBindLoading.value = true
    try {
      await bindMerchantGoogleAuth(bindCode.value)
      ElMessage.success(
        translateMerchantText('merchant google auth bound successfully', '谷歌验证器绑定成功')
      )
      bindCode.value = ''
      await loadSecurity()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '谷歌验证器绑定失败'))
    } finally {
      googleBindLoading.value = false
    }
  }

  async function handleUnbindGoogleAuth() {
    if (!ensureGoogleCode(unbindCode.value)) {
      return
    }

    googleUnbindLoading.value = true
    try {
      await unbindMerchantGoogleAuth(unbindCode.value)
      ElMessage.success(
        translateMerchantText('merchant google auth unbound successfully', '谷歌验证器解绑成功')
      )
      unbindCode.value = ''
      await loadSecurity()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '谷歌验证器解绑失败'))
    } finally {
      googleUnbindLoading.value = false
    }
  }

  async function handleSubmit() {
    if (!formRef.value) {
      return
    }

    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) {
      return
    }

    submitting.value = true
    try {
      const data = await updateMerchantPassword(formData.newpwd, formData.renewpwd)
      ElMessage.success(
        translateMerchantText(
          data.relogin_required
            ? 'merchant password updated successfully, please sign in again'
            : 'merchant password updated successfully, please sign in again'
        )
      )
      merchantStore.clearSession()
      await router.replace('/merchant/login')
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '密码修改失败'))
    } finally {
      submitting.value = false
    }
  }

  async function handleCancelAccount() {
    const confirmationPhrase = String(accountCancellation.value?.confirmation_phrase || '').trim()
    if (!accountCancellationEnabled.value) {
      ElMessage.warning('当前未开启账号注销功能')
      return
    }

    if (!confirmationPhrase || accountCancellationConfirmation.value !== confirmationPhrase) {
      ElMessage.warning('确认口令不正确，请按页面提示输入后再提交')
      return
    }

    try {
      await ElMessageBox.confirm(
        '注销后将立即清理当前商户归属数据，你的余额、下级关系、未完成交易等将按自愿放弃处理，且不可恢复，确认继续吗？',
        '确认注销',
        {
          type: 'warning',
          confirmButtonText: '确认注销',
          cancelButtonText: '取消'
        }
      )
    } catch {
      return
    }

    accountCancellationSubmitting.value = true
    try {
      await cancelMerchantAccount(accountCancellationConfirmation.value)
      ElMessage.success('商户账号已注销')
      merchantStore.clearSession()
      await router.replace('/merchant/login')
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '账号注销失败'))
      await loadSecurity()
    } finally {
      accountCancellationSubmitting.value = false
    }
  }

  onMounted(() => {
    loadSecurity()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-grid-2--top {
    align-items: flex-start;
  }

  .merchant-security-side {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .merchant-security-stack {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 18px;
  }

  .merchant-security-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-security-panel__head {
    display: flex;
    gap: 14px;
    align-items: center;
  }

  .merchant-security-panel__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 14px;
    font-size: 20px;
    flex-shrink: 0;
  }

  .merchant-security-panel__icon--teal {
    color: #0f766e;
    background: rgb(13 148 136 / 10%);
  }

  .merchant-security-panel__copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  .merchant-security-panel__copy strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
  }

  .merchant-security-panel__copy span {
    color: var(--merchant-muted);
    font-size: 13px;
    line-height: 1.7;
  }

  .merchant-security-panel__meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 18px;
  }

  .merchant-security-note {
    margin: 16px 0 18px;
  }

  .merchant-security-note--danger {
    color: #991b1b;
    background: rgb(254 242 242 / 88%);
    border-color: rgb(248 113 113 / 24%);
  }

  .merchant-google-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .merchant-google-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .merchant-google-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .merchant-google-card__head strong {
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
  }

  .merchant-google-qr {
    display: flex;
    justify-content: center;
    padding: 12px;
    border-radius: 18px;
    background: linear-gradient(180deg, rgb(255 255 255 / 92%), rgb(248 250 252 / 96%));
    border: 1px solid rgb(148 163 184 / 20%);
  }

  .merchant-google-qr img {
    width: 100%;
    max-width: 220px;
    aspect-ratio: 1;
    object-fit: contain;
    border-radius: 16px;
    background: #fff;
    padding: 10px;
  }

  .merchant-google-secret {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .merchant-google-secret span {
    color: var(--merchant-muted);
    font-size: 12px;
  }

  .merchant-google-secret code {
    padding: 10px 12px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid rgb(148 163 184 / 18%);
    color: #0f172a;
    font-size: 12px;
    word-break: break-all;
  }

  .merchant-google-placeholder {
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    border-radius: 18px;
    background: rgb(248 250 252 / 85%);
    border: 1px dashed rgb(148 163 184 / 26%);
    color: var(--merchant-muted);
    line-height: 1.8;
    text-align: center;
  }

  .merchant-kv-grid--compact {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .merchant-form-actions--split {
    justify-content: space-between;
  }

  .merchant-form-actions--end {
    justify-content: flex-end;
  }

  .merchant-danger-card {
    border-color: rgb(248 113 113 / 18%);
  }

  .merchant-confirm-block {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
  }

  .merchant-confirm-block span {
    color: var(--merchant-muted);
    font-size: 12px;
  }

  .merchant-confirm-block code {
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px dashed rgb(248 113 113 / 30%);
    background: rgb(254 242 242 / 78%);
    color: #991b1b;
    font-size: 12px;
    word-break: break-all;
  }

  .merchant-warning-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 14px 0;
  }

  .merchant-warning-list--muted .merchant-warning-list__item {
    color: #92400e;
    background: rgb(255 251 235 / 88%);
    border-color: rgb(251 191 36 / 20%);
  }

  .merchant-warning-list__item {
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid rgb(248 113 113 / 18%);
    background: rgb(254 242 242 / 88%);
    color: #991b1b;
    font-size: 13px;
    line-height: 1.7;
  }

  :global(html.dark .merchant-google-qr) {
    background: linear-gradient(180deg, rgb(15 23 42 / 90%), rgb(30 41 59 / 82%));
    border-color: rgb(71 85 105 / 34%);
  }

  :global(html.dark .merchant-google-qr img) {
    background: rgb(15 23 42 / 92%);
    box-shadow: 0 14px 30px rgb(2 6 23 / 28%);
  }

  :global(html.dark .merchant-google-secret code) {
    background: rgb(15 23 42 / 84%);
    border-color: rgb(71 85 105 / 34%);
    color: #e2e8f0;
  }

  :global(html.dark .merchant-google-placeholder) {
    background: linear-gradient(180deg, rgb(15 23 42 / 88%), rgb(30 41 59 / 80%));
    border-color: rgb(71 85 105 / 28%);
  }

  :global(html.dark .merchant-google-placeholder .iconify) {
    color: #67e8f9;
  }

  :global(html.dark .merchant-confirm-block code) {
    background: rgb(15 23 42 / 84%);
    border-color: rgb(248 113 113 / 30%);
    color: #fecaca;
  }

  :global(html.dark .merchant-warning-list--muted .merchant-warning-list__item) {
    background: rgb(69 26 3 / 42%);
    border-color: rgb(245 158 11 / 26%);
    color: #fde68a;
  }

  :global(html.dark .merchant-warning-list__item) {
    background: rgb(30 41 59 / 82%);
    border-color: rgb(71 85 105 / 34%);
    color: #fecaca;
  }

  @media (width <= 1200px) {
    .merchant-google-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (width <= 900px) {
    .merchant-form-actions--split {
      justify-content: flex-end;
    }
  }

  @media (width <= 768px) {
    .merchant-security-panel__meta,
    .merchant-kv-grid--compact {
      grid-template-columns: 1fr;
    }
  }
</style>
