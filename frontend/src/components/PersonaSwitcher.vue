<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { authStore, loadDemoAccounts, loginAsDemo } from '../lib/auth.js'
import { loadSummary } from '../lib/store.js'

/** Demo mode only: switch the signed-in persona in place, to walk through a workflow across roles. */
const { t } = useI18n()
const router = useRouter()

const switching = ref(false)
const failed = ref(false)

onMounted(loadDemoAccounts)

const accounts = computed(() => authStore.demoAccounts ?? [])
const currentId = computed(() => authStore.user?.id)

async function onChange(event) {
  const id = Number(event.target.value)
  if (!id || id === currentId.value) return
  switching.value = true
  failed.value = false
  try {
    await loginAsDemo(id)
    loadSummary()
    // The new persona may not have access to the current page (e.g. the follow-up queue).
    const { meta } = router.currentRoute.value
    if (meta.canVisit && !meta.canVisit(authStore.user)) router.replace({ name: 'overview' })
  } catch {
    failed.value = true
    event.target.value = String(currentId.value)
  } finally {
    switching.value = false
  }
}
</script>

<template>
  <div v-if="accounts.length" class="flex items-center gap-2 text-sm">
    <label for="persona" class="shrink-0 text-muted sm:hidden md:inline">{{ t('demo.viewAs') }}</label>
    <select
      id="persona"
      class="min-w-0 flex-1 rounded-md border border-control bg-surface px-2 py-1.5 text-ink disabled:opacity-60 sm:max-w-[18rem] sm:flex-none"
      :value="currentId"
      :disabled="switching"
      :aria-label="t('demo.viewAs')"
        @change="onChange"
    >
      <option v-for="user in accounts" :key="user.id" :value="user.id">
        {{ user.name }} · {{ user.department ? user.department.name : t(`roles.${user.role}`) }}
      </option>
    </select>
    <span v-if="switching" role="status" class="sr-only">{{ t('demo.switching') }}</span>
    <span v-if="failed" role="alert" class="text-critical-fg">{{ t('demo.switchFailed') }}</span>
  </div>
</template>
