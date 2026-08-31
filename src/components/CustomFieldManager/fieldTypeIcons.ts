import type { Component } from 'vue'
import { t } from '@nextcloud/l10n'
import type { FieldType } from '@/api/types'
import FormatTextIcon from '@icons/FormatText.vue'
import NumericIcon from '@icons/Numeric.vue'
import CheckboxMarkedOutlineIcon from '@icons/CheckboxMarkedOutline.vue'
import CalendarIcon from '@icons/Calendar.vue'
import FormatListBulletedTypeIcon from '@icons/FormatListBulletedType.vue'

export interface FieldTypeOption {
  key: FieldType
  label: string
  component: Component
}

/** The five field types in their locked presentation order (per the UI contract). */
export const FIELD_TYPES: FieldTypeOption[] = [
  { key: 'text', label: t('pantry', 'Text'), component: FormatTextIcon },
  { key: 'number', label: t('pantry', 'Number'), component: NumericIcon },
  { key: 'checkbox', label: t('pantry', 'Checkbox'), component: CheckboxMarkedOutlineIcon },
  { key: 'date', label: t('pantry', 'Date'), component: CalendarIcon },
  { key: 'select', label: t('pantry', 'Select'), component: FormatListBulletedTypeIcon },
]

const byKey = Object.fromEntries(FIELD_TYPES.map((o) => [o.key, o])) as Record<
  FieldType,
  FieldTypeOption
>

export function fieldTypeIconComponent(type: FieldType): Component {
  return byKey[type]?.component ?? FormatTextIcon
}

export function fieldTypeLabel(type: FieldType): string {
  return byKey[type]?.label ?? type
}
