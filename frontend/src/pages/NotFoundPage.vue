<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import PageHeader from '../components/PageHeader.vue'

const { t } = useI18n()
</script>

<template>
  <PageHeader :title="t('notFound.title')">{{ t('notFound.text') }}</PageHeader>
  <RouterLink to="/" class="font-medium text-accent underline underline-offset-4 hover:text-accent-hover">
    {{ t('notFound.back') }}
  </RouterLink>
</template>
