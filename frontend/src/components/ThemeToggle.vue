<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'

const { t } = useI18n()
defineProps({
  mode: { type: String, required: true },
})
const emit = defineEmits(['change'])

// Icon-only to keep the top bar narrow on phones; each button carries its name.
const OPTIONS = [
  { value: 'system', icon: 'monitor' },
  { value: 'light', icon: 'sun' },
  { value: 'dark', icon: 'moon' },
]
</script>

<template>
  <fieldset class="flex items-center gap-2 text-sm">
    <legend class="sr-only">{{ t('theme.legend') }}</legend>
    <div class="flex rounded-md border border-control">
      <button
        v-for="option in OPTIONS"
        :key="option.value"
        type="button"
        class="px-2 py-1.5 first:rounded-l-md last:rounded-r-md"
        :class="mode === option.value ? 'bg-ink text-surface' : 'text-secondary hover:bg-sunken'"
        :aria-pressed="mode === option.value"
        :aria-label="t(`theme.${option.value}`)"
        :title="t(`theme.${option.value}`)"
        @click="emit('change', option.value)"
      >
        <AppIcon :name="option.icon" />
      </button>
    </div>
  </fieldset>
</template>
