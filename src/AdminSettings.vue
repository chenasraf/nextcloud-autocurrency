<template>
  <div id="autocurrency-content" class="section">
    <h2>{{ strings.title }}</h2>

    <NcNoteCard type="info">
      <p v-html="strings.instructionsHelp" />
    </NcNoteCard>

    <NcAppSettingsSection :name="strings.cronSettingsHeader">
      <section>
        <form @submit.prevent="save">
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
            <NcButton type="submit">{{ strings.save }}</NcButton>
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
  },
  data() {
    return {
      loading: true,
      interval: null,
      lastUpdate: null,
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
        cronSettingsHeader: t(APP_ID, 'Cron Settings'),
        instructionsHelp: t(
          APP_ID,
          'See the {aStart}Personal settings{aEnd} to view instructions on how to set up your currencies.',
          {
            aStart: `<a style="text-decoration:underline;" href="${generateUrl(
              '/settings/user/autocurrency',
            )}">`,
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
        const resp = await ocs.put('/settings', { data: { interval } })
        const data = resp.data
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
}
</style>
