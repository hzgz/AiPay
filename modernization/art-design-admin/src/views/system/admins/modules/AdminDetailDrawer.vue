<template>
  <ElDrawer
    v-model="drawerVisible"
    size="840px"
    destroy-on-close
    :title="activeAdmin ? `${activeAdmin.display} #${activeAdmin.id}` : '管理员详情'"
  >
    <div v-loading="detailLoading" class="admin-detail">
      <template v-if="activeAdmin">
        <section class="detail-hero">
          <div class="detail-hero-copy">
            <h3>{{ activeAdmin.display }}</h3>
            <p>{{ activeAdmin.scope_label }} / {{ activeAdmin.status_label }}</p>
            <span>{{ activeAdmin.permission_coverage_label }}</span>
          </div>
          <div class="detail-hero-actions">
            <ElButton v-if="canRestoreActiveAdmin" type="success" plain @click="emit('restore')">
              恢复
            </ElButton>
            <template v-else>
              <ElButton v-if="canEditActiveAdmin" plain @click="emit('edit')">编辑</ElButton>
              <ElButton v-if="canAssignRolesActiveAdmin" plain @click="emit('role')">角色</ElButton>
              <ElButton
                v-if="canAssignDirectPermissionsActiveAdmin"
                plain
                @click="emit('permission')"
              >
                权限
              </ElButton>
              <ElButton
                v-if="canToggleStatusActiveAdmin"
                :type="activeAdmin.status === 1 ? 'warning' : 'success'"
                plain
                @click="emit('status')"
              >
                {{ activeAdmin.status === 1 ? '停用' : '启用' }}
              </ElButton>
              <ElButton v-if="canDeleteActiveAdmin" type="danger" plain @click="emit('delete')">
                移入回收站
              </ElButton>
            </template>
          </div>
        </section>

        <section class="drawer-section">
          <div class="drawer-grid">
            <div class="drawer-item">
              <span>用户名</span>
              <strong>{{ activeAdmin.username }}</strong>
            </div>
            <div class="drawer-item">
              <span>昵称</span>
              <strong>{{ activeAdmin.nickname || '--' }}</strong>
            </div>
            <div class="drawer-item">
              <span>作用域</span>
              <strong>{{ activeAdmin.scope_label }}</strong>
            </div>
            <div class="drawer-item">
              <span>状态</span>
              <strong>{{ activeAdmin.status_label }}</strong>
            </div>
            <div class="drawer-item">
              <span>角色数量</span>
              <strong>{{ activeAdmin.role_count }}</strong>
            </div>
            <div class="drawer-item">
              <span>权限覆盖</span>
              <strong>{{ activeAdmin.permission_coverage_label }}</strong>
            </div>
            <div class="drawer-item">
              <span>直属权限</span>
              <strong>{{ activeAdmin.direct_permission_count }}</strong>
            </div>
            <div class="drawer-item">
              <span>令牌状态</span>
              <strong>{{ activeAdmin.token_active ? '存在活跃令牌' : '无活跃令牌' }}</strong>
            </div>
            <div class="drawer-item">
              <span>创建时间</span>
              <strong>{{ activeAdmin.create_time || '--' }}</strong>
            </div>
            <div class="drawer-item">
              <span>更新时间</span>
              <strong>{{ activeAdmin.update_time || '--' }}</strong>
            </div>
            <div class="drawer-item">
              <span>删除时间</span>
              <strong>{{ activeAdmin.delete_time || '--' }}</strong>
            </div>
          </div>
        </section>

        <section class="drawer-section">
          <h4>角色绑定</h4>
          <div v-if="activeAdmin.roles.length" class="role-tags">
            <ElTag
              v-for="role in activeAdmin.roles"
              :key="role.id"
              :type="role.code === 'super_admin' ? 'danger' : 'primary'"
              effect="plain"
            >
              {{ role.name }}
            </ElTag>
          </div>
          <p v-else class="empty-copy">暂未分配角色</p>
        </section>

        <section class="drawer-section">
          <h4>直属权限树</h4>
          <ElScrollbar height="320px" class="permission-scroll">
            <ElTree
              :data="directPermissionTree"
              node-key="id"
              default-expand-all
              :expand-on-click-node="false"
              :props="{ children: 'children', label: 'title' }"
            >
              <template #default="{ data: treeItem }">
                <div class="permission-node">
                  <div class="permission-meta">
                    <strong>{{ treeItem.title }}</strong>
                    <p>{{ treeItem.path || '--' }}</p>
                  </div>
                  <ElTag size="small" :type="treeItem.checked ? 'warning' : 'info'" effect="light">
                    {{ treeItem.checked ? '直属授权' : '继承 / 无' }}
                  </ElTag>
                </div>
              </template>
            </ElTree>
          </ElScrollbar>
        </section>

        <section class="drawer-section">
          <h4>生效权限树</h4>
          <ElScrollbar height="360px" class="permission-scroll">
            <ElTree
              :data="permissionTree"
              node-key="id"
              default-expand-all
              :expand-on-click-node="false"
              :props="{ children: 'children', label: 'title' }"
            >
              <template #default="{ data: treeItem }">
                <div class="permission-node">
                  <div class="permission-meta">
                    <strong>{{ treeItem.title }}</strong>
                    <p>{{ treeItem.path || '--' }}</p>
                  </div>
                  <ElTag size="small" :type="treeItem.checked ? 'success' : 'info'" effect="light">
                    {{ treeItem.checked ? '已授权' : '未授权' }}
                  </ElTag>
                </div>
              </template>
            </ElTree>
          </ElScrollbar>
        </section>

        <p v-if="detailEditable?.read_only_reasons.length" class="dialog-hint warning">
          {{ detailEditable.read_only_reasons.join(' / ') }}
        </p>
      </template>
    </div>
  </ElDrawer>
