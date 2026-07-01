<template>
  <header ref="toolbarRef" class="pantry-toolbar">
    <div ref="leftRef" class="pantry-toolbar__left">
      <slot name="before-title" />
      <h2 v-if="title" class="pantry-toolbar__title">{{ title }}</h2>
      <slot name="after-title" />
    </div>
    <div
      v-if="actions.length || $slots.actions"
      class="pantry-toolbar__actions"
      :style="{ visibility: measuring ? 'hidden' : 'visible' }"
    >
      <slot name="actions" />

      <template v-for="(action, i) in actions" :key="action.key">
        <NcActions
          v-if="action.type === 'menu'"
          v-show="measuring || !collapsedKeys.has(action.key)"
          :ref="(el) => setItemRef(el, i)"
          :menu-name="action.label"
          :aria-label="action.caption ?? action.label"
          :title="action.caption ?? action.label"
          type="tertiary"
        >
          <template #icon>
            <component :is="action.icon" :size="20" />
          </template>
          <template v-for="(opt, oi) in action.options" :key="opt.key">
            <NcActionSeparator
              v-if="oi > 0 && optionKind(opt) !== optionKind(action.options[oi - 1])"
            />
            <NcActionCheckbox
              v-if="opt.type === 'checkbox'"
              :model-value="opt.checked"
              @update:model-value="opt.onChange"
            >
              {{ opt.label }}
            </NcActionCheckbox>
            <NcActionButton
              v-else
              :class="{ 'pantry-toolbar__opt--active': opt.active }"
              @click="opt.onClick"
            >
              <template #icon>
                <RadioboxMarkedIcon v-if="opt.active" :size="20" />
                <component :is="opt.icon" v-else-if="opt.icon" :size="20" />
                <RadioboxBlankIcon v-else :size="20" />
              </template>
              {{ opt.label }}
            </NcActionButton>
          </template>
        </NcActions>
        <NcButton
          v-else
          v-show="measuring || !collapsedKeys.has(action.key)"
          :ref="(el) => setItemRef(el, i)"
          :variant="action.variant ?? 'tertiary'"
          :aria-pressed="action.pressed"
          @click="action.onClick"
        >
          <template #icon>
            <component :is="action.icon" :size="20" />
          </template>
          {{ action.label }}
        </NcButton>
      </template>

      <NcActions
        v-if="!measuring && collapsedActions.length"
        :aria-label="strings.moreActions"
        :title="strings.moreActions"
      >
        <template #icon>
          <DotsHorizontalIcon :size="20" />
        </template>
        <template v-for="(action, ai) in collapsedActions" :key="action.key">
          <template v-if="action.type === 'menu'">
            <NcActionCaption :name="action.caption ?? action.label" />
            <template v-for="(opt, oi) in action.options" :key="opt.key">
              <NcActionSeparator
                v-if="oi > 0 && optionKind(opt) !== optionKind(action.options[oi - 1])"
              />
              <NcActionCheckbox
                v-if="opt.type === 'checkbox'"
                :model-value="opt.checked"
                @update:model-value="opt.onChange"
              >
                {{ opt.label }}
              </NcActionCheckbox>
              <NcActionButton
                v-else
                :class="{ 'pantry-toolbar__opt--active': opt.active }"
                @click="opt.onClick"
              >
                <template #icon>
                  <RadioboxMarkedIcon v-if="opt.active" :size="20" />
                  <component :is="opt.icon" v-else-if="opt.icon" :size="20" />
                  <RadioboxBlankIcon v-else :size="20" />
                </template>
                {{ opt.label }}
              </NcActionButton>
            </template>
            <NcActionSeparator v-if="ai < collapsedActions.length - 1" />
          </template>
          <NcActionButton v-else @click="action.onClick">
            <template #icon>
              <component :is="action.icon" :size="20" />
            </template>
            {{ action.label }}
          </NcActionButton>
        </template>
      </NcActions>
    </div>
  </header>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, nextTick, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import DotsHorizontalIcon from '@icons/DotsHorizontal.vue'
import RadioboxBlankIcon from '@icons/RadioboxBlank.vue'
import RadioboxMarkedIcon from '@icons/RadioboxMarked.vue'
import type { ToolbarAction, ToolbarMenuOption } from './types'

const props = withDefaults(
  defineProps<{
    title?: string
    actions?: ToolbarAction[]
  }>(),
  { actions: () => [] },
)

