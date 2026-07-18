<template>
  <div class="merchant-page">
    <section class="merchant-page-header">
      <div class="merchant-page-header__title">
        <h1>绑定中心</h1>
        <p>管理快捷登录和通知联系方式。</p>
      </div>

      <div v-if="payload" class="merchant-chip-row">
        <span class="merchant-chip">快捷登录 {{ payload.summary?.quick_login_count ?? 0 }} 项</span>
        <span class="merchant-chip"
          >已绑定 {{ payload.summary?.bound_quick_login_count ?? 0 }} 项</span
        >
        <span class="merchant-chip"
          >已配渠道 {{ payload.summary?.configured_contact_count ?? 0 }} 项</span
        >
      </div>
    </section>

    <div v-if="loading" class="merchant-panel merchant-state-card">
      <ElSkeleton :rows="8" animated />
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
              <h2>快捷登录绑定</h2>
              <p>查看 QQ、微信快捷登录绑定状态，可直接解除已绑定关系。</p>
            </div>
          </div>

          <div class="merchant-connection-list">
            <div
              v-for="item in payload.quick_logins || []"
              :key="item.id"
              class="merchant-connection-item"
            >
              <div class="merchant-connection-item__main">
                <div class="merchant-connection-item__icon">
                  <Icon :icon="connectionIcon(item.id, item.label)" />
                </div>

                <div class="merchant-connection-item__copy">
                  <strong>{{ translateMerchantText(item.label) }}</strong>
                  <p>{{ translateMerchantText(item.write_message) }}</p>
                  <small>
                    标识：{{ translateMerchantText(item.identifier_masked || '未配置') }}
                  </small>
                </div>
              </div>

              <div class="merchant-connection-item__side">
                <div class="merchant-chip-row merchant-chip-row--compact">
                  <ElTag :type="item.status_type" effect="plain">
                    {{ translateMerchantText(item.status_label) }}
                  </ElTag>
                  <ElTag :type="item.bound_type" effect="plain">
                    {{ translateMerchantText(item.bound_label) }}
                  </ElTag>
                </div>

                <ElButton
                  v-if="item.unbind_allowed"
                  size="small"
                  plain
                  type="danger"
                  :loading="quickUnbindLoading === String(item.id)"
                  @click="handleUnbind(String(item.id), translateMerchantText(item.label))"
                >
                  解除绑定
                </ElButton>
              </div>
            </div>
          </div>
        </article>

        <article class="merchant-card">
          <div class="merchant-card__head">
            <div>
              <h2>联系渠道总览</h2>
              <p>汇总邮箱、手机、微信推送和电报通知状态。</p>
            </div>
          </div>

          <div class="merchant-connection-list">
            <div
              v-for="item in payload.contact_bindings || []"
              :key="item.id"
              class="merchant-connection-item"
            >
              <div class="merchant-connection-item__main">
                <div
                  class="merchant-connection-item__icon merchant-connection-item__icon--secondary"
                >
                  <Icon :icon="connectionIcon(item.id, item.label)" />
                </div>

                <div class="merchant-connection-item__copy">
                  <strong>{{ translateMerchantText(item.label) }}</strong>
                  <p>
                    {{
                      item.id === 'email'
                        ? formatMerchantContactValue(item.value_display, {
                            emptyLabel: '未配置',
                            fallbackLabel: '邮箱已脱敏',
                            maskMode: 'email'
                          })
                        : item.id === 'mobile'
                          ? formatMerchantContactValue(item.value_display, {
                              emptyLabel: '未配置',
                              fallbackLabel: '手机已脱敏',
                              maskMode: 'mobile'
                            })
                          : translateMerchantText(item.value_display || '未配置')
                    }}
                  </p>
                  <small>{{ translateMerchantText(item.write_message) }}</small>
                </div>
              </div>

              <div class="merchant-chip-row merchant-chip-row--compact">
                <ElTag :type="item.status_type" effect="plain">
                  {{ translateMerchantText(item.status_label) }}
                </ElTag>
                <ElTag :type="item.configured_type" effect="plain">
                  {{ translateMerchantText(item.configured_label) }}
                </ElTag>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="merchant-grid-2">
        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>邮箱验证绑定</h2>
              <p>发送验证码后完成绑定或解绑。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip">当前模式：{{ emailModeLabel }}</span>
            <span class="merchant-chip">当前状态：{{ emailBindingStatus }}</span>
          </div>

          <div class="merchant-note merchant-connection-note">
            {{ translateMerchantText(emailBinding.write_message) }}
          </div>

          <ElInput
            v-model.trim="emailInput"
            :disabled="emailInputDisabled"
            placeholder="请输入邮箱地址"
            autocomplete="email"
          />

          <div class="merchant-inline-form">
            <ElInput
              v-model.trim="emailCaptcha"
              maxlength="6"
              placeholder="请输入 6 位验证码"
              autocomplete="one-time-code"
            />
            <ElButton
              plain
              :disabled="!emailBinding.available"
              :loading="emailCodeLoading"
              @click="sendEmailCode"
            >
              发送验证码
            </ElButton>
          </div>

          <div v-if="emailDebugCode" class="merchant-debug-code">
            调试验证码：{{ emailDebugCode }}
          </div>

          <div class="merchant-form-actions merchant-form-actions--split">
            <span class="merchant-fine-print">
              {{ emailInputDisabled ? '验证码会发送到已绑定邮箱。' : '收到验证码后提交即可。' }}
            </span>
            <ElButton
              type="primary"
              :disabled="!emailBinding.available"
              :loading="emailSubmitLoading"
              @click="submitEmailBinding"
            >
              {{ emailMode === 'bind' ? '确认绑定邮箱' : '确认解绑邮箱' }}
            </ElButton>
          </div>
        </article>

        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>手机验证绑定</h2>
              <p>用于补齐短信通知和安全联系。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip">当前模式：{{ mobileModeLabel }}</span>
            <span class="merchant-chip">当前状态：{{ mobileBindingStatus }}</span>
          </div>

          <div class="merchant-note merchant-connection-note">
            {{ translateMerchantText(mobileBinding.write_message) }}
          </div>

          <ElInput
            v-model.trim="mobileInput"
            :disabled="mobileInputDisabled"
            maxlength="11"
            placeholder="请输入手机号"
            autocomplete="tel"
          />

          <div class="merchant-inline-form">
            <ElInput
              v-model.trim="mobileCaptcha"
              maxlength="6"
              placeholder="请输入 6 位验证码"
              autocomplete="one-time-code"
            />
            <ElButton
              plain
              :disabled="!mobileBinding.available"
              :loading="mobileCodeLoading"
              @click="sendMobileCode"
            >
              发送验证码
            </ElButton>
          </div>

          <div v-if="mobileDebugCode" class="merchant-debug-code">
            调试验证码：{{ mobileDebugCode }}
          </div>

          <div class="merchant-form-actions merchant-form-actions--split">
            <span class="merchant-fine-print">
              {{ mobileInputDisabled ? '验证码会发送到已绑定手机号。' : '收到验证码后提交即可。' }}
            </span>
            <ElButton
              type="primary"
              :disabled="!mobileBinding.available"
              :loading="mobileSubmitLoading"
              @click="submitMobileBinding"
            >
              {{ mobileMode === 'bind' ? '确认绑定手机' : '确认解绑手机' }}
            </ElButton>
          </div>
        </article>
      </section>

      <section class="merchant-grid-2">
        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>微信推送标识</h2>
              <p>支持扫码绑定，也支持手动填写推送标识。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip"
              >当前状态：{{ wxpusherConfigured ? '已配置' : '未配置' }}</span
            >
            <span class="merchant-chip">扫码绑定：{{ wxpusherQrEnabled ? '可用' : '未开启' }}</span>
            <span class="merchant-chip">手动录入：可用</span>
          </div>

          <div class="merchant-note merchant-connection-note">
            {{ wxpusherEnrollmentHint }}
          </div>

          <div class="merchant-wxpusher-panel">
            <div class="merchant-wxpusher-panel__visual">
              <div v-if="wxpusherQrLoading" class="merchant-wxpusher-panel__loading">
                <ElSkeleton :rows="5" animated />
              </div>

              <div v-else-if="wxpusherQrImage" class="merchant-wxpusher-panel__qr">
                <img :src="wxpusherQrImage" alt="微信推送绑定二维码" />
                <div class="merchant-fine-print">有效期：{{ wxpusherQrExpireLabel }}</div>
              </div>

              <div v-else class="merchant-wxpusher-panel__placeholder">
                <Icon icon="ri:qr-code-line" />
                <p>
                  {{
                    wxpusherQrEnabled ? '点击下方按钮生成二维码。' : '当前未开启扫码绑定能力。'
                  }}
                </p>
              </div>

              <div class="merchant-action-row merchant-action-row--left">
                <ElButton
                  plain
                  :disabled="!wxpusherQrEnabled"
                  :loading="wxpusherQrLoading"
                  @click="generateWxPusherQr()"
                >
                  {{ wxpusherQrImage ? '刷新二维码' : '获取二维码' }}
                </ElButton>
                <ElButton plain :loading="wxpusherStatusLoading" @click="checkWxPusherStatus()">
                  检查状态
                </ElButton>
              </div>

              <div
                v-if="wxpusherStatusMessage"
                class="merchant-fine-print merchant-wxpusher-panel__status"
              >
                {{ wxpusherStatusMessage }}
              </div>
            </div>

            <div class="merchant-wxpusher-panel__form">
              <div class="merchant-chip-row merchant-chip-row--compact">
                <span class="merchant-chip">当前值：{{ wxpusherCurrentValueLabel }}</span>
              </div>

              <ElInput
                v-model.trim="wxpusherUid"
                placeholder="请输入微信推送标识"
                autocomplete="off"
              />

              <div class="merchant-fine-print merchant-wxpusher-panel__tip">
                扫码未回写时，可手动填写后保存。
              </div>

              <div class="merchant-form-actions merchant-form-actions--split">
                <span class="merchant-fine-print">
                  {{ wxpusherConfigured ? '填写新标识后会覆盖保存。' : '也可直接粘贴标识保存。' }}
                </span>
                <div class="merchant-action-row">
                  <ElButton
                    v-if="wxpusherConfigured"
                    plain
                    type="danger"
                    :loading="wxUnbindLoading"
                    @click="handleUnbind('wxpusher_uid', '微信推送')"
                  >
                    清空
                  </ElButton>
                  <ElButton type="primary" :loading="wxLoading" @click="saveWxPusher">
                    保存微信推送标识
                  </ElButton>
                </div>
              </div>
            </div>
          </div>
        </article>

        <article class="merchant-form-card">
          <div class="merchant-form-card__head">
            <div>
              <h2>电报会话标识</h2>
              <p>用于接收订单、余额和安全提醒。</p>
            </div>
          </div>

          <div class="merchant-chip-row">
            <span class="merchant-chip"
              >当前状态：{{ telegramConfigured ? '已配置' : '未配置' }}</span
            >
            <span class="merchant-chip">维护方式：手动填写</span>
          </div>

          <ElInput
            v-model.trim="telegramChatId"
            placeholder="请输入电报会话标识"
            autocomplete="off"
          />

          <div class="merchant-form-actions merchant-form-actions--split">
            <span class="merchant-fine-print"> 当前值：{{ telegramCurrentValueLabel }} </span>
            <div class="merchant-action-row">
              <ElButton
                v-if="telegramConfigured"
                plain
                type="danger"
                :loading="telegramUnbindLoading"
                @click="handleUnbind('tg_chat_id', '电报通知')"
              >
                清空
              </ElButton>
              <ElButton type="primary" :loading="telegramLoading" @click="saveTelegram">
                保存电报会话标识
              </ElButton>
            </div>
          </div>
        </article>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { Icon } from '@iconify/vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    MerchantApiError,
    fetchMerchantConnections,
    fetchMerchantWxPusherQrCode,
    fetchMerchantWxPusherUidStatus,
    requestMerchantConnectionCode,
    saveMerchantTelegramChatId,
    saveMerchantWxPusherUid,
    submitMerchantEmailBinding,
    submitMerchantMobileBinding,
    unbindMerchantConnection
  } from '@/api/merchant'
  import { formatMerchantContactValue, translateMerchantText } from '../shared/text'

  defineOptions({ name: 'MerchantConnections' })

  const loading = ref(true)
  const wxLoading = ref(false)
  const telegramLoading = ref(false)
  const wxUnbindLoading = ref(false)
  const wxpusherQrLoading = ref(false)
  const wxpusherStatusLoading = ref(false)
  const telegramUnbindLoading = ref(false)
  const emailCodeLoading = ref(false)
  const emailSubmitLoading = ref(false)
  const mobileCodeLoading = ref(false)
  const mobileSubmitLoading = ref(false)
  const quickUnbindLoading = ref('')
  const payload = ref<Record<string, any> | null>(null)
  const emailInput = ref('')
  const mobileInput = ref('')
  const emailCaptcha = ref('')
  const mobileCaptcha = ref('')
  const emailDebugCode = ref('')
  const mobileDebugCode = ref('')
  const wxpusherUid = ref('')
  const wxpusherQrPayload = ref<Record<string, any> | null>(null)
  const wxpusherStatusMessage = ref('')
  const telegramChatId = ref('')
  let wxpusherStatusTimer: ReturnType<typeof setInterval> | null = null

  const emailBinding = computed(() => findBinding('email'))
  const mobileBinding = computed(() => findBinding('mobile'))
  const wxpusherBinding = computed(() => findBinding('wxpusher_uid'))
  const telegramBinding = computed(() => findBinding('tg_chat_id'))
  const wxpusherEnrollment = computed<Record<string, any>>(
    () => (payload.value?.wxpusher_enrollment as Record<string, any>) || {}
  )
  const emailMode = computed<'bind' | 'unbind'>(() =>
    emailBinding.value?.value_present ? 'unbind' : 'bind'
  )
  const mobileMode = computed<'bind' | 'unbind'>(() =>
    mobileBinding.value?.value_present ? 'unbind' : 'bind'
  )
  const emailModeLabel = computed(() => (emailMode.value === 'bind' ? '绑定邮箱' : '解绑邮箱'))
  const mobileModeLabel = computed(() => (mobileMode.value === 'bind' ? '绑定手机' : '解绑手机'))
  const emailBindingStatus = computed(() =>
    emailBinding.value?.value_present
      ? `已绑定 ${formatMerchantContactValue(emailBinding.value?.value_display, {
          emptyLabel: '未配置',
          fallbackLabel: '邮箱已脱敏',
          maskMode: 'email'
        })}`
      : '未绑定'
  )
  const mobileBindingStatus = computed(() =>
    mobileBinding.value?.value_present
      ? `已绑定 ${formatMerchantContactValue(mobileBinding.value?.value_display, {
          emptyLabel: '未配置',
          fallbackLabel: '手机已脱敏',
          maskMode: 'mobile'
        })}`
      : '未绑定'
  )
  const emailInputDisabled = computed(() => emailMode.value === 'unbind')
  const mobileInputDisabled = computed(() => mobileMode.value === 'unbind')
  const wxpusherConfigured = computed(() => Boolean(wxpusherBinding.value?.value_present))
  const telegramConfigured = computed(() => Boolean(telegramBinding.value?.value_present))
  const wxpusherQrEnabled = computed(
    () =>
      Boolean(payload.value?.write_actions?.wxpusher_qrcode) &&
      Boolean(wxpusherEnrollment.value?.write_allowed)
  )
  const wxpusherCurrentValueLabel = computed(() =>
    translateMerchantText(String(wxpusherBinding.value?.value_display || '未配置'))
  )
  const telegramCurrentValueLabel = computed(() =>
    translateMerchantText(String(telegramBinding.value?.value_display || '未配置'))
  )
  const wxpusherEnrollmentHint = computed(() =>
    translateMerchantText(
      String(wxpusherEnrollment.value?.write_message || '扫码后会自动写入推送标识，也可手动填写。')
    )
  )
  const wxpusherQrImage = computed(() =>
    String(
      wxpusherQrPayload.value?.qrcode_url ||
        wxpusherQrPayload.value?.short_url ||
        wxpusherQrPayload.value?.shortUrl ||
        ''
    ).trim()
  )
  const wxpusherQrExpireLabel = computed(() => {
    const value = String(
      wxpusherQrPayload.value?.expires_at || wxpusherQrPayload.value?.expires || ''
    ).trim()

    if (value) {
      return value
    }

    return `生成后约 ${Number(wxpusherEnrollment.value?.expires_seconds || 1800)} 秒内有效`
  })

  const summaryCards = computed(() => [
    {
      label: '快捷登录配置',
      value: String(payload.value?.summary?.quick_login_count ?? 0),
      hint: '可用快捷登录数',
      icon: 'ri:flashlight-line'
    },
    {
      label: '已绑定快捷登录',
      value: String(payload.value?.summary?.bound_quick_login_count ?? 0),
      hint: '已完成第三方绑定',
      icon: 'ri:link-m'
    },
    {
      label: '联系渠道总数',
      value: String(payload.value?.summary?.contact_binding_count ?? 0),
      hint: '联系方式总数',
      icon: 'ri:contacts-book-2-line'
    },
    {
      label: '已完成配置',
      value: String(payload.value?.summary?.configured_contact_count ?? 0),
      hint: '已保存到商户资料',
      icon: 'ri:checkbox-circle-line'
    }
  ])

  function resolveMerchantError(error: unknown, fallback: string) {
    return error instanceof MerchantApiError
      ? translateMerchantText(error.message, error.message)
      : fallback
  }

  function findBinding(id: string) {
    return (
      (payload.value?.contact_bindings || []).find((item: Record<string, any>) => item.id === id) ||
      {}
    )
  }

  function applyPayload(data: Record<string, any>) {
    payload.value = data

    const nextEmail = (data.contact_bindings || []).find(
      (item: Record<string, any>) => item.id === 'email'
    )
    const nextMobile = (data.contact_bindings || []).find(
      (item: Record<string, any>) => item.id === 'mobile'
    )
    const nextWx = (data.contact_bindings || []).find(
      (item: Record<string, any>) => item.id === 'wxpusher_uid'
    )
    const nextTg = (data.contact_bindings || []).find(
      (item: Record<string, any>) => item.id === 'tg_chat_id'
    )

    emailInput.value = nextEmail?.value || ''
    mobileInput.value = nextMobile?.value || ''
    wxpusherUid.value = nextWx?.value || ''
    telegramChatId.value = nextTg?.value || ''
    emailCaptcha.value = ''
    mobileCaptcha.value = ''
    emailDebugCode.value = ''
    mobileDebugCode.value = ''

    if (!data?.write_actions?.wxpusher_qrcode) {
      wxpusherQrPayload.value = null
    }

    if (nextWx?.value_present) {
      stopWxPusherStatusPolling()
      wxpusherStatusMessage.value = '已绑定微信推送标识。'
    }
  }

  function connectionIcon(id: unknown, label: unknown) {
    const key = String(id || label || '').toLowerCase()
    if (key.includes('qq')) {
      return 'ri:qq-line'
    }
    if (key.includes('wechat') || key.includes('wx')) {
      return 'ri:wechat-line'
    }
    if (key.includes('telegram') || key.includes('tg')) {
      return 'ri:telegram-2-line'
    }
    if (key.includes('mail') || key.includes('email')) {
      return 'ri:mail-line'
    }
    if (key.includes('mobile') || key.includes('phone')) {
      return 'ri:smartphone-line'
    }

    return 'ri:links-line'
  }

  async function loadConnections() {
    loading.value = true
    try {
      const data = await fetchMerchantConnections()
      applyPayload(data)
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '绑定信息加载失败'))
    } finally {
      loading.value = false
    }
  }

  function stopWxPusherStatusPolling() {
    if (wxpusherStatusTimer !== null) {
      clearInterval(wxpusherStatusTimer)
      wxpusherStatusTimer = null
    }
  }

  function startWxPusherStatusPolling() {
    stopWxPusherStatusPolling()
    if (!wxpusherQrEnabled.value || wxpusherConfigured.value) {
      return
    }

    wxpusherStatusTimer = setInterval(() => {
      void checkWxPusherStatus(true)
    }, 5000)
  }

  async function generateWxPusherQr(silent = false) {
    if (!wxpusherQrEnabled.value) {
      if (!silent) {
        ElMessage.warning('当前未开启微信推送扫码绑定能力')
      }
      return
    }

    wxpusherQrLoading.value = true
    try {
      const data = await fetchMerchantWxPusherQrCode()
      wxpusherQrPayload.value = data
      wxpusherStatusMessage.value = '二维码已生成，扫码后会自动写入标识。'
      startWxPusherStatusPolling()
      if (!silent) {
        ElMessage.success('二维码已生成')
      }
    } catch (error) {
      const message = resolveMerchantError(error, '微信推送二维码生成失败')
      wxpusherStatusMessage.value = message
      if (!silent) {
        ElMessage.error(message)
      }
    } finally {
      wxpusherQrLoading.value = false
    }
  }

  async function checkWxPusherStatus(silent = false) {
    if (!silent) {
      wxpusherStatusLoading.value = true
    }

    try {
      const draftUid = wxpusherUid.value.trim()
      const result = await fetchMerchantWxPusherUidStatus(draftUid ? 'edit' : 'bind', draftUid)

      if (result.code === 1) {
        wxpusherStatusMessage.value = !wxpusherConfigured.value
          ? '已检测到扫码绑定，正在刷新状态。'
          : draftUid
            ? '检测到新的推送标识，可直接保存覆盖。'
            : '当前已存在微信推送绑定。'
        stopWxPusherStatusPolling()
        await loadConnections()
        if (!silent) {
          ElMessage.success('已获取当前绑定状态')
        }
        return
      }

      if (!silent) {
        wxpusherStatusMessage.value = '暂未检测到新的扫码结果，请稍后再试。'
        ElMessage.info('暂未检测到新的扫码结果')
      }
    } catch (error) {
      const message = resolveMerchantError(error, '微信推送状态检查失败')
      wxpusherStatusMessage.value = message
      if (!silent) {
        ElMessage.error(message)
      }
    } finally {
      wxpusherStatusLoading.value = false
    }
  }

  async function sendEmailCode() {
    const target =
      emailMode.value === 'bind'
        ? emailInput.value.trim()
        : String(emailBinding.value?.value || emailInput.value || '').trim()
    if (!target) {
      ElMessage.warning('请输入邮箱地址')
      return
    }

    emailCodeLoading.value = true
    try {
      const data = await requestMerchantConnectionCode('email', emailMode.value, target)
      emailDebugCode.value = String(data.debug_code || '')
      if (emailDebugCode.value) {
        emailCaptcha.value = emailDebugCode.value
      }
      ElMessage.success(
        emailDebugCode.value ? `验证码已发送，调试码 ${emailDebugCode.value}` : '邮箱验证码已发送'
      )
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '邮箱验证码发送失败'))
    } finally {
      emailCodeLoading.value = false
    }
  }

  async function submitEmailBinding() {
    const target =
      emailMode.value === 'bind'
        ? emailInput.value.trim()
        : String(emailBinding.value?.value || emailInput.value || '').trim()
    if (!target) {
      ElMessage.warning('请输入邮箱地址')
      return
    }
    if (!emailCaptcha.value.trim()) {
      ElMessage.warning('请输入验证码')
      return
    }

    emailSubmitLoading.value = true
    try {
      await submitMerchantEmailBinding(
        emailMode.value === 'bind' ? 1 : 2,
        target,
        emailCaptcha.value.trim()
      )
      ElMessage.success(emailMode.value === 'bind' ? '邮箱绑定成功' : '邮箱解绑成功')
      await loadConnections()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '邮箱操作失败'))
    } finally {
      emailSubmitLoading.value = false
    }
  }

  async function sendMobileCode() {
    const target =
      mobileMode.value === 'bind'
        ? mobileInput.value.trim()
        : String(mobileBinding.value?.value || mobileInput.value || '').trim()
    if (!target) {
      ElMessage.warning('请输入手机号')
      return
    }

    mobileCodeLoading.value = true
    try {
      const data = await requestMerchantConnectionCode('mobile', mobileMode.value, target)
      mobileDebugCode.value = String(data.debug_code || '')
      if (mobileDebugCode.value) {
        mobileCaptcha.value = mobileDebugCode.value
      }
      ElMessage.success(
        mobileDebugCode.value ? `验证码已发送，调试码 ${mobileDebugCode.value}` : '手机验证码已发送'
      )
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '手机验证码发送失败'))
    } finally {
      mobileCodeLoading.value = false
    }
  }

  async function submitMobileBinding() {
    const target =
      mobileMode.value === 'bind'
        ? mobileInput.value.trim()
        : String(mobileBinding.value?.value || mobileInput.value || '').trim()
    if (!target) {
      ElMessage.warning('请输入手机号')
      return
    }
    if (!mobileCaptcha.value.trim()) {
      ElMessage.warning('请输入验证码')
      return
    }

    mobileSubmitLoading.value = true
    try {
      await submitMerchantMobileBinding(
        mobileMode.value === 'bind' ? 1 : 2,
        target,
        mobileCaptcha.value.trim()
      )
      ElMessage.success(mobileMode.value === 'bind' ? '手机绑定成功' : '手机解绑成功')
      await loadConnections()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '手机操作失败'))
    } finally {
      mobileSubmitLoading.value = false
    }
  }

  async function handleUnbind(type: string, label: string) {
    try {
      await ElMessageBox.confirm(`确认解除当前 ${label} 吗？`, '解除绑定', {
        type: 'warning',
        confirmButtonText: '确认',
        cancelButtonText: '取消'
      })
    } catch {
      return
    }

    if (type === 'wxpusher_uid') {
      wxUnbindLoading.value = true
    } else if (type === 'tg_chat_id') {
      telegramUnbindLoading.value = true
    } else {
      quickUnbindLoading.value = type
    }

    try {
      await unbindMerchantConnection(type)
      ElMessage.success(`${label} 已解除绑定`)
      await loadConnections()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, `${label} 解绑失败`))
    } finally {
      if (type === 'wxpusher_uid') {
        wxUnbindLoading.value = false
      } else if (type === 'tg_chat_id') {
        telegramUnbindLoading.value = false
      } else {
        quickUnbindLoading.value = ''
      }
    }
  }

  async function saveWxPusher() {
    wxLoading.value = true
    try {
      await saveMerchantWxPusherUid(wxpusherUid.value)
      ElMessage.success('微信推送标识已保存')
      stopWxPusherStatusPolling()
      await loadConnections()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '微信推送标识保存失败'))
    } finally {
      wxLoading.value = false
    }
  }

  async function saveTelegram() {
    telegramLoading.value = true
    try {
      await saveMerchantTelegramChatId(telegramChatId.value)
      ElMessage.success('电报会话标识已保存')
      await loadConnections()
    } catch (error) {
      ElMessage.error(resolveMerchantError(error, '电报会话标识保存失败'))
    } finally {
      telegramLoading.value = false
    }
  }

  onMounted(async () => {
    await loadConnections()
    if (!wxpusherConfigured.value && wxpusherQrEnabled.value) {
      await generateWxPusherQr(true)
    }
  })

  onBeforeUnmount(() => {
    stopWxPusherStatusPolling()
  })
