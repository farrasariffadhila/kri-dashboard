<script setup>
import { computed } from 'vue'

const props = defineProps({
  score: { type: Number, default: null },
  bands: { type: Object, required: true }, // { warning: 50, critical: 75 }
})

const position = computed(() => Math.min(100, Math.max(0, props.score ?? 0)))
</script>

<template>
  <!-- Decorative: the score and status are already given as text next to the ruler. -->
  <div class="relative h-3 w-32" aria-hidden="true">
    <div class="absolute inset-x-0 top-1 flex h-1.5 gap-0.5">
      <div class="bg-safe-mark/35" :style="{ width: `${bands.warning}%` }" />
      <div class="bg-warning-mark/45" :style="{ width: `${bands.critical - bands.warning}%` }" />
      <div class="flex-1 bg-critical-mark/45" />
    </div>
    <div
      v-if="score != null"
      class="absolute top-0 h-3 w-0.5 -translate-x-1/2 bg-ink ring-2 ring-surface"
      :style="{ left: `${position}%` }"
    />
  </div>
</template>
