<template>
  <div id="autocurrency-content" class="section">
    <h2>{{ strings.title }}</h2>
    <NcAppSettingsSection :name="strings.infoTitle">
      <p>{{ strings.info }}</p>

      <p v-html="strings.requirements"></p>

      <ol class="ol">
        <li v-for="li in strings.requirementsList" :key="li" v-html="li"></li>
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

      <div class="history-block">
        <h3>{{ strings.historyHeader }}</h3>

        <div class="history-controls">
          <!-- Project -->
          <NcSelect
            v-model="selectedProjectId"
            :options="projectOptions"
            :option-value="'id'"
            :option-label="'label'"
            :input-label="strings.projectLabel"
            :disabled="loading || projectsLoading"
            required
          />

          <!-- Currency -->
          <NcSelect
            v-model="selectedCurrencyCode"
            :options="currencyOptions"
            :option-value="'id'"
            :return-object="true"
            :option-label="'label'"
            :input-label="strings.currencyLabel"
            :disabled="loading || projectsLoading || !currencyOptions.length"
            required
          />

          <!-- Date range -->
          <label>
            {{ strings.from }}
            <NcDateTimePicker v-model="dateFrom" type="date" :max="dateTo || todayISO" />
          </label>
          <label>
            {{ strings.to }}
            <NcDateTimePicker v-model="dateTo" type="date" :max="todayISO" />
          </label>
        </div>

        <div class="chart-wrap">
          <canvas ref="historyCanvas" height="120"></canvas>
        </div>
      </div>
    </NcAppSettingsSection>
  </div>
</template>

<script>
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'

import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { parseISO as parseDate } from 'date-fns/parseISO'
import { format as formatDate } from 'date-fns/format'
import { Chart } from 'chart.js/auto'

