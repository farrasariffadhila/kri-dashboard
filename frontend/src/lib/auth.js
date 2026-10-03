import { reactive } from 'vue'
import { ApiError, demoLogin, fetchCsrfCookie, fetchDemoAccounts, fetchMe, fetchWorkQueue, login as apiLogin, logout as apiLogout } from './api.js'
import { resetRiskStore } from './store.js'

/** Signed-in user and their pending work. The session itself lives in an HttpOnly cookie. */
export const authStore = reactive({
  user: null,
  checked: false,
  workCounts: { action_needed: 0, waiting: 0 },
  demoAccounts: null, // null = not loaded yet, [] = demo mode off
})

/** Demo personas, when the API runs in portfolio demo mode. */
export async function loadDemoAccounts() {
  if (authStore.demoAccounts) return authStore.demoAccounts
  try {
    authStore.demoAccounts = (await fetchDemoAccounts()).data
  } catch {
    authStore.demoAccounts = [] // 404: demo mode is off, use the normal sign-in form
  }
  return authStore.demoAccounts
}

/** Sign in (or switch) as a demo persona; the server starts a fresh session. */
export async function loginAsDemo(userId) {
  await fetchCsrfCookie()
  authStore.user = (await demoLogin(userId)).user
  authStore.checked = true
  resetRiskStore()
  refreshWorkCounts()
}

/** Resolve the current session once (used by the router guard). */
export async function ensureSession() {
  if (authStore.checked) return authStore.user
  try {
    authStore.user = (await fetchMe()).user
  } catch (e) {
    if (!(e instanceof ApiError) || e.status !== 401) throw e
    authStore.user = null
  } finally {
    authStore.checked = true
  }
  if (authStore.user) refreshWorkCounts()
  return authStore.user
}

export async function login(email, password) {
  await fetchCsrfCookie()
  authStore.user = (await apiLogin(email, password)).user
  authStore.checked = true
  refreshWorkCounts()
}

export async function logout() {
  try {
    await apiLogout()
  } finally {
    clearSession()
  }
}

export function clearSession() {
  authStore.user = null
  authStore.workCounts = { action_needed: 0, waiting: 0 }
  resetRiskStore()
}

export async function refreshWorkCounts() {
  if (!authStore.user?.has_work_queue) {
    authStore.workCounts = { action_needed: 0, waiting: 0 }
    return
  }
  try {
    authStore.workCounts = (await fetchWorkQueue()).counts
  } catch {
    // The badge is a convenience; the work queue page shows its own error.
  }
}

export function hasRole(...roles) {
  return roles.includes(authStore.user?.role)
}
