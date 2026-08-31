<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div class="cf-manager">
      <div v-if="loading" class="cf-manager__loading">
        <NcLoadingIcon :size="32" />
      </div>

      <p v-else-if="groups.length === 0 && !creating" class="cf-manager__empty">
        {{ strings.emptyHint }}
      </p>

      <ul v-else class="cf-acc">
        <template v-for="group in groups" :key="group.key">
          <li class="cf-acc__group">{{ group.title }}</li>
          <li v-for="field in group.fields" :key="field.id" class="cf-acc__item">
            <button
              type="button"
              class="cf-acc__head"
              :aria-expanded="openId === field.id"
              @click="toggle(field)"
            >
              <component
                :is="fieldTypeIconComponent(field.type)"
                :size="18"
                class="cf-acc__ticon"
              />
              <span class="cf-acc__name">{{ field.name }}</span>
              <span class="cf-acc__type">{{ fieldTypeLabel(field.type) }}</span>
              <BellIcon
                v-if="field.type === 'date' && field.notifyDefault"
                :size="14"
                class="cf-acc__bell"
                :title="strings.reminderOn"
              />
              <ChevronDownIcon
                :size="18"
                class="cf-acc__chev"
                :class="{ 'cf-acc__chev--open': openId === field.id }"
              />
            </button>
            <div v-if="openId === field.id" class="cf-acc__body">
              <CustomFieldEditor
                v-model="draft"
                :lists="lists"
                :saving="saving"
                :error="error"
                :is-new="false"
                @save="submit"
                @delete="confirmDelete(field)"
                @cancel="collapse"
              />
            </div>
          </li>
        </template>

        <li v-if="creating" class="cf-acc__item cf-acc__item--new">
          <div class="cf-acc__body">
            <CustomFieldEditor
              v-model="draft"
              :lists="lists"
              :saving="saving"
              :error="error"
              :is-new="true"
              @save="submit"
              @delete="collapse"
              @cancel="collapse"
            />
          </div>
        </li>
      </ul>

      <NcButton
        v-if="!loading"
        variant="secondary"
        class="cf-manager__add"
        :disabled="creating || openId !== null"
        @click="startCreate"
      >
        <template #icon><PlusIcon :size="18" /></template>
        {{ strings.addField }}
      </NcButton>
    </div>

    <NcDialog
      :open="deleting !== null"
      :name="strings.deleteTitle"
      size="small"
      @update:open="
        (v: boolean) => {
          if (!v) deleting = null
        }
      "
    >
      <p>{{ strings.deleteConfirm }}</p>
      <template #actions>
        <NcButton @click="deleting = null">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" :disabled="saving" @click="submitDelete">
          {{ strings.delete }}
        </NcButton>
      </template>
    </NcDialog>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PlusIcon from '@icons/Plus.vue'
import BellIcon from '@icons/Bell.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import type { FieldDefinition } from '@/api/types'
import type { CreateFieldInput, UpdateFieldPatch } from '@/api/customFields'
import { useCustomFields } from '@/composables/useCustomFields'
import { useChecklists } from '@/composables/useChecklist'
import { fieldTypeIconComponent, fieldTypeLabel } from './fieldTypeIcons'
import CustomFieldEditor from './CustomFieldEditor.vue'
import type { FieldDraft } from './draft'
import { blankDraft, draftFromField, draftToCreate, draftToPatch } from './draft'

const props = defineProps<{ open: boolean; houseId: number }>()
defineEmits<{ 'update:open': [value: boolean] }>()

const fields = useCustomFields(props.houseId)
const items = computed(() => fields.items.value)
const loading = computed(() => fields.loading.value)
const { lists, load: loadLists } = useChecklists(props.houseId)

const openId = ref<number | null>(null)
const creating = ref(false)
const draft = ref<FieldDraft>(blankDraft())
const saving = ref(false)
const error = ref<string | null>(null)
const deleting = ref<FieldDefinition | null>(null)