export default {
  name: 'App',
  components: {
    NcAppSettingsSection,
    NcNoteCard,
    NcSelect,
    NcTextField,
    NcDateTimePicker,
  },
  data() {
    const today = new Date()
    const oneMonthAgo = new Date(today)
    oneMonthAgo.setMonth(today.getMonth() - 1)

    const toISODate = (d) => formatDate(d, 'yyyy-MM-dd')
    return {
      loading: true,
      supportedCurrencies: [],
      currencySearch: '',
      projectsLoading: true,
      projects: [],
      selectedProjectId: null,
      selectedCurrencyCode: null,
      dateFrom: toISODate(oneMonthAgo),
      dateTo: toISODate(today),
      todayISO: toISODate(today),
      chart: null,
      historyPoints: [],
      historyReqId: 0,
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
        exampleHeader: t('autocurrency', 'Example names:'),
        supportedCurrencies: t('autocurrency', 'Supported currencies:'),
        currencySearchLabel: t('autocurrency', 'Search'),
        currencySearchPlaceholder: t('autocurrency', 'e.g. $, USD, US Dollar'),
        tableSymbol: t('autocurrency', 'Symbol'),
        tableCode: t('autocurrency', 'Code'),
        tableName: t('autocurrency', 'Name'),
        historyHeader: t('autocurrency', 'Exchange rate history'),
        projectLabel: t('autocurrency', 'Project'),
        currencyLabel: t('autocurrency', 'Currency'),
        from: t('autocurrency', 'From'),
        to: t('autocurrency', 'To'),
      },
    }
  },
  created() {
    this.fetchSettings()
    this.fetchProjects()
  },
  watch: {
    selectedProjectId() {
      this.resetCurrencyForProject()
    },
    selectedCurrencyCode() {
      if (this.selectedProjectId && this.selectedCurrencyCode) {
        this.fetchHistory()
      }
    },
    dateFrom() {
      if (this.selectedProjectId && this.selectedCurrencyCode) {
        this.fetchHistory()
      }
    },
    dateTo() {
      if (this.selectedProjectId && this.selectedCurrencyCode) {
        this.fetchHistory()
      }
    },
  },
  methods: {
    async fetchSettings() {
      try {
        this.loading = true
        const resp = await ocs.get('/user-settings')
        const data = resp.data
        this.loading = false
        console.debug('[DEBUG] Auto Currency settings fetched', data)

        this.supportedCurrencies = (data.supported_currencies ?? []).sort((a, b) =>
          a.code.localeCompare(b.code),
        )
      } catch (e) {
        console.error('Failed to fetch Auto Currency settings', e)
      }
    },

    clearCurrencySearch() {
      this.currencySearch = ''
    },

    async fetchProjects() {
      try {
        this.projectsLoading = true
        const resp = await ocs.get('/projects')
        const data = resp.data ?? {}
        this.projects = data.projects ?? []

        // If nothing selected yet, pick the first project
        if (!this.selectedProjectId && this.projects.length) {
          this.selectedProjectId = String(this.projects[0].id)
        }

        // Ensure a currency is selected for the (new) project
        this.resetCurrencyForProject()
      } catch (e) {
        console.error('Failed to fetch projects', e)
      } finally {
        this.projectsLoading = false
      }
    },

    async fetchHistory() {
      if (!this.selectedProjectId || !this.selectedCurrencyCode) return
      const myReq = ++this.historyReqId
      try {
        const params = {
          projectId: this.selectedProjectId,
          currency: this.selectedCurrencyCode.id.toLowerCase(),
          from: this.dateFrom,
          to: this.dateTo,
        }
        const resp = await ocs.get('/history', { params })

        if (myReq !== this.historyReqId) return

        const payload = resp.data ?? {}
        const points = (payload.points ?? [])
          .filter((p) => p.fetchedAt && p.rate)
          .map((p) => ({ x: p.fetchedAt, y: Number(p.rate) }))

        this.historyPoints = points
        await this.$nextTick()
        this.renderChart()
      } catch (e) {
        console.error('Failed to fetch history', e)
      }
    },

    renderChart() {
      const canvas = this.$refs.historyCanvas
      if (!canvas) return

      const labels = this.historyPoints.map((p) => formatDate(parseDate(p.x), 'yyyy-MM-dd HH:mm'))
      const data = this.historyPoints.map((p) => p.y)
      const label = `${this.selectedCurrencyCode?.label ?? ''} rate`

      if (this.chart) {
        this.chart.destroy()
        this.chart = null
      }

      const ctx = canvas.getContext('2d')
      this.chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label,
              data,
              tension: 0.25,
              pointRadius: 2,
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          plugins: {
            legend: { display: true },
            tooltip: {
              mode: 'index',
              intersect: false,
              callbacks: {
                label: (ctx) => {
                  const dsLabel = ctx.dataset.label ?? ''
                  const y = typeof ctx.raw === 'number' ? ctx.raw : ctx.parsed.y
                  const precise = new Intl.NumberFormat(undefined, {
                    useGrouping: false,
                    maximumFractionDigits: 12,
                  }).format(y)
                  return `${dsLabel}: ${precise}`
                },
              },
            },
          },
          scales: {
            x: { title: { display: true, text: 'Time' } },
            y: { title: { display: true, text: 'Rate' }, beginAtZero: false },
          },
        },
      })
    },

    // Pick the first allowed currency for the selected project
    resetCurrencyForProject() {
      const p = this.projects.find((pr) => String(pr.id) === String(this.selectedProjectId))
      if (!p) {
        this.selectedCurrencyCode = null
        return
      }

      const options = this.currencyOptions
      if (!options.length) {
        this.selectedCurrencyCode = null
        return
      }

      // If current selection is missing/invalid for this project, pick first
      if (
        !this.selectedCurrencyCode ||
        !options.some((opt) => opt.id === this.selectedCurrencyCode.id)
      ) {
        this.selectedCurrencyCode = options[0]
      }
    },
  },
  computed: {
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
    projectOptions() {
      return this.projects.map((p) => ({ id: p.id, label: p.name }))
    },
    currencyOptions() {
      const p = this.projects.find((pr) => String(pr.id) === String(this.selectedProjectId))
      if (!p || !Array.isArray(p.currencies)) return []
      return p.currencies.map((code) => {
        const sc = this.supportedCurrencies.find(
          (s) => s.code.toLowerCase() === String(code).toLowerCase(),
        )
        if (sc) {
          return { id: sc.code.toLowerCase(), label: `${sc.code} (${sc.symbol})` }
        }
        // Fallback if not in supported list
        const c = String(code).toUpperCase()
        return { id: c.toLowerCase(), label: c }
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

  .history-block {
    margin: 1.5em 0;

    .history-controls {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 12px;
      align-items: end;
    }

    .chart-wrap {
      position: relative;
      height: 280px;
      margin-top: 12px;
      border: 1px solid var(--color-border);
      border-radius: 8px;
      padding: 8px;
    }
  }
}
</style>
