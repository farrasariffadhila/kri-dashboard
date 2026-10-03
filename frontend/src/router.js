import { createRouter, createWebHistory } from 'vue-router'
import OverviewPage from './pages/OverviewPage.vue'
import { onUnauthorized } from './lib/api.js'
import { authStore, clearSession, ensureSession } from './lib/auth.js'
import { t } from './i18n/index.js'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/masuk',
      name: 'login',
      component: () => import('./pages/LoginPage.vue'),
      meta: { titleKey: 'titles.login', public: true, layout: 'bare' },
    },
    { path: '/', name: 'overview', component: OverviewPage, meta: { titleKey: 'titles.overview' } },
    {
      path: '/departemen/:id(\\d+)',
      name: 'department',
      component: () => import('./pages/DepartmentPage.vue'),
      props: true,
      meta: { titleKey: 'titles.department' },
    },
    {
      path: '/tindak-lanjut',
      name: 'work-queue',
      component: () => import('./pages/WorkQueuePage.vue'),
      meta: { titleKey: 'titles.workQueue', canVisit: (user) => user.has_work_queue },
    },
    {
      path: '/temuan-audit',
      name: 'audits',
      component: () => import('./pages/AuditFindingsPage.vue'),
      meta: { titleKey: 'titles.audits' },
    },
    {
      path: '/temuan-audit/:id(\\d+)',
      name: 'audit-detail',
      component: () => import('./pages/FindingDetailPage.vue'),
      props: true,
      meta: { titleKey: 'titles.auditDetail' },
    },
    {
      path: '/server',
      name: 'servers',
      component: () => import('./pages/ServerInventoryPage.vue'),
      meta: { titleKey: 'titles.servers' },
    },
    {
      path: '/metodologi',
      name: 'methodology',
      component: () => import('./pages/MethodologyPage.vue'),
      meta: { titleKey: 'titles.methodology' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('./pages/NotFoundPage.vue'),
      meta: { titleKey: 'titles.notFound' },
    },
  ],
  scrollBehavior(to, from) {
    // Filter changes on the same page keep the scroll position; real navigation starts at the top.
    return to.path === from.path ? false : { top: 0 }
  },
})

// Every page except the login page needs a session.
router.beforeEach(async (to) => {
  let user = null
  try {
    user = await ensureSession()
  } catch {
    // API unreachable: fall through to the login page, which explains the connection problem on submit.
  }
  if (to.meta.public) return user && to.name === 'login' ? { name: 'overview' } : true
  if (!user) return { name: 'login', query: to.fullPath !== '/' ? { lanjut: to.fullPath } : {} }
  // Pages that only some roles use (the follow-up queue) fall back to the overview.
  if (to.meta.canVisit && !to.meta.canVisit(user)) return { name: 'overview', replace: true }
  return true
})

export function updateDocumentTitle(route = router.currentRoute.value) {
  const appTitle = t('app.title')
  document.title = route.meta.titleKey ? `${t(route.meta.titleKey)} · ${appTitle}` : appTitle
}

router.afterEach((to) => updateDocumentTitle(to))

// An expired session anywhere sends the user back to the login page, keeping where they were.
onUnauthorized(() => {
  if (!authStore.user) return
  clearSession()
  const current = router.currentRoute.value
  router.replace({ name: 'login', query: { lanjut: current.fullPath, sesi: 'berakhir' } })
})

export default router
