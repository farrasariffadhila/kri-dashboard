<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref, watch } from 'vue'
import { riskStore } from '../lib/store.js'
import { KRIS, contributions, statusFromRating } from '../lib/kri.js'
import { formatDate, formatMonthLong, formatNumber, formatScore } from '../lib/format.js'
import PageHeader from '../components/PageHeader.vue'
import StatusBadge from '../components/StatusBadge.vue'
import KpiCard from '../components/KpiCard.vue'
import RiskHeatmap from '../components/RiskHeatmap.vue'
import TrendPanel from '../components/TrendPanel.vue'
import ScoreComposition from '../components/ScoreComposition.vue'
import KriDrilldownDialog from '../components/KriDrilldownDialog.vue'

const { t } = useI18n()

const summary = computed(() => riskStore.summary)
const departments = computed(() => summary.value?.data ?? [])
const top = computed(() => departments.value[0] ?? null)
const topStatus = computed(() => statusFromRating(top.value?.rating))
// The two indicators that add most to the top department's score.
const topCauses = computed(() => (top.value ? contributions(top.value.kris).slice(0, 2) : []))
const BANNER_TONES = {
  critical: 'border-critical-mark bg-critical-bg',
  warning: 'border-warning-mark bg-warning-bg',
  safe: 'border-safe-mark bg-safe-bg',
}
const selectedDepartment = ref(null)
const trendDepartmentId = ref(null)
watch(
  departments,
  (list) => {
    if (trendDepartmentId.value == null && list.length) trendDepartmentId.value = list[0].id
  },
  { immediate: true },
)

const totals = computed(() => summary.value?.totals)
</script>

<template>
  <div v-if="!summary" aria-busy="true" class="space-y-6">
    <p class="sr-only" role="status">{{ t('common.loading') }}</p>
    <div class="h-24 max-w-3xl animate-pulse rounded-md bg-sunken" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-40 animate-pulse rounded-md bg-sunken" />
    </div>
    <div class="h-96 animate-pulse rounded-md bg-sunken" />
  </div>

  <div v-else-if="!departments.length" class="text-secondary">
    {{ t('overview.empty') }}
  </div>

  <template v-else>
    <PageHeader :title="t('overview.title')">
      {{ t('common.period', { month: formatMonthLong(summary.reporting_month), date: formatDate(summary.as_of) }) }}
    </PageHeader>

    <!-- 1. Riskiest department and its main drivers, as one alert line. -->
    <section
      aria-labelledby="ringkasan-title"
      class="flex flex-wrap items-center gap-x-6 gap-y-3 border-l-4 px-5 py-4"
      :class="BANNER_TONES[topStatus]"
    >
      <h2 id="ringkasan-title" class="sr-only">{{ t('overview.conclusion') }}</h2>
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <StatusBadge :status="topStatus" />
        <span class="font-serif text-xl font-semibold">{{ top.name }}</span>
        <span class="text-xl font-semibold tabular">{{ formatScore(top.composite_score) }}</span>
      </div>
      <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        <li v-for="kri in topCauses" :key="kri.key">
          <span class="text-secondary">{{ kri.label }}</span> <span class="font-semibold tabular">{{ kri.format(kri.value) }}</span>
        </li>
      </ul>
      <button
        type="button"
        class="rounded-md bg-accent px-4 py-2 text-sm font-medium text-on-accent hover:bg-accent-hover sm:ml-auto"
        :aria-label="t('overview.viewDetail', { name: top.name })"
        @click="selectedDepartment = top"
      >
        {{ t('heatmap.details') }}
      </button>
    </section>

    <!-- 2. Bank-wide indicators and how they moved since last month. -->
    <section aria-labelledby="kpi-title" class="mt-12">
      <h2 id="kpi-title" class="font-serif text-xl font-semibold">{{ t('overview.bankTitle') }}</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard
          v-for="kri in KRIS"
          :key="kri.key"
          :kri="kri"
          :current="totals.current[kri.key]"
          :previous="totals.previous[kri.key]"
          :previous-month="summary.previous_month"
        >
          <template #detail>
            <template v-if="kri.key === 'unpatched_servers'">
              {{ t('overview.serversDetail', { nc: formatNumber(totals.current.unpatched_servers.non_compliant), total: formatNumber(totals.current.unpatched_servers.total_servers) }) }}
            </template>
            <template v-else-if="kri.key === 'open_high_audits'">
              {{ t('overview.overdueDetail', { count: formatNumber(totals.current.open_high_audits.overdue) }) }}
            </template>
            <template v-else-if="kri.key === 'uptime'">
              {{ t('overview.appsDetail', { count: formatNumber(totals.current.uptime.applications) }) }}
            </template>
            <template v-else>
              {{ t('overview.employeesDetail', { failed: formatNumber(totals.current.phishing_failure.total_failed), tested: formatNumber(totals.current.phishing_failure.total_tested) }) }}
            </template>
          </template>
        </KpiCard>
      </div>
    </section>

    <!-- 3. The heatmap: every department against every indicator. -->
    <section aria-labelledby="peta-title" class="mt-12">
      <h2 id="peta-title" class="mb-4 font-serif text-xl font-semibold">{{ t('overview.heatmapTitle') }}</h2>
      <RiskHeatmap :departments="departments" :methodology="summary.methodology" @open="selectedDepartment = $event" />
    </section>

    <!-- 4. How the scores developed, and what they are made of. -->
    <div class="mt-12 grid grid-cols-1 gap-6 2xl:grid-cols-2">
      <section aria-labelledby="tren-title" class="border border-border bg-surface p-5 sm:p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
          <h2 id="tren-title" class="font-serif text-xl font-semibold">{{ t('overview.trendTitle') }}</h2>
          <label class="flex items-center gap-2 text-sm">
            <span class="text-secondary">{{ t('overview.department') }}</span>
            <select v-model="trendDepartmentId" class="rounded-md border border-control bg-surface px-3 py-1.5 text-ink">
              <option v-for="d in departments" :key="d.id" :value="d.id">
                {{ d.name }} ({{ formatScore(d.composite_score) }})
              </option>
            </select>
          </label>
        </div>
        <TrendPanel :department-id="trendDepartmentId" :bands="summary.methodology.bands" />
      </section>

      <section aria-labelledby="komposisi-title" class="border border-border bg-surface p-5 sm:p-6">
        <h2 id="komposisi-title" class="mb-4 font-serif text-xl font-semibold">{{ t('overview.compositionTitle') }}</h2>
        <ScoreComposition :departments="departments" :bands="summary.methodology.bands" />
      </section>
    </div>

    <KriDrilldownDialog
      :department="selectedDepartment"
      :methodology="summary.methodology"
      :reporting-month="summary.reporting_month"
      :as-of="summary.as_of"
      :sources="summary.data_sources"
      @close="selectedDepartment = null"
    />
  </template>
</template>
