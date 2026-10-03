import { createI18n } from 'vue-i18n'
import id from './id.js'
import en from './en.js'

export const LOCALES = [
  { code: 'id', short: 'ID', name: 'Bahasa Indonesia', intl: 'id-ID' },
  { code: 'en', short: 'EN', name: 'English', intl: 'en-GB' },
]
const STORAGE_KEY = 'kri-locale'

function initialLocale() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (LOCALES.some((l) => l.code === saved)) return saved
  } catch {
    // Storage unavailable: fall back to the default.
  }
  return 'id' // primary audience: an Indonesian bank
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: 'id',
  messages: { id, en },
})

document.documentElement.lang = i18n.global.locale.value

/** Translate outside components (plain JS modules). Reactive when called during render. */
export const t = (...args) => i18n.global.t(...args)

export function currentLocale() {
  return i18n.global.locale.value
}

/** BCP 47 tag for Intl formatting of the current UI language. */
export function intlLocale() {
  return LOCALES.find((l) => l.code === currentLocale())?.intl ?? 'id-ID'
}

export function setLocale(code) {
  if (!LOCALES.some((l) => l.code === code)) return
  i18n.global.locale.value = code
  // The page language drives screen readers and stops the browser from offering to translate it.
  document.documentElement.lang = code
  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    // Not persisted; the choice lasts for this visit.
  }
}

/** Pick the current language from a { id, en } value sent by the API. */
export function localized(value) {
  if (value == null || typeof value !== 'object') return value
  return value[currentLocale()] ?? value.id
}
