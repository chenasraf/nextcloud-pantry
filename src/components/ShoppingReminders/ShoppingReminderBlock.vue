<template>
  <section class="reminder-block" :class="{ 'reminder-block--empty': reminders.length === 0 }">
    <header class="reminder-block__head">
      <BellRingIcon :size="18" />
      <span class="reminder-block__title">{{ strings.title }}</span>
      <NcButton
        v-if="reminders.length > 0"
        class="reminder-block__manage"
        variant="tertiary"
        :aria-label="strings.manage"
        :title="strings.manage"
        @click="$emit('manage')"
      >
        <template #icon><PencilIcon :size="16" /></template>
      </NcButton>
    </header>

    <ul v-if="reminders.length > 0" class="reminder-block__list">
      <li v-for="reminder in reminders" :key="reminder.id" class="reminder-block__item">
        <NcCheckboxRadioSwitch
          :model-value="acked.has(reminder.id)"
          @update:model-value="toggleAck(reminder.id, $event)"
        >
          <span :class="{ 'reminder-block__done': acked.has(reminder.id) }">{{
            reminder.text
          }}</span>
        </NcCheckboxRadioSwitch>
      </li>
    </ul>

    <div v-else class="reminder-block__empty">
      <span class="reminder-block__empty-text">{{ strings.empty }}</span>
      <NcButton variant="secondary" @click="$emit('manage')">
        <template #icon><PlusIcon :size="18" /></template>
        {{ strings.addCta }}
      </NcButton>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import BellRingIcon from '@icons/BellRing.vue'
import PencilIcon from '@icons/Pencil.vue'
import PlusIcon from '@icons/Plus.vue'
import { useReminders } from '@/composables/useReminders'
import type { ShoppingReminderMoment } from '@/api/types'

const props = defineProps<{
  houseId: number
  moment: ShoppingReminderMoment
}>()
defineEmits<{
  manage: []
}>()

const store = useReminders(props.houseId)
const reminders = computed(() =>
  store.items.value.filter((r) => r.enabled && r.showOn === props.moment),
)

// Acknowledgement is deliberately client-local and ephemeral (ADR 0002): ticking
// a reminder just visually checks it off; nothing is persisted, so it resets on
// reload / remount.
const acked = ref<Set<number>>(new Set())
function toggleAck(id: number, on: boolean) {
  const next = new Set(acked.value)
  if (on) next.add(id)
  else next.delete(id)
  acked.value = next
}

onMounted(() => void store.load())

const strings = {
  // TRANSLATORS: Heading above the shopping reminder prompts.
  title: t('pantry', 'Reminders'),
  // TRANSLATORS: Button that opens the reminder manager to edit prompts.
  manage: t('pantry', 'Manage reminders'),
  // TRANSLATORS: Shown in the reminders area when this step has no reminders yet.
  empty: t('pantry', 'No reminders for this step yet.'),
  // TRANSLATORS: Button that opens the manager to create the first reminders.
  addCta: t('pantry', 'Add reminders'),
}
</script>

<style scoped lang="scss">
.reminder-block {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  background: var(--color-background-hover);
  padding: 0.75rem 1rem;

  &__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-text-maxcontrast);
    font-weight: 600;
    margin-bottom: 0.25rem;
  }

  &__title {
    flex: 1;
    min-width: 0;
  }

  &__manage {
    flex-shrink: 0;
  }

  &__list {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  &__item {
    padding: 0.1rem 0;
  }

  &__done {
    text-decoration: line-through;
    color: var(--color-text-maxcontrast);
  }

  &__empty {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  &__empty-text {
    color: var(--color-text-maxcontrast);
    font-weight: 400;
  }

  // A moment with no reminders is a quiet prompt, not a callout.
  &--empty {
    background: transparent;
    border-style: dashed;
  }
}
</style>
