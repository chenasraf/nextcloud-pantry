<template>
  <span
    class="status-badge"
    :class="statusClass"
    :style="customStyle"
    :title="tooltipText"
    @click="handleClick"
  >
    <component :is="iconComponent" v-if="iconComponent" :size="16" class="status-icon" />
    <span class="status-label">{{ label }}</span>
  </span>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { t } from '@nextcloud/l10n'
import CheckIcon from '@icons/Check.vue'
import AlertIcon from '@icons/Alert.vue'
import ClockIcon from '@icons/ClockOutline.vue'

export type StatusType = 'success' | 'warning' | 'pending' | 'error'

export default defineComponent({
  name: 'StatusBadge',
  components: {
    CheckIcon,
    AlertIcon,
    ClockIcon,
  },
  props: {
    status: {
      type: String as PropType<StatusType>,
      required: true,
      validator: (value: string) => ['success', 'warning', 'pending', 'error'].includes(value),
    },
    label: {
      type: String,
      default: '',
    },
    showIcon: {
      type: Boolean,
      default: true,
    },
    clickable: {
      type: Boolean,
      default: false,
    },
    customColor: {
      type: String,
      default: null,
    },
  },
  emits: ['click'],
  computed: {
    statusClass(): Record<string, boolean> {
      return {
        [`status-${this.status}`]: true,
        'status-clickable': this.clickable,
      }
    },
    customStyle(): Record<string, string> | null {
      if (!this.customColor) {
        return null
      }
      return {
        '--status-color': this.customColor,
        backgroundColor: this.customColor,
      }
    },
    tooltipText(): string {
      const statusLabels: Record<StatusType, string> = {
        success: t('nextcloudapptemplate', 'Completed successfully'),
        warning: t('nextcloudapptemplate', 'Completed with warnings'),
        pending: t('nextcloudapptemplate', 'In progress'),
        error: t('nextcloudapptemplate', 'Failed'),
      }
      return statusLabels[this.status] || ''
    },
    iconComponent(): typeof CheckIcon | typeof AlertIcon | typeof ClockIcon | null {
      if (!this.showIcon) {
        return null
      }
      const icons: Record<StatusType, typeof CheckIcon | typeof AlertIcon | typeof ClockIcon> = {
        success: CheckIcon,
        warning: AlertIcon,
        pending: ClockIcon,
        error: AlertIcon,
      }
      return icons[this.status]
    },
  },
  methods: {
    handleClick(event: MouseEvent): void {
      if (this.clickable) {
        this.$emit('click', event)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;

  &.status-success {
    background-color: var(--color-success-light, #e8f5e9);
    color: var(--color-success, #4caf50);
  }

  &.status-warning {
    background-color: var(--color-warning-light, #fff3e0);
    color: var(--color-warning, #ff9800);
  }

  &.status-pending {
    background-color: var(--color-info-light, #e3f2fd);
    color: var(--color-info, #2196f3);
  }

  &.status-error {
    background-color: var(--color-error-light, #ffebee);
    color: var(--color-error, #f44336);
  }

  &.status-clickable {
    cursor: pointer;

    &:hover {
      opacity: 0.8;
    }
  }
}

.status-icon {
  flex-shrink: 0;
}

.status-label {
  white-space: nowrap;
}
</style>
