import { reactive } from 'vue'
import { fetchHistoricalRisk, fetchRiskSummary } from './api.js'

/** App-wide risk data. The summary is fetched once and shared by every page and the sidebar. */
export const riskStore = reactive({
  summary: null,
  loading: false,
  error: false,
  histories: {},
})

let summaryRequest = null

export function loadSummary({ force = false } = {}) {
  if (riskStore.summary && !force) return Promise.resolve(riskStore.summary)
  if (summaryRequest) return summaryRequest

  riskStore.loading = true
  riskStore.error = false
  summaryRequest = fetchRiskSummary()
    .then((data) => (riskStore.summary = data))
    .catch(() => {
      riskStore.error = true
    })
    .finally(() => {
      riskStore.loading = false
      summaryRequest = null
    })
  return summaryRequest
}

/** Cached per department; rejects on failure so the caller can show its own error state. */
export async function loadHistory(departmentId) {
  if (!riskStore.histories[departmentId]) {
    riskStore.histories[departmentId] = await fetchHistoricalRisk(departmentId)
  }
  return riskStore.histories[departmentId]
}

export function findDepartment(id) {
  return riskStore.summary?.data.find((d) => d.id === Number(id)) ?? null
}

/** Forget cached data (on logout, or after an action that changes the KRIs). */
export function resetRiskStore() {
  riskStore.summary = null
  riskStore.error = false
  riskStore.histories = {}
}
