import type { Component } from 'vue'

export interface ToolbarMenuRadioOption {
  type?: 'radio'
  key: string
  label: string
  /** Optional leading icon; ignored while the option is active (shows a radio mark). */
  icon?: Component
  active?: boolean
  onClick: () => void
}

export interface ToolbarMenuCheckboxOption {
  type: 'checkbox'
  key: string
  label: string
  checked: boolean
  onChange: (checked: boolean) => void
}

/**
 * An entry in a menu action's dropdown. Radio options show a radio mark when
 * active; checkbox options render a toggle. A separator is inserted
 * automatically wherever adjacent options are of different kinds.
 */
export type ToolbarMenuOption = ToolbarMenuRadioOption | ToolbarMenuCheckboxOption

interface ToolbarActionBase {
  key: string
  label: string
  icon: Component
  /** Higher priority stays inline longer; lowest priority collapses first. Default 0. */
  priority?: number
}

export interface ToolbarButtonAction extends ToolbarActionBase {
  type?: 'button'
  variant?: 'primary' | 'secondary' | 'tertiary' | 'error' | 'warning' | 'success'
  /** Renders as aria-pressed for toggle buttons. */
  pressed?: boolean
  onClick: () => void
}

export interface ToolbarMenuAction extends ToolbarActionBase {
  type: 'menu'
  /** Short heading shown above the options when collapsed into the overflow menu. */
  caption?: string
  options: ToolbarMenuOption[]
}

export type ToolbarAction = ToolbarButtonAction | ToolbarMenuAction
