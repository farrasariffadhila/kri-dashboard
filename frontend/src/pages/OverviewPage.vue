<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref, watch } from 'vue'
import { riskStore } from '../lib/store.js'
import { KRIS, STATUS, contributions, statusFromRating } from '../lib/kri.js'
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
const topCauses = computed(() => (top.value ? contributions(top.value.kris).slice(0, 2).map((k) => k.cause(k.value)) : []))
const statusCounts = computed(() =>
  ['critical', 'warning', 'safe'].map((status) => ({
    status,
    count: departments.value.filter((d) => statusFromRating(d.rating) === status).length,
  })),
)

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

    <!-- 1. The answer first: who is riskiest and why. -->
    <section aria-labelledby="ringkasan-title">
      <h2 id="ringkasan-title" class="sr-only">{{ t('overview.conclusion') }}</h2>
      <p class="max-w-4xl font-serif text-2xl font-medium leading-snug">
        <template v-if="topStatus === 'safe'">
          {{ t('overview.allSafe', { name: top.name, score: formatScore(top.composite_score) }) }}
        </template>
        <template v-else>
          {{ t('overview.headline', { name: top.name, status: STATUS[topStatus].label, score: formatScore(top.composite_score) }) }}
        </template>
      </p>
      <p v-if="topCauses.length && topStatus !== 'safe'" class="mt-2 max-w-3xl text-lg text-secondary">
        {{ t('overview.causes', { causes: topCauses.join(` ${t('overview.and')} `) }) }}
      </p>
      <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3">
        <button
          type="button"
          class="rounded-md bg-accent px-4 py-2.5 font-medium text-on-accent hover:bg-accent-hover"
          @click="selectedDepartment = top"
        >
          {{ t('overview.viewDetail', { name: top.name }) }}
        </button>
        <p class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-secondary">
          <span>{{ t('overview.ofDepartments', { count: formatNumber(departments.length) }) }}</span>
          <span v-for="c in statusCounts" :key="c.status" class="inline-flex items-center gap-1.5">
            <span class="font-semibold tabular text-ink">{{ c.count }}</span>
            <StatusBadge size="sm" :status="c.status" />
          </span>
        </p>
      </div>
    </section>

    <!-- 2. Bank-wide indicators and how they moved since last month. -->
    <section aria-labelledby="kpi-title" class="mt-12">
      <h2 id="kpi-title" class="font-serif text-xl font-semibold">{{ t('overview.bankTitle') }}</h2>
      <p class="mt-1 text-sm text-secondary">
        {{ t('overview.bankSubtitle', { month: formatMonthLong(summary.previous_month) }) }}
      </p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard
          v-for="kri in KRIS"
          :key="kri.key"
          :kri="kri"
          :current="totals.current[kri.key]"
          :previous="totals.previous[kri.key]"
          :previous-month="summary.previous_month"
          :source="summary.data_sources[kri.key]"
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
      <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 id="peta-title" class="font-serif text-xl font-semibold">{{ t('overview.heatmapTitle') }}</h2>
          <p class="mt-1 max-w-prose text-sm text-secondary">
            {{ t('overview.heatmapSubtitle') }}
          </p>
        </div>
        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm" :aria-label="t('status.legend')">
          <li v-for="(s, key) in STATUS" :key="key" class="inline-flex items-center gap-2">
            <StatusBadge :status="key" />
            <span class="text-secondary">{{ s.range }}</span>
          </li>
        </ul>
      </div>
      <RiskHeatmap :departments="departments" :methodology="summary.methodology" @open="selectedDepartment = $event" />
    </section>

    <!-- 4. How the scores developed, and what they are made of. -->
    <div class="mt-12 grid grid-cols-1 gap-6 2xl:grid-cols-2">
      <section aria-labelledby="tren-title" class="border border-border bg-surface p-5 sm:p-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 id="tren-title" class="font-serif text-xl font-semibold">{{ t('overview.trendTitle') }}</h2>
            <p class="mt-1 text-sm text-secondary">{{ t('overview.trendSubtitle') }}</p>
          </div>
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
        <h2 id="komposisi-title" class="font-serif text-xl font-semibold">{{ t('overview.compositionTitle') }}</h2>
        <p class="mb-4 mt-1 text-sm text-secondary">{{ t('overview.compositionSubtitle') }}</p>
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
