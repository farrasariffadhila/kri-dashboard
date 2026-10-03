<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { riskStore } from '../lib/store.js'
import { KRIS, STATUS, contributions, statusFromRating } from '../lib/kri.js'
import { formatDateTime, formatMonthLong, formatNumber, formatPercent, formatScore } from '../lib/format.js'
import { localized } from '../i18n/index.js'
import PageHeader from '../components/PageHeader.vue'
import StatusBadge from '../components/StatusBadge.vue'

const { t } = useI18n()

const summary = computed(() => riskStore.summary)
const methodology = computed(() => summary.value?.methodology)
const sources = computed(() => summary.value?.data_sources ?? {})
const example = computed(() => summary.value?.data[0] ?? null)
const exampleRows = computed(() => (example.value ? contributions(example.value.kris) : []))
const anySimulated = computed(() => Object.values(sources.value).some((s) => s.simulated))

const ANCHORS = [
  { key: 'appetite', score: 0 },
  { key: 'tolerance', score: 50 },
  { key: 'limit', score: 75 },
  { key: 'max', score: 100 },
]
</script>

<template>
  <PageHeader :title="t('methodology.title')" :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('nav.methodology') }]">
    {{ t('methodology.intro') }}
  </PageHeader>

  <div v-if="!methodology" aria-busy="true" class="h-96 animate-pulse rounded-md bg-sunken">
    <span class="sr-only" role="status">{{ t('methodology.loading') }}</span>
  </div>

  <div v-else class="max-w-5xl space-y-12">
    <section aria-labelledby="m-kri">
      <h2 id="m-kri" class="font-serif text-xl font-semibold">{{ t('methodology.s1') }}</h2>
      <div class="relative mt-4 overflow-x-auto" tabindex="0" role="region" :aria-label="t('methodology.s1Aria')">
        <table class="w-full min-w-xl border-collapse text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs font-medium text-secondary">
              <th scope="col" class="py-2 pr-4">{{ t('detail.indicator') }}</th>
              <th scope="col" class="px-3 py-2">{{ t('methodology.howCalculated') }}</th>
              <th scope="col" class="py-2 pl-3 text-right">{{ t('detail.weight') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="kri in KRIS" :key="kri.key" class="border-b border-hairline">
              <th scope="row" class="py-3 pr-4 text-left font-medium">{{ kri.label }}</th>
              <td class="px-3 py-3 text-secondary">{{ kri.formula }}</td>
              <td class="py-3 pl-3 text-right font-semibold tabular">{{ formatPercent(methodology.weights[kri.key] * 100, 0) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section aria-labelledby="m-scale">
      <h2 id="m-scale" class="font-serif text-xl font-semibold">{{ t('methodology.s2') }}</h2>
      <p class="mt-2 max-w-prose text-secondary">{{ t('methodology.s2Text') }}</p>
      <dl class="mt-4 grid gap-4 sm:grid-cols-2">
        <div v-for="a in ANCHORS" :key="a.key" class="border border-border bg-surface p-4">
          <dt class="font-medium">
            {{ t(`methodology.anchors.${a.key}.label`) }}
            <span class="text-muted">{{ t('methodology.anchorScore', { score: a.score }) }}</span>
          </dt>
          <dd class="mt-1 text-sm text-secondary">{{ t(`methodology.anchors.${a.key}.meaning`) }}</dd>
        </div>
      </dl>
      <p class="mt-4 rounded-md bg-sunken px-4 py-3 font-serif text-lg">{{ t('methodology.formula') }}</p>
      <p class="mt-1 text-sm text-muted">{{ t('methodology.formulaHelp') }}</p>

      <div class="relative mt-6 overflow-x-auto" tabindex="0" role="region" :aria-label="t('methodology.thresholdsAria')">
        <table class="w-full min-w-160 border-collapse text-sm">
          <caption class="pb-2 text-left text-xs text-muted">{{ t('methodology.thresholdsCaption') }}</caption>
          <thead>
            <tr class="border-b border-border text-left text-xs font-medium text-secondary">
              <th scope="col" class="py-2 pr-4">{{ t('detail.indicator') }}</th>
              <th v-for="a in ANCHORS" :key="a.key" scope="col" class="px-3 py-2 text-right">
                {{ t('methodology.anchorHeader', { name: t(`methodology.anchors.${a.key}.short`), score: a.score }) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="kri in KRIS" :key="kri.key" class="border-b border-hairline">
              <th scope="row" class="py-3 pr-4 text-left font-medium">{{ kri.label }}</th>
              <td v-for="a in ANCHORS" :key="a.key" class="px-3 py-3 text-right tabular">{{ kri.format(methodology.thresholds[kri.key][a.key]) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section aria-labelledby="m-composite">
      <h2 id="m-composite" class="font-serif text-xl font-semibold">{{ t('methodology.s3') }}</h2>
      <p class="mt-2 max-w-prose text-secondary">{{ t('methodology.s3Text') }}</p>
      <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
        <li v-for="(s, key) in STATUS" :key="key" class="inline-flex items-center gap-2">
          <StatusBadge :status="key" /> <span class="text-secondary">{{ s.range }}</span>
        </li>
      </ul>
    </section>

    <section v-if="example" aria-labelledby="m-example">
      <h2 id="m-example" class="font-serif text-xl font-semibold">
        {{ t('methodology.s4', { name: example.name, month: formatMonthLong(summary.reporting_month) }) }}
      </h2>
      <div class="relative mt-4 overflow-x-auto" tabindex="0" role="region" :aria-label="t('methodology.s4Aria')">
        <table class="w-full min-w-xl border-collapse text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs font-medium text-secondary">
              <th scope="col" class="py-2 pr-4">{{ t('detail.indicator') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.value') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.score') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.weight') }}</th>
              <th scope="col" class="py-2 pl-3 text-right">{{ t('methodology.scoreTimesWeight') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in exampleRows" :key="row.key" class="border-b border-hairline">
              <th scope="row" class="py-3 pr-4 text-left font-medium">{{ row.label }}</th>
              <td class="px-3 py-3 text-right tabular">{{ row.format(row.value) }}</td>
              <td class="px-3 py-3 text-right tabular">{{ formatScore(row.score) }}</td>
              <td class="px-3 py-3 text-right tabular">{{ formatPercent(row.weight * 100, 0) }}</td>
              <td class="py-3 pl-3 text-right font-semibold tabular">{{ formatScore(row.contribution) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th scope="row" colspan="4" class="py-3 pr-3 text-right font-medium">{{ t('detail.composite') }}</th>
              <td class="py-3 pl-3 text-right">
                <span class="font-semibold tabular">{{ formatScore(example.composite_score) }}</span>
                <StatusBadge class="ml-2" size="sm" :status="statusFromRating(example.rating)" />
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section aria-labelledby="m-lineage">
      <h2 id="m-lineage" class="font-serif text-xl font-semibold">{{ t('methodology.s5') }}</h2>
      <p class="mt-2 max-w-prose text-secondary">{{ t('methodology.s5Text') }}</p>
      <p v-if="anySimulated" class="mt-2 max-w-prose text-sm font-medium text-warning-fg">{{ t('methodology.s5Simulated') }}</p>
      <div class="mt-4 space-y-4">
        <article v-for="kri in KRIS" :key="kri.key" class="border border-border bg-surface p-5">
          <h3 class="font-medium">{{ kri.label }}</h3>
          <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[10rem_1fr]">
            <dt class="text-muted">{{ t('methodology.sourceSystem') }}</dt>
            <dd>
              {{ localized(sources[kri.key].system) }}{{ sources[kri.key].simulated ? ` ${t('common.simulated')}` : '' }}
            </dd>
            <dt class="text-muted">{{ t('methodology.dataOwner') }}</dt>
            <dd>{{ localized(sources[kri.key].owner) }}</dd>
            <dt class="text-muted">{{ t('methodology.cadence') }}</dt>
            <dd>{{ localized(sources[kri.key].cadence) }}</dd>
          </dl>
          <div class="relative mt-3 overflow-x-auto" tabindex="0" role="region" :aria-label="t('methodology.importsAria', { name: kri.label })">
            <table class="w-full min-w-xl table-fixed border-collapse text-sm">
              <caption class="pb-1 text-left text-xs text-muted">{{ t('methodology.lastImport') }}</caption>
              <thead>
                <tr class="border-b border-border text-left text-xs font-medium text-secondary">
                  <th scope="col" class="w-[38%] py-2 pr-4">{{ t('methodology.file') }}</th>
                  <th scope="col" class="w-[12%] px-3 py-2 text-right">{{ t('methodology.rows') }}</th>
                  <th scope="col" class="w-[25%] px-3 py-2">{{ t('methodology.importedAt') }}</th>
                  <th scope="col" class="w-[25%] py-2 pl-3">{{ t('methodology.checksum') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="load in sources[kri.key].loads" :key="load.table" class="border-b border-hairline">
                  <th scope="row" class="py-2 pr-4 text-left font-normal tabular">{{ load.source_file ?? load.table }}</th>
                  <td class="px-3 py-2 text-right tabular">{{ load.row_count == null ? '–' : formatNumber(load.row_count) }}</td>
                  <td class="px-3 py-2 whitespace-nowrap tabular">{{ load.loaded_at ? formatDateTime(load.loaded_at) : t('methodology.neverImported') }}</td>
                  <td class="py-2 pl-3 tabular text-secondary" :title="load.checksum ?? ''">
                    {{ load.checksum ? `${load.checksum.slice(0, 16)}…` : '–' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </div>
    </section>

    <section aria-labelledby="m-data">
      <h2 id="m-data" class="font-serif text-xl font-semibold">{{ t('methodology.s6') }}</h2>
      <ul class="mt-2 max-w-prose list-disc space-y-2 pl-5 text-secondary">
        <li>{{ t('methodology.s6a', { month: formatMonthLong(summary.reporting_month) }) }}</li>
        <li>{{ t('methodology.s6b') }}</li>
        <li>{{ t('app.syntheticData') }}</li>
      </ul>
    </section>
  </div>
</template>
