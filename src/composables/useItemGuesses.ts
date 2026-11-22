import { computed, ref, watch, type Ref } from 'vue'
import { getLanguage } from '@nextcloud/l10n'
import { useCategories } from '@/composables/useCategories'
import { useItemMemory } from '@/composables/useItemMemory'
import { useSmartInput } from '@/composables/useSmartInput'
import { type GuessDrops, guessLine, type ResolvedLine, resolveLine } from '@/utils/itemGuess'

/**
 * Smart input for a form that adds items from typed lines: splits each line
 * into name and amount, guesses a category and amount from item memory, and
 * tracks which of those the person dropped.
 */
export function useItemGuesses(options: {
  houseId: number
  lines: Ref<string[]>
  listId: Ref<number | null>
  formCategoryId: Ref<number | null>
  formQuantity: Ref<string>
}) {
  const { smartInput } = useSmartInput()
  const memory = useItemMemory(options.houseId)
  const { categoriesForList } = useCategories(options.houseId)
  const categories = computed(() => categoriesForList(options.listId.value))
  const drops = ref<Map<string, GuessDrops>>(new Map())

  watch(smartInput, (on) => on && void memory.refresh(), { immediate: true })

  const context = computed(() => ({
    locale: getLanguage(),
    memory: memory.index.value,
    listId: options.listId.value,
    usableCategoryIds: new Set(categories.value.map((c) => c.id)),
    formCategoryId: options.formCategoryId.value,
    formQuantity: options.formQuantity.value,
  }))

  const rows = computed(() =>
    smartInput.value
      ? options.lines.value.map((line) => ({
          key: line,
          guess: guessLine(line, context.value, drops.value.get(line)),
        }))
      : [],
  )

  function drop(key: string, part: keyof GuessDrops) {
    drops.value = new Map(drops.value).set(key, { ...drops.value.get(key), [part]: true })
  }

  /** The values a typed line is saved with. */
  function resolve(line: string): ResolvedLine {
    const form = { categoryId: options.formCategoryId.value, quantity: options.formQuantity.value }
    if (!smartInput.value) {
      return { name: line, quantity: form.quantity.trim() || null, categoryId: form.categoryId }
    }
    const lineDrops = drops.value.get(line) ?? {}
    return resolveLine(guessLine(line, context.value, lineDrops), lineDrops, form)
  }

  function reset() {
    drops.value = new Map()
  }

  return { rows, categories, drop, resolve, reset, refresh: memory.refresh }
}
