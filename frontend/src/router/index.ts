import { createRouter, createWebHistory } from 'vue-router'
import { authApi } from '../auth/api'
import { useAuthStore } from '../stores/auth'
import { useShopsStore } from '../stores/shops'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', meta: { public: true, layout: 'blank' }, component: () => import('../auth/LoginView.vue') },
    { path: '/onboarding', component: () => import('../auth/OnboardingView.vue') },
    { path: '/', component: () => import('../shared/HomeView.vue') },
    { path: '/settings', component: () => import('../shops/ShopSettingsView.vue') },
    { path: '/team', component: () => import('../shops/TeamView.vue') },
    { path: '/catalog', component: () => import('../catalog/CatalogView.vue') },
    { path: '/staff', component: () => import('../staff/StaffView.vue') },
    { path: '/customers', component: () => import('../customers/CustomersView.vue') },
    { path: '/reports', component: () => import('../reports/ReportsView.vue') },
    { path: '/admin', meta: { admin: true }, component: () => import('../admin/AdminView.vue') },
    { path: '/admin/permissions', meta: { admin: true }, component: () => import('../admin/PermissionsView.vue') },
    { path: '/marketing', component: () => import('../marketing/MarketingView.vue') },
    { path: '/bot', component: () => import('../bot/BotView.vue') },
    { path: '/expenses', component: () => import('../expenses/ExpensesView.vue') },
    { path: '/pos', meta: { layout: 'counter' }, component: () => import('../billing/PosView.vue') },
  ],
})

declare module 'vue-router' {
  interface RouteMeta { layout?: 'counter' | 'blank'; public?: boolean; admin?: boolean }
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.isLoggedIn) {
    useShopsStore().reset()
    return to.meta.public ? true : '/login'
  }
  if (!auth.me) {
    try {
      auth.me = await authApi.me()
    } catch {
      auth.clear()
      return '/login'
    }
  }
  if (auth.me.is_super_admin) return to.meta.admin ? true : '/admin'
  if (to.meta.admin) return '/'
  if (to.path === '/login') return '/'
  if (auth.needsBusiness || auth.needsOnboarding) return to.path === '/onboarding' ? true : '/onboarding'
  const shops = useShopsStore()
  if (!shops.loaded) await shops.load()
  if (to.path === '/onboarding') return '/'
  return true
})