const toolbarRef = ref<HTMLElement | null>(null)
const leftRef = ref<HTMLElement | null>(null)

// Layout measurement. Item widths are cached and only re-measured when the set
// of actions changes; plain resizes recompute the split from the cache so the
// toolbar doesn't flash while the user drags the window.
const measuring = ref(false)
const collapsedKeys = ref<Set<string>>(new Set())
const collapsedActions = ref<ToolbarAction[]>([])
let itemEls: (HTMLElement | null)[] = []
let itemWidths: number[] = []

const GAP = 8 // matches the flex gap (0.5rem) between toolbar actions
const OVERFLOW_WIDTH = 44 // approx width of the kebab overflow trigger

function setItemRef(el: unknown, i: number) {
  if (!el) {
    itemEls[i] = null
    return
  }
  const dom = (el as { $el?: HTMLElement }).$el ?? (el as HTMLElement)
  itemEls[i] = dom ?? null
}

function priorityOf(i: number): number {
  return props.actions[i].priority ?? 0
}

function optionKind(opt: ToolbarMenuOption): 'checkbox' | 'radio' {
  return opt.type === 'checkbox' ? 'checkbox' : 'radio'
}

function availableWidth(): number {
  const tb = toolbarRef.value
  if (!tb) return 0
  const cs = getComputedStyle(tb)
  const padStart = parseFloat(cs.paddingInlineStart) || 0
  const padEnd = parseFloat(cs.paddingInlineEnd) || 0
  const content = tb.clientWidth - padStart - padEnd
  const leftW = leftRef.value?.offsetWidth ?? 0
  return content - leftW - GAP
}

function recompute() {
  if (measuring.value) return
  const actions = props.actions
  const n = actions.length
  if (!n || itemWidths.length !== n) {
    collapsedKeys.value = new Set()
    collapsedActions.value = []
    return
  }
  const avail = availableWidth()
  const totalAll = itemWidths.reduce((s, w) => s + w, 0) + GAP * (n - 1)
  if (avail <= 0 || totalAll <= avail) {
    collapsedKeys.value = new Set()
    collapsedActions.value = []
    return
  }

  // Collapse lowest-priority items first; break ties by collapsing the
  // right-most (later-declared) item first.
  const order = actions.map((_, i) => i).sort((a, b) => priorityOf(a) - priorityOf(b) || b - a)
  const collapsed = new Set<number>()
  const currentWidth = () => {
    let s = OVERFLOW_WIDTH
    let visible = 0
    for (let i = 0; i < n; i++) {
      if (collapsed.has(i)) continue
      s += itemWidths[i]
      visible++
    }
    return s + GAP * visible // one gap per visible item + the overflow trigger
  }
  for (const idx of order) {
    if (currentWidth() <= avail) break
    collapsed.add(idx)
  }

  collapsedKeys.value = new Set([...collapsed].map((i) => actions[i].key))
  collapsedActions.value = actions.filter((a) => collapsedKeys.value.has(a.key))
}

async function measureWidths() {
  if (!props.actions.length) {
    itemWidths = []
    recompute()
    return
  }
  measuring.value = true
  await nextTick()
  itemWidths = props.actions.map((_, i) => itemEls[i]?.offsetWidth ?? 0)
  measuring.value = false
  await nextTick()
  recompute()
}

let observer: ResizeObserver | null = null

onMounted(() => {
  observer = new ResizeObserver(() => recompute())
  if (toolbarRef.value) observer.observe(toolbarRef.value)
  if (leftRef.value) observer.observe(leftRef.value)
  void measureWidths()
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})

// Re-measure when actions are added/removed; recompute (cheap) when only their
// labels/state change.
watch(
  () => props.actions.map((a) => a.key).join('|'),
  () => {
    itemEls = []
    void measureWidths()
  },
)
watch(
  () => props.actions,
  () => recompute(),
)

const strings = {
  moreActions: t('pantry', 'More actions'),
}
</script>

<style scoped lang="scss">
.pantry-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 1rem;
  // The NC sidebar toggle overlays the top-left area of the content.
  // Add left padding so the toolbar content is not hidden behind it.
  padding-inline-start: var(--default-clickable-area, 44px);

  &__left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    flex: 0 1 auto;
  }

  &__title {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: min(42vw, 420px);
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 0 0 auto;
  }

  &__opt--active {
    font-weight: 600;
  }
}
</style>