const strings = {
  title: t('pantry', 'Custom fields'),
  emptyHint: t('pantry', 'No custom fields yet. Add one to attach extra info to items.'),
  addField: t('pantry', 'Add field'),
  // TRANSLATORS: Header for fields available on every list (not tied to one list)
  allLists: t('pantry', 'All lists'),
  reminderOn: t('pantry', 'Reminder on'),
  deleteTitle: t('pantry', 'Delete field'),
  deleteConfirm: t('pantry', 'Delete this field? Values already set on items are kept but hidden.'),
  cancel: t('pantry', 'Cancel'),
  delete: t('pantry', 'Delete'),
}

interface Group {
  key: string
  title: string
  fields: FieldDefinition[]
}

const groups = computed<Group[]>(() => {
  const out: Group[] = []
  const globals = items.value.filter((f) => f.listId == null)
  if (globals.length) {
    out.push({ key: 'all', title: strings.allLists, fields: globals })
  }
  for (const list of lists.value) {
    const scoped = items.value.filter((f) => f.listId === list.id)
    if (scoped.length) {
      out.push({ key: `list-${list.id}`, title: list.name, fields: scoped })
    }
  }
  // Fields whose list isn't loaded still need a home.
  const knownListIds = new Set(lists.value.map((l) => l.id))
  const orphans = items.value.filter((f) => f.listId != null && !knownListIds.has(f.listId))
  if (orphans.length) {
    out.push({ key: 'orphans', title: t('pantry', 'Other lists'), fields: orphans })
  }
  return out
})

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      collapse()
      void loadLists()
      void fields.load()
    }
  },
  { immediate: true },
)

function collapse(): void {
  openId.value = null
  creating.value = false
  error.value = null
}

function toggle(field: FieldDefinition): void {
  if (openId.value === field.id) {
    collapse()
    return
  }
  creating.value = false
  error.value = null
  openId.value = field.id
  draft.value = draftFromField(field)
}

function startCreate(): void {
  openId.value = null
  error.value = null
  creating.value = true
  draft.value = blankDraft()
}

async function submit(): Promise<void> {
  const name = draft.value.name.trim()
  if (!name) {
    error.value = t('pantry', 'Name cannot be empty')
    return
  }
  saving.value = true
  error.value = null
  try {
    if (creating.value) {
      const input: CreateFieldInput = draftToCreate(draft.value)
      await fields.create(input)
    } else if (openId.value !== null) {
      const patch: UpdateFieldPatch = draftToPatch(draft.value)
      await fields.update(openId.value, patch)
    }
    collapse()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    saving.value = false
  }
}

function confirmDelete(field: FieldDefinition): void {
  deleting.value = field
}

async function submitDelete(): Promise<void> {
  if (!deleting.value) return
  saving.value = true
  try {
    await fields.remove(deleting.value.id)
    deleting.value = null
    collapse()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    saving.value = false
  }
}
</script>

<style scoped lang="scss">
.cf-manager {
  min-width: 340px;
  padding: 0.25rem 0;

  &__loading {
    display: flex;
    justify-content: center;
    padding: 2rem 0;
  }

  &__empty {
    color: var(--color-text-maxcontrast);
    margin: 0 0 0.75rem 0;
  }

  &__add {
    margin-top: 0.25rem;
  }
}

.cf-acc {
  list-style: none;
  margin: 0 0 0.75rem;
  padding: 0;

  &__group {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-maxcontrast);
    padding: 0.9rem 0 0.35rem;
  }

  &__item {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius, 8px);
    margin-bottom: 0.4rem;
    overflow: hidden;

    &--new {
      border-color: var(--color-primary-element);
    }
  }

  &__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 9px 11px;
    background: var(--color-main-background);
    border: none;
    cursor: pointer;
    text-align: start;

    &:hover {
      background: var(--color-background-hover);
    }
  }

  &__ticon {
    color: var(--color-primary-element);
    flex-shrink: 0;
  }

  &__name {
    flex: 1;
    font-weight: 500;
  }

  &__type {
    font-size: 0.78rem;
    color: var(--color-text-maxcontrast);
  }

  &__bell {
    color: var(--color-primary-element);
  }

  &__chev {
    color: var(--color-text-maxcontrast);
    transition: transform 0.15s ease;

    &--open {
      transform: rotate(180deg);
    }
  }

  &__body {
    padding: 0.75rem 0.9rem 0.9rem;
    border-top: 1px solid var(--color-border);
  }
}

@media (max-width: 500px) {
  .cf-manager {
    min-width: 0;
  }
}
</style>
