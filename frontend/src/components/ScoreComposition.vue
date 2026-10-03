<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref } from 'vue'
import { KRIS, contributions } from '../lib/kri.js'
import { formatScore } from '../lib/format.js'

const { t } = useI18n()

const props = defineProps({
  departments: { type: Array, required: true },
  bands: { type: Object, required: true },
})

const showTable = ref(false)

/** Each department's composite split into the points contributed by each KRI. */
const rows = computed(() =>
  props.departments.map((d) => {
    const byKey = Object.fromEntries(contributions(d.kris).map((c) => [c.key, c.contribution]))
    return {
      id: d.id,
      name: d.name,
      total: d.composite_score,
      // Fixed KRI order so every row stacks the same way; colour follows the KRI, not its rank.
      segments: KRIS.map((k) => ({ ...k, points: byKey[k.key] ?? 0 })).filter((s) => s.points > 0),
      byKey,
    }
  }),
)

const TICKS = [0, 25, 50, 75, 100]
</script>

<template>
  <div>
    <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-secondary" :aria-label="t('composition.legend')">
      <li v-for="kri in KRIS" :key="kri.key" class="inline-flex items-center gap-2">
        <span class="inline-block size-3 rounded-sm" :style="{ background: kri.series }" aria-hidden="true" />
        {{ kri.label }}
      </li>
    </ul>

    <!-- Visual chart; the same numbers are available in the table below. -->
    <div class="mt-5" aria-hidden="true">
      <div class="grid grid-cols-[6.5rem_1fr_2.75rem] items-end gap-x-3 pb-1 text-xs text-muted sm:grid-cols-[10rem_1fr_3.5rem]">
        <span />
        <div class="relative h-4">
          <span
            v-for="t in TICKS"
            :key="t"
            class="absolute -translate-x-1/2 tabular"
            :class="{ 'translate-x-[-100%]': t === 100, 'translate-x-0': t === 0, 'hidden sm:inline': t === 25 || t === 75 }"
            :style="{ left: `${t}%` }"
          >{{ t }}</span>
        </div>
        <span class="text-right">{{ t('composition.score') }}</span>
      </div>

      <div
        v-for="row in rows"
        :key="row.id"
        class="grid grid-cols-[6.5rem_1fr_2.75rem] items-center gap-x-3 border-t border-hairline py-2.5 sm:grid-cols-[10rem_1fr_3.5rem]"
      >
        <span class="truncate text-sm font-medium">{{ row.name }}</span>
        <div class="relative h-6">
          <!-- Band thresholds: dashed because they are thresholds, not gridlines. -->
          <span class="absolute inset-y-[-0.625rem] w-px border-l border-dashed border-warning-mark" :style="{ left: `${bands.warning}%` }" />
          <span class="absolute inset-y-[-0.625rem] w-px border-l border-dashed border-critical-mark" :style="{ left: `${bands.critical}%` }" />
          <div class="relative flex h-full gap-0.5" :style="{ width: `${row.total}%` }">
            <span
              v-for="(segment, i) in row.segments"
              :key="segment.key"
              class="h-full"
              :class="{ 'rounded-r-sm': i === row.segments.length - 1 }"
              :style="{ flexGrow: segment.points, flexBasis: 0, background: segment.series }"
              :title="t('composition.segment', { label: segment.label, points: formatScore(segment.points) })"
            />
          </div>
        </div>
        <span class="text-right text-sm font-semibold tabular">{{ formatScore(row.total) }}</span>
      </div>
      <p class="mt-2 flex gap-4 text-xs text-muted">
        <span>{{ t('composition.thresholds', { warning: bands.warning, critical: bands.critical }) }}</span>
      </p>
    </div>

    <button
      type="button"
      class="mt-3 py-1.5 text-sm font-medium text-accent underline underline-offset-4 hover:text-accent-hover"
      :aria-expanded="showTable"
      aria-controls="composition-table"
      @click="showTable = !showTable"
    >
      {{ showTable ? t('common.hideTable') : t('common.showTable') }}
    </button>

    <div v-show="showTable" id="composition-table" class="relative mt-3 overflow-x-auto" tabindex="0" role="region" :aria-label="t('composition.tableAria')">
      <table class="w-full min-w-160 border-collapse text-sm">
        <caption class="pb-2 text-left text-xs text-muted">{{ t('composition.tableCaption') }}</caption>
        <thead>
          <tr class="border-b border-border text-left text-xs font-medium text-secondary">
            <th scope="col" class="py-2 pr-4">{{ t('heatmap.department') }}</th>
            <th v-for="kri in KRIS" :key="kri.key" scope="col" class="px-3 py-2 text-right">{{ kri.label }}</th>
            <th scope="col" class="py-2 pl-3 text-right">{{ t('composition.score') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-b border-hairline">
            <th scope="row" class="py-2.5 pr-4 text-left font-normal">{{ row.name }}</th>
            <td v-for="kri in KRIS" :key="kri.key" class="px-3 py-2.5 text-right tabular">{{ formatScore(row.byKey[kri.key] ?? null) }}</td>
            <td class="py-2.5 pl-3 text-right font-semibold tabular">{{ formatScore(row.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
