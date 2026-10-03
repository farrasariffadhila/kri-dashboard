<script setup>
import { useI18n } from 'vue-i18n'
import { KRIS, statusFromRating, statusFromScore } from '../lib/kri.js'
import { formatPercent, formatScore } from '../lib/format.js'
import StatusBadge from './StatusBadge.vue'
import ScoreRuler from './ScoreRuler.vue'

const { t } = useI18n()

defineProps({
  departments: { type: Array, required: true },
  methodology: { type: Object, required: true },
})

const emit = defineEmits(['open'])

const cellTone = {
  critical: 'bg-critical-bg',
  warning: 'bg-warning-bg',
  safe: 'bg-safe-bg',
}
</script>

<template>
  <p class="mb-2 text-sm text-muted lg:hidden">{{ t('common.scrollHint') }}</p>
  <div class="relative overflow-x-auto border-y border-border bg-surface">
    <table class="w-full min-w-[56rem] border-collapse text-left">
      <caption class="sr-only">{{ t('heatmap.caption') }}</caption>
      <thead>
        <tr class="border-b border-border align-bottom text-xs font-medium text-secondary">
          <th scope="col" class="sticky left-0 z-10 bg-surface px-4 py-3">{{ t('heatmap.department') }}</th>
          <th scope="col" class="px-4 py-3">{{ t('heatmap.composite') }}</th>
          <th v-for="kri in KRIS" :key="kri.key" scope="col" class="px-4 py-3 text-right">
            <span class="block">{{ kri.label }}</span>
            <span class="block font-normal text-muted tabular">
              {{ t('heatmap.weight', { value: formatPercent(methodology.weights[kri.key] * 100, 0) }) }}
            </span>
          </th>
          <th scope="col" class="px-4 py-3"><span class="sr-only">{{ t('heatmap.actions') }}</span></th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="dept in departments"
          :key="dept.id"
          class="group cursor-pointer border-b border-hairline last:border-b-0"
          @click="emit('open', dept)"
        >
          <th
            scope="row"
            class="sticky left-0 z-10 bg-surface px-4 py-4 align-middle font-serif text-lg font-semibold group-hover:bg-sunken"
          >
            {{ dept.name }}
          </th>
          <td class="px-4 py-4 align-middle group-hover:bg-sunken">
            <div class="flex items-center gap-3">
              <span class="w-14 text-right text-xl font-semibold tabular">{{ formatScore(dept.composite_score) }}</span>
              <div class="flex flex-col gap-1.5">
                <StatusBadge :status="statusFromRating(dept.rating)" />
                <ScoreRuler :score="dept.composite_score" :bands="methodology.bands" />
              </div>
            </div>
          </td>
          <td
            v-for="kri in KRIS"
            :key="kri.key"
            class="border-l-2 border-surface px-4 py-4 text-right align-middle"
            :class="cellTone[statusFromScore(dept.kris[kri.key].score, methodology.bands)]"
          >
            <span class="block text-base font-semibold tabular">{{ kri.format(dept.kris[kri.key].value) }}</span>
            <StatusBadge size="sm" :status="statusFromScore(dept.kris[kri.key].score, methodology.bands)" />
          </td>
          <td class="px-4 py-4 text-right align-middle group-hover:bg-sunken">
            <button
              type="button"
              class="rounded-md border border-control px-3 py-1.5 text-sm font-medium text-accent hover:border-accent hover:bg-accent-subtle"
              :aria-label="t('heatmap.detailsAria', { name: dept.name })"
            >
              {{ t('heatmap.details') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
