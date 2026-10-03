import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/**
 * Server-side list state (filters, sort, page) kept in the URL query string,
 * so a filtered view can be bookmarked or shared. The URL is the single source of truth.
 *
 * @param {(params: object, signal: AbortSignal) => Promise<object>} fetchPage
 * @param {object} defaults Values used when a key is absent from the URL
 */
export function useListQuery(fetchPage, defaults = {}) {
  const route = useRoute()
  const router = useRouter()

  const query = computed(() => ({ ...defaults, ...route.query }))
  const result = ref(null)
  const loading = ref(false)
  const error = ref(false)

  let controller = null

  /** API params: drop empty values and the explicit "all" choice. */
  function apiParams() {
    return Object.fromEntries(Object.entries(query.value).filter(([, v]) => v !== '' && v != null && v !== 'all'))
  }

  async function load() {
    controller?.abort()
    const current = (controller = new AbortController())
    loading.value = true
    error.value = false
    try {
      result.value = await fetchPage(apiParams(), current.signal)
    } catch (e) {
      if (e.name !== 'AbortError') error.value = true
    } finally {
      if (controller === current) loading.value = false
    }
  }

  /** Change filters; any filter change returns to the first page. */
  function update(changes, { resetPage = true } = {}) {
    const next = { ...route.query, ...changes }
    if (resetPage) delete next.page
    for (const [key, value] of Object.entries(next)) {
      if (value === '' || value == null || value === false) delete next[key]
    }
    router.replace({ query: next })
  }

  function reset() {
    router.replace({ query: {} })
  }

  const stop = watch(() => route.query, load, { immediate: true })
  onBeforeUnmount(() => {
    stop()
    controller?.abort()
  })

  return { query, result, loading, error, update, reset, reload: load }
}

/** Debounced text input bound to one query key (for search boxes). */
export function useDebouncedQueryText(query, update, key, delay = 350) {
  const text = ref(query.value[key] ?? '')
  let timer = null

  watch(text, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => update({ [key]: value.trim() }), delay)
  })
  // Keep the box in sync when the URL changes from elsewhere (reset, back button).
  watch(
    () => query.value[key],
    (value) => {
      if ((value ?? '') !== text.value.trim()) text.value = value ?? ''
    },
  )
  onBeforeUnmount(() => clearTimeout(timer))

  return text
}
