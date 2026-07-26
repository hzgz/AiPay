<!-- 表格按钮 -->
<template>
  <button
    type="button"
    :class="[
      'inline-flex shrink-0 items-center justify-center text-sm c-p align-middle transition-colors',
      buttonClass
    ]"
    :style="buttonStyle"
    @click="handleClick"
  >
    <ArtSvgIcon :icon="iconContent" />
  </button>
</template>

<script setup lang="ts">
  defineOptions({ name: 'ArtButtonTable' })

  interface Props {
    /** 按钮类型 */
    type?: 'add' | 'edit' | 'delete' | 'more' | 'view'
    /** 按钮图标 */
    icon?: string
    /** 按钮样式类 */
    iconClass?: string
    /** icon 颜色 */
    iconColor?: string
    /** 按钮背景色 */
    buttonBgColor?: string
  }

  const props = withDefaults(defineProps<Props>(), {})

  const emit = defineEmits<{
    (e: 'click'): void
  }>()

  // 默认按钮配置
  const defaultButtons = {
    add: { icon: 'ri:add-fill', class: 'bg-theme/12 text-theme' },
    edit: { icon: 'ri:pencil-line', class: 'bg-secondary/12 text-secondary' },
    delete: { icon: 'ri:delete-bin-5-line', class: 'bg-error/12 text-error' },
    view: { icon: 'ri:eye-line', class: 'bg-info/12 text-info' },
    more: { icon: 'ri:more-2-fill', class: '' }
  } as const

  // 获取图标内容
  const iconContent = computed(() => {
    return props.icon || (props.type ? defaultButtons[props.type]?.icon : '') || ''
  })

  // 获取按钮样式类
  const buttonClass = computed(() => {
    return props.iconClass || (props.type ? defaultButtons[props.type]?.class : '') || ''
  })

  const buttonStyle = computed(() => ({
    width: 'var(--app-action-height)',
    height: 'var(--app-action-height)',
    minWidth: 'var(--app-action-height)',
    minHeight: 'var(--app-action-height)',
    padding: '0',
    border: 'none',
    borderRadius: 'var(--app-action-radius)',
    backgroundColor: props.buttonBgColor || '',
    color: props.iconColor || ''
  }))

  const handleClick = () => {
    emit('click')
  }
</script>
