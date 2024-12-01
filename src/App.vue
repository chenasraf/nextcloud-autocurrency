<template>
  <div id="autocurrency-content" class="section">
    <h2>Auto Currency for Cospend</h2>
    <NcAppSettingsSection name="Cron Settings">
      <section>
        <form @submit.prevent>
          <NcSelect
            v-model="interval"
            :options="intervals"
            input-label="Currency conversion rate update interval"
            required
            :disabled="interval == null"
          />
          <div class="submit-buttons">
            <NcButton native-type="submit">Save</NcButton>
          </div>
        </form>
      </section>
    </NcAppSettingsSection>
  </div>
</template>

<script>
// import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import axios from '@nextcloud/axios'

export default {
	name: 'App',
	components: {
		// NcAppContent,
    NcAppSettingsSection,
    NcSelect,
    NcButton,
	},
  data() {
    return {
      interval: null,
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
    // this.interval = this.getIntervalByValue(24).label
    this.fetchSettings()
  },
  methods: {
    async fetchSettings() {
      try {
        const resp = await axios.get('/cron')
        const data = resp.data.ocs.data
        console.debug('[DEBUG] Settings fetched', data)
        const interval = this.getIntervalByValue(data.interval)
        if (interval) {
          console.debug('[DEBUG] Interval found', interval)
          this.interval = interval.label
        } else {
          console.warn('Invalid interval value', data.interval)
        }
      } catch (e) {
        console.error('Failed to fetch settings', e)
      }
    },
    getIntervalByValue(value) {
      return this.intervalOptions.find((x) => x.value === value)
    },
    getIntervalByLabel(label) {
      return this.intervalOptions.find((x) => x.label === label)
    },
  },
  computed: {
    intervals() {
      return this.intervalOptions.map((x) => x.label)
    },
    // selectedIntervals() {
    //   return this.intervals.filter(interval => interval.selected)
    // },
  },
}
</script>

<style scoped lang="scss">
#autocurrency-content {
  /* margin: 0 16px; */
  h2:first-child {
    margin-top: 0;
  }

  .submit-buttons {
    margin-top: 16px;
  }
}
</style>
