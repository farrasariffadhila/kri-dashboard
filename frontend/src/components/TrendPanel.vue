<script setup>
import { useI18n } from 'vue-i18n'
import { inject, ref, watch } from 'vue'
import { loadHistory } from '../lib/store.js'
import RiskTrendChart from './RiskTrendChart.vue'

const { t } = useI18n()

/** Loads and shows the 6-month trend of one department, with its own loading and error states. */
const props = defineProps({
  departmentId: { type: Number, default: null },
  bands: { type: Object, required: true },
})

const themeVersion = inject('themeVersion')
const history = ref(null)
const loading = ref(false)
const error = ref(false)

async function load() {
  if (props.departmentId == null) return
  loading.value = true
  error.value = false
  try {
    history.value = await loadHistory(props.departmentId)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

watch(() => props.departmentId, load, { immediate: true })
</script>

<template>
  <div v-if="error" role="alert" class="flex h-80 flex-col items-start justify-center gap-3">
    <p class="text-secondary">{{ t('trend.failed') }}</p>
    <button type="button" class="rounded-md border border-control px-3 py-1.5 text-sm font-medium hover:bg-sunken" @click="load">
      {{ t('common.retry') }}
    </button>
  </div>
  <div v-else-if="!history" aria-busy="true" class="h-80 animate-pulse rounded-md bg-sunken">
    <span class="sr-only" role="status">{{ t('trend.loading') }}</span>
  </div>
  <RiskTrendChart
    v-else
    :class="{ 'opacity-60': loading }"
    :history="history"
    :bands="bands"
    :theme-version="themeVersion"
  />
</template>
