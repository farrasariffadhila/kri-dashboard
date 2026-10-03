<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const { t } = useI18n()

defineProps({
  title: { type: String, required: true },
  breadcrumbs: { type: Array, default: () => [] }, // [{ label, to }]
})
</script>

<template>
  <header class="mb-8 border-b-2 border-ink pb-5">
    <nav v-if="breadcrumbs.length" :aria-label="t('common.breadcrumb')" class="mb-2 text-sm text-secondary">
      <ol class="flex flex-wrap items-center gap-1.5">
        <li v-for="(crumb, i) in breadcrumbs" :key="crumb.label" class="flex items-center gap-1.5">
          <RouterLink v-if="crumb.to" :to="crumb.to" class="hover:text-accent hover:underline">{{ crumb.label }}</RouterLink>
          <span v-else aria-current="page">{{ crumb.label }}</span>
          <span v-if="i < breadcrumbs.length - 1" aria-hidden="true" class="text-muted">/</span>
        </li>
      </ol>
    </nav>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <!-- Focus target after navigation, so the new page is announced. -->
        <h1 id="page-title" tabindex="-1" class="font-serif text-3xl font-semibold focus:outline-none">{{ title }}</h1>
        <p v-if="$slots.default" class="mt-2 max-w-3xl text-secondary"><slot /></p>
      </div>
      <slot name="aside" />
    </div>
  </header>
</template>
