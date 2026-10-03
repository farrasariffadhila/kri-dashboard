// Apply the saved theme before first paint to avoid a light/dark flash.
// Kept as a blocking classic script (not inline) so the CSP can stay at script-src 'self'.
try {
  const theme = localStorage.getItem('kri-theme')
  if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme
} catch {}
