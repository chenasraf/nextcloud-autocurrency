<template>
  <div id="autocurrency-content" class="section">
    <h2>Auto Currency for Cospend</h2>
    <NcAppSettingsSection name="Information">
      <p>
        To make sure your currencies are found for the rates to be updated, please ensure your
        currencies are named appropriately.
      </p>

      <p>Currency names must contain at least <b>one of</b>:</p>

      <ol class="ol">
        <li>
          The currency symbol - e.g. <code>$</code>, <code>€</code>,
          <code>£</code>
        </li>
        <li>
          The currency code - e.g. <code>USD</code>, <code>EUR</code>,
          <code>GBP</code> (case-insensitive)
        </li>
      </ol>

      <p>Example names:</p>

      <ul>
        <li>✅ <code>$</code></li>
        <li>✅ <code>USD</code></li>
        <li>✅ <code>$ USD</code></li>
        <li>❌ <code>US Dollar</code></li>
        <li>❌ <code>United States Dollar</code></li>
      </ul>

      <NcNoteCard type="info">
        <p>The naming rules apply for both main &amp; additional currencies.</p>
      </NcNoteCard>
    </NcAppSettingsSection>

    <NcAppSettingsSection name="Cron Settings">
      <section>
        <form @submit.prevent @submit="save">
          <div class="cron-flex">
            <NcSelect
              v-model="interval"
              :options="intervals"
              input-label="Currency conversion rate update interval"
              required
              :disabled="loading"
            />

            <div class="cron-last-update-container">
              <NcButton @click="doCron" :disabled="loading">Fetch Rates Now</NcButton>

              <div>
                Rates last fetched:
                <span v-if="loading">Loading...</span>
                <span v-if="!loading && !lastUpdate">Never</span>
                <NcDateTime v-if="!loading && lastUpdate" :timestamp="lastUpdate.valueOf()" />
              </div>
            </div>
          </div>
          <div class="submit-buttons">
            <NcButton native-type="submit">Save</NcButton>
          </div>
        </form>
      </section>
    </NcAppSettingsSection>
  </div>
</template>

<script>
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcDateTime from '@nextcloud/vue/dist/Components/NcDateTime.js'

import axios from '@nextcloud/axios'
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
  },
  data() {
    return {
      loading: true,
      interval: null,
      lastUpdate: null,
      intervalOptions: [
        { label: 'Every hour', value: 1 },
        { label: 'Every 3 hours', value: 3 },
        { label: 'Every 6 hours', value: 6 },
        { label: 'Every 9 hours', value: 9 },
        { label: 'Every 12 hours', value: 12 },
        { label: 'Every 24 hours (default)', value: 24 },
      ],
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
}
</style>
