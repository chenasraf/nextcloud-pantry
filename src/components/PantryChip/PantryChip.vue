<template>
  <component
    :is="interactive ? 'button' : 'span'"
    :type="interactive ? 'button' : undefined"
    class="pantry-chip"
    :class="[
      `pantry-chip--${variant}`,
      `pantry-chip--${size}`,
      {
        'pantry-chip--interactive': interactive,
        'pantry-chip--filled': filled && !color,
        'pantry-chip--color': color && !solid,
        'pantry-chip--solid': color && solid,
        'pantry-chip--dark': isDarkTheme,
      },
    ]"
    :style="colorStyle"
    @click="$emit('click', $event)"
  >
    <span v-if="$slots.icon" class="pantry-chip__icon">
      <slot name="icon" />
    </span>
    <span v-if="$slots.default" class="pantry-chip__label">
      <slot />
    </span>
    <span v-if="$slots.trailing" class="pantry-chip__trailing">
      <slot name="trailing" />
    </span>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import { contrastColor } from '@/components/ChecklistIconPicker/checklistColors'

const props = withDefaults(
  defineProps<{
    /** Theme-accent emphasis; ignored when `color` is set. */
    variant?: 'primary' | 'secondary' | 'tertiary'
    /** Entity color (hex). Renders a tinted chip in that color. */
    color?: string
    /** Fill the chip with a solid `color` and contrast text, for use over imagery. */
    solid?: boolean
    /** `md` for interactive chips, `sm` for dense display badges. */
    size?: 'md' | 'sm'
    /** Render as a button with hover/focus affordances; `false` renders a display span. */
    interactive?: boolean
    /** Solid primary-accent fill with contrast text; ignored when `color` is set. */
    filled?: boolean
  }>(),
  {
    variant: 'tertiary',
    color: undefined,
    solid: false,
    size: 'md',
    interactive: true,
    filled: false,
  },
)

defineEmits<{
  click: [event: MouseEvent]
}>()

const isDarkTheme = useIsDarkTheme()

const colorStyle = computed(() => {
  if (!props.color) return undefined
  if (props.solid) {
    return { '--chip-solid-bg': props.color, '--chip-solid-fg': contrastColor(props.color) }
  }
  return { '--chip-color': props.color }
})
</script>

<style scoped lang="scss">
.pantry-chip {
  // Resting (neutral) tokens; the variant / color / solid modifiers below and
  // dark mode override the tint stops.
  --chip-bg: var(--color-background-hover);
  --chip-bg-hover: var(--color-background-dark);
  --chip-fg: var(--color-text-maxcontrast);
  --chip-border: transparent;
  --chip-weight: 600;

  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  margin: 0;
  padding: 5px 10px;
  border: 1px solid var(--chip-border);
  border-radius: 8px;
  background-color: var(--chip-bg);
  color: var(--chip-fg);
  font: inherit;
  font-size: 13px;
  font-weight: var(--chip-weight);
  line-height: 1.25;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease;

  // Neutralize the global button box so a chip keeps its own compact height
  // instead of the 44px clickable-area minimum.
  appearance: none;
  min-height: 0;
  height: auto;

  &__icon {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
  }

  &__label {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__trailing {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
  }
}

.pantry-chip--sm {
  gap: 4px;
  padding: 2px 8px;
  border-radius: 7px;
  font-size: 0.8125rem;
}

.pantry-chip--interactive {
  cursor: pointer;
  // Resting interactive chips carry a visible outline; the accent variants and
  // color modifier below replace it.
  --chip-border: var(--color-border);

  &:hover {
    background-color: var(--chip-bg-hover);
  }

  &:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 1px;
  }
}

// A value is set on this field, but its tray is not open: subtle accent tint.
.pantry-chip--secondary {
  --chip-bg: color-mix(in srgb, var(--color-primary-element) 12%, var(--color-main-background));
  --chip-bg-hover: color-mix(
    in srgb,
    var(--color-primary-element) 20%,
    var(--color-main-background)
  );
  --chip-border: color-mix(in srgb, var(--color-primary-element) 30%, transparent);
  --chip-fg: var(--color-main-text);
}

// Selected / active: stronger accent tint, full-color border, heavier weight.
.pantry-chip--primary {
  --chip-bg: color-mix(in srgb, var(--color-primary-element) 20%, var(--color-main-background));
  --chip-bg-hover: color-mix(
    in srgb,
    var(--color-primary-element) 30%,
    var(--color-main-background)
  );
  --chip-border: var(--color-primary-element);
  --chip-fg: var(--color-main-text);
  --chip-weight: 700;
}

// Solid primary-accent fill with contrast text: the selected/active state for
// filter chips, where the tint variants would read as too subtle.
.pantry-chip--filled {
  --chip-bg: var(--color-primary-element);
  --chip-bg-hover: var(--color-primary-element-hover, var(--color-primary-element));
  --chip-border: var(--color-primary-element);
  --chip-fg: var(--color-primary-element-text);
  --chip-weight: 700;
}

// Entity-colored chip: the color tints the fill and border, and blends with the
// theme text color so it stays legible in either mode and for any hue.
.pantry-chip--color {
  --chip-bg: color-mix(in srgb, var(--chip-color) 14%, var(--color-main-background));
  --chip-bg-hover: color-mix(in srgb, var(--chip-color) 24%, var(--color-main-background));
  --chip-border: color-mix(in srgb, var(--chip-color) 38%, transparent);
  --chip-fg: color-mix(in srgb, var(--chip-color) 62%, var(--color-main-text));
}

// Solid fill for chips laid over imagery, where a tint would be swallowed.
.pantry-chip--solid {
  --chip-bg: var(--chip-solid-bg);
  --chip-bg-hover: var(--chip-solid-bg);
  --chip-border: transparent;
  --chip-fg: var(--chip-solid-fg);
}

// Dark themes swallow low-alpha tints, so the accent stops run heavier there to
// keep the fill visible and the border legible against a dark background.
.pantry-chip--dark {
  &.pantry-chip--secondary {
    --chip-bg: color-mix(in srgb, var(--color-primary-element) 24%, var(--color-main-background));
    --chip-bg-hover: color-mix(
      in srgb,
      var(--color-primary-element) 34%,
      var(--color-main-background)
    );
    --chip-border: color-mix(in srgb, var(--color-primary-element) 45%, transparent);
  }

  &.pantry-chip--primary {
    --chip-bg: color-mix(in srgb, var(--color-primary-element) 34%, var(--color-main-background));
    --chip-bg-hover: color-mix(
      in srgb,
      var(--color-primary-element) 44%,
      var(--color-main-background)
    );
  }

  &.pantry-chip--color {
    --chip-bg: color-mix(in srgb, var(--chip-color) 26%, var(--color-main-background));
    --chip-bg-hover: color-mix(in srgb, var(--chip-color) 36%, var(--color-main-background));
    --chip-border: color-mix(in srgb, var(--chip-color) 52%, transparent);
  }
}
</style>
