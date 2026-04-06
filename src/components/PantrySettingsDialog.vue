<template>
  <NcAppSettingsDialog
    :open="open"
    :name="strings.title"
    :show-navigation="true"
    @update:open="(v) => emit('update:open', v)"
  >
    <NcAppSettingsSection id="pantry-images" :name="strings.imagesSection">
      <p class="pantry-settings__hint">{{ strings.imagesHint }}</p>
      <form class="pantry-settings__form" @submit.prevent="save">
        <div class="pantry-settings__folder-row">
          <NcTextField v-model="folder" :label="strings.folderLabel" placeholder="/Pantry" />
          <NcButton type="button" variant="secondary" @click="browseFolder">
            <template #icon>
              <FolderIcon :size="20" />
            </template>
            {{ strings.browse }}
          </NcButton>
        </div>
        <div class="pantry-settings__actions">
          <NcButton type="submit" variant="primary" :disabled="saving || !folder.trim()">
            {{ saving ? strings.saving : strings.save }}
          </NcButton>
          <span v-if="saved" class="pantry-settings__saved">{{ strings.saved }}</span>
        </div>
      </form>
    </NcAppSettingsSection>
  </NcAppSettingsDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import FolderIcon from '@icons/Folder.vue'
import { getImageFolder, setImageFolder } from '@/api/prefs'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const folder = ref('/Pantry')
const saving = ref(false)
const saved = ref(false)

async function loadFolder() {
  try {
    folder.value = await getImageFolder()
  } catch {
    // Keep default.
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      saved.value = false
      void loadFolder()
    }
  },
  { immediate: true },
)

async function browseFolder() {
  const picker = getFilePickerBuilder(strings.pickerTitle)
    .setMultiSelect(false)
    .setMimeTypeFilter([])
    .allowDirectories(true)
    .setType(1) // Choose
    .startAt(folder.value || '/')
    .build()
  try {
    const picked = await picker.pick()
    const path = Array.isArray(picked) ? picked[0] : picked
    if (typeof path === 'string' && path.length > 0) {
      folder.value = path
      saved.value = false
    }
  } catch {
    // User cancelled — no-op.
  }
}

async function save() {
  const value = folder.value.trim()
  if (!value) return
  saving.value = true
  saved.value = false
  try {
    folder.value = await setImageFolder(value)
    saved.value = true
  } finally {
    saving.value = false
  }
}

const strings = {
  title: t('pantry', 'Pantry settings'),
  imagesSection: t('pantry', 'Images'),
  imagesHint: t(
    'pantry',
    'Pick the base folder where Pantry will store uploaded images. Shopping list item images go into a "Shopping list items" subfolder inside it, created automatically.',
  ),
  folderLabel: t('pantry', 'Upload folder'),
  browse: t('pantry', 'Browse …'),
  pickerTitle: t('pantry', 'Pick an upload folder'),
  save: t('pantry', 'Save'),
  saving: t('pantry', 'Saving …'),
  saved: t('pantry', 'Saved.'),
}
</script>

<style scoped lang="scss">
.pantry-settings__hint {
  color: var(--color-text-maxcontrast);
  margin: 0 0 0.75rem 0;
  font-size: 0.9rem;
}

.pantry-settings__form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pantry-settings__folder-row {
  display: flex;
  align-items: end;
  gap: 0.5rem;

  :deep(.input-field) {
    flex: 1;
    min-width: 0;
  }
}

.pantry-settings__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.pantry-settings__saved {
  color: var(--color-success);
  font-size: 0.85rem;
}
</style>
