<template>
  <div id="nextcloudapptemplate-content" class="section">
    <h2>{{ strings.title }}</h2>

    <!-- Information / quick start -->
    <NcAppSettingsSection :name="strings.infoTitle">
      <p v-html="strings.infoIntro"></p>

      <ol class="ol">
        <li v-for="li in strings.gettingStartedList" :key="li" v-html="li"></li>
      </ol>

      <NcNoteCard type="info">
        <p v-html="strings.tipsNote"></p>
      </NcNoteCard>
    </NcAppSettingsSection>

    <!-- Live examples -->
    <NcAppSettingsSection :name="strings.examplesHeader">
      <section class="example-grid">
        <!-- v-model example -->
        <div class="card">
          <h3 class="card-title">{{ strings.nameInputHeader }}</h3>
          <NcTextField
            v-model="name"
            :label="strings.nameInputLabel"
            :placeholder="strings.nameInputPlaceholder"
          />
          <p class="mt-8">
            {{ strings.livePreview }} <b>{{ greeting }}</b>
          </p>
        </div>

        <!-- Select + computed example -->
        <div class="card">
          <h3 class="card-title">{{ strings.themeHeader }}</h3>
          <NcSelect
            v-model="themeLabel"
            :options="themeOptionsLabels"
            :input-label="strings.themeLabel"
          />
          <p class="mt-8">
            {{ strings.themePreview }}
            <code>{{ activeTheme.value }}</code>
          </p>
        </div>

        <!-- Counter + events example -->
        <div class="card">
          <h3 class="card-title">{{ strings.counterHeader }}</h3>
          <div class="row gap-8">
            <NcButton @click="decrement">{{ strings.minus }}</NcButton>
            <span class="counter">{{ counter }}</span>
            <NcButton @click="increment">{{ strings.plus }}</NcButton>
          </div>
        </div>
      </section>
    </NcAppSettingsSection>

    <!-- Table + add/remove items example -->
    <NcAppSettingsSection :name="strings.itemsHeader">
      <div class="row align-start gap-16">
        <div style="max-width: 320px">
          <NcTextField
            v-model="newItem"
            :label="strings.newItemLabel"
            :placeholder="strings.newItemPlaceholder"
            trailing-button-icon="plus"
            :show-trailing-button="newItem.trim() !== ''"
            @trailing-button-click="addItem"
          />
        </div>
        <NcButton @click="addItem" :disabled="newItem.trim() === ''">{{ strings.add }}</NcButton>
        <NcButton type="secondary" @click="clearItems" :disabled="items.length === 0">
          {{ strings.clear }}
        </NcButton>
      </div>

      <table class="mt-16">
        <thead>
          <tr>
            <th style="width: 60%">{{ strings.tableItem }}</th>
            <th style="width: 40%">{{ strings.tableActions }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in items" :key="item.id">
            <td>
              <input class="inline-input" :aria-label="strings.editItemAria" v-model="item.label" />
            </td>
            <td>
              <div class="row gap-8">
                <NcButton type="tertiary" @click="duplicate(idx)">{{ strings.duplicate }}</NcButton>
                <NcButton type="error" @click="remove(idx)">{{ strings.remove }}</NcButton>
              </div>
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="2" class="muted">{{ strings.noItems }}</td>
          </tr>
        </tbody>
      </table>
    </NcAppSettingsSection>

    <!-- Backend calls example -->
    <NcAppSettingsSection :name="strings.backendHeader">
      <form @submit.prevent @submit="save">
        <div class="row gap-16 align-center">
          <NcButton @click="fetchHello" :disabled="loading">{{ strings.fetchHello }}</NcButton>
          <NcButton :disabled="loading" @click="submit">{{ strings.save }}</NcButton>

          <span>
            <span v-if="loading">{{ strings.loading }}</span>
            <span v-else-if="lastHelloAt">
              {{ strings.lastHelloAt }}
              <NcDateTime :timestamp="lastHelloAt.valueOf()" />
            </span>
            <span v-else class="muted">{{ strings.never }}</span>
          </span>
        </div>
      </form>

      <NcNoteCard v-if="serverMessage" type="success" class="mt-12">
        <p>
          {{ strings.serverSaid }} <code>{{ serverMessage }}</code>
        </p>
      </NcNoteCard>
    </NcAppSettingsSection>
  </div>
</template>

<script>
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'

export default {
  name: 'HelloWorld',
  components: {
    NcAppSettingsSection,
    NcButton,
    NcDateTime,
    NcNoteCard,
    NcSelect,
    NcTextField,
  },
  data() {
    return {
      // UI state
      loading: false,

      // Example: simple input
      name: '',

      // Example: select with label <-> value mapping (like your intervals)
      themeLabel: null,
      themeOptions: [
        { label: t('nextcloudapptemplate', 'Light'), value: 'light' },
        { label: t('nextcloudapptemplate', 'Dark'), value: 'dark' },
        {
          label: n('nextcloudapptemplate', 'System (1 option)', 'System (%n options)', 2),
          value: 'system',
        },
      ],

      // Example: small counter
      counter: 0,

      // Example: simple items table
      items: [],
      newItem: '',

      // Example: tracking server interactions
      lastHelloAt: null,
      serverMessage: '',

      // All user-visible strings go here
      strings: {
        // Titles / headers
        title: t('nextcloudapptemplate', 'Hello World — App Template'),
        infoTitle: t('nextcloudapptemplate', 'Information'),
        examplesHeader: t('nextcloudapptemplate', 'Quick Examples'),
        itemsHeader: t('nextcloudapptemplate', 'Editable List'),
        backendHeader: t('nextcloudapptemplate', 'Backend Calls'),

        // Info
        infoIntro: t(
          'nextcloudapptemplate',
          'This view shows {bStart}small, focused examples{bEnd} for inputs, lists, selections, and backend calls.',
          { bStart: '<b>', bEnd: '</b>' },
          undefined,
          { escape: false },
        ),

        gettingStartedList: [
          t(
            'nextcloudapptemplate',
            'Import UI parts from {cStart}@nextcloud/vue{cEnd} and wire them with {cStart}v-model{cEnd}.',
            { cStart: '<code>', cEnd: '</code>' },
            undefined,
            { escape: false },
          ),
          t(
            'nextcloudapptemplate',
            'Use {cStart}axios{cEnd} for API calls; return OCS data as needed.',
            { cStart: '<code>', cEnd: '</code>' },
            undefined,
            { escape: false },
          ),
          t(
            'nextcloudapptemplate',
            'Keep user-facing text in a central {cStart}strings{cEnd} object with {cStart}t/n{cEnd}.',
            { cStart: '<code>', cEnd: '</code>' },
            undefined,
            { escape: false },
          ),
        ],

        tipsNote: t(
          'nextcloudapptemplate',
          'Pro tip: keep labels in {cStart}label{cEnd} and values in {cStart}value{cEnd} to simplify mapping.',
          { cStart: '<code>', cEnd: '</code>' },
          undefined,
          { escape: false },
        ),

        // Name example
        nameInputHeader: t('nextcloudapptemplate', 'Your Name'),
        nameInputLabel: t('nextcloudapptemplate', 'Name'),
        nameInputPlaceholder: t('nextcloudapptemplate', 'e.g. Ada Lovelace'),
        livePreview: t('nextcloudapptemplate', 'Live preview:'),

        // Theme example
        themeHeader: t('nextcloudapptemplate', 'Theme'),
        themeLabel: t('nextcloudapptemplate', 'Choose a theme'),
        themePreview: t('nextcloudapptemplate', 'Active value:'),

        // Counter example
        counterHeader: t('nextcloudapptemplate', 'Counter'),
        plus: t('nextcloudapptemplate', '+1'),
        minus: t('nextcloudapptemplate', '-1'),

        // Items table
        newItemLabel: t('nextcloudapptemplate', 'New item'),
        newItemPlaceholder: t('nextcloudapptemplate', 'e.g. Hello item'),
        add: t('nextcloudapptemplate', 'Add'),
        clear: t('nextcloudapptemplate', 'Clear'),
        tableItem: t('nextcloudapptemplate', 'Item'),
        tableActions: t('nextcloudapptemplate', 'Actions'),
        editItemAria: t('nextcloudapptemplate', 'Edit item'),
        duplicate: t('nextcloudapptemplate', 'Duplicate'),
        remove: t('nextcloudapptemplate', 'Remove'),
        noItems: t('nextcloudapptemplate', 'No items yet'),

        // Backend
        fetchHello: t('nextcloudapptemplate', 'Fetch Hello'),
        save: t('nextcloudapptemplate', 'Save'),
        loading: t('nextcloudapptemplate', 'Loading…'),
        lastHelloAt: t('nextcloudapptemplate', 'Last hello at:'),
        never: t('nextcloudapptemplate', 'Never'),
        serverSaid: t('nextcloudapptemplate', 'Server said:'),
      },
    }
  },
  created() {
    // Load initial data if you want
    this.fetchHello()
  },
  computed: {
    // Map selected theme label -> full option
    activeTheme() {
      return this.themeOptions.find((x) => x.label === this.themeLabel) ?? this.themeOptions[0]
    },
    // Convenience list for NcSelect (labels only)
    themeOptionsLabels() {
      return this.themeOptions.map((x) => x.label)
    },
    // Live greeting preview (reacts to "name")
    greeting() {
      return this.name.trim() ? `Hello, ${this.name.trim()}!` : 'Hello!'
    },
  },
  methods: {
    // Counter handlers
    increment() {
      this.counter++
    },
    decrement() {
      this.counter--
    },

    // Items handlers
    addItem() {
      const label = this.newItem.trim()
      if (!label) return
      this.items.push({ id: cryptoRandom(), label })
      this.newItem = ''
    },
    duplicate(index) {
      const src = this.items[index]
      if (!src) return
      this.items.splice(index + 1, 0, { id: cryptoRandom(), label: src.label })
    },
    remove(index) {
      this.items.splice(index, 1)
    },
    clearItems() {
      this.items = []
    },

    // Backend examples (adjust endpoints to your app’s routes)
    async fetchHello() {
      try {
        this.loading = true
        // Example GET -> /hello  (expects: { ocs: { data: { message: string, at: string }}})
        const resp = await ocs.get('/hello')
        this.serverMessage = resp.data.message ?? '👋'
        // If backend returns ISO date strings, store a Date instance
        if (resp.data.at) this.lastHelloAt = new Date(resp.data.at)
      } catch (e) {
        console.error('Failed to fetch hello', e)
      } finally {
        this.loading = false
      }
    },
    async save() {
      try {
        this.loading = true
        // Example POST -> /hello  (send minimal payload)
        const payload = {
          name: this.name.trim() || null,
          theme: this.activeTheme.value,
          items: this.items.map((x) => x.label),
          counter: this.counter,
        }
        const resp = await ocs.post('/hello', { data: payload })
        // Update preview/message
        if (resp.data.message) this.serverMessage = resp.data.message
        if (resp.data.at) this.lastHelloAt = new Date(resp.data.at)
      } catch (e) {
        console.error('Failed to save hello', e)
      } finally {
        this.loading = false
      }
    },
  },
}

/** Small helper for local IDs (no crypto dep) */
function cryptoRandom() {
  return Math.random().toString(36).slice(2, 10)
}
</script>

<style scoped lang="scss">
#nextcloudapptemplate-content {
  h2:first-child {
    margin-top: 0;
  }

  .mt-8 {
    margin-top: 8px;
  }

  .mt-12 {
    margin-top: 12px;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .row {
    display: flex;

    &.align-start {
      align-items: flex-start;
    }

    &.align-center {
      align-items: center;
    }

    &.gap-8 {
      gap: 8px;
    }

    &.gap-16 {
      gap: 16px;
    }
  }

  .example-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
  }

  .card {
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 12px;
  }

  .card-title {
    margin: 0 0 8px 0;
    font-size: 1rem;
    font-weight: 600;
  }

  .counter {
    min-width: 3ch;
    text-align: center;
    font-variant-numeric: tabular-nums;
  }

  .inline-input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-main-background);
    color: var(--color-main-text);
  }

  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .ol {
    padding-left: 2.5em;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--color-border);
    margin-top: 8px;

    tr:not(:last-child),
    thead tr {
      border-bottom: 1px solid var(--color-border);
    }

    thead,
    tbody tr {
      display: table;
      width: 100%;
      table-layout: fixed;
    }

    td,
    th {
      padding: 6px 8px;
      vertical-align: middle;
    }
  }
}
</style>
