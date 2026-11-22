import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createIconMock, nextcloudL10nMock } from '@/test-utils'

const { stateRef, markSeen } = vi.hoisted(() => ({
  stateRef: { current: null as { seenVersion: string; installedVersion: string } | null },
  markSeen: vi.fn(() => Promise.resolve('0.41.0')),
}))

vi.mock('@nextcloud/l10n', () => nextcloudL10nMock)
vi.mock('@nextcloud/initial-state', () => ({
  loadState: (_app: string, _key: string, fallback: unknown) => stateRef.current ?? fallback,
}))
vi.mock('@/api/prefs', () => ({ markWhatsNewSeen: markSeen }))
vi.mock('@/releaseNotes', () => ({
  releaseNotesByVersion: { '0.40.0': 'Forty notes', '0.41.0': 'Forty-one notes' },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
  default: {
    name: 'NcDialog',
    props: ['open', 'name'],
    emits: ['update:open'],
    template: '<div v-if="open" class="dialog"><slot /><slot name="actions" /></div>',
  },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: { name: 'NcButton', template: '<button><slot /></button>' },
}))
vi.mock('@nextcloud/vue/components/NcRichText', () => ({
  default: { name: 'NcRichText', props: ['text'], template: '<div class="notes">{{ text }}</div>' },
}))
vi.mock('@icons/LightbulbOutline.vue', () => createIconMock('LightbulbIcon'))

async function mountDialog() {
  vi.resetModules()
  const { default: WhatsNewDialog } = await import('./WhatsNewDialog.vue')
  return mount(WhatsNewDialog)
}

describe('WhatsNewDialog', () => {
  beforeEach(() => {
    markSeen.mockClear()
  })

  it('shows every version since the last one seen, newest first, with the smart input step', async () => {
    stateRef.current = { seenVersion: '0.39.0', installedVersion: '0.41.0' }
    const wrapper = await mountDialog()

    const versions = wrapper.findAll('[data-testid="whats-new-version"]')
    expect(versions.map((v) => v.find('h3').text())).toEqual(['Pantry 0.41.0', 'Pantry 0.40.0'])
    expect(versions[1]?.text()).toContain('Type the amount with the item')
    expect(versions[0]?.text()).not.toContain('Type the amount with the item')
  })

  it('stays closed when the installed version was already seen', async () => {
    stateRef.current = { seenVersion: '0.41.0', installedVersion: '0.41.0' }
    const wrapper = await mountDialog()
    expect(wrapper.find('.dialog').exists()).toBe(false)
  })

  it('records the version as seen when dismissed', async () => {
    stateRef.current = { seenVersion: '0.40.0', installedVersion: '0.41.0' }
    const wrapper = await mountDialog()

    await wrapper.find('[data-testid="whats-new-dismiss"]').trigger('click')
    await flushPromises()

    expect(markSeen).toHaveBeenCalledOnce()
    expect(wrapper.find('.dialog').exists()).toBe(false)
  })
})
