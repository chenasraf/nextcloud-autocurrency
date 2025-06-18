<template>
  <div id="autocurrency-content" class="section">
    <h2>{{ strings.title }}</h2>
    <NcAppSettingsSection :name="strings.infoTitle">
      <p>
        {{ strings.info }}
      </p>

      <p v-html="strings.requirements"></p>

      <ol class="ol">
        <li v-for="li in strings.requirementsList" v-html="li"></li>
      </ol>

      <NcNoteCard type="info">
        <p v-html="strings.infoNote"></p>
      </NcNoteCard>

      <p>{{ strings.exampleHeader }}</p>

      <ul>
        <li>✅ <code>$</code></li>
        <li>✅ <code>USD</code></li>
        <li>✅ <code>$ USD</code></li>
        <li>❌ <code>US Dollar</code></li>
        <li>❌ <code>United States Dollar</code></li>
      </ul>

      <div class="currency-list">
        <p>{{ strings.supportedCurrencies }}</p>

        <div style="max-width: 300px">
          <NcTextField
            v-model="currencySearch"
            :label="strings.currencySearchLabel"
            trailing-button-icon="close"
            :placeholder="strings.currencySearchPlaceholder"
            :show-trailing-button="currencySearch !== ''"
            @trailing-button-click="clearCurrencySearch"
          />
        </div>

        <table>
          <thead>
            <tr>
              <th>{{ strings.tableSymbol }}</th>
              <th>{{ strings.tableCode }}</th>
              <th>{{ strings.tableName }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="currency in currencies" :key="currency.code">
              <td>{{ currency.symbol }}</td>
              <td>{{ currency.code }}</td>
              <td>{{ currency.name }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </NcAppSettingsSection>

    <NcAppSettingsSection :name="strings.cronSettingsHeader">
      <section>
        <form @submit.prevent @submit="save">
          <div class="cron-flex">
            <NcSelect
              v-model="interval"
              :options="intervals"
              :input-label="strings.intervalLabel"
              required
              :disabled="loading"
            />

            <div class="cron-last-update-container">
              <NcButton @click="doCron" :disabled="loading">{{ strings.fetchNow }}</NcButton>

              <div>
                {{ strings.lastFetched }}
                <span v-if="loading">{{ strings.loading }}</span>
                <span v-if="!loading && !lastUpdate">{{ strings.never }}</span>
                <NcDateTime v-if="!loading && lastUpdate" :timestamp="lastUpdate.valueOf()" />
              </div>
            </div>
          </div>
          <div class="submit-buttons">
            <NcButton native-type="submit">{{ strings.save }}</NcButton>
          </div>
        </form>
      </section>
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

import axios from '@nextcloud/axios'
import { t, n } from '@nextcloud/l10n'
import { parseISO as parseDate } from 'date-fns/parseISO'
import { format as formatDate } from 'date-fns/format'

export default {
  name: 'App',
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
      loading: true,
      interval: null,
      lastUpdate: null,
      intervalOptions: [
        { label: t('autocurrency', 'Every hour'), value: 1 },
        { label: n('autocurrency', 'Every %n hour', 'Every %n hours', 3), value: 3 },
        { label: n('autocurrency', 'Every %n hour', 'Every %n hours', 6), value: 6 },
        { label: n('autocurrency', 'Every %n hour', 'Every %n hours', 9), value: 9 },
        { label: n('autocurrency', 'Every %n hour', 'Every %n hours', 12), value: 12 },
        {
          label: n('autocurrency', 'Every %n hour (default)', 'Every %n hours (default)', 24),
          value: 24,
        },
      ],
      supportedCurrencies: [],
      currencySearch: '',
      strings: {
        title: t('autocurrency', 'Auto Currency for Cospend'),
        infoTitle: t('autocurrency', 'Information'),
        info: t(
          'autocurrency',
          'To make sure your currencies are found for the rates to be updated, please ensure your ' +
            'currencies are named appropriately.',
        ),
        requirements: t(
          'autocurrency',
          'Currency names must contain {bStart}at least one of{bEnd}:',
          { bStart: '<b>', bEnd: '</b>' },
          undefined,
          { escape: false },
        ),
        requirementsList: [
          t(
            'autocurrency',
            'The currency symbol - e.g. {cStart}${cEnd}, {cStart}€{cEnd}, {cStart}£{cEnd}',
            { cStart: '<code>', cEnd: '</code>' },
            undefined,
            { escape: false },
          ),
          t(
            'autocurrency',
            'The currency code - e.g. {cStart}USD{cEnd}, {cStart}EUR{cEnd}, {cStart}GBP{cEnd} (case-insensitive)',
            { cStart: '<code>', cEnd: '</code>' },
            undefined,
            { escape: false },
          ),
        ],
        infoNote: t(
          'autocurrency',
          'The naming rules apply for both main &amp; additional currencies.',
          undefined,
          undefined,
          { escape: false },
        ),
        cronSettingsHeader: t('autocurrency', 'Cron Settings'),
        exampleHeader: t('autocurrency', 'Example names:'),
        supportedCurrencies: t('autocurrency', 'Supported currencies:'),
        currencySearchLabel: t('autocurrency', 'Search'),
        currencySearchPlaceholder: t('autocurrency', 'e.g. $, USD, US Dollar'),
        intervalLabel: t('autocurrency', 'Currency conversion rate update interval'),
        tableSymbol: t('autocurrency', 'Symbol'),
        tableCode: t('autocurrency', 'Code'),
        tableName: t('autocurrency', 'Name'),
        fetchNow: t('autocurrency', 'Fetch Rates Now'),
        lastFetched: t('autocurrency', 'Rates last fetched:'),
        loading: t('autocurrency', 'Loading…'),
        never: t('autocurrency', 'Never'),
        save: t('autocurrency', 'Save'),
      },
    }
  },
  created() {
    this.fetchSettings()
  },
  methods: {
    async fetchSettings() {
      try {
        this.loading = true
        const resp = await axios.get('/cron')
        const data = resp.data.ocs.data
        this.loading = false
        console.debug('[DEBUG] Auto Currency settings fetched', data)

        const interval = this.getIntervalByValue(data.interval)
        if (interval) {
          console.debug('[DEBUG] Interval found', interval)
          this.interval = interval.label
        } else {
          console.warn('Invalid interval value', data.interval)
        }

        if (data.last_update) {
          const lastUpdate = parseDate(data.last_update, new Date())
          this.lastUpdate = lastUpdate
        }

        this.supportedCurrencies = data.supported_currencies.sort((a, b) =>
          a.code.localeCompare(b.code),
        )
      } catch (e) {
        console.error('Failed to fetch Auto Currency settings', e)
      }
    },
    getIntervalByValue(value) {
      return this.intervalOptions.find((x) => x.value === value)
    },
    getIntervalByLabel(label) {
      return this.intervalOptions.find((x) => x.label === label)
    },
    async doCron() {
      try {
        const resp = await axios.post('/cron/run')
        const data = resp.data.ocs.data
        console.debug('[DEBUG] Cron executed', data)
        this.fetchSettings()
      } catch (e) {
        console.error('Failed to run cron', e)
      }
    },
    clearCurrencySearch() {
      this.currencySearch = ''
    },
    async save() {
      try {
        this.loading = true
        const interval = this.getIntervalByLabel(this.interval)?.value ?? 24
        const resp = await axios.put('/cron', { data: { interval } })
        const data = resp.data.ocs.data
        this.loading = false
        console.debug('[DEBUG] Auto Currency settings saved', data)
        this.fetchSettings()
      } catch (e) {
        console.error('Failed to update Auto Currency settings', e)
      }
    },
  },
  computed: {
    intervals() {
      return this.intervalOptions.map((x) => x.label)
    },
    currencies() {
      if (!this.supportedCurrencies) {
        return []
      }
      if (!this.currencySearch) {
        return this.supportedCurrencies
      }

      return this.supportedCurrencies.filter((currency) => {
        return [
          currency.symbol.toLowerCase().includes(this.currencySearch.toLowerCase()),
          currency.code.toLowerCase().includes(this.currencySearch.toLowerCase()),
          currency.name.toLowerCase().includes(this.currencySearch.toLowerCase()),
        ].some(Boolean)
      })
    },
  },
}
</script>

<style scoped lang="scss">
#autocurrency-content {
  h2:first-child {
    margin-top: 0;
  }

  .submit-buttons {
    margin-top: 16px;
  }

  .cron-flex {
    display: flex;
    align-items: start;
    gap: 24px;
  }

  .cron-last-update-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  p {
    margin: 0.5em 0;
  }

  ol {
    padding-left: 2.5em;
  }

  ul {
    padding-left: 1em;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--color-border);

    tr:not(:last-child),
    thead tr {
      border-bottom: 1px solid var(--color-border);
    }

    tbody {
      display: block;
      max-height: 300px;
      overflow-y: scroll;
    }

    thead,
    tbody tr {
      display: table;
      width: 100%;
      table-layout: fixed;
    }

    td,
    th {
      padding: 4px 8px;
    }
  }

  .currency-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 2em;
  }
}
</style>