</script>

<style lang="scss">
  @use '../styles';
</style>

<style lang="scss" scoped>
  .merchant-grid-2--top {
    align-items: flex-start;
  }

  .merchant-connection-note {
    margin: 16px 0 18px;
  }

  .merchant-connection-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .merchant-connection-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    justify-content: space-between;
    padding: 16px 18px;
    background: rgb(148 163 184 / 8%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 18px;
  }

  .merchant-connection-item__main {
    display: flex;
    gap: 14px;
    min-width: 0;
  }

  .merchant-connection-item__side {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
  }

  .merchant-connection-item__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    color: var(--main-color);
    background: var(--merchant-active-bg);
    border-radius: 14px;
    font-size: 22px;
    flex-shrink: 0;
  }

  .merchant-connection-item__icon--secondary {
    color: #0f766e;
    background: rgb(13 148 136 / 10%);
  }

  .merchant-connection-item__copy {
    min-width: 0;
  }

  .merchant-connection-item__copy strong {
    display: block;
    margin-bottom: 6px;
    color: var(--merchant-heading-color);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
  }

  .merchant-connection-item__copy p,
  .merchant-connection-item__copy small {
    margin: 0;
    color: var(--merchant-muted);
    line-height: 1.75;
  }

  .merchant-connection-item__copy small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
  }

  .merchant-inline-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    margin-top: 14px;
  }

  .merchant-debug-code {
    margin-top: 12px;
    padding: 12px 14px;
    color: #92400e;
    background: rgb(245 158 11 / 12%);
    border: 1px solid rgb(245 158 11 / 16%);
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.6;
  }

  .merchant-action-row {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
  }

  .merchant-form-actions--split {
    justify-content: space-between;
  }

  .merchant-action-row--left {
    justify-content: flex-start;
  }

  .merchant-wxpusher-panel {
    display: grid;
    grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
    gap: 18px;
    align-items: stretch;
  }

  .merchant-wxpusher-panel__visual,
  .merchant-wxpusher-panel__form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 18px;
    background: rgb(148 163 184 / 8%);
    border: 1px solid rgb(148 163 184 / 12%);
    border-radius: 18px;
  }

  .merchant-wxpusher-panel__loading,
  .merchant-wxpusher-panel__qr,
  .merchant-wxpusher-panel__placeholder {
    min-height: 280px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background:
      radial-gradient(circle at top, rgb(255 255 255 / 95%), rgb(241 245 249 / 92%)),
      linear-gradient(135deg, rgb(226 232 240 / 50%), rgb(255 255 255 / 90%));
    border: 1px solid rgb(148 163 184 / 14%);
  }

  .merchant-wxpusher-panel__qr {
    padding: 16px;
    gap: 12px;
  }

  .merchant-wxpusher-panel__qr img {
    width: 100%;
    max-width: 220px;
    aspect-ratio: 1;
    object-fit: contain;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 18px 30px rgb(15 23 42 / 8%);
  }

  .merchant-wxpusher-panel__placeholder {
    padding: 18px;
    color: var(--merchant-muted);
    text-align: center;
    gap: 10px;
  }

  .merchant-wxpusher-panel__placeholder .iconify {
    font-size: 52px;
    color: #0f766e;
  }

  .merchant-wxpusher-panel__placeholder p,
  .merchant-wxpusher-panel__status,
  .merchant-wxpusher-panel__tip {
    margin: 0;
    line-height: 1.7;
  }

  @media (width <= 900px) {
    .merchant-wxpusher-panel {
      grid-template-columns: 1fr;
    }

    .merchant-form-actions--split {
      justify-content: flex-end;
    }
  }

  @media (width <= 768px) {
    .merchant-inline-form {
      grid-template-columns: 1fr;
    }

    .merchant-connection-item,
    .merchant-connection-item__main,
    .merchant-connection-item__side {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