</template>

<script setup lang="ts">
  import { computed } from 'vue'

  defineOptions({ name: 'AdminDetailDrawer' })

  type AdminAccountItem = Api.AdminAccounts.AdminAccountListItem
  type AdminEditable = Api.AdminAccounts.AdminAccountEditable
  type PermissionTreeItem = Api.Roles.PermissionTreeItem

  interface Props {
    visible: boolean
    detailLoading: boolean
    activeAdmin: AdminAccountItem | null
    detailEditable: AdminEditable | null
    permissionTree: PermissionTreeItem[]
    directPermissionTree: PermissionTreeItem[]
    canEditActiveAdmin: boolean
    canAssignRolesActiveAdmin: boolean
    canAssignDirectPermissionsActiveAdmin: boolean
    canToggleStatusActiveAdmin: boolean
    canDeleteActiveAdmin: boolean
    canRestoreActiveAdmin: boolean
  }

  const props = defineProps<Props>()

  const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'edit'): void
    (e: 'role'): void
    (e: 'permission'): void
    (e: 'status'): void
    (e: 'delete'): void
    (e: 'restore'): void
  }>()

  const drawerVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
  })
</script>

<style scoped lang="scss">
  .admin-detail {
    display: flex;
    min-height: 260px;
    flex-direction: column;
    gap: 24px;
    --admin-detail-card-border: var(--el-border-color-lighter);
    --admin-detail-card-bg: rgb(248 250 252 / 0.82);
    --admin-detail-scroll-bg: rgb(248 250 252 / 0.55);
  }

  :global(html.dark .admin-detail ){
    --admin-detail-card-border: rgb(71 85 105 / 0.42);
    --admin-detail-card-bg: rgb(15 23 42 / 0.84);
    --admin-detail-scroll-bg: rgb(15 23 42 / 0.72);
  }

  .detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 20px 22px;
    border: 1px solid rgb(245 158 11 / 0.18);
    border-radius: 20px;
    background:
      radial-gradient(circle at top left, rgb(245 158 11 / 0.16), transparent 46%),
      linear-gradient(135deg, rgb(15 23 42 / 0.96), rgb(30 41 59 / 0.92));
    color: #f8fafc;
  }

  .detail-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .detail-hero-copy h3 {
    margin: 0;
    font-size: 22px;
  }

  .detail-hero-copy p,
  .detail-hero-copy span {
    margin: 0;
    color: rgb(226 232 240 / 0.9);
  }

  .detail-hero-actions,
  .role-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .detail-hero-actions {
    justify-content: flex-end;
  }

  .drawer-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .drawer-section h4 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 15px;
  }

  .drawer-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .drawer-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--admin-detail-card-border);
    border-radius: 14px;
    background: var(--admin-detail-card-bg);
  }

  .drawer-item span {
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .drawer-item strong {
    color: var(--el-text-color-primary);
    word-break: break-all;
  }

  .empty-copy,
  .dialog-hint {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 13px;
    line-height: 1.7;
  }

  .dialog-hint.warning {
    color: #b45309;
  }

  .permission-scroll {
    border: 1px solid var(--admin-detail-card-border);
    border-radius: 14px;
    padding: 12px;
    background: var(--admin-detail-scroll-bg);
  }

  .permission-scroll :deep(.el-tree-node__content) {
    height: auto;
    min-height: 44px;
    align-items: flex-start;
    padding: 6px 0;
  }

  .permission-scroll :deep(.el-tree-node__content > .permission-node) {
    flex: 1;
    min-width: 0;
  }

  .permission-scroll :deep(.el-tree-node__expand-icon),
  .permission-scroll :deep(.el-checkbox) {
    margin-top: 6px;
  }

  .permission-node {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    width: 100%;
    gap: 12px;
  }

  .permission-meta {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 2px;
  }

  .permission-meta strong {
    color: var(--el-text-color-primary);
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
  }

  .permission-meta p {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.5;
    word-break: break-all;
  }

  .permission-node :deep(.el-tag) {
    flex-shrink: 0;
    margin-top: 2px;
  }

  @media (width <= 991px) {
    .detail-hero {
      flex-direction: column;
    }

    .detail-hero-actions {
      justify-content: flex-start;
    }

    .drawer-grid {
      grid-template-columns: 1fr;
    }

    .permission-node {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
