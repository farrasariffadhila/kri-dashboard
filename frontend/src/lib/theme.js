import { onBeforeUnmount, ref } from 'vue'

const STORAGE_KEY = 'kri-theme'
const MODES = ['system', 'light', 'dark']

function readStoredMode() {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    return MODES.includes(value) ? value : 'system'
  } catch {
    return 'system'
  }
}

/**
 * Theme preference: follows the OS by default, with a manual override that is remembered.
 * `version` changes whenever the effective palette changes, so canvas charts can redraw.
 */
export function useTheme() {
  const mode = ref(readStoredMode())
  const version = ref(0)
  const media = window.matchMedia('(prefers-color-scheme: dark)')

  function apply() {
    const root = document.documentElement
    if (mode.value === 'system') delete root.dataset.theme
    else root.dataset.theme = mode.value
    version.value++
  }

  function setMode(next) {
    mode.value = next
    try {
      localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Storage can be unavailable (private mode); the choice then lasts for this visit only.
    }
    apply()
  }

  const onSystemChange = () => {
    if (mode.value === 'system') version.value++
  }
  media.addEventListener('change', onSystemChange)
  onBeforeUnmount(() => media.removeEventListener('change', onSystemChange))

  apply()
  return { mode, version, setMode, modes: MODES }
}

/** Read a CSS custom property from the root element (used by the canvas chart). */
export function cssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}
