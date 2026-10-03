<script setup>
defineProps({
  name: { type: String, required: true },
})

// 20px line icons, 1.6px stroke. Decorative: the accompanying text always names the action.
const PATHS = {
  overview: 'M3 3h6v8H3zM11 3h6v5h-6zM11 10h6v7h-6zM3 13h6v4H3z',
  building: 'M4 17V4h8v13M12 8h4v9M2 17h16M7 7h2M7 10h2M7 13h2',
  clipboard: 'M7 3h6v3H7zM5 4.5H4v12.5h12V4.5h-1M7 10h6M7 13h4',
  server: 'M3 3.5h14v5H3zM3 11.5h14v5H3zM6 6h.01M6 14h.01',
  book: 'M3 4.5c2.5-1 5-1 7 .5 2-1.5 4.5-1.5 7-.5V16c-2.5-1-5-1-7 .5-2-1.5-4.5-1.5-7-.5zM10 5v11.5',
  menu: 'M3 5h14M3 10h14M3 15h14',
  close: 'M5 5l10 10M15 5L5 15',
  arrow: 'M4 10h11M11 6l4 4-4 4',
  back: 'M16 10H5M9 6l-4 4 4 4',
  inbox: 'M3 11.5l2.5-7.5h9l2.5 7.5V16H3zM3 11.5h4l1 2h4l1-2h4',
  monitor: 'M2.5 3.5h15v10h-15zM7 17h6M10 13.5V17',
  sun: 'M10 6.5a3.5 3.5 0 100 7 3.5 3.5 0 000-7zM10 2v1.5M10 16.5V18M2 10h1.5M16.5 10H18M4.3 4.3l1.1 1.1M14.6 14.6l1.1 1.1M4.3 15.7l1.1-1.1M14.6 5.4l1.1-1.1',
  moon: 'M16.5 12.5A7 7 0 017.5 3.5a7 7 0 109 9z',
  paperclip: 'M13.5 6.5l-6 6a2 2 0 002.8 2.8l6.4-6.4a3.5 3.5 0 00-5-5L5.3 10.3a5 5 0 007.1 7.1L17 12.8',
}
</script>

<template>
  <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <path :d="PATHS[name]" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
  </svg>
</template>
