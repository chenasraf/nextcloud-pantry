<template>
  <NcDialog
    :name="dialogTitle"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form :id="formId" class="pantry-form" autocomplete="off" @submit.prevent="submit">
      <NcTextField
        v-model="nameValue"
        :label="strings.nameLabel"
        :placeholder="strings.namePlaceholder"
        autocomplete="off"
      />
      <ShareEditor
        v-if="folder"
        :house-id="folder.houseId"
        entity-type="photos-folder"
        :entity-id="folder.id"
        :can-manage="isAdmin"
      />
      <p v-if="folder" class="pantry-share-hint">{{ strings.shareHint }}</p>
    </form>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton :form="formId" type="submit" variant="primary" :disabled="!nameValue.trim()">
        {{ strings.save }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { PhotoFolder } from '@/api/types'
import { ShareEditor } from '@/components/ShareEditor'
import { useCurrentHouse } from '@/composables/useCurrentHouse'

const props = defineProps<{
  open: boolean
  folder?: PhotoFolder | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  save: [name: string]
}>()

const { isAdmin } = useCurrentHouse()
const formId = 'pantry-folder-dialog-form'
const nameValue = ref('')

const dialogTitle = props.folder ? t('pantry', 'Edit folder') : t('pantry', 'Create folder')

watch(
  () => props.open,
  (v) => {
    if (v) {
      nameValue.value = props.folder?.name ?? ''
    }
  },
  { immediate: true },
)

function submit() {
  const name = nameValue.value.trim()
  if (!name) return
  emit('save', name)
}

const strings = {
  nameLabel: t('pantry', 'Folder name'),
  namePlaceholder: t('pantry', 'e.g. Recipes'),
  cancel: t('pantry', 'Cancel'),
  save: t('pantry', 'Save'),
  shareHint: t('pantry', 'Sharing a folder gives access to every photo inside it.'),
}
</script>

<style scoped lang="scss">
.pantry-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.5rem 0;
}

.pantry-share-hint {
  margin: 0;
  font-size: 0.85em;
  color: var(--color-text-maxcontrast);
}
</style>
