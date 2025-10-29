<template>
  <div id="autocurrency-content" class="section">
    <h2>{{ strings.title }}</h2>

    <NcNoteCard type="info">
      <p v-html="strings.instructionsHelp" />
    </NcNoteCard>

    <NcAppSettingsSection id="custom-currencies" :name="strings.customCurrenciesHeader">
      <NcNoteCard type="info">
        <p v-html="strings.customCurrenciesHelp" />
      </NcNoteCard>

      <div class="settings-section">
        <div class="custom-currencies-list">
          <div
            v-for="(currency, index) in customCurrencies"
            :key="currency.tempId || currency.id"
            class="currency-item"
          >
            <div class="currency-fields">
              <div class="field-row">
                <NcTextField
                  v-model="currency.code"
                  :label="strings.currencyCode"
                  :placeholder="strings.currencyCodePlaceholder"
                  required
                  :disabled="loading"
                />
                <NcTextField
                  v-model="currency.symbol"
                  :label="strings.currencySymbol"
                  :placeholder="strings.currencySymbolPlaceholder"
                  :disabled="loading"
                />
              </div>

              <div class="field-row">
                <NcTextField
                  v-model="currency.api_endpoint"
                  :label="strings.apiEndpoint"
                  type="url"
                  :placeholder="strings.apiEndpointPlaceholder"
                  required
                  :disabled="loading"
                />
              </div>

              <div class="field-row">
                <NcTextField
                  v-model="currency.api_key"
                  :label="strings.apiKey"
                  type="password"
                  :placeholder="strings.apiKeyPlaceholder"
                  :disabled="loading"
                />
                <NcTextField
                  v-model="currency.json_path"
                  :label="strings.jsonPath"
                  :placeholder="strings.jsonPathPlaceholder"
                  required
                  :disabled="loading"
                />
              </div>
            </div>

            <NcButton
              type="error"
              @click="removeCurrency(index)"
              :disabled="loading"
              :aria-label="strings.deleteCurrency"
            >
              <template #icon>
                <Delete :size="20" />
              </template>
            </NcButton>
          </div>

          <NcButton @click="addCurrency" :disabled="loading">
            <template #icon>
              <Plus :size="20" />
            </template>
            {{ strings.addCurrency }}
          </NcButton>
        </div>

        <div class="submit-buttons">
          <NcButton type="primary" @click="saveCustomCurrencies" :disabled="loading">
            {{ strings.save }}
          </NcButton>
        </div>
      </div>
    </NcAppSettingsSection>

    <NcAppSettingsSection id="cron-settings" :name="strings.cronSettingsHeader">
      <section>
        <form @submit.prevent="save">
          <div class="settings-section">
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
            <div class="retention-field">
              <NcTextField
                v-model="retentionDays"
                type="number"
                :label="strings.retentionDaysLabel"
                :helper-text="strings.retentionDaysHelp"
                min="0"
                required
                :disabled="loading"
              />
            </div>
            <div class="submit-buttons">
              <NcButton type="submit">{{ strings.save }}</NcButton>
            </div>
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
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Plus from '@icons/Plus.vue'
import Delete from '@icons/Delete.vue'

import { APP_ID } from '@/consts'
import { generateUrl } from '@nextcloud/router'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { parseISO as parseDate } from 'date-fns/parseISO'

