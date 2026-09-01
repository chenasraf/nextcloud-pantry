<template>
  <div class="pantry-detail">
    <PageToolbar :title="isMeta ? strings.allListsTitle : list?.name" :actions="toolbarActions">
      <template #before-title>
        <NcButton
          variant="tertiary"
          :aria-label="strings.back"
          @click="$router.push({ name: 'lists', params: { houseId } })"
        >
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
        </NcButton>
        <span v-if="isMeta" class="pantry-detail__title-icon pantry-detail__title-icon--meta">
          <ViewListIcon :size="20" />
        </span>
        <span v-else-if="list" class="pantry-detail__title-icon" :style="iconWrapStyle(list.color)">
          <component :is="checklistIconComponent(list.icon)" :size="20" />
        </span>
      </template>
    </PageToolbar>

    <div class="pantry-detail__body">
      <div v-if="selectionMode" class="pantry-detail__selection-bar">
        <NcButton
          variant="tertiary"
          :aria-label="strings.exitSelection"
          :title="strings.exitSelection"
          @click="exitSelection"
        >
          <template #icon>
            <CloseIcon :size="20" />
          </template>
        </NcButton>
        <span class="pantry-detail__selection-count">{{ selectionCountLabel }}</span>
        <NcButton variant="tertiary" :disabled="allVisibleSelected" @click="selectAllVisible">
          {{ strings.selectAll }}
        </NcButton>
        <NcButton v-if="selectedCount > 0" variant="tertiary" @click="clearSelection">
          {{ strings.clearSelection }}
        </NcButton>
        <span class="pantry-detail__selection-spacer" />
        <NcButton
          variant="tertiary"
          :disabled="!canBulkMove"
          :aria-label="strings.moveSelected"
          :title="strings.moveSelected"
          @click="openBulkMove"
        >
          <template #icon>
            <ArrowRightIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCopy"
          :aria-label="strings.copySelected"
          :title="strings.copySelected"
          @click="openBulkCopy"
        >
          <template #icon>
            <ContentCopyIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCategory"
          :aria-label="strings.assignCategory"
          :title="strings.assignCategory"
          @click="openBulkCategory"
        >
          <template #icon>
            <CategoryEntityIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCategory"
          :aria-label="strings.assignStores"
          :title="strings.assignStores"
          @click="openBulkStores"
        >
          <template #icon>
            <StoreEntityIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkCategory"
          :aria-label="strings.assignLabels"
          :title="strings.assignLabels"
          @click="openBulkLabels"
        >
          <template #icon>
            <LabelEntityIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          v-if="!trashMode && !archiveMode"
          variant="tertiary"
          :disabled="!canBulkArchive"
          :aria-label="strings.archiveSelected"
          :title="strings.archiveSelected"
          @click="bulkArchive"
        >
          <template #icon>
            <ArchiveArrowDownOutlineIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          v-if="archiveMode"
          variant="tertiary"
          :disabled="!canBulkArchive"
          :aria-label="strings.unarchiveSelected"
          :title="strings.unarchiveSelected"
          @click="bulkUnarchive"
        >
          <template #icon>
            <ArchiveArrowUpOutlineIcon :size="20" />
          </template>
        </NcButton>
        <NcButton
          variant="tertiary"
          :disabled="!canBulkDelete"
          :aria-label="strings.deleteSelected"
          :title="strings.deleteSelected"
          @click="bulkDelete"
        >
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
        </NcButton>
      </div>

      <ChecklistAddForm
        v-if="
          !selectionMode &&
          can.canAddItems &&
          !trashMode &&
          !archiveMode &&
          (isMeta || writableHere)
        "
        ref="addForm"
        :house-id="houseIdNum"
        :adding="adding"
        :delete-on-done-default="list?.deleteOnDoneDefault ?? false"
        :require-list-selector="isMeta"
        :available-lists="isMeta ? allLists : []"
        :reuse-candidates="reuseCandidates"
        :current-list-id="isMeta ? null : listIdNum"
        :default-currency="defaultCurrency"
        @add="handleAdd"
        @update:delete-on-done-default="handleDeleteOnDoneDefaultChange"
        @reuse-existing="onReuseFromSuggestion"
      />

      <ChecklistFilter
        v-if="items.length > 0"
        v-model:query="filterQuery"
        v-model:selected-category-ids="filterCategoryIds"
        v-model:selected-store-ids="filterStoreIds"
        v-model:selected-label-ids="filterLabelIds"
        v-model:selected-list-ids="filterListIds"
        v-model:price-filter="filterPrice"
        :items="items"
        :categories="categories.items.value"
        :stores="stores.items.value"
        :labels="labels.items.value"
        :lists="isMeta ? allLists : undefined"
        class="pantry-detail__filter"
      />

      <div v-if="loading" class="pantry-detail__center">
        <NcLoadingIcon :size="36" />
      </div>

      <NcEmptyContent
        v-else-if="items.length === 0"
        :name="emptyStateTitle"
        :description="emptyStateBody"
      >
        <template #icon>
          <TrashCanIcon v-if="trashMode" />
          <ArchiveOutlineIcon v-else-if="archiveMode" />
          <span
            v-else
            class="pantry-detail__empty-icon"
            :style="iconWrapStyle(list?.color ?? null)"
          >
            <component :is="checklistIconComponent(list?.icon)" :size="32" />
          </span>
        </template>
      </NcEmptyContent>

      <template v-else>
        <div v-if="trashMode" class="pantry-detail__trash-bar">
          <NcButton variant="error" @click="confirmingEmptyTrash = true">
            <template #icon>
              <TrashCanIcon :size="20" />
            </template>
            {{ strings.emptyTrashAction }}
          </NcButton>
        </div>
        <ul v-if="uncheckedItems.length > 0" ref="uncheckedListRef" class="pantry-detail__items">
          <template v-for="gi in uncheckedGridItems" :key="gi.key">
            <li
              v-if="gi.type === 'placeholder'"
              class="pantry-detail__placeholder"
              @dragover.prevent
              @drop.prevent.stop="onPlaceholderDrop"
            />
            <li
              v-else-if="gi.type === 'header'"
              class="pantry-detail__category-header"
              :style="gi.category ? { color: gi.category.color } : undefined"
            >
              <component :is="categoryIconComponent(gi.category?.icon)" :size="18" />
              <span class="pantry-detail__category-header-name">
                {{ gi.category?.name ?? strings.noCategory }}
              </span>
            </li>
            <li
              v-else-if="gi.type === 'store-header'"
              class="pantry-detail__category-header"
              :style="gi.store ? { color: gi.store.color } : undefined"
            >
              <component :is="storeIconComponent(gi.store?.icon)" :size="18" />
              <span class="pantry-detail__category-header-name">
                {{ gi.store?.name ?? strings.noStore }}
              </span>
            </li>
            <ChecklistItemRow
              v-else
              :item="gi.item"
              :category="categoryFor(gi.item.categoryId)"
              :stores="storesFor(gi.item.storeIds)"
              :labels="labelsFor(gi.item.labelIds)"
              :hide-category="showCategoryHeaders"
              :hide-store="showStoreHeaders"
              :price-store-id="gi.priceStoreId"
              :list="isMeta ? listFor(gi.item.listId) : null"
              :list-writable="isMeta ? listWritable(listFor(gi.item.listId)) : writableHere"
              :house-id="houseIdNum"
              :reorder-enabled="
                reorderActive && (isMeta ? listWritable(listFor(gi.item.listId)) : writableHere)
              "
              :reorder-group-key="gi.groupKey"
              :trash-mode="trashMode"
              :archive-mode="archiveMode"
              :row-click-action="rowClickAction"
              :show-added-by="showAddedBy"
              :selection-mode="selectionMode"
              :selected="selectedIds.has(gi.item.id)"
              @toggle="handleToggle"
              @toggle-select="toggleSelect"
              @view="openView"
              @view-store="openStoreView"
              @edit="startEdit"
              @move="startMoveItem"
              @copy="startCopyItem"
              @remove="handleRemove"
              @restore="handleRestore"
              @archive="handleArchive"
              @unarchive="handleUnarchive"
              @preview="openPreview"
              @drag-start="onItemDragStart"
              @reorder-over="onReorderOver"
            />
          </template>
        </ul>
        <template v-if="checkedItems.length > 0">
          <div class="pantry-detail__section-header">
            <button
              type="button"
              class="pantry-detail__section-title pantry-detail__section-toggle"
              :aria-expanded="!doneCollapsed"
              @click="doneCollapsed = !doneCollapsed"
            >
              <ChevronDownIcon
                class="pantry-detail__section-chevron"
                :class="{ 'pantry-detail__section-chevron--collapsed': doneCollapsed }"
                :size="18"
              />
              <span>{{ strings.doneTitle }}</span>
            </button>
            <NcButton
              v-if="canUncheckAll"
              class="pantry-detail__uncheck-all"
              variant="tertiary"
              :disabled="unchecking"
              @click="confirmingUncheckAll = true"
            >
              <template #icon>
                <CheckboxMultipleBlankOutlineIcon :size="18" />
              </template>
              {{ strings.uncheckAll }}
            </NcButton>
            <NcButton
              v-if="canRemoveAll"
              class="pantry-detail__uncheck-all"
              variant="tertiary"
              :disabled="removingAll"
              @click="confirmingRemoveAll = true"
            >
              <template #icon>
                <DeleteIcon :size="18" />
              </template>
              {{ strings.removeAll }}
            </NcButton>
          </div>
          <ul
            v-show="!doneCollapsed"
            ref="checkedListRef"
            class="pantry-detail__items pantry-detail__items--done"
          >
            <template v-for="gi in checkedGridItems" :key="gi.key">
              <li
                v-if="gi.type === 'placeholder'"
                class="pantry-detail__placeholder"
                @dragover.prevent
                @drop.prevent.stop="onPlaceholderDrop"
              />
              <li
                v-else-if="gi.type === 'header'"
                class="pantry-detail__category-header"
                :style="gi.category ? { color: gi.category.color } : undefined"
              >
                <component :is="categoryIconComponent(gi.category?.icon)" :size="18" />
                <span class="pantry-detail__category-header-name">
                  {{ gi.category?.name ?? strings.noCategory }}
                </span>
              </li>
              <li
                v-else-if="gi.type === 'store-header'"
                class="pantry-detail__category-header"
                :style="gi.store ? { color: gi.store.color } : undefined"
              >
                <component :is="storeIconComponent(gi.store?.icon)" :size="18" />
                <span class="pantry-detail__category-header-name">
                  {{ gi.store?.name ?? strings.noStore }}
                </span>
              </li>
              <ChecklistItemRow
                v-else
                :item="gi.item"
                :category="categoryFor(gi.item.categoryId)"
                :stores="storesFor(gi.item.storeIds)"
                :labels="labelsFor(gi.item.labelIds)"
                :hide-category="showCategoryHeaders"
                :hide-store="showStoreHeaders"
                :price-store-id="gi.priceStoreId"
                :list="isMeta ? listFor(gi.item.listId) : null"
                :list-writable="isMeta ? listWritable(listFor(gi.item.listId)) : writableHere"
                :house-id="houseIdNum"
                :reorder-enabled="
                  reorderActive && (isMeta ? listWritable(listFor(gi.item.listId)) : writableHere)
                "
                :reorder-group-key="gi.groupKey"
                :trash-mode="trashMode"
                :archive-mode="archiveMode"
                :row-click-action="rowClickAction"
                :show-added-by="showAddedBy"
                :selection-mode="selectionMode"
                :selected="selectedIds.has(gi.item.id)"
                @toggle="handleToggle"
                @toggle-select="toggleSelect"
                @view="openView"
                @view-store="openStoreView"
                @edit="startEdit"
                @move="startMoveItem"
                @copy="startCopyItem"
                @remove="handleRemove"
                @restore="handleRestore"
                @archive="handleArchive"
                @unarchive="handleUnarchive"
                @preview="openPreview"
                @drag-start="onItemDragStart"
                @reorder-over="onReorderOver"
              />
            </template>
          </ul>
        </template>
      </template>
    </div>

    <ChecklistItemEditDialog
      v-if="editing"
      :open="!!editing"
      :item="editing"
      :house-id="houseIdNum"
      :saving="savingEdit"
      :default-currency="defaultCurrency"
      @update:open="(v) => !v && (editing = null)"
      @save="handleSaveEdit"
    />

    <ChecklistItemViewDialog
      v-if="viewing"
      :open="!!viewing"
      :item="viewing"
      :category="categoryFor(viewing.categoryId)"
      :stores="storesFor(viewing.storeIds)"
      :labels="labelsFor(viewing.labelIds)"
      :house-id="houseIdNum"
      :show-added-by="showAddedBy"
      @update:open="(v) => !v && (viewing = null)"
      @edit="viewToEdit"
      @preview="openPreview"
      @toggle-task="handleToggleTask"
    />

    <ChecklistImagePreview
      v-if="previewing"
      :open="!!previewing"
      :item="previewing"
      :house-id="houseIdNum"
      @update:open="(v) => !v && (previewing = null)"
    />

    <ChecklistFormDialog
      v-if="list"
      :open="editingList"
      :list="list"
      @update:open="(v) => (editingList = v)"
      @save="submitEditList"
    />

    <CategoryManagerDialog
      :open="showCategoryManager"
      :house-id="houseIdNum"
      @update:open="showCategoryManager = $event"
      @sort-changed="onCategorySortChanged"
      @items-affected="load"
    />

    <LabelManagerDialog
      :open="showLabelManager"
      :house-id="houseIdNum"
      @update:open="showLabelManager = $event"
      @items-affected="load"
    />

    <StoreViewDialog
      v-if="viewingStore"
      :open="!!viewingStore"
      :store="viewingStore"
      @update:open="(v) => !v && (viewingStore = null)"
      @edit="editStoreFromView"
    />

    <StoreManagerDialog
      :open="showStoreManager"
      :house-id="houseIdNum"
      :edit-store="storeManagerEditStore"
      @update:open="onStoreManagerToggle"
    />

    <CustomFieldManagerDialog
      :open="showCustomFieldManager"
      :house-id="houseIdNum"
      @update:open="showCustomFieldManager = $event"
    />

    <!-- Move item(s) to another list -->
    <NcDialog
      v-if="moveDialogOpen"
      :name="strings.moveToList"
      :open="moveDialogOpen"
      close-on-click-outside
      @update:open="(v) => !v && closeMoveDialog()"
    >
      <div class="pantry-move-list">
        <NcButton v-for="cl in otherLists" :key="cl.id" wide @click="submitMoveItem(cl.id)">
          <template #icon>
            <component :is="checklistIconComponent(cl.icon)" :size="20" />
          </template>
          {{ cl.name }}
        </NcButton>
        <NcButton wide @click="createListForMove">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.newList }}
        </NcButton>
      </div>
    </NcDialog>

    <ChecklistFormDialog
      :open="showCreateForMove"
      @update:open="showCreateForMove = $event"
      @save="submitCreateListAndMove"
    />

    <!-- Copy item(s) to another list -->
    <NcDialog
      v-if="copyDialogOpen"
      :name="strings.copyToList"
      :open="copyDialogOpen"
      close-on-click-outside
      @update:open="(v) => !v && closeCopyDialog()"
    >
      <div class="pantry-move-list">
        <NcButton v-for="cl in copyTargetLists" :key="cl.id" wide @click="submitCopyItem(cl.id)">
          <template #icon>
            <component :is="checklistIconComponent(cl.icon)" :size="20" />
          </template>
          {{ cl.name }}
        </NcButton>
        <NcButton wide @click="createListForCopy">
          <template #icon>
            <PlusIcon :size="20" />
          </template>
          {{ strings.newList }}
        </NcButton>
      </div>
    </NcDialog>

    <ChecklistFormDialog
      :open="showCreateForCopy"
      @update:open="showCreateForCopy = $event"
      @save="submitCreateListAndCopy"
    />

    <!-- Assign category to selected items -->
    <NcDialog
      v-if="showBulkCategory"
      :name="strings.assignCategoryTitle"
      :open="showBulkCategory"
      close-on-click-outside
      @update:open="(v) => !v && (showBulkCategory = false)"
    >
      <div class="pantry-bulk-category">
        <CategoryPicker
          v-model="bulkCategoryId"
          :house-id="houseIdNum"
          :list-id="isMeta ? null : listIdNum"
        />
      </div>
      <template #actions>
        <NcButton @click="showBulkCategory = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="tertiary" @click="applyBulkCategory(null)">
          {{ strings.removeCategory }}
        </NcButton>
        <NcButton
          variant="primary"
          :disabled="bulkCategoryId == null"
          @click="applyBulkCategory(bulkCategoryId)"
        >
          {{ strings.apply }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- Assign stores to selected items -->
    <NcDialog
      v-if="showBulkStores"
      :name="strings.assignStoresTitle"
      :open="showBulkStores"
      close-on-click-outside
      @update:open="(v) => !v && (showBulkStores = false)"
    >
      <div class="pantry-bulk-category">
        <StoreMultiPicker v-model="bulkStoreIds" :house-id="houseIdNum" />
      </div>
      <template #actions>
        <NcButton @click="showBulkStores = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="tertiary" @click="applyBulkStores([])">
          {{ strings.removeStores }}
        </NcButton>
        <NcButton variant="primary" @click="applyBulkStores(bulkStoreIds)">
          {{ strings.apply }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- Assign labels to selected items -->
    <NcDialog
      v-if="showBulkLabels"
      :name="strings.assignLabelsTitle"
      :open="showBulkLabels"
      close-on-click-outside
      @update:open="(v) => !v && (showBulkLabels = false)"
    >
      <div class="pantry-bulk-category">
        <LabelChipList
          v-model="bulkLabelIds"
          :house-id="houseIdNum"
          :list-id="isMeta ? null : listIdNum"
        />
      </div>
      <template #actions>
        <NcButton @click="showBulkLabels = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="tertiary" @click="applyBulkLabels([])">
          {{ strings.removeLabels }}
        </NcButton>
        <NcButton variant="primary" @click="applyBulkLabels(bulkLabelIds)">
          {{ strings.apply }}
        </NcButton>
      </template>
    </NcDialog>

    <!-- Permanently delete selected items (trash view) -->
    <NcDialog
      v-if="confirmingBulkDelete"
      :name="strings.bulkDeleteTitle"
      :open="confirmingBulkDelete"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingBulkDelete = false)"
    >
      <p>{{ strings.bulkDeleteConfirm }}</p>
      <template #actions>
        <NcButton @click="confirmingBulkDelete = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitBulkDeletePermanent">
          {{ strings.deleteSelected }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="confirmingEmptyTrash"
      :name="strings.emptyTrashTitle"
      :open="confirmingEmptyTrash"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingEmptyTrash = false)"
    >
      <p>{{ strings.emptyTrashConfirm }}</p>
      <template #actions>
        <NcButton @click="confirmingEmptyTrash = false">{{ strings.cancel }}</NcButton>
        <NcButton variant="error" @click="submitEmptyTrash">
          {{ strings.emptyTrashAction }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="pendingReseed"
      :name="strings.reseedConfirmTitle"
      :open="!!pendingReseed"
      close-on-click-outside
      @update:open="(v) => !v && (pendingReseed = null)"
    >
      <p>{{ strings.reseedConfirmBody }}</p>
      <template #actions>
        <NcButton :disabled="reseeding" @click="pendingReseed = null">
          {{ strings.cancel }}
        </NcButton>
        <NcButton variant="primary" :disabled="reseeding" @click="runReseed(pendingReseed)">
          {{ strings.reseedConfirmAction }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="confirmingUncheckAll"
      :name="strings.uncheckAllTitle"
      :open="confirmingUncheckAll"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingUncheckAll = false)"
    >
      <p>{{ strings.uncheckAllConfirm }}</p>
      <template #actions>
        <NcButton :disabled="unchecking" @click="confirmingUncheckAll = false">
          {{ strings.cancel }}
        </NcButton>
        <NcButton variant="primary" :disabled="unchecking" @click="runUncheckAll">
          {{ strings.uncheckAll }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="confirmingRemoveAll"
      :name="strings.removeAllTitle"
      :open="confirmingRemoveAll"
      close-on-click-outside
      @update:open="(v) => !v && (confirmingRemoveAll = false)"
    >
      <p>{{ strings.removeAllConfirm }}</p>
      <template #actions>
        <NcButton :disabled="removingAll" @click="confirmingRemoveAll = false">
          {{ strings.cancel }}
        </NcButton>
        <NcButton variant="error" :disabled="removingAll" @click="runRemoveAll">
          {{ strings.removeAll }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="currentReuse"
      :name="strings.reuseTitle"
      :open="!!currentReuse"
      close-on-click-outside
      @update:open="(v) => !v && resolveCurrentReuse('cancel')"
    >
      <p>{{ reusePrompt }}</p>
      <template #actions>
        <NcButton @click="resolveCurrentReuse('cancel')">{{ strings.cancel }}</NcButton>
        <NcButton @click="resolveCurrentReuse('add')">{{ strings.reuseAddAnyway }}</NcButton>
        <NcButton variant="primary" @click="resolveCurrentReuse('reuse')">
          {{ strings.reuseAction }}
        </NcButton>
      </template>
    </NcDialog>

    <NcDialog
      v-if="reuseConfirm"
      :name="strings.reuseTitle"
      :open="!!reuseConfirm"
      close-on-click-outside
      @update:open="(v) => !v && resolveReuseConfirm(false)"
    >
      <p>{{ reuseConfirmPrompt }}</p>
      <template #actions>
        <NcButton @click="resolveReuseConfirm(false)">{{ strings.cancel }}</NcButton>
        <NcButton variant="primary" @click="resolveReuseConfirm(true)">
          {{ strings.reuseAction }}
        </NcButton>
      </template>
    </NcDialog>

    <MarkdownExportDialog
      v-model:open="showExport"
      :list-name="isMeta ? strings.allListsTitle : (list?.name ?? '')"
      :items="items"
      :category-for="categoryFor"
    />

    <MarkdownImportDialog
      v-model:open="showImport"
      :house-id="houseIdNum"
      :importing="importing"
      :reuse-pref="reuseExistingItems"
      :require-list-selector="isMeta"
      :current-list-id="isMeta ? null : listIdNum"
      :lists="isMeta ? allLists : []"
      @import="handleImportItems"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { t, n } from '@nextcloud/l10n'
import { showUndo, showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import ChevronDownIcon from '@icons/ChevronDown.vue'
import ArrowRightIcon from '@icons/ArrowRight.vue'
import CartIcon from '@icons/Cart.vue'
import ContentCopyIcon from '@icons/ContentCopy.vue'
import DeleteIcon from '@icons/Delete.vue'
import PlusIcon from '@icons/Plus.vue'
import SortIcon from '@icons/Sort.vue'
import SortReverseVariantIcon from '@icons/SortReverseVariant.vue'
import CheckboxMultipleBlankOutlineIcon from '@icons/CheckboxMultipleBlankOutline.vue'
import SelectMultipleIcon from '@icons/SelectMultiple.vue'
import CloseIcon from '@icons/Close.vue'
import FormatListBulletedTypeIcon from '@icons/FormatListBulletedType.vue'
import TrashCanIcon from '@icons/TrashCan.vue'
import ArchiveOutlineIcon from '@icons/ArchiveOutline.vue'
import ArchiveArrowDownOutlineIcon from '@icons/ArchiveArrowDownOutline.vue'
import ArchiveArrowUpOutlineIcon from '@icons/ArchiveArrowUpOutline.vue'
import ViewListIcon from '@icons/ViewList.vue'
import PencilIcon from '@icons/Pencil.vue'
import FileExportIcon from '@icons/FileExport.vue'
import FileImportIcon from '@icons/FileImport.vue'
import PageToolbar, { type ToolbarAction } from '@/components/PageToolbar'
import { ChecklistAddForm } from '@/components/ChecklistAddForm'
import {
  ChecklistFilter,
  NO_CATEGORY_ID,
  NO_STORE_ID,
  NO_LABEL_ID,
  type PriceFilterValue,
} from '@/components/ChecklistFilter'
import { ChecklistItemRow } from '@/components/ChecklistItemRow'
import { ChecklistItemEditDialog } from '@/components/ChecklistItemEditDialog'
import { ChecklistItemViewDialog } from '@/components/ChecklistItemViewDialog'
import { ChecklistImagePreview } from '@/components/ChecklistImagePreview'
import { CategoryManagerDialog } from '@/components/CategoryManager'
import { CustomFieldManagerDialog } from '@/components/CustomFieldManager'
import { LabelManagerDialog } from '@/components/LabelManager'
import { StoreManagerDialog, StoreViewDialog } from '@/components/StoreManager'
import { MarkdownExportDialog } from '@/components/MarkdownExportDialog'
import { MarkdownImportDialog } from '@/components/MarkdownImportDialog'
import CategoryPicker, { categoryIconComponent } from '@/components/CategoryPicker'
import StoreMultiPicker from '@/components/StoreMultiPicker'
import LabelChipList from '@/components/LabelChipList'
import { storeIconComponent } from '@/components/StoreMultiPicker/storeIcons'
import {
  checklistIconComponent,
  ChecklistFormDialog,
  contrastColor,
} from '@/components/ChecklistIconPicker'
import { entityIcon } from '@/utils/entityIcons'

const CategoryEntityIcon = entityIcon.category
const LabelEntityIcon = entityIcon.label
const StoreEntityIcon = entityIcon.store

function iconWrapStyle(color: string | null) {
  if (!color) return undefined
  return { background: color, color: contrastColor(color) }
}
import { useChecklists, useChecklistItems, ALL_LISTS_ID } from '@/composables/useChecklist'
import { useCategories } from '@/composables/useCategories'
import { useLabels } from '@/composables/useLabels'
import { useStores } from '@/composables/useStores'
import { useTouchReorder } from '@/composables/useTouchReorder'
import { useLongPress } from '@/composables/useLongPress'
import { getList, updateList as apiUpdateList } from '@/api/lists'
import type { ItemInput } from '@/api/lists'
import type { Checklist, ChecklistItem, Category, Store, Label, ItemPrice } from '@/api/types'
import type { ChecklistItemSort, ReuseExistingItems } from '@/api/prefs'
import {
  getChecklistItemSort,
  setChecklistItemSort,
  getLastCurrency,
  setLastCurrency,
} from '@/api/prefs'
import { hasPrice } from '@/utils/price'
import { reorderToTrueOrder } from '@/utils/reorderItems'
import { orderItemsByCategory } from '@/utils/categoryOrder'
import { storeGroupKey, byStoreGroupOrder, itemsInStoreGroup } from '@/utils/storeGroupOrder'
import { reseedOrder, type ReseedBasis } from '@/utils/reseedOrder'
import { DEFAULT_CURRENCY } from '@/utils/currencies'
import { useRowClickAction } from '@/composables/useRowClickAction'
import { useShowAddedBy } from '@/composables/useShowAddedBy'
import { useReuseExistingItems } from '@/composables/useReuseExistingItems'
import { useCurrentHouse } from '@/composables/useCurrentHouse'

const props = defineProps<{ houseId: string; listId: string }>()
const router = useRouter()
const route = useRoute()

function startShopping() {
  void router.push({
    name: 'shopping-start',
    params: { houseId: String(houseIdNum.value) },
    // Preselect this list on the start screen; the "All lists" view preselects all.
    query: { lists: isMeta.value ? 'all' : String(listIdNum.value) },
  })
}

const houseIdNum = computed(() => Number(props.houseId))
const isMeta = computed(() => props.listId === 'all')
const listIdNum = computed(() => (isMeta.value ? ALL_LISTS_ID : Number(props.listId)))

const list = ref<Checklist | null>(null)
const {
  items,
  loading,
  load,
  add,
  update,
  copy,
  toggle,
  undoToggle,
  reorderItems,
  remove,
  undoRemove,
  removePermanently,
  restore,
  emptyTrash,
  archive,
  unarchive,
  undoArchive,
  uploadImage,
  clearImage,
  moveMany,
  copyMany,
  removeMany,
  removeManyPermanent,
  archiveMany,
  unarchiveMany,
  undoArchiveMany,
  setCategoryMany,
  setStoresMany,
  setLabelsMany,
  uncheckMany,
  undoRemoveMany,
  sortBy,
  viewMode,
  trashMode,
  archiveMode,
} = useChecklistItems(houseIdNum.value, listIdNum.value)

async function toggleTrash() {
  exitSelection()
  viewMode.value = trashMode.value ? 'active' : 'trash'
  await load()
}

async function toggleArchive() {
  exitSelection()
  viewMode.value = archiveMode.value ? 'active' : 'archive'
  await load()
}

const emptyStateTitle = computed(() => {
  if (trashMode.value) return strings.trashEmptyTitle
  if (archiveMode.value) return strings.archiveEmptyTitle
  return strings.emptyTitle
})
const emptyStateBody = computed(() => {
  if (trashMode.value) return strings.trashEmptyBody
  if (archiveMode.value) return strings.archiveEmptyBody
  return strings.emptyBody
})

const confirmingEmptyTrash = ref(false)

async function submitEmptyTrash() {
  confirmingEmptyTrash.value = false
  await emptyTrash()
}
const categories = useCategories(houseIdNum.value)

function categoryFor(id: number | null) {
  return categories.findById(id) ?? null
}

const stores = useStores(houseIdNum.value)

function storesFor(ids: number[] | null | undefined): Store[] {
  if (!ids || ids.length === 0) return []
  return ids.map((id) => stores.findById(id)).filter((s): s is Store => s != null)
}

const labels = useLabels(houseIdNum.value)

function labelsFor(ids: number[] | null | undefined): Label[] {
  if (!ids || ids.length === 0) return []
  return ids.map((id) => labels.findById(id)).filter((l): l is Label => l != null)
}

function listFor(id: number) {
  return allLists.value.find((l) => l.id === id) ?? null
}

// A list reached only through a view-only share blocks every write affordance;
// role-based access (and editor shares) keep the granular capabilities.
function listWritable(l?: Checklist | null): boolean {
  return !(l?.sharedOnly && !l?.canEdit)
}

// Writability of the list currently in focus (single-list mode).
const writableHere = computed(() => listWritable(list.value))

// ----- Markdown import / export -----

const showExport = ref(false)
const showImport = ref(false)
const importing = ref(false)

async function handleImportItems(
  inputs: ItemInput[],
  forceReuse: boolean,
  targetListId: number | null,
) {
  // Close the import dialog first so the reuse-existing-items prompts (when the
  // pref is set to "ask") render cleanly over the list instead of stacking on
  // top of this dialog.
  showImport.value = false
  importing.value = true
  // When the user ticked "Reuse existing items" in the dialog, force reuse for
  // this import regardless of the global pref ("ask"/"never").
  const modeOverride = forceReuse ? 'reuse' : undefined
  try {
    // Route each item through the normal add path so imports honor the
    // reuse-existing-items pref: reuse silently, prompt per duplicate, or add.
    // Sequential awaiting also resolves any "ask" prompts one at a time and
    // dedupes names that repeat within the imported batch itself.
    // In the meta view the dialog supplies a target list; in a single-list view
    // it stays null and handleAdd falls back to the currently open list.
    for (const input of inputs) {
      await handleAdd(input, null, targetListId, modeOverride)
    }
    showSuccess(n('pantry', 'Imported %n item', 'Imported %n items', inputs.length))
  } catch (e) {
    showError((e as Error).message)
  } finally {
    importing.value = false
  }
}

// ----- Sort -----

const currentSort = ref<ChecklistItemSort>('custom')

const allItemSortOptions: { value: ChecklistItemSort; label: string }[] = [
  { value: 'newest', label: t('pantry', 'Newest first') },
  { value: 'oldest', label: t('pantry', 'Oldest first') },
  { value: 'name_asc', label: t('pantry', 'Name A\u2013Z') },
  { value: 'name_desc', label: t('pantry', 'Name Z\u2013A') },
  { value: 'category', label: t('pantry', 'Category') },
  // TRANSLATORS: Noun, a shop where items are bought. Sort/group option.
  { value: 'store', label: t('pantry', 'Store') },
  { value: 'custom', label: t('pantry', 'Custom') },
]

// Custom sort is per-list, so it's hidden in the meta "All lists" view.
const itemSortOptions = computed(() =>
  isMeta.value ? allItemSortOptions.filter((o) => o.value !== 'custom') : allItemSortOptions,
)

const sortMenuName = computed(() => {
  const label = allItemSortOptions.find((o) => o.value === currentSort.value)?.label ?? ''
  return t('pantry', 'Sort by: {value}', { value: label })
})

async function loadSortPref() {
  const prefs = await getChecklistItemSort(houseIdNum.value)
  // Custom sort is per-list — meta view falls back to "newest" instead.
  const sort: ChecklistItemSort = isMeta.value && prefs.sort === 'custom' ? 'newest' : prefs.sort
  currentSort.value = sort
  sortBy.value = sort
}

async function changeSort(value: ChecklistItemSort) {
  currentSort.value = value
  sortBy.value = value
  await setChecklistItemSort(houseIdNum.value, value)
  await load(value)
}

// ----- Reset custom order (reseed sort_order from a basis) -----

// The action is per-list (sort_order is per-list), so it's hidden in meta and
// only offered on the active list (not trash/archive).
const canResetOrder = computed(() => !isMeta.value && viewMode.value === 'active')
// The basis the user picked, awaiting confirmation of the destructive overwrite.
const pendingReseed = ref<ReseedBasis | null>(null)
const reseeding = ref(false)

async function runReseed(basis: ReseedBasis | null) {
  pendingReseed.value = null
  if (basis === null) return
  const all = items.value
  if (all.length === 0) return
  // In category view the reseed is grouped by category so grouping is preserved;
  // otherwise it is a flat basis order (still leaves each store group ordered).
  const categoryOrder = isCategorySort.value ? categories.items.value.map((c) => c.id) : undefined
  const entries = reseedOrder(all, basis, categoryOrder)
  reseeding.value = true
  try {
    await reorderItems(entries)
    showSuccess(strings.reseedDone)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    reseeding.value = false
  }
}

// ----- Uncheck all -----

// Per-list (sort_order/done are per-list); hidden in meta and for view-only
// lists or accounts without the check-items permission.
const canUncheckAll = computed(
  () =>
    !isMeta.value && viewMode.value === 'active' && writableHere.value && can.value.canCheckItems,
)
const confirmingUncheckAll = ref(false)
const unchecking = ref(false)

async function runUncheckAll() {
  confirmingUncheckAll.value = false
  // All done items in the list (unfiltered) — "uncheck all in the list", not
  // just what a search/category filter currently shows.
  const ids = items.value.filter((i) => i.done).map((i) => i.id)
  if (ids.length === 0) return
  unchecking.value = true
  try {
    const result = await uncheckMany(ids)
    const cleared = ids.length - result.skipped.length
    showSuccess(n('pantry', 'Unchecked %n item', 'Unchecked %n items', cleared))
  } catch (e) {
    showError((e as Error).message)
  } finally {
    unchecking.value = false
  }
}

// ----- Remove all done -----

const canRemoveAll = computed(
  () =>
    !isMeta.value && viewMode.value === 'active' && writableHere.value && can.value.canDeleteItems,
)
const confirmingRemoveAll = ref(false)
const removingAll = ref(false)

async function runRemoveAll() {
  confirmingRemoveAll.value = false
  // All done items in the list (unfiltered) — "remove all in the list", not just
  // what a search/category filter currently shows.
  const done = items.value.filter((i) => i.done)
  const ids = done.map((i) => i.id)
  if (ids.length === 0) return
  const snapshots = done.map((i) => ({ ...i }))
  removingAll.value = true
  try {
    const result = await removeMany(ids)
    const removed = ids.length - result.skipped.length
    showUndo(
      n('pantry', 'Removed %n item', 'Removed %n items', removed),
      () => {
        void undoRemoveMany(snapshots).catch(() => showError(strings.restoreFailed))
      },
      { timeout: 6000 },
    )
  } catch (e) {
    showError((e as Error).message)
  } finally {
    removingAll.value = false
  }
}

// ----- Loading -----

async function loadList() {
  if (isMeta.value) {
    list.value = null
    return
  }
  list.value = await getList(houseIdNum.value, listIdNum.value)
}

const { rowClickAction } = useRowClickAction()
const { can } = useCurrentHouse()
const showAddedBy = computed(() => useShowAddedBy(houseIdNum.value).showAddedBy.value)
// Sorting by category always groups items under category headers.
const showCategoryHeaders = computed(() => currentSort.value === 'category')
// Sorting by store groups items under store headers; an item in several stores
// is duplicated under each (see withStoreGroups).
const showStoreHeaders = computed(() => currentSort.value === 'store')

onMounted(async () => {
  await loadSortPref()
  // Meta view needs the full list catalog to render per-item chips and the
  // required list picker; non-meta views read it lazily for move/copy dialogs,
  // by which time the sidebar has already populated the shared state.
  const tasks: Promise<unknown>[] = [
    loadList(),
    load(),
    categories.load(),
    stores.load(),
    labels.load(),
  ]
  if (isMeta.value) tasks.push(loadLists())
  await Promise.all(tasks)
  openDeepLinkedItem()
})

watch(
  () => [props.houseId, props.listId],
  async () => {
    exitSelection()
    filterListIds.value = loadListFilter()
    doneCollapsed.value = loadDoneCollapsed()
    await loadSortPref()
    const tasks: Promise<unknown>[] = [loadList(), load()]
    if (isMeta.value) tasks.push(loadLists())
    await Promise.all(tasks)
    openDeepLinkedItem()
  },
)

// A deep link arriving while already on this list changes only the query, so
// neither the mount nor the param watch fires — open it directly.
watch(
  () => route.query.item,
  (item) => {
    if (item != null) openDeepLinkedItem()
  },
)

// ----- Silent polling while window is focused -----
//
// Refresh items every 30 s so multi-device edits show up without the user
// reloading. Pause when the window loses focus; on refocus, fire an immediate
// refresh and resume the interval.

const POLL_INTERVAL_MS = 30_000
let pollTimer: number | null = null

function silentRefresh() {
  void load(undefined, { silent: true })
}

function startPolling() {
  stopPolling()
  pollTimer = window.setInterval(silentRefresh, POLL_INTERVAL_MS)
}

function stopPolling() {
  if (pollTimer !== null) {
    window.clearInterval(pollTimer)
    pollTimer = null
  }
}

function onWindowFocus() {
  silentRefresh()
  startPolling()
}

function onWindowBlur() {
  stopPolling()
}

onMounted(() => {
  window.addEventListener('focus', onWindowFocus)
  window.addEventListener('blur', onWindowBlur)
  if (document.hasFocus()) startPolling()
})

onBeforeUnmount(() => {
  window.removeEventListener('focus', onWindowFocus)
  window.removeEventListener('blur', onWindowBlur)
  stopPolling()
})

// ----- Filter -----

const filterQuery = ref('')
const filterCategoryIds = ref<number[]>([])
const filterStoreIds = ref<number[]>([])
const filterLabelIds = ref<number[]>([])
const filterPrice = ref<PriceFilterValue>({ min: null, max: null, currency: null })

// ----- Last-used currency (per house) -----
// Preselected for new/edited prices, and updated whenever an item is saved with
// a currency so the next one defaults to it.
const defaultCurrency = ref(DEFAULT_CURRENCY)

async function loadDefaultCurrency() {
  try {
    defaultCurrency.value = await getLastCurrency(houseIdNum.value)
  } catch {
    // Keep the fallback currency on failure.
  }
}
void loadDefaultCurrency()
watch(houseIdNum, () => void loadDefaultCurrency())

function rememberCurrency(currency: string | null | undefined) {
  const code = (currency ?? '').toUpperCase()
  if (!code || code === defaultCurrency.value) return
  defaultCurrency.value = code
  void setLastCurrency(houseIdNum.value, code).catch(() => {})
}

// Remember the currency from a saved price set, preferring the store-less price.
function rememberCurrencyFrom(prices: ItemPrice[] | undefined) {
  if (!prices?.length) return
  const chosen = prices.find((p) => p.storeId == null) ?? prices[0]
  if (chosen.priceType === 'set' || chosen.priceType === 'range') {
    rememberCurrency(chosen.priceCurrency)
  }
}

// List filter is only meaningful in the meta "All lists" view. Its selection is
// persisted per house in localStorage so it survives navigation and reloads.
const listFilterStorageKey = computed(() => `pantry:list-filter:${props.houseId}`)
const filterListIds = ref<number[]>(loadListFilter())

function loadListFilter(): number[] {
  try {
    const raw = window.localStorage.getItem(`pantry:list-filter:${props.houseId}`)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed.filter((v): v is number => typeof v === 'number') : []
  } catch {
    return []
  }
}

watch(
  filterListIds,
  (ids) => {
    try {
      window.localStorage.setItem(listFilterStorageKey.value, JSON.stringify(ids))
    } catch {
      // Ignore storage failures (e.g. private mode quota).
    }
  },
  { deep: true },
)

// Collapse state for the "Done" section, persisted per house+list so it
// survives navigation and reloads.
const doneCollapsedStorageKey = computed(
  () => `pantry:done-collapsed:${props.houseId}:${props.listId}`,
)
const doneCollapsed = ref<boolean>(loadDoneCollapsed())

function loadDoneCollapsed(): boolean {
  try {
    return window.localStorage.getItem(doneCollapsedStorageKey.value) === 'true'
  } catch {
    return false
  }
}

watch(doneCollapsed, (collapsed) => {
  try {
    window.localStorage.setItem(doneCollapsedStorageKey.value, String(collapsed))
  } catch {
    // Ignore storage failures (e.g. private mode quota).
  }
})

const filteredItems = computed(() => {
  let result = items.value
  if (isMeta.value && filterListIds.value.length > 0) {
    const listIds = filterListIds.value
    result = result.filter((i) => listIds.includes(i.listId))
  }
  const catIds = filterCategoryIds.value
  if (catIds.length > 0) {
    const wantUncategorized = catIds.includes(NO_CATEGORY_ID)
    result = result.filter((i) =>
      i.categoryId != null ? catIds.includes(i.categoryId) : wantUncategorized,
    )
  }
  const storeIds = filterStoreIds.value
  if (storeIds.length > 0) {
    const wantStoreless = storeIds.includes(NO_STORE_ID)
    result = result.filter((i) => {
      const attached = i.storeIds ?? []
      if (attached.length === 0) return wantStoreless
      return attached.some((id) => storeIds.includes(id))
    })
  }
  const labelIds = filterLabelIds.value
  if (labelIds.length > 0) {
    // Match ANY selected label (OR): an item passes if it carries at least one
    // of the chosen labels, or is unlabeled when "No label" is selected.
    const wantUnlabeled = labelIds.includes(NO_LABEL_ID)
    result = result.filter((i) => {
      const attached = i.labelIds ?? []
      if (attached.length === 0) return wantUnlabeled
      return attached.some((id) => labelIds.includes(id))
    })
  }
  const price = filterPrice.value
  if (price.min != null || price.max != null || price.currency != null) {
    result = result.filter((i) => matchesPriceFilter(i, price))
  }
  const q = filterQuery.value.trim().toLowerCase()
  if (q) {
    result = result.filter(
      (i) =>
        i.name.toLowerCase().includes(q) ||
        (i.description && i.description.toLowerCase().includes(q)),
    )
  }
  return result
})

// A single price passes when it overlaps [min, max]. A "set" price is treated as
// a zero-width range. When a currency is chosen, only prices in that currency
// match; otherwise amounts compare verbatim across currencies (no conversion).
function priceEntryMatches(p: ItemPrice, price: PriceFilterValue): boolean {
  if (!hasPrice(p)) return false
  if (price.currency != null && (p.priceCurrency ?? '').toUpperCase() !== price.currency) {
    return false
  }
  const lo = p.priceMin!
  const hi = p.priceType === 'range' && p.priceMax != null ? p.priceMax : lo
  if (price.min != null && hi < price.min) return false
  if (price.max != null && lo > price.max) return false
  return true
}

// An item passes when any of its prices (store-less or per-store) overlaps the
// filter, so an item priced only for a store is not hidden. Items with no price
// never match an active filter.
function matchesPriceFilter(item: ChecklistItem, price: PriceFilterValue): boolean {
  return item.prices.some((p) => priceEntryMatches(p, price))
}

// ----- Partitioned items -----

function sortWithinPartition(arr: ChecklistItem[]): ChecklistItem[] {
  if (currentSort.value === 'custom') {
    return [...arr].sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
  }
  if (currentSort.value === 'category') {
    // Meta "All lists" keeps the backend's name-within-category order: sortOrder
    // is per-list, so there is no cross-list custom order to honour.
    if (isMeta.value) return arr
    // Category grouping is derived client-side (not left to backend order) so a
    // within-category drag renders correctly before any reload. Header order
    // follows the categorySort pref, already baked into categories.items.
    return orderItemsByCategory(
      arr,
      categories.items.value.map((c) => c.id),
    )
  }
  return arr
}

const isCustomSort = computed(() => currentSort.value === 'custom')
const isCategorySort = computed(() => currentSort.value === 'category')
const isStoreSort = computed(() => currentSort.value === 'store')
const uncheckedItems = computed(() =>
  sortWithinPartition(filteredItems.value.filter((i) => !i.done)),
)
const checkedItems = computed(() => sortWithinPartition(filteredItems.value.filter((i) => i.done)))

// ----- Multi-select state -----
// Declared here (ahead of the drag/drop + long-press wiring that reads them) so
// reorder can stand down while a selection is active. The rest of the
// multi-select logic lives further down.
const selectionMode = ref(false)
const selectedIds = ref<Set<number>>(new Set())
// Drag-to-reorder is available in custom sort (flat), category sort (constrained
// to each category block) and store sort (constrained to each store group);
// never while selecting or in the meta "All lists" view (sortOrder is per-list).
const reorderActive = computed(
  () =>
    !isMeta.value &&
    (isCustomSort.value || isCategorySort.value || isStoreSort.value) &&
    !selectionMode.value,
)

// ----- Drag/drop reorder (custom + category sort, per partition) -----

type ListGridItem =
  // `groupKey` marks which reorder group a rendered row belongs to when the same
  // item can appear in several (store sort duplicates a multi-store item under
  // each store). Undefined in flat/custom and category sort.
  // `priceStoreId` is the store the row's price chip resolves against under store
  // grouping (null for the "No store" group); undefined outside store sort.
  | {
      type: 'item'
      key: string
      item: ChecklistItem
      groupKey?: string
      priceStoreId?: number | null
    }
  | { type: 'placeholder'; key: string }
  | { type: 'header'; key: string; category: Category | null }
  | { type: 'store-header'; key: string; store: Store | null }

type Partition = 'unchecked' | 'checked'

const draggingItemId = ref<number | null>(null)
const draggingPartition = ref<Partition | null>(null)
// Category of the dragged item — a category-sort drag is constrained to its own
// category block. Null in custom sort (no constraint) and for uncategorized.
const draggingCategoryId = ref<number | null>(null)
// Store group key of the grabbed row — a store-sort drag is constrained to the
// column it started in (a multi-store item is grabbed from one specific group).
// Undefined outside store sort.
const draggingStoreKey = ref<string | undefined>(undefined)
const dropIndex = ref<number | null>(null)
const uncheckedListRef = ref<HTMLElement | null>(null)
const checkedListRef = ref<HTMLElement | null>(null)

function partitionItems(p: Partition): ChecklistItem[] {
  return p === 'unchecked' ? uncheckedItems.value : checkedItems.value
}

// The items the current drag may reorder among, in the same order they render
// in the group. In custom sort that's the whole partition; in category sort the
// dragged item's own category block; in store sort the dragged item's own store
// column. dropIndex is relative to this scope, so a drag can't cross a boundary.
function dragScope(p: Partition): ChecklistItem[] {
  const source = partitionItems(p)
  if (isCategorySort.value) {
    const cat = draggingCategoryId.value ?? null
    return source.filter((i) => (i.categoryId ?? null) === cat)
  }
  if (isStoreSort.value) {
    return storeGroupItems(source, draggingStoreKey.value)
  }
  return source
}

// Items rendered under a given store group key, ordered exactly as the group
// renders so dropIndex lines up with the visible rows.
function storeGroupItems(items: ChecklistItem[], key: string | undefined): ChecklistItem[] {
  const storeIdSet = new Set(stores.items.value.map((s) => s.id))
  return itemsInStoreGroup(items, key ?? storeGroupKey(null), storeIdSet)
}

function resetDragState() {
  draggingItemId.value = null
  draggingPartition.value = null
  draggingCategoryId.value = null
  draggingStoreKey.value = undefined
  dropIndex.value = null
}

function withCategoryHeaders(items: ListGridItem[]): ListGridItem[] {
  if (!showCategoryHeaders.value) return items
  const out: ListGridItem[] = []
  let prevCategoryId: number | null | undefined = undefined
  for (const gi of items) {
    if (gi.type !== 'item') {
      out.push(gi)
      continue
    }
    const catId = gi.item.categoryId ?? null
    if (prevCategoryId === undefined || prevCategoryId !== catId) {
      out.push({ type: 'header', key: `hdr-${gi.key}`, category: categoryFor(catId) })
    }
    prevCategoryId = catId
    out.push(gi)
  }
  return out
}

// Groups a partition's items under store headers, following the store order in
// `stores.items`. An item attached to several stores appears once under each;
// items with no (known) store fall under a trailing "No store" header. Every
// rendered copy shares the underlying item id, so toggling one toggles all.
function withStoreGroups(items: ChecklistItem[], p: Partition): ListGridItem[] {
  const orderedStores = stores.items.value
  const storeIdSet = new Set(orderedStores.map((s) => s.id))
  const buckets = new Map<number, ChecklistItem[]>()
  const noStore: ChecklistItem[] = []
  for (const it of items) {
    const ids = (it.storeIds ?? []).filter((id) => storeIdSet.has(id))
    if (ids.length === 0) {
      noStore.push(it)
      continue
    }
    for (const id of ids) {
      const arr = buckets.get(id)
      if (arr) arr.push(it)
      else buckets.set(id, [it])
    }
  }
  const out: ListGridItem[] = []
  for (const store of orderedStores) {
    const groupItems = buckets.get(store.id)
    if (!groupItems || groupItems.length === 0) continue
    const groupKey = storeGroupKey(store.id)
    out.push({ type: 'store-header', key: `sh-${p}-${store.id}`, store })
    for (const it of [...groupItems].sort(byStoreGroupOrder)) {
      out.push({
        type: 'item',
        key: `i-${p}-${store.id}-${it.id}`,
        item: it,
        groupKey,
        priceStoreId: store.id,
      })
    }
  }
  if (noStore.length > 0) {
    const groupKey = storeGroupKey(null)
    out.push({ type: 'store-header', key: `sh-${p}-none`, store: null })
    for (const it of [...noStore].sort(byStoreGroupOrder)) {
      out.push({
        type: 'item',
        key: `i-${p}-none-${it.id}`,
        item: it,
        groupKey,
        priceStoreId: null,
      })
    }
  }
  return out
}

// Splice a drop placeholder into an already-built (headered) grid at the
// scope-relative dropIndex, hiding nothing extra. `scope` is the drag's group
// (the dragged item still included); `matches` locates a group row by item id.
// Since headers are already in `grid`, a "first in group" drop lands *after*
// the group header, not above it.
function insertDropPlaceholder(
  grid: ListGridItem[],
  scope: ChecklistItem[],
  dragId: number,
  matches: (g: ListGridItem, itemId: number) => boolean,
): ListGridItem[] {
  const scopeWithout = scope.filter((i) => i.id !== dragId)
  const clampedIdx = Math.min(dropIndex.value!, scopeWithout.length)
  const atEnd = clampedIdx >= scopeWithout.length
  const anchorId = atEnd
    ? (scopeWithout[scopeWithout.length - 1]?.id ?? null)
    : scopeWithout[clampedIdx].id
  // Nothing to anchor against (dragged item was alone in its group) — unreachable
  // in practice since a lone group offers no other row to hover.
  if (anchorId === null) return grid
  const placeholder: ListGridItem = { type: 'placeholder', key: 'drop-placeholder' }
  const anchorPos = grid.findIndex((g) => matches(g, anchorId))
  if (anchorPos === -1) grid.push(placeholder)
  else grid.splice(atEnd ? anchorPos + 1 : anchorPos, 0, placeholder)
  return grid
}

function buildGridItems(p: Partition): ListGridItem[] {
  const source = partitionItems(p)
  const dragId = draggingItemId.value
  const dragActive =
    reorderActive.value &&
    dragId !== null &&
    dropIndex.value !== null &&
    draggingPartition.value === p

  // Store grouping renders its own headers and duplicates multi-store items, so
  // the placeholder is matched within the grabbed column (groupKey) only; the
  // dragged row is hidden from every group until the drop resolves.
  if (showStoreHeaders.value) {
    if (!dragActive) return withStoreGroups(source, p)
    const grid = withStoreGroups(
      source.filter((i) => i.id !== dragId),
      p,
    )
    return insertDropPlaceholder(
      grid,
      dragScope(p),
      dragId!,
      (g, itemId) =>
        g.type === 'item' && g.groupKey === draggingStoreKey.value && g.item.id === itemId,
    )
  }

  const toGrid = (i: ChecklistItem): ListGridItem => ({ type: 'item', key: 'i-' + i.id, item: i })
  if (!dragActive) return withCategoryHeaders(source.map(toGrid))
  const grid = withCategoryHeaders(source.filter((i) => i.id !== dragId).map(toGrid))
  return insertDropPlaceholder(
    grid,
    dragScope(p),
    dragId!,
    (g, itemId) => g.type === 'item' && g.item.id === itemId,
  )
}

const uncheckedGridItems = computed<ListGridItem[]>(() => buildGridItems('unchecked'))
const checkedGridItems = computed<ListGridItem[]>(() => buildGridItems('checked'))

function findPartitionOf(itemId: number): Partition | null {
  if (uncheckedItems.value.some((i) => i.id === itemId)) return 'unchecked'
  if (checkedItems.value.some((i) => i.id === itemId)) return 'checked'
  return null
}

function onItemDragStart(itemId: number, groupKey?: string) {
  draggingItemId.value = itemId
  draggingPartition.value = findPartitionOf(itemId)
  draggingCategoryId.value = filteredItems.value.find((i) => i.id === itemId)?.categoryId ?? null
  // In store sort the row carries its column's key; the drag stays in that column.
  draggingStoreKey.value = groupKey
  dropIndex.value = null
}

function computeItemDropIndex(
  hoveredItemId: number,
  clientY: number,
  target: HTMLElement | null,
  hoveredGroupKey?: string,
) {
  const dragId = draggingItemId.value
  if (!dragId || dragId === hoveredItemId) return

  const partition = draggingPartition.value
  if (!partition) return

  // Constrain the drop to the drag scope: same partition, and — in category or
  // store sort — the dragged item's own group. In store sort the hovered row
  // must be in the same column (a multi-store item renders in several); a row
  // outside the scope is ignored (idx === -1), so a drag can't cross a boundary.
  if (isStoreSort.value && hoveredGroupKey !== draggingStoreKey.value) return
  const scope = dragScope(partition)
  const without = scope.filter((i) => i.id !== dragId)
  const idx = without.findIndex((i) => i.id === hoveredItemId)
  if (idx === -1) return

  if (target) {
    const rect = target.getBoundingClientRect()
    const past = clientY > rect.top + rect.height / 2
    dropIndex.value = past ? idx + 1 : idx
  } else {
    dropIndex.value = idx
  }
}

function onReorderOver(hoveredItemId: number, e: MouseEvent, groupKey?: string) {
  computeItemDropIndex(hoveredItemId, e.clientY, e.currentTarget as HTMLElement | null, groupKey)
}

function onPlaceholderDrop() {
  commitReorder()
}

async function commitReorder() {
  const dragId = draggingItemId.value
  const idx = dropIndex.value
  const partition = draggingPartition.value
  // Capture the scope before clearing state (dragScope reads draggingCategoryId).
  const scope = partition ? dragScope(partition) : []
  resetDragState()

  if (dragId === null || idx === null || !partition) return

  // Reconstruct the dragged item's slot in the *full* list order (all items, by
  // true sortOrder) so its done-state never leaks into the stored sort_order.
  // Only the dragged item moves; every other item — checked ones and other
  // categories included — keeps its true position. In category sort the scope
  // is one category block, so the item stays between its category neighbours;
  // the "checked sink to the bottom" and category grouping are render-time only.
  const entries = reorderToTrueOrder(filteredItems.value, scope, dragId, idx)
  if (entries.length === 0) return
  await reorderItems(entries)
}

// Capture-phase listeners — attached to both partition lists.
function onDropCapture() {
  commitReorder()
}
function onDragEndCapture() {
  resetDragState()
}
function bindDragListeners(el: HTMLElement | null) {
  if (!el) return
  el.addEventListener('drop', onDropCapture, true)
  el.addEventListener('dragend', onDragEndCapture, true)
}
function unbindDragListeners(el: HTMLElement | null) {
  if (!el) return
  el.removeEventListener('drop', onDropCapture, true)
  el.removeEventListener('dragend', onDragEndCapture, true)
}
// The partition lists are gated behind `v-if` (loading / empty state), so the
// refs are null at mount and only appear once items render. Rebind on change
// rather than once in onMounted, or desktop drops never reach commitReorder.
watch(uncheckedListRef, (el, prev) => {
  unbindDragListeners(prev ?? null)
  bindDragListeners(el)
})
watch(checkedListRef, (el, prev) => {
  unbindDragListeners(prev ?? null)
  bindDragListeners(el)
})
onBeforeUnmount(() => {
  unbindDragListeners(uncheckedListRef.value)
  unbindDragListeners(checkedListRef.value)
})

// Resolve the hovered row element for its bounding rect. In store sort the same
// item id renders in several columns, so match the column (group) too.
function hoveredRowEl(
  list: HTMLElement | null,
  hoveredId: number,
  groupKey?: string,
): HTMLElement | null {
  if (!list) return null
  const sel =
    groupKey != null
      ? `[data-drag-id="${hoveredId}"][data-drag-group="${groupKey}"]`
      : `[data-drag-id="${hoveredId}"]`
  return list.querySelector<HTMLElement>(sel)
}

// Touch reorder — one composable instance per partition list.
useTouchReorder(
  uncheckedListRef,
  {
    onDragStart: onItemDragStart,
    onReorderOver(hoveredId, _clientX, clientY, groupKey) {
      const el = hoveredRowEl(uncheckedListRef.value, hoveredId, groupKey)
      computeItemDropIndex(hoveredId, clientY, el, groupKey)
    },
    onDrop: commitReorder,
    onCancel: resetDragState,
  },
  reorderActive,
)

useTouchReorder(
  checkedListRef,
  {
    onDragStart: onItemDragStart,
    onReorderOver(hoveredId, _clientX, clientY, groupKey) {
      const el = hoveredRowEl(checkedListRef.value, hoveredId, groupKey)
      computeItemDropIndex(hoveredId, clientY, el, groupKey)
    },
    onDrop: commitReorder,
    onCancel: resetDragState,
  },
  reorderActive,
)

// Long-press enters multi-select on touch — only where reorder isn't already
// claiming the press (i.e. not in custom, category or store sort) and we're not
// already selecting.
const longPressActive = computed(() => !reorderActive.value && !selectionMode.value)
function onRowLongPress(id: number) {
  enterSelection(id)
}
useLongPress(uncheckedListRef, onRowLongPress, longPressActive)
useLongPress(checkedListRef, onRowLongPress, longPressActive)

// ----- Add -----

const adding = ref(false)

const { reuseExistingItems } = useReuseExistingItems()

// ----- Reuse existing items -----
//
// When adding an item whose normalized name already matches one in the same
// list, the user's "reuse existing items" pref decides what happens: reuse
// (uncheck the existing item, add nothing), ask (prompt per duplicate), or
// never (fall through to a normal add). Bulk add fires handleAdd concurrently,
// so "ask" prompts are queued and resolved one at a time.

function normalizeName(name: string): string {
  return name.trim().toLowerCase()
}

function findExistingItem(
  name: string,
  useListId: number | null | undefined,
): ChecklistItem | null {
  if (useListId == null || useListId <= 0) return null
  const norm = normalizeName(name)
  if (!norm) return null
  return items.value.find((i) => i.listId === useListId && normalizeName(i.name) === norm) ?? null
}

async function reuseItem(existing: ChecklistItem) {
  // "Reusing" means surfacing the existing item as active again — only the done
  // ones need an uncheck; an already-active match is left untouched.
  if (existing.done) {
    await toggle(existing.id)
  }
  showSuccess(t('pantry', 'Reused existing item "{name}"', { name: existing.name }))
}

type ReuseDecision = 'reuse' | 'add' | 'cancel'

interface ReuseRequest {
  existing: ChecklistItem
  resolve: (decision: ReuseDecision) => void
}

const reuseQueue = ref<ReuseRequest[]>([])
const currentReuse = computed(() => reuseQueue.value[0] ?? null)
const reusePrompt = computed(() =>
  currentReuse.value
    ? t(
        'pantry',
        'An item named "{name}" already exists in this list. Reuse it instead of adding a new one?',
        { name: currentReuse.value.existing.name },
      )
    : '',
)

function askReuse(existing: ChecklistItem): Promise<ReuseDecision> {
  return new Promise((resolve) => {
    reuseQueue.value = [...reuseQueue.value, { existing, resolve }]
  })
}

function resolveCurrentReuse(decision: ReuseDecision) {
  const req = reuseQueue.value[0]
  if (!req) return
  reuseQueue.value = reuseQueue.value.slice(1)
  req.resolve(decision)
}

// ----- Live reuse suggestions -----
//
// The add form surfaces existing items on the target list that fuzzily match
// what's being typed. Tapping one asks for a plain confirm (Cancel / Reuse
// existing) — no "add anyway", since the user picked a specific item — then
// reuses it (unchecking if done) and clears the input.

const addForm = ref<{ clearName: () => void } | null>(null)

// Only active items, and only when the user can check items. No pref check:
// the panel is a manual discovery affordance, distinct from on-submit dedup.
const reuseCandidates = computed<ChecklistItem[]>(() =>
  can.value.canCheckItems ? items.value.filter((i) => !i.deletedAt) : [],
)

const reuseConfirm = ref<{ item: ChecklistItem; resolve: (ok: boolean) => void } | null>(null)
const reuseConfirmPrompt = computed(() =>
  reuseConfirm.value
    ? t(
        'pantry',
        'An item named "{name}" already exists in this list. Reuse it instead of adding a new one?',
        { name: reuseConfirm.value.item.name },
      )
    : '',
)

function confirmReuseSuggestion(item: ChecklistItem): Promise<boolean> {
  return new Promise((resolve) => {
    reuseConfirm.value = { item, resolve }
  })
}

function resolveReuseConfirm(ok: boolean) {
  const req = reuseConfirm.value
  if (!req) return
  reuseConfirm.value = null
  req.resolve(ok)
}

async function onReuseFromSuggestion(item: ChecklistItem) {
  const ok = await confirmReuseSuggestion(item)
  if (!ok) return
  await reuseItem(item)
  addForm.value?.clearName()
}

async function handleAdd(
  input: ItemInput,
  pendingImage: File | null,
  targetListId: number | null,
  modeOverride?: ReuseExistingItems,
) {
  const mode = modeOverride ?? reuseExistingItems.value
  if (mode !== 'never' && !trashMode.value) {
    const useListId = isMeta.value ? targetListId : listIdNum.value
    const existing = findExistingItem(input.name, useListId)
    if (existing) {
      if (mode === 'reuse') {
        await reuseItem(existing)
        return
      }
      const decision = await askReuse(existing)
      if (decision === 'cancel') return
      if (decision === 'reuse') {
        await reuseItem(existing)
        return
      }
      // 'add' falls through to a normal add below.
    }
  }
  rememberCurrencyFrom(input.prices)
  adding.value = true
  try {
    const created = await add(input, targetListId ?? undefined)
    if (pendingImage) {
      try {
        await uploadImage(created.id, pendingImage)
      } catch (e) {
        showError((e as Error).message)
      }
    }
  } finally {
    adding.value = false
  }
}

const editingList = ref(false)

async function submitEditList(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  if (!list.value) return
  try {
    list.value = await apiUpdateList(houseIdNum.value, listIdNum.value, {
      name: data.name,
      description: data.description,
      icon: data.icon,
      color: data.color || null,
    })
    editingList.value = false
  } catch (e) {
    showError((e as Error).message)
  }
}

async function handleDeleteOnDoneDefaultChange(value: boolean) {
  if (!list.value || list.value.deleteOnDoneDefault === value) return
  const prev = list.value
  list.value = { ...prev, deleteOnDoneDefault: value }
  try {
    const updated = await apiUpdateList(houseIdNum.value, listIdNum.value, {
      deleteOnDoneDefault: value,
    })
    list.value = updated
  } catch (e) {
    list.value = prev
    showError((e as Error).message)
  }
}

// ----- Toggle / Remove -----

async function handleToggle(itemId: number) {
  const prev = items.value.find((i) => i.id === itemId)
  if (!prev) return
  const snapshot = { ...prev }
  await toggle(itemId)
  // Only offer undo when an item is marked done (undone → done).
  if (snapshot.done) return
  showUndo(
    strings.itemMarkedDone,
    () => {
      void undoToggle(snapshot).catch(() => {
        showError(strings.restoreFailed)
      })
    },
    { timeout: 6000 },
  )
}

async function handleRemove(itemId: number) {
  if (trashMode.value || archiveMode.value) {
    await removePermanently(itemId)
    return
  }
  const prev = items.value.find((i) => i.id === itemId)
  if (!prev) return
  const snapshot = { ...prev }
  await remove(itemId)
  showUndo(
    strings.itemRemoved,
    () => {
      void undoRemove(snapshot).catch(() => {
        showError(strings.restoreFailed)
      })
    },
    { timeout: 6000 },
  )
}

async function handleRestore(itemId: number) {
  await restore(itemId)
}

async function handleArchive(itemId: number) {
  const prev = items.value.find((i) => i.id === itemId)
  if (!prev) return
  const snapshot = { ...prev }
  await archive(itemId)
  showUndo(
    strings.itemArchived,
    () => {
      void undoArchive(snapshot).catch(() => {
        showError(strings.unarchiveFailed)
      })
    },
    { timeout: 6000 },
  )
}

async function handleUnarchive(itemId: number) {
  await unarchive(itemId)
}

// ----- Edit -----

const editing = ref<ChecklistItem | null>(null)
const savingEdit = ref(false)

function startEdit(item: ChecklistItem) {
  editing.value = item
}

async function handleSaveEdit(
  itemId: number,
  patch: Partial<ItemInput>,
  pendingImage: File | null,
  shouldClearImage: boolean,
) {
  rememberCurrencyFrom(patch.prices)
  savingEdit.value = true
  try {
    await update(itemId, patch)
    if (pendingImage) {
      await uploadImage(itemId, pendingImage)
    } else if (shouldClearImage) {
      await clearImage(itemId)
    }
    editing.value = null
  } finally {
    savingEdit.value = false
  }
}

// ----- View / Preview -----

const viewing = ref<ChecklistItem | null>(null)
const previewing = ref<ChecklistItem | null>(null)

function openView(item: ChecklistItem) {
  viewing.value = item
}

// A reminder notification deep-links to `?item={id}`; open that item's view
// dialog once its list has loaded, then drop the param so a refresh or back
// navigation doesn't reopen it.
function openDeepLinkedItem() {
  const raw = route.query.item
  const id = Array.isArray(raw) ? raw[0] : raw
  if (id == null) return
  const itemId = Number(id)
  if (!Number.isFinite(itemId)) return
  const item = items.value.find((i) => i.id === itemId)
  if (!item) return
  openView(item)
  const query = { ...route.query }
  delete query.item
  void router.replace({ query })
}

function viewToEdit(item: ChecklistItem) {
  viewing.value = null
  startEdit(item)
}

async function handleToggleTask(item: ChecklistItem, description: string) {
  await update(item.id, { description })
  // Re-point the open dialog at the refreshed item so the checkbox reflects
  // the persisted state.
  viewing.value = items.value.find((i) => i.id === item.id) ?? viewing.value
}

function openPreview(item: ChecklistItem) {
  previewing.value = item
}

// ----- Category manager -----

const showCategoryManager = ref(false)
const showLabelManager = ref(false)
const showStoreManager = ref(false)
const showCustomFieldManager = ref(false)

// ----- Store details (opened from a store chip on an item) -----

const viewingStore = ref<Store | null>(null)
const storeManagerEditStore = ref<Store | null>(null)

function openStoreView(store: Store) {
  viewingStore.value = store
}

function editStoreFromView(store: Store) {
  viewingStore.value = null
  storeManagerEditStore.value = store
  showStoreManager.value = true
}

function onStoreManagerToggle(open: boolean) {
  showStoreManager.value = open
  if (!open) {
    storeManagerEditStore.value = null
  }
}

async function onCategorySortChanged() {
  // The list endpoint groups items by category, so changing category order
  // requires a re-fetch to reflect the new grouping.
  if (currentSort.value === 'category') {
    await load()
  }
}

// ----- Move item to another list -----

const { lists: allLists, create: createList, load: loadLists } = useChecklists(houseIdNum.value)

// Drop any persisted list-filter ids that no longer correspond to an existing
// list (e.g. a list was deleted) once the catalog has loaded.
watch(allLists, (lists) => {
  if (!isMeta.value || lists.length === 0 || filterListIds.value.length === 0) return
  const valid = new Set(lists.map((l) => l.id))
  const pruned = filterListIds.value.filter((id) => valid.has(id))
  if (pruned.length !== filterListIds.value.length) filterListIds.value = pruned
})
// In meta view, exclude the item's own current list (per movingItem); in a
// regular view, exclude the current list. For a bulk move the source varies, so
// offer every list (meta) or every list but the current one (single-list view).
const otherLists = computed(() => {
  if (moveIsBulk.value) {
    return isMeta.value ? allLists.value : allLists.value.filter((l) => l.id !== listIdNum.value)
  }
  const excludeId = isMeta.value ? (movingItem.value?.listId ?? null) : listIdNum.value
  return excludeId === null ? allLists.value : allLists.value.filter((l) => l.id !== excludeId)
})
const movingItem = ref<ChecklistItem | null>(null)
const moveIsBulk = ref(false)
const showCreateForMove = ref(false)
const moveDialogOpen = computed(() => !!movingItem.value || moveIsBulk.value)

function startMoveItem(item: ChecklistItem) {
  moveIsBulk.value = false
  movingItem.value = item
}

function openBulkMove() {
  if (!canBulkMove.value) return
  moveIsBulk.value = true
}

function closeMoveDialog() {
  movingItem.value = null
  moveIsBulk.value = false
}

async function submitMoveItem(targetListId: number) {
  if (moveIsBulk.value) {
    await submitBulkMove(targetListId)
    return
  }
  if (!movingItem.value) return
  const itemName = movingItem.value.name
  const targetList = allLists.value.find((l) => l.id === targetListId)
  await update(movingItem.value.id, { targetListId })
  // In a normal list view the item leaves the view after move. In the meta
  // "All lists" view it stays — just under a different list.
  if (!isMeta.value) {
    items.value = items.value.filter((i) => i.id !== movingItem.value!.id)
  }
  movingItem.value = null
  showSuccess(
    t('pantry', '{item} moved to {list}', { item: itemName, list: targetList?.name ?? '' }),
  )
}

async function submitBulkMove(targetListId: number) {
  const ids = writableSelectedIds.value
  const targetList = allLists.value.find((l) => l.id === targetListId)
  const total = selectedCount.value
  closeMoveDialog()
  if (ids.length === 0) return
  try {
    const result = await moveMany(ids, targetListId)
    showSuccess(
      n('pantry', 'Moved %n item to {list}', 'Moved %n items to {list}', result.items.length, {
        list: targetList?.name ?? '',
      }),
    )
    reportSkipped(total - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    exitSelection()
  }
}

function createListForMove() {
  showCreateForMove.value = true
}

async function submitCreateListAndMove(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const newList = await createList(
    data.name,
    data.description || null,
    data.icon || null,
    data.color || null,
  )
  showCreateForMove.value = false
  await submitMoveItem(newList.id)
}

// ----- Copy item to another list -----

const copyingItem = ref<ChecklistItem | null>(null)
const copyIsBulk = ref(false)
const showCreateForCopy = ref(false)
const copyDialogOpen = computed(() => !!copyingItem.value || copyIsBulk.value)
// Copy can target the current list (creates a duplicate in place) as well as
// any other list — unlike move, where the current list would be a no-op.
const copyTargetLists = computed(() => allLists.value)

function startCopyItem(item: ChecklistItem) {
  copyIsBulk.value = false
  copyingItem.value = item
}

function openBulkCopy() {
  if (!canBulkCopy.value) return
  copyIsBulk.value = true
}

function closeCopyDialog() {
  copyingItem.value = null
  copyIsBulk.value = false
}

async function submitCopyItem(targetListId: number) {
  if (copyIsBulk.value) {
    await submitBulkCopy(targetListId)
    return
  }
  if (!copyingItem.value) return
  const itemName = copyingItem.value.name
  const targetList = allLists.value.find((l) => l.id === targetListId)
  await copy(copyingItem.value.id, targetListId)
  copyingItem.value = null
  showSuccess(
    t('pantry', '{item} copied to {list}', { item: itemName, list: targetList?.name ?? '' }),
  )
}

async function submitBulkCopy(targetListId: number) {
  // Copy operates on the whole selection (read-only sources are allowed).
  const ids = selectedItems.value.map((i) => i.id)
  const targetList = allLists.value.find((l) => l.id === targetListId)
  closeCopyDialog()
  if (ids.length === 0) return
  try {
    const result = await copyMany(ids, targetListId)
    showSuccess(
      n('pantry', 'Copied %n item to {list}', 'Copied %n items to {list}', result.items.length, {
        list: targetList?.name ?? '',
      }),
    )
    reportSkipped(result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    exitSelection()
  }
}

function createListForCopy() {
  showCreateForCopy.value = true
}

async function submitCreateListAndCopy(data: {
  name: string
  description: string
  icon: string
  color: string
}) {
  const newList = await createList(
    data.name,
    data.description || null,
    data.icon || null,
    data.color || null,
  )
  showCreateForCopy.value = false
  await submitCopyItem(newList.id)
}

// ----- Multi-select (group actions) -----
// (selectionMode / selectedIds / reorderActive are declared earlier.)

const selectedItems = computed(() => items.value.filter((i) => selectedIds.value.has(i.id)))
const selectedCount = computed(() => selectedItems.value.length)
const selectionCountLabel = computed(() =>
  n('pantry', '%n selected', '%n selected', selectedCount.value),
)
const allVisibleSelected = computed(
  () =>
    filteredItems.value.length > 0 && filteredItems.value.every((i) => selectedIds.value.has(i.id)),
)

// Whether the current user can write to the list an item lives on (in the meta
// view lists vary per item; in a single-list view it's the list in focus).
function itemWritable(item: ChecklistItem): boolean {
  return isMeta.value ? listWritable(listFor(item.listId)) : writableHere.value
}

// Ids eligible for write actions (move / delete / assign category).
const writableSelectedIds = computed(() =>
  selectedItems.value.filter(itemWritable).map((i) => i.id),
)

const canBulkMove = computed(() => can.value.canMoveItems && writableSelectedIds.value.length > 0)
// Copy only reads the source, so read-only items are eligible too.
const canBulkCopy = computed(() => can.value.canCopyItems && selectedCount.value > 0)
const canBulkDelete = computed(
  () => can.value.canDeleteItems && writableSelectedIds.value.length > 0,
)
const canBulkCategory = computed(
  () => can.value.canEditLists && writableSelectedIds.value.length > 0,
)
// Archive/unarchive is gated by canEditLists (an organizing action).
const canBulkArchive = computed(
  () => can.value.canEditLists && writableSelectedIds.value.length > 0,
)
// Show the toolbar entry only when there's something to select and at least one
// group action is reachable for this user.
const canSelect = computed(
  () =>
    items.value.length > 0 &&
    (can.value.canMoveItems ||
      can.value.canCopyItems ||
      can.value.canDeleteItems ||
      can.value.canEditLists),
)

function enterSelection(seedId?: number) {
  selectionMode.value = true
  if (seedId !== undefined) {
    selectedIds.value = new Set([seedId])
  }
}

function exitSelection() {
  selectionMode.value = false
  selectedIds.value = new Set()
}

function toggleSelect(id: number) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  selectedIds.value = next
}

function selectAllVisible() {
  selectedIds.value = new Set(filteredItems.value.map((i) => i.id))
}

function clearSelection() {
  selectedIds.value = new Set()
}

// Drop ids that are no longer visible/present (filter change, silent poll,
// completed bulk op) so the selection and its count stay honest.
watch([filteredItems], () => {
  if (!selectionMode.value || selectedIds.value.size === 0) return
  const visible = new Set(filteredItems.value.map((i) => i.id))
  const pruned = new Set([...selectedIds.value].filter((id) => visible.has(id)))
  if (pruned.size !== selectedIds.value.size) selectedIds.value = pruned
})

function reportSkipped(skipped: number) {
  if (skipped > 0) {
    showError(
      n(
        'pantry',
        '%n item was skipped because you cannot edit it.',
        '%n items were skipped because you cannot edit them.',
        skipped,
      ),
    )
  }
}

// ----- Bulk assign category -----

const showBulkCategory = ref(false)
const bulkCategoryId = ref<number | null>(null)

function openBulkCategory() {
  bulkCategoryId.value = null
  showBulkCategory.value = true
}

// Apply a category to the writable selection, or clear it when categoryId is null.
async function applyBulkCategory(categoryId: number | null) {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await setCategoryMany(ids, categoryId)
    showSuccess(n('pantry', 'Updated %n item', 'Updated %n items', result.items.length))
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    showBulkCategory.value = false
    exitSelection()
  }
}

// ----- Bulk stores -----

const showBulkStores = ref(false)
const bulkStoreIds = ref<number[]>([])

function openBulkStores() {
  bulkStoreIds.value = []
  showBulkStores.value = true
}

// Replace the stores on the writable selection (empty array clears them).
async function applyBulkStores(storeIds: number[]) {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await setStoresMany(ids, storeIds)
    showSuccess(n('pantry', 'Updated %n item', 'Updated %n items', result.items.length))
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    showBulkStores.value = false
    exitSelection()
  }
}

// ----- Bulk labels -----

const showBulkLabels = ref(false)
const bulkLabelIds = ref<number[]>([])

function openBulkLabels() {
  bulkLabelIds.value = []
  showBulkLabels.value = true
}

// Replace the labels on the writable selection (empty array clears them).
async function applyBulkLabels(labelIds: number[]) {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await setLabelsMany(ids, labelIds)
    showSuccess(n('pantry', 'Updated %n item', 'Updated %n items', result.items.length))
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
  } catch (e) {
    showError((e as Error).message)
  } finally {
    showBulkLabels.value = false
    exitSelection()
  }
}

// ----- Bulk delete -----

const confirmingBulkDelete = ref(false)

async function bulkDelete() {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  if (trashMode.value || archiveMode.value) {
    confirmingBulkDelete.value = true
    return
  }
  const snapshots = selectedItems.value.filter((i) => itemWritable(i)).map((i) => ({ ...i }))
  try {
    const result = await removeMany(ids)
    const deleted = ids.length - result.skipped.length
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
    showUndo(
      n('pantry', 'Deleted %n item', 'Deleted %n items', deleted),
      () => {
        void undoRemoveMany(snapshots).catch(() => showError(strings.restoreFailed))
      },
      { timeout: 6000 },
    )
  } catch (e) {
    showError((e as Error).message)
  }
}

async function submitBulkDeletePermanent() {
  const ids = writableSelectedIds.value
  confirmingBulkDelete.value = false
  if (ids.length === 0) return
  try {
    const result = await removeManyPermanent(ids)
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
  } catch (e) {
    showError((e as Error).message)
  }
}

// ----- Bulk archive / unarchive -----

async function bulkArchive() {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  const snapshots = selectedItems.value.filter((i) => itemWritable(i)).map((i) => ({ ...i }))
  try {
    const result = await archiveMany(ids)
    const archived = ids.length - result.skipped.length
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
    showUndo(
      n('pantry', 'Archived %n item', 'Archived %n items', archived),
      () => {
        void undoArchiveMany(snapshots).catch(() => showError(strings.unarchiveFailed))
      },
      { timeout: 6000 },
    )
  } catch (e) {
    showError((e as Error).message)
  }
}

async function bulkUnarchive() {
  const ids = writableSelectedIds.value
  if (ids.length === 0) return
  try {
    const result = await unarchiveMany(ids)
    reportSkipped(selectedCount.value - ids.length + result.skipped.length)
    exitSelection()
  } catch (e) {
    showError((e as Error).message)
  }
}

const strings = {
  back: t('pantry', 'Back to lists'),
  allListsTitle: t('pantry', 'All lists'),
  emptyTitle: t('pantry', 'No items yet'),
  emptyBody: t('pantry', 'Add items using the form above.'),
  trashEmptyTitle: t('pantry', 'Trash is empty'),
  trashEmptyBody: t('pantry', 'Deleted items will appear here.'),
  archiveEmptyTitle: t('pantry', 'Archive is empty'),
  archiveEmptyBody: t('pantry', 'Archived items will appear here.'),
  sortLabel: t('pantry', 'Sort order'),
  // TRANSLATORS: Menu action that overwrites the list's manual order with a
  // fresh order computed from a chosen basis (date added / name). The trailing
  // non-breaking space + ellipsis signal that picking a basis follows.
  resetOrderLabel: t('pantry', 'Reset custom order to …'),
  resetOrderCaption: t('pantry', 'Reset order'),
  // TRANSLATORS: Reset-order basis. Orders items by when each was added.
  reseedDateAdded: t('pantry', 'Date added'),
  reseedNameAsc: t('pantry', 'Name A–Z'),
  reseedNameDesc: t('pantry', 'Name Z–A'),
  reseedConfirmTitle: t('pantry', 'Reset custom order?'),
  reseedConfirmBody: t(
    'pantry',
    'This overwrites the current custom order for this list. You can rearrange items afterwards.',
  ),
  reseedConfirmAction: t('pantry', 'Reset order'),
  reseedDone: t('pantry', 'Custom order reset'),
  // TRANSLATORS: Button that clears the done-state on every checked item in the list.
  uncheckAll: t('pantry', 'Uncheck all'),
  uncheckAllTitle: t('pantry', 'Uncheck all items?'),
  uncheckAllConfirm: t(
    'pantry',
    'Every checked item in this list will be returned to the active list.',
  ),
  // TRANSLATORS: Button that soft-deletes every checked (done) item in the list.
  removeAll: t('pantry', 'Remove all'),
  removeAllTitle: t('pantry', 'Remove all done items?'),
  removeAllConfirm: t(
    'pantry',
    'Every checked item in this list will be moved to the trash. You can restore them afterwards.',
  ),
  trashLabel: t('pantry', 'Trash'),
  // TRANSLATORS: Noun. Toolbar toggle that opens the view of archived items (not the "archive" action).
  archiveLabel: t('pantry', 'Archive'),
  // TRANSLATORS: Section heading above the completed (checked-off) items.
  doneTitle: t('pantry', 'Done'),
  noCategory: t('pantry', 'No category'),
  noStore: t('pantry', 'No store'),
  manageCategories: t('pantry', 'Manage categories'),
  manageCustomFields: t('pantry', 'Manage custom fields'),
  manageLabels: t('pantry', 'Manage labels'),
  // TRANSLATORS: Noun (plural), shops where items are bought. Toolbar action opening the store manager.
  manageStores: t('pantry', 'Manage stores'),
  editList: t('pantry', 'Edit list'),
  // TRANSLATORS: Button that opens Shopping Mode for this list.
  shop: t('pantry', 'Start shopping'),
  // TRANSLATORS: Verb. Toolbar button that exports the list as Markdown.
  exportMarkdown: t('pantry', 'Export'),
  // TRANSLATORS: Verb. Toolbar button that imports items from Markdown.
  importMarkdown: t('pantry', 'Import'),
  moveToList: t('pantry', 'Move to list'),
  copyToList: t('pantry', 'Copy to list'),
  newList: t('pantry', 'New list'),
  emptyTrashAction: t('pantry', 'Empty trash'),
  emptyTrashTitle: t('pantry', 'Empty trash?'),
  emptyTrashConfirm: t(
    'pantry',
    'All deleted items in this list will be permanently removed. This cannot be undone.',
  ),
  cancel: t('pantry', 'Cancel'),
  itemMarkedDone: t('pantry', 'Item marked as done'),
  itemRemoved: t('pantry', 'Item moved to trash'),
  itemArchived: t('pantry', 'Item archived'),
  restoreFailed: t('pantry', 'Failed to restore item.'),
  unarchiveFailed: t('pantry', 'Failed to unarchive item.'),
  reuseTitle: t('pantry', 'Item already exists'),
  reuseAction: t('pantry', 'Reuse existing'),
  reuseAddAnyway: t('pantry', 'Add anyway'),
  // TRANSLATORS: Verb. Toolbar toggle that enters multi-select mode to act on several items at once.
  select: t('pantry', 'Select'),
  selectAll: t('pantry', 'Select all'),
  clearSelection: t('pantry', 'Clear selection'),
  exitSelection: t('pantry', 'Exit selection'),
  // TRANSLATORS: Verb. Button that moves the selected items to another list.
  moveSelected: t('pantry', 'Move'),
  // TRANSLATORS: Verb. Button that copies the selected items to another list.
  copySelected: t('pantry', 'Copy'),
  assignCategory: t('pantry', 'Assign category'),
  // TRANSLATORS: Verb. Button that sets which stores the selected items can be bought at.
  assignStores: t('pantry', 'Assign stores'),
  // TRANSLATORS: Verb. Button that sets which labels the selected items carry.
  assignLabels: t('pantry', 'Assign labels'),
  archiveSelected: t('pantry', 'Archive items'),
  unarchiveSelected: t('pantry', 'Unarchive items'),
  // TRANSLATORS: Verb. Button that deletes the selected items.
  deleteSelected: t('pantry', 'Delete items'),
  assignCategoryTitle: t('pantry', 'Assign category'),
  // TRANSLATORS: Verb. Dialog button that applies the chosen category to the selected items.
  apply: t('pantry', 'Apply'),
  removeCategory: t('pantry', 'Remove category'),
  // TRANSLATORS: Noun (plural), shops where items are bought. Dialog title for bulk assignment.
  assignStoresTitle: t('pantry', 'Assign stores'),
  // TRANSLATORS: Verb. Dialog button that detaches all stores from the selected items.
  removeStores: t('pantry', 'Remove stores'),
  // TRANSLATORS: Noun (plural), tags on items. Dialog title for bulk assignment.
  assignLabelsTitle: t('pantry', 'Assign labels'),
  // TRANSLATORS: Verb. Dialog button that detaches all labels from the selected items.
  removeLabels: t('pantry', 'Remove labels'),
  bulkDeleteTitle: t('pantry', 'Delete items?'),
  bulkDeleteConfirm: t(
    'pantry',
    'The selected items will be permanently removed. This cannot be undone.',
  ),
}

const toolbarActions = computed<ToolbarAction[]>(() => {
  const actions: ToolbarAction[] = [
    {
      key: 'shop',
      label: strings.shop,
      icon: CartIcon,
      variant: 'primary',
      priority: 7,
      onClick: startShopping,
    },
    {
      key: 'sort',
      type: 'menu',
      label: sortMenuName.value,
      caption: strings.sortLabel,
      icon: SortIcon,
      priority: 5,
      options: itemSortOptions.value.map((opt) => ({
        key: opt.value,
        label: opt.label,
        active: currentSort.value === opt.value,
        onClick: () => changeSort(opt.value),
      })),
    },
  ]

  if (canResetOrder.value) {
    const reseedOption = (key: ReseedBasis, label: string) => ({
      key,
      label,
      // Pick a basis → confirm the destructive overwrite before running.
      onClick: () => (pendingReseed.value = key),
    })
    actions.push({
      key: 'reset-order',
      type: 'menu',
      label: strings.resetOrderLabel,
      caption: strings.resetOrderCaption,
      icon: SortReverseVariantIcon,
      priority: 1,
      options: [
        reseedOption('dateAdded', strings.reseedDateAdded),
        reseedOption('name_asc', strings.reseedNameAsc),
        reseedOption('name_desc', strings.reseedNameDesc),
      ],
    })
  }

  if (!isMeta.value) {
    if (list.value && (list.value.canEdit ?? can.value.canEditLists)) {
      actions.push({
        key: 'edit',
        label: strings.editList,
        icon: PencilIcon,
        priority: 4,
        onClick: () => (editingList.value = true),
      })
    }
    actions.push(
      {
        key: 'archive',
        label: strings.archiveLabel,
        icon: ArchiveOutlineIcon,
        variant: archiveMode.value ? 'primary' : 'tertiary',
        pressed: archiveMode.value,
        priority: 2,
        onClick: toggleArchive,
      },
      {
        key: 'trash',
        label: strings.trashLabel,
        icon: TrashCanIcon,
        variant: trashMode.value ? 'primary' : 'tertiary',
        pressed: trashMode.value,
        priority: 2,
        onClick: toggleTrash,
      },
    )
  }

  // Export/import are available in both single-list and "All lists" views.
  // In the meta view, export bundles every list and import prompts for a target.
  actions.push(
    {
      key: 'export',
      label: strings.exportMarkdown,
      icon: FileExportIcon,
      alwaysCollapsed: true,
      onClick: () => (showExport.value = true),
    },
    {
      key: 'import',
      label: strings.importMarkdown,
      icon: FileImportIcon,
      alwaysCollapsed: true,
      onClick: () => (showImport.value = true),
    },
  )

  actions.push({
    key: 'categories',
    label: strings.manageCategories,
    icon: CategoryEntityIcon,
    alwaysCollapsed: true,
    onClick: () => (showCategoryManager.value = true),
  })

  actions.push({
    key: 'labels',
    label: strings.manageLabels,
    icon: LabelEntityIcon,
    alwaysCollapsed: true,
    onClick: () => (showLabelManager.value = true),
  })

  actions.push({
    key: 'stores',
    label: strings.manageStores,
    icon: StoreEntityIcon,
    alwaysCollapsed: true,
    onClick: () => (showStoreManager.value = true),
  })

  if (can.value.canEditFields) {
    actions.push({
      key: 'custom-fields',
      label: strings.manageCustomFields,
      icon: FormatListBulletedTypeIcon,
      alwaysCollapsed: true,
      onClick: () => (showCustomFieldManager.value = true),
    })
  }

  if (canSelect.value) {
    actions.push({
      key: 'select',
      label: strings.select,
      icon: SelectMultipleIcon,
      variant: selectionMode.value ? 'primary' : 'tertiary',
      pressed: selectionMode.value,
      priority: 3,
      onClick: () => (selectionMode.value ? exitSelection() : enterSelection()),
    })
  }

  return actions
})
</script>

<style scoped lang="scss">
.pantry-detail {
  &__title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);

    &--meta {
      background: var(--color-primary-element);
      color: var(--color-primary-element-text);
    }
  }

  &__empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    border-radius: 16px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);
  }

  &__body {
    max-width: 900px;
    margin: 0 auto;
    padding-bottom: 4rem;
  }

  &__filter {
    margin-top: 1rem;
    margin-bottom: 1.5rem;
  }

  &__center {
    display: flex;
    justify-content: center;
    padding: 2rem;
  }

  &__items {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  &__trash-bar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.75rem;
  }

  &__selection-bar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.5rem;
    margin-bottom: 0.75rem;
    border-radius: var(--border-radius, 8px);
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  }

  &__selection-count {
    font-weight: 600;
    white-space: nowrap;
  }

  &__selection-spacer {
    flex: 1;
  }

  &__placeholder {
    min-height: 48px;
    border: 3px dashed var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    background: rgba(var(--color-primary-element-rgb, 0, 120, 212), 0.08);
    list-style: none;
  }

  &__category-header {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    // Stick each category header to the top of the scroll area so the group a
    // row belongs to stays visible while scrolling through it. An opaque
    // background and a z-index keep item rows from showing through as they
    // scroll underneath. Top margin is avoided on purpose — a sticky element's
    // transparent margin would leave a see-through strip above the stuck
    // header; the internal top padding provides the separation instead.
    position: sticky;
    top: 0;
    z-index: 2;
    margin: 0;
    padding: 0.6rem 0.25rem 0.4rem;
    background: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
    font-weight: 600;
    // Falls back to the default text color for the "No category" header (no
    // inline color set); real categories set their own color inline.
    color: var(--color-text-maxcontrast);
  }

  &__category-header-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__section-title {
    margin: 1.5rem 0 0.5rem;
    padding: 0 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  &__section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-inline-end: 0.5rem;
    margin-block-start: 1.5rem;
  }

  // The toggle fills the row and absorbs any shrink; the uncheck-all button keeps
  // its natural width so its label never truncates.
  &__uncheck-all {
    flex: 0 0 auto;
    white-space: nowrap;
  }

  &__section-toggle {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex: 1 1 auto;
    min-width: 0;
    background: none;
    border: none;
    cursor: pointer;
    text-align: start;

    &:hover,
    &:focus-visible {
      color: var(--color-main-text);
    }
  }

  &__section-chevron {
    transition: transform 0.15s ease;

    &--collapsed {
      transform: rotate(-90deg);
    }
  }
}

.pantry-move-list {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.5rem 0;
}

.pantry-bulk-category {
  padding: 0.5rem 0 1rem;
}

.pantry-sort-active {
  font-weight: 600;
}
</style>
