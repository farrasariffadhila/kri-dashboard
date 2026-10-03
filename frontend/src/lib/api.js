import { currentLocale } from '../i18n/index.js'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api'

export class ApiError extends Error {
  constructor(status, body = null) {
    super(body?.message ?? `API request failed with status ${status}`)
    this.status = status
    this.body = body
  }

  /** Laravel validation errors as { field: [messages] }. */
  get fieldErrors() {
    return this.body?.errors ?? {}
  }
}

let unauthorizedHandler = null
/** Called whenever the session has expired, so the app can send the user back to the login page. */
export function onUnauthorized(handler) {
  unauthorizedHandler = handler
}

function xsrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

async function request(method, path, { params, body, signal } = {}) {
  const search = params ? new URLSearchParams(params).toString() : ''
  // The API localises its messages from Accept-Language.
  const headers = { Accept: 'application/json', 'Accept-Language': currentLocale() }
  if (method !== 'GET') {
    const token = xsrfToken()
    if (token) headers['X-XSRF-TOKEN'] = token
    if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json'
  }

  const response = await fetch(`${BASE_URL}${path}${search ? `?${search}` : ''}`, {
    method,
    headers,
    body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin', // session cookie; never a token in JavaScript-readable storage
    signal,
  })

  if (response.status === 204) return null
  const data = await response.json().catch(() => null)
  if (!response.ok) {
    if (response.status === 401 && path !== '/me') unauthorizedHandler?.()
    throw new ApiError(response.status, data)
  }
  return data
}

const get = (path, options) => request('GET', path, options)
const post = (path, body) => request('POST', path, { body })

/** Sanctum sets the XSRF-TOKEN cookie; must run before the first state-changing request. */
export function fetchCsrfCookie() {
  return fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' })
}

export const login = (email, password) => post('/login', { email, password })
/** Portfolio demo mode: the API answers 404 here when it is switched off. */
export const fetchDemoAccounts = () => get('/demo/accounts')
export const demoLogin = (userId) => post('/demo/login', { user_id: userId })
export const logout = () => post('/logout')
export const fetchMe = () => get('/me')

export const fetchRiskSummary = (signal) => get('/departments/risk-summary', { signal })
export const fetchHistoricalRisk = (departmentId, signal) =>
  get(`/departments/${encodeURIComponent(departmentId)}/historical-risk`, { signal })
export const fetchAuditIssues = (params, signal) => get('/audit-issues', { params, signal })
export const fetchAuditIssue = (id, signal) => get(`/audit-issues/${encodeURIComponent(id)}`, { signal })
export const fetchServers = (params, signal) => get('/servers', { params, signal })
export const fetchWorkQueue = (signal) => get('/work-queue', { signal })

export const submitPlan = (issueId, formData) => post(`/audit-issues/${encodeURIComponent(issueId)}/plans`, formData)
export const submitEvidence = (issueId, formData) => post(`/audit-issues/${encodeURIComponent(issueId)}/evidence`, formData)
export const reviewSubmission = (submissionId, decision, note) =>
  post(`/remediation-submissions/${encodeURIComponent(submissionId)}/review`, { decision, note: note || null })

export const attachmentUrl = (id) => `${BASE_URL}/attachments/${encodeURIComponent(id)}`
