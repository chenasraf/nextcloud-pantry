// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'

import { toggleMarkdownTask } from './markdownTask'

describe('toggleMarkdownTask', () => {
  it('checks an unchecked item', () => {
    expect(toggleMarkdownTask('- [ ] Milk', 0)).toBe('- [x] Milk')
  })

  it('unchecks a checked item', () => {
    expect(toggleMarkdownTask('- [x] Milk', 0)).toBe('- [ ] Milk')
    expect(toggleMarkdownTask('- [X] Milk', 0)).toBe('- [ ] Milk')
  })

  it('toggles the item at the given index only', () => {
    const src = '- [ ] Milk\n- [ ] Eggs\n- [ ] Bread'
    expect(toggleMarkdownTask(src, 1)).toBe('- [ ] Milk\n- [x] Eggs\n- [ ] Bread')
  })

  it('supports *, + and ordered list markers', () => {
    expect(toggleMarkdownTask('* [ ] a', 0)).toBe('* [x] a')
    expect(toggleMarkdownTask('+ [ ] a', 0)).toBe('+ [x] a')
    expect(toggleMarkdownTask('1. [ ] a', 0)).toBe('1. [x] a')
    expect(toggleMarkdownTask('2) [ ] a', 0)).toBe('2) [x] a')
  })

  it('preserves indentation on nested items', () => {
    const src = '- [ ] parent\n  - [ ] child'
    expect(toggleMarkdownTask(src, 1)).toBe('- [ ] parent\n  - [x] child')
  })

  it('ignores task-like lines inside fenced code blocks', () => {
    const src = ['- [ ] real', '', '```', '- [ ] not a task', '```', '', '- [ ] second'].join('\n')
    // index 1 is the second *rendered* checkbox, i.e. "second", not the fenced line
    const out = toggleMarkdownTask(src, 1)
    expect(out).toBe(
      ['- [ ] real', '', '```', '- [ ] not a task', '```', '', '- [x] second'].join('\n'),
    )
  })

  it('does not treat non-task brackets as checkboxes', () => {
    const src = 'Some [ ] text\n- [ ] task'
    expect(toggleMarkdownTask(src, 0)).toBe('Some [ ] text\n- [x] task')
  })

  it('returns the text unchanged for an out-of-range index', () => {
    expect(toggleMarkdownTask('- [ ] only', 3)).toBe('- [ ] only')
  })

  it('returns empty text unchanged', () => {
    expect(toggleMarkdownTask('', 0)).toBe('')
  })
})
