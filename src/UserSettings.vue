<template>
  <div id="autocurrency-content" class="section">
    <h2>{{ strings.title }}</h2>
    <NcAppSettingsSection id="autocurrency-info" :name="strings.infoTitle">
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
            v-model="selectedProject"
            :options="projects"
            :option-value="'id'"
            :option-label="'name'"
            :return-object="true"
            :input-label="strings.projectLabel"
            :disabled="loading || projectsLoading"
            required
          />

          <!-- Currency -->
          <NcSelect
            v-model="selectedCurrency"
            :options="currencyOptions"
            :option-value="'id'"
            :option-label="'label'"
            :return-object="true"
            :input-label="strings.currencyLabel"
            :disabled="loading || projectsLoading || !currencyOptions.length"
            required
          />

          <!-- Date range -->
          <label>
            {{ strings.from }}
            <NcDateTimePicker v-model="dateFrom" type="date" :max="dateTo || todayDate" />
          </label>
          <label>
            {{ strings.to }}
            <NcDateTimePicker v-model="dateTo" type="date" :max="todayDate" />
          </label>

          <!-- Reverse toggle -->
          <NcCheckboxRadioSwitch v-model="showReversed">
            {{ strings.showReversed }}
          </NcCheckboxRadioSwitch>
        </div>

        <div class="chart-wrap">
          <canvas ref="historyCanvas" height="120"></canvas>
        </div>
      </div>
    </NcAppSettingsSection>
  </div>
</template>

<script lang="ts">
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { APP_ID } from '@/consts'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { isValid } from 'date-fns'
import { parseISO as parseDate } from 'date-fns/parseISO'
import { format as formatDateFns } from 'date-fns/format'
import { Chart } from 'chart.js/auto'

type SupportedCurrency = { code: string; symbol: string; name: string }
type Project = {
  id: string
  name: string
  baseCurrency: string
  currencies: string[]
  label?: string
}
type CurrencyOption = { id: string; label: string }
type HistoryPoint = { x: string; y: number }

