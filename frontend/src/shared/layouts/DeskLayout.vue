<template>
  <v-navigation-drawer v-model="drawer" :rail="rail && !mobile" :temporary="mobile" color="surface" border="e">
    <div class="brand pa-4 d-flex align-center ga-3">
      <v-avatar color="primary" size="36"><v-icon icon="mdi-content-cut" /></v-avatar>
      <span v-if="!rail || mobile" class="text-h6 font-weight-bold">Salon Manager</span>
    </div>
    <v-list nav density="comfortable" class="px-2">
      <v-list-item v-for="n in nav" :key="n.title" :to="n.to" :prepend-icon="n.icon" :title="n.title" rounded="lg" color="primary" />
      <v-divider v-if="!auth.me?.is_super_admin" class="my-2" />
      <v-list-subheader v-if="!auth.me?.is_super_admin && (!rail || mobile)">COMING SOON</v-list-subheader>
      <v-list-item v-for="n in (auth.me?.is_super_admin ? [] : soon)" :key="n.title" :prepend-icon="n.icon" :title="n.title" rounded="lg" disabled />
    </v-list>
    <template #append>
      <v-list nav class="px-2">
        <v-list-item prepend-icon="mdi-logout" title="Sign out" rounded="lg" @click="logout" />
      </v-list>
    </template>
  </v-navigation-drawer>

  <v-app-bar flat border="b" color="surface">
    <v-app-bar-nav-icon @click="mobile ? (drawer = !drawer) : (rail = !rail)" />
    <v-select
      v-if="shops.shops.length"
      v-model="selected"
      :items="items"
      density="compact"
      hide-details
      variant="outlined"
      prepend-inner-icon="mdi-store"
      class="shop-switcher"
      aria-label="Active shop"
    />
    <v-spacer />
    <InstallBanner />
    <v-btn v-if="!auth.me?.is_super_admin" to="/pos" color="primary" variant="flat" prepend-icon="mdi-receipt-text-plus" class="mr-2">New bill</v-btn>
    <v-menu>
      <template #activator="{ props }">
        <v-btn v-bind="props" variant="text" class="px-2">
          <v-avatar color="primary" size="32" class="mr-2">{{ initials }}</v-avatar>
          <span class="d-none d-sm-inline">{{ auth.me?.name }}</span>
          <v-icon icon="mdi-chevron-down" />
        </v-btn>
      </template>
      <v-card min-width="220">
        <v-list>
          <v-list-item :title="auth.me?.name" :subtitle="auth.me?.email ?? auth.me?.mobile ?? ''" />
          <v-divider />
          <v-list-item prepend-icon="mdi-cog" title="Settings" to="/settings" />
          <v-list-item prepend-icon="mdi-logout" title="Sign out" @click="logout" />
        </v-list>
      </v-card>
    </v-menu>
  </v-app-bar>
  <v-main><v-container class="py-6"><slot /></v-container></v-main>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '../../stores/auth'
import { useShopStore } from '../../stores/shop'
import { useShopsStore } from '../../stores/shops'
import { authApi } from '../../auth/api'
import InstallBanner from '../../mobile/InstallBanner.vue'

const auth = useAuthStore()
const shops = useShopsStore()
const ctx = useShopStore()
const router = useRouter()
const { mobile } = useDisplay()
const drawer = ref(!mobile.value)
const rail = ref(false)

type NavItem = { title: string; icon: string; to: string }
const ownerNav: NavItem[] = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard', to: '/' },
  { title: 'Billing / POS', icon: 'mdi-cash-register', to: '/pos' },
  { title: 'Reports', icon: 'mdi-chart-box', to: '/reports' },
  { title: 'Customers', icon: 'mdi-account-heart', to: '/customers' },
  { title: 'Marketing', icon: 'mdi-bullhorn', to: '/marketing' },
  { title: 'WhatsApp assistant', icon: 'mdi-whatsapp', to: '/bot' },
  { title: 'Catalog', icon: 'mdi-scissors-cutting', to: '/catalog' },
  { title: 'Staff', icon: 'mdi-account-tie', to: '/staff' },
  { title: 'Expenses', icon: 'mdi-wallet-outline', to: '/expenses' },
  { title: 'Settings', icon: 'mdi-cog', to: '/settings' },
  { title: 'Team', icon: 'mdi-account-group', to: '/team' },
]
// Role-aware menu: only owners see back-office pages; everyone else gets the counter essentials.
const counterNav: NavItem[] = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard', to: '/' },
  { title: 'Billing / POS', icon: 'mdi-cash-register', to: '/pos' },
  { title: 'Customers', icon: 'mdi-account-heart', to: '/customers' },
]
const nav = computed<NavItem[]>(() => auth.me?.is_super_admin
  ? [{ title: 'Salons', icon: 'mdi-domain', to: '/admin' }, { title: 'Roles & permissions', icon: 'mdi-shield-key', to: '/admin/permissions' }]
  : auth.me?.is_owner ? ownerNav : counterNav)
const soon = [
  { title: 'Appointments', icon: 'mdi-calendar-clock' },
  { title: 'Inventory', icon: 'mdi-package-variant' },
]
const initials = computed(() => (auth.me?.name ?? '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase())

// "All shops" is offered to owners only.
const items = computed(() => [
  ...(auth.me?.is_owner ? [{ title: 'All shops', value: null as number | null }] : []),
  ...shops.shops.map((s) => ({ title: s.name, value: s.id as number | null })),
])
const selected = computed({ get: () => ctx.shopId, set: (v: number | null) => ctx.setShop(v) })

async function logout() {
  try { await authApi.logout() } catch { /* token may already be invalid */ }
  auth.clear()
  shops.reset()
  await router.replace('/login')
}
</script>

<style scoped>
.shop-switcher { max-width: 240px; margin-left: 8px; }
@media (max-width: 600px) { .shop-switcher { max-width: 170px; min-width: 130px; } }
</style>
