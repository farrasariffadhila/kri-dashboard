<script setup>
import { useI18n } from 'vue-i18n'
import { LOCALES, setLocale } from '../i18n/index.js'

const { t, locale } = useI18n()
</script>

<template>
  <fieldset class="flex items-center gap-2 text-sm">
    <legend class="sr-only">{{ t('language.legend') }}</legend>
    <div class="flex rounded-md border border-control">
      <button
        v-for="option in LOCALES"
        :key="option.code"
        type="button"
        class="px-2.5 py-1.5 font-medium first:rounded-l-md last:rounded-r-md"
        :class="locale === option.code ? 'bg-ink text-surface' : 'text-secondary hover:bg-sunken'"
        :aria-pressed="locale === option.code"
        :lang="option.code"
        :aria-label="option.name"
        @click="setLocale(option.code)"
      >
        {{ option.short }}
      </button>
    </div>
  </fieldset>
</template>
