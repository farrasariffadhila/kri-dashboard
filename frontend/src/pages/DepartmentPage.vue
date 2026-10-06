<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { findDepartment, riskStore } from '../lib/store.js'
import { KRIS, statusFromRating, statusFromScore } from '../lib/kri.js'
import { formatDate, formatMonthLong, formatNumber, formatPercent, formatScore } from '../lib/format.js'
import PageHeader from '../components/PageHeader.vue'
import StatusBadge from '../components/StatusBadge.vue'
import TrendPanel from '../components/TrendPanel.vue'
import DepartmentDetail from '../components/DepartmentDetail.vue'

const { t } = useI18n()

const props = defineProps({
  id: { type: String, required: true },
})

const summary = computed(() => riskStore.summary)
const department = computed(() => findDepartment(props.id))
const rank = computed(() => (summary.value?.data.findIndex((d) => d.id === Number(props.id)) ?? -1) + 1)

const change = computed(() => {
  const d = department.value
  if (d?.composite_score == null || d.previous_composite_score == null) return null
  const diff = d.composite_score - d.previous_composite_score
  if (Math.abs(diff) < 0.05) return { trend: 'flat', text: t('common.unchanged') }
  return {
    trend: diff > 0 ? 'worse' : 'better',
    arrow: diff > 0 ? '▲' : '▼',
    text: t(diff > 0 ? 'common.up' : 'common.down', { amount: t('common.points', { value: formatScore(Math.abs(diff)) }) }),
  }
})
</script>

<template>
  <div v-if="!summary" aria-busy="true" class="space-y-6">
    <p class="sr-only" role="status">{{ t('department.loading') }}</p>
    <div class="h-28 max-w-3xl animate-pulse rounded-md bg-sunken" />
    <div class="h-96 animate-pulse rounded-md bg-sunken" />
  </div>

  <div v-else-if="!department">
    <PageHeader :title="t('department.notFound')" :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('nav.departments') }]">
      {{ t('department.notFoundText', { id }) }}
    </PageHeader>
    <RouterLink to="/" class="font-medium text-accent underline underline-offset-4">{{ t('department.backToOverview') }}</RouterLink>
  </div>

  <template v-else>
    <PageHeader
      :title="department.name"
      :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('nav.departments') }, { label: department.name }]"
    >
      {{ t('common.period', { month: formatMonthLong(summary.reporting_month), date: formatDate(summary.as_of) }) }} ·
      {{ t('department.rank', { rank, count: formatNumber(summary.data.length) }) }}
    </PageHeader>

    <section aria-labelledby="skor-title" class="flex flex-wrap items-end gap-x-10 gap-y-4">
      <div>
        <h2 id="skor-title" class="text-sm font-medium text-secondary">{{ t('department.composite') }}</h2>
        <p class="mt-1 flex flex-wrap items-center gap-3">
          <span class="text-5xl font-semibold">{{ formatScore(department.composite_score) }}</span>
          <span class="text-muted">{{ t('common.of100') }}</span>
          <StatusBadge :status="statusFromRating(department.rating)" />
        </p>
      </div>
      <p
        v-if="change"
        class="pb-1 font-medium"
        :class="{ 'text-critical-fg': change.trend === 'worse', 'text-safe-fg': change.trend === 'better', 'text-secondary': change.trend === 'flat' }"
      >
        <span v-if="change.arrow" aria-hidden="true">{{ change.arrow }}</span>
        {{ t('department.change', { change: change.text, month: formatMonthLong(summary.previous_month), score: formatScore(department.previous_composite_score) }) }}
      </p>
    </section>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" :aria-label="t('department.krisAria')">
      <li v-for="kri in KRIS" :key="kri.key" class="border border-border bg-surface p-5">
        <div class="flex items-start justify-between gap-3">
          <p class="text-sm font-medium text-secondary">{{ kri.label }}</p>
          <StatusBadge size="sm" :status="statusFromScore(department.kris[kri.key].score, summary.methodology.bands)" />
        </div>
        <p class="mt-3 text-3xl font-semibold">{{ kri.format(department.kris[kri.key].value) }}</p>
        <p class="mt-2 text-sm text-muted tabular">
          {{ t('department.kriScore', { score: formatScore(department.kris[kri.key].score), weight: formatPercent(department.kris[kri.key].weight * 100, 0) }) }}
        </p>
      </li>
    </ul>

    <section aria-labelledby="dept-tren-title" class="mt-10 border border-border bg-surface p-5 sm:p-6">
      <h2 id="dept-tren-title" class="font-serif text-xl font-semibold">{{ t('overview.trendTitle') }}</h2>
      <TrendPanel :department-id="department.id" :bands="summary.methodology.bands" />
    </section>

    <div class="mt-12">
      <DepartmentDetail :department="department" :methodology="summary.methodology" :as-of="summary.as_of" :heading-level="2" :sources="summary.data_sources" />
    </div>
  </template>
</template>