export default {
  name: 'App',
  components: {
    NcAppSettingsSection,
    NcNoteCard,
    NcSelect,
    NcTextField,
    NcDateTimePicker,
    NcCheckboxRadioSwitch,
  },
  data() {
    const today = new Date()
    const oneMonthAgo = new Date(today)
    oneMonthAgo.setMonth(today.getMonth() - 1)

    return {
      loading: true as boolean,
      supportedCurrencies: [] as SupportedCurrency[],
      currencySearch: '' as string,
      projectsLoading: true as boolean,
      projects: [] as Project[],
      selectedProject: null as Project | null,
      selectedCurrency: null as CurrencyOption | null,
      dateFrom: oneMonthAgo as Date,
      dateTo: today as Date,
      todayDate: today as Date,
      chart: null as Chart<'line', number[], string> | null,
      historyPoints: [] as HistoryPoint[],
      historyReqId: 0 as number,
      showReversed: false as boolean,
      strings: {
        title: t(APP_ID, 'Auto Currency for Cospend'),
        infoTitle: t(APP_ID, 'Information'),
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
        exampleHeader: t(APP_ID, 'Example names:'),
        supportedCurrencies: t(APP_ID, 'Supported currencies:'),
        currencySearchLabel: t(APP_ID, 'Search'),
        currencySearchPlaceholder: t(APP_ID, 'e.g. $, USD, US Dollar'),
        tableSymbol: t(APP_ID, 'Symbol'),
        tableCode: t(APP_ID, 'Code'),
        tableName: t(APP_ID, 'Name'),
        historyHeader: t(APP_ID, 'Exchange rate history'),
        projectLabel: t(APP_ID, 'Project'),
        currencyLabel: t(APP_ID, 'Currency'),
        from: t(APP_ID, 'From'),
        to: t(APP_ID, 'To'),
        showReversed: t(APP_ID, 'Flip conversion'),
        chartTime: t(APP_ID, 'Time'),
        chartDash: t(APP_ID, '-'),
      } as const,
    }
  },
  created() {
    this.fetchSettings()
    this.fetchProjects()
  },
  watch: {
    selectedProject() {
      const currencyChanged = this.resetCurrencyForProject()
      if (this.selectedProject && this.selectedCurrency && !currencyChanged) {
        this.fetchHistory() // project changed, currency remained same -> refetch
      }
    },
    selectedCurrency() {
      if (this.selectedProject && this.selectedCurrency) {
        this.fetchHistory()
      }
    },
    dateFrom(val) {
      if (this.selectedProject && this.selectedCurrency) this.fetchHistory()
    },
    dateTo(val) {
      if (this.selectedProject && this.selectedCurrency) this.fetchHistory()
    },
    showReversed() {
      this.$nextTick(() => this.renderChart())
    },
  },
  methods: {
    formatDate(value: Date | string | null | undefined): string {
      if (!value) return ''
      const d =
        value instanceof Date
          ? value
          : typeof value === 'string'
          ? parseDate(value)
          : new Date(value as any)

      if (!isValid(d)) {
        console.warn('Invalid date received:', value)
        return ''
      }
      return formatDateFns(d, 'yyyy-MM-dd')
    },
    findCurrency(query: string | null | undefined) {
      const raw = String(query ?? '').trim()
      if (!raw) return undefined
      const uc = raw.toUpperCase()
      return this.supportedCurrencies.find((c) => c.code.toUpperCase() === uc || c.symbol === raw)
    },
    async fetchSettings() {
      try {
        this.loading = true
        const resp = await ocs.get('/user-settings')
        const data = resp.data
        this.loading = false

        this.supportedCurrencies = (data.supported_currencies ?? []).sort(
          (a: SupportedCurrency, b: SupportedCurrency) => a.code.localeCompare(b.code),
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
        this.projects = (data.projects ?? []).map((p: any) => ({
          ...p,
          label: p?.name && String(p.name).trim() !== '' ? p.name : p.id,
        }))

        // If nothing selected yet, pick the first project
        if (!this.selectedProject && this.projects.length) {
          this.selectedProject = this.projects[0]!
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
      if (!this.selectedProject || !this.selectedCurrency) return
      const myReq = ++this.historyReqId
      try {
        const params = {
          projectId: this.selectedProject.id,
          currency: this.selectedCurrency.id.toLowerCase(),
          from: this.formatDate(this.dateFrom),
          to: this.formatDate(this.dateTo),
        }
        const resp = await ocs.get<{
          points: { fetchedAt: string; rate: number }[]
        }>('/history', { params })

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
      const canvas = this.$refs.historyCanvas as HTMLCanvasElement | undefined
      if (!canvas) return

      const labels = this.historyPoints.map((p) =>
        formatDateFns(parseDate(p.x), 'yyyy-MM-dd HH:mm'),
      )

      const rValues = this.historyPoints.map((p) => {
        const r = Number(p.y)
        return isFinite(r) && r > 0 ? r : NaN
      })

      const primaryData = this.showReversed ? rValues.map((r) => 1 / r) : rValues
      const fromCode = this.showReversed ? this.baseCode : this.selectedCode
      const toCode = this.showReversed ? this.selectedCode : this.baseCode

      const dsLabel = t(APP_ID, 'Rate ({dir})', { dir: `${fromCode}→${toCode}` })

      if (this.chart) {
        this.chart.destroy()
        this.chart = null
      }

      const fmt = (n: number) =>
        new Intl.NumberFormat(undefined, { useGrouping: false, maximumFractionDigits: 12 }).format(
          n,
        )

      const ctx = canvas.getContext('2d')!
      this.chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: dsLabel,
              data: primaryData,
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
                label: (tt) => {
                  const primary = typeof tt.raw === 'number' ? tt.raw : tt.parsed.y
                  if (!isFinite(primary) || primary <= 0) {
                    return t(APP_ID, '{label}: {value}', {
                      label: tt.dataset.label ?? '',
                      value: this.strings.chartDash,
                    })
                  }
                  const inverse = 1 / primary

                  const header = t(APP_ID, '{label}: {value}', {
                    label: tt.dataset.label ?? '',
                    value: fmt(primary),
                  })
                  const linePrimary = t(APP_ID, '1 {from} = {value} {to}', {
                    value: fmt(primary),
                    from: fromCode,
                    to: toCode,
                  })
                  const lineSecondary = t(APP_ID, '1 {from} = {value} {to}', {
                    value: fmt(inverse),
                    from: toCode,
                    to: fromCode,
                  })

                  return [header, linePrimary, lineSecondary]
                },
              },
            },
          },
          scales: {
            x: { title: { display: true, text: this.strings.chartTime } },
            y: {
              title: {
                display: true,
                text: t(APP_ID, '{from} per {to}', {
                  from: toCode,
                  to: fromCode,
                }),
              },
              beginAtZero: false,
            },
          },
        },
      })
    },

    // Pick the first allowed currency for the selected project
    resetCurrencyForProject(): boolean {
      const p = this.projects.find((pr) => String(pr.id) === String(this.selectedProject?.id))
      if (!p) {
        this.selectedCurrency = null
        return true
      }

      const options = this.currencyOptions
      if (!options.length) {
        this.selectedCurrency = null
        return true
      }

      const currentId = this.selectedCurrency?.id
      const stillValid = currentId && options.some((opt) => opt.id === currentId)

      if (stillValid) {
        return false
      }

      this.selectedCurrency = options[0]!
      return true
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
    currencyOptions() {
      const p = this.projects.find((pr) => String(pr.id) === String(this.selectedProject?.id))
      if (!p || !Array.isArray(p.currencies)) return []
      return p.currencies.map((code) => {
        const sc = this.findCurrency(code)
        if (sc) return { id: sc.code.toLowerCase(), label: `${sc.code} (${sc.symbol})` }
        const c = String(code).toUpperCase()
        return { id: c.toLowerCase(), label: c }
      })
    },
    baseCode(): string {
      const s = this.findCurrency(this.selectedProject?.baseCurrency)
      const raw = String(this.selectedProject?.baseCurrency ?? '')
        .trim()
        .toUpperCase()
      return s?.code ?? raw
    },
    selectedCode(): string {
      const s = this.findCurrency(this.selectedCurrency?.id)
      const raw = String(this.selectedCurrency?.id ?? '')
        .trim()
        .toUpperCase()
      return s?.code ?? raw
    },
    baseSymbol(): string {
      const s = this.findCurrency(this.baseCode)
      return s?.symbol ?? this.baseCode
    },
    selectedSymbol(): string {
      const s = this.findCurrency(this.selectedCode)
      return s?.symbol ?? this.selectedCode
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
