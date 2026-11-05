import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { onUnauthenticated } from '@/lib/api/client';
import AppLayout from '@/components/layout/AppLayout.vue';
import LoginView from '@/views/LoginView.vue';
import ConfigView from '@/views/ConfigView.vue';
import DataConstraintView from '@/views/DataConstraintView.vue';
import RunView from '@/views/RunView.vue';
import SyncView from '@/views/SyncView.vue';
import SuperadminRunsView from '@/views/SuperadminRunsView.vue';

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
  {
    path: '/',
    component: AppLayout,
    redirect: { name: 'data' },
    children: [
      { path: 'admin/history', name: 'admin-history', component: SuperadminRunsView, meta: { title: 'Riwayat run fakultas', superadmin: true } },
      { path: 'config', name: 'config', component: ConfigView, meta: { title: 'Konfigurasi GA' } },
      { path: 'data', name: 'data', component: DataConstraintView, meta: { title: 'Data dan constraint' } },
      { path: 'run', name: 'run', component: RunView, meta: { title: 'Run penjadwalan' } },
      { path: 'sync', name: 'sync', component: SyncView, meta: { title: 'Sinkronisasi' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: { name: 'data' } },
];

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    guest?: boolean;
    superadmin?: boolean;
  }
}

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const { isLoggedIn, restore, user } = useAuth();
  await restore();
  if (!to.meta.guest && !isLoggedIn.value) return { name: 'login' };
  if (to.meta.guest && isLoggedIn.value) {
    return { name: 'data' };
  }
  if (to.meta.superadmin && user.value?.role !== 'superadmin') return { name: 'config' };
});

onUnauthenticated(() => {
  useAuth().forget();
  if (!router.currentRoute.value.meta.guest) void router.push({ name: 'login' });
});

export default router;