export default {
  name: 'App',
  components: {
    NcAppSettingsSection,
    NcButton,
    NcDateTime,
    NcSelect,
    NcNoteCard,
    NcTextField,
    Plus,
    Delete,
  },
  data() {
    return {
      loading: true,
      interval: null,
      retentionDays: 30,
      lastUpdate: null,
      customCurrencies: [],
      originalCustomCurrencies: [],
      tempIdCounter: 0,
      intervalOptions: [
        { label: t(APP_ID, 'Every hour'), value: 1 },
        { label: n(APP_ID, 'Every %n hour', 'Every %n hours', 3), value: 3 },
        { label: n(APP_ID, 'Every %n hour', 'Every %n hours', 6), value: 6 },
        { label: n(APP_ID, 'Every %n hour', 'Every %n hours', 9), value: 9 },
        { label: n(APP_ID, 'Every %n hour', 'Every %n hours', 12), value: 12 },
        {
          label: n(APP_ID, 'Every %n hour (default)', 'Every %n hours (default)', 24),
          value: 24,
        },
      ],
      strings: {
        title: t(APP_ID, 'Auto Currency for Cospend'),
        customCurrenciesHeader: t(APP_ID, 'Custom Currencies'),
        customCurrenciesHelp: t(
          APP_ID,
          `Define custom currencies with their own API endpoints.{br}Use {cStart}{base}{cEnd} in the endpoint URL or JSON path to substitute the project's base currency.{br}The API should return a rate in the base currency (or USD if {cStart}{base}{cEnd} is not used).{br}The API key will be passed in the {cStart}Authorization{cEnd} header as {cStart}Bearer{cEnd} if provided.`,
          { br: '<br />', cStart: '<code>', cEnd: '</code>' },
          undefined,
          { escape: false },
        ),
        currencyCode: t(APP_ID, 'Currency Code'),
        currencyCodePlaceholder: t(APP_ID, 'e.g., BTC'),
        currencySymbol: t(APP_ID, 'Symbol (optional)'),
        currencySymbolPlaceholder: t(APP_ID, 'e.g., ₿'),
        apiEndpoint: t(APP_ID, 'API Endpoint'),
        apiEndpointPlaceholder: t(APP_ID, 'e.g., https://api.example.com/rates/{base}'),
        apiKey: t(APP_ID, 'API Key (optional)'),
        apiKeyPlaceholder: t(APP_ID, 'Leave empty if not required'),
        jsonPath: t(APP_ID, 'JSON Path'),
        jsonPathPlaceholder: t(APP_ID, 'e.g., $.rates.{base} or data[0].rate'),
        addCurrency: t(APP_ID, 'Add Currency'),
        deleteCurrency: t(APP_ID, 'Delete Currency'),
        cronSettingsHeader: t(APP_ID, 'Cron Settings'),
        intervalLabel: t(APP_ID, 'Update Interval'),
        instructionsHelp: t(
          APP_ID,
          'See the {aStart}Personal settings{aEnd} to view instructions on how to set up your currencies.',
          {
            aStart: `<a href="${generateUrl('/settings/user/autocurrency')}">`,
            aEnd: '</a>',
          },
          undefined,
          { escape: false },
        ),
        fetchNow: t(APP_ID, 'Fetch Rates Now'),
        lastFetched: t(APP_ID, 'Rates last fetched:'),
        loading: t(APP_ID, 'Loading…'),
        never: t(APP_ID, 'Never'),
        save: t(APP_ID, 'Save'),
        retentionDaysLabel: t(APP_ID, 'History Retention (days)'),
        retentionDaysHelp: t(
          APP_ID,
          'Number of days to keep currency history. Set to 0 for no limit (default: 30)',
        ),
      },
    }
  },
  created() {
    this.fetchSettings()
    this.fetchCustomCurrencies()
  },
  methods: {
    async fetchSettings() {
      try {
        this.loading = true
        const resp = await ocs.get('/settings')
        const data = resp.data
        this.loading = false
        console.debug('[DEBUG] Auto Currency settings fetched', data)

        const interval = this.getIntervalByValue(data.interval)
        if (interval) {
          console.debug('[DEBUG] Interval found', interval)
          this.interval = interval.label
        } else {
          console.warn('Invalid interval value', data.interval)
        }

        if (data.retention_days !== undefined) {
          this.retentionDays = data.retention_days
        }

        if (data.last_update) {
          const lastUpdate = parseDate(data.last_update, new Date())
          this.lastUpdate = lastUpdate
        }
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
        const resp = await ocs.post('/cron/run')
        const data = resp.data
        console.debug('[DEBUG] Cron executed', data)
        this.fetchSettings()
      } catch (e) {
        console.error('Failed to run cron', e)
      }
    },
    async save() {
      try {
        this.loading = true
        const interval = this.getIntervalByLabel(this.interval)?.value ?? 24
        const retentionDays = this.retentionDays ?? 30
        const resp = await ocs.put('/settings', {
          data: {
            interval,
            retention_days: retentionDays,
          },
        })
        const data = resp.data
        this.loading = false
        console.debug('[DEBUG] Auto Currency settings saved', data)
        this.fetchSettings()
      } catch (e) {
        console.error('Failed to update Auto Currency settings', e)
      }
    },
    async fetchCustomCurrencies() {
      try {
        this.loading = true
        const resp = await ocs.get('/custom-currencies')
        this.customCurrencies = resp.data.currencies.map((c) => ({ ...c }))
        this.originalCustomCurrencies = JSON.parse(JSON.stringify(resp.data.currencies))
        this.loading = false
        console.debug('[DEBUG] Custom currencies fetched', this.customCurrencies)
      } catch (e) {
        console.error('Failed to fetch custom currencies', e)
        this.loading = false
      }
    },
    addCurrency() {
      this.customCurrencies.push({
        tempId: `temp-${this.tempIdCounter++}`,
        code: '',
        symbol: '',
        api_endpoint: '',
        api_key: '',
        json_path: '',
      })
    },
    removeCurrency(index) {
      this.customCurrencies.splice(index, 1)
    },
    async saveCustomCurrencies() {
      try {
        this.loading = true

        // Determine what needs to be created, updated, or deleted
        const toCreate = this.customCurrencies.filter((c) => c.tempId)
        const toUpdate = this.customCurrencies.filter((c) => c.id && !c.tempId)
        const toDelete = this.originalCustomCurrencies.filter(
          (orig) => !this.customCurrencies.some((curr) => curr.id === orig.id),
        )

        // Delete removed currencies
        for (const currency of toDelete) {
          await ocs.delete(`/custom-currencies/${currency.id}`)
          console.debug('[DEBUG] Deleted currency', currency.id)
        }

        // Create new currencies
        for (const currency of toCreate) {
          const { tempId, ...data } = currency
          await ocs.post('/custom-currencies', { data })
          console.debug('[DEBUG] Created currency', data.code)
        }

        // Update existing currencies
        for (const currency of toUpdate) {
          const { id, ...data } = currency
          await ocs.put(`/custom-currencies/${id}`, { data })
          console.debug('[DEBUG] Updated currency', id)
        }

        // Refresh the list
        await this.fetchCustomCurrencies()
      } catch (e) {
        console.error('Failed to save custom currencies', e)
        this.loading = false
      }
    },
  },
  computed: {
    intervals() {
      return this.intervalOptions.map((x) => x.label)
    },
  },
}
</script>

<style scoped lang="scss">
#autocurrency-content {
  h2:first-child {
    margin-top: 0;
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

  .custom-currencies-list {
    margin-top: 16px;

    .currency-item {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding: 16px;
      border: 1px solid var(--color-border);
      border-radius: var(--border-radius-large);
      margin-bottom: 12px;
      background-color: var(--color-background-hover);

      .currency-fields {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 12px;

        .field-row {
          display: flex;
          gap: 12px;
          flex-wrap: wrap;

          >* {
            flex: 1;
            min-width: 200px;
          }
        }
      }
    }
  }

  .settings-section {
    display: flex;
    flex-direction: column;
    gap: 32px;
  }

  .retention-field {
    max-width: 300px;
    width: 100%;
  }
}
</style>
