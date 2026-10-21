import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/components/layout/AppLayout.vue';
import LoginView from '@/views/LoginView.vue';
import ConfigView from '@/views/ConfigView.vue';
import DataConstraintView from '@/views/DataConstraintView.vue';
import RunView from '@/views/RunView.vue';
import SyncView from '@/views/SyncView.vue';

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
  {
    path: '/',
    component: AppLayout,
    redirect: { name: 'config' },
    children: [
      { path: 'config', name: 'config', component: ConfigView, meta: { title: 'Konfigurasi GA' } },
      { path: 'data', name: 'data', component: DataConstraintView, meta: { title: 'Data & Constraint' } },
      { path: 'run', name: 'run', component: RunView, meta: { title: 'Run' } },
      { path: 'sync', name: 'sync', component: SyncView, meta: { title: 'Sinkronisasi' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: { name: 'config' } },
];

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    guest?: boolean;
  }
}

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const { isLoggedIn, restore } = useAuth();
  await restore();
  if (!to.meta.guest && !isLoggedIn.value) return { name: 'login' };
  if (to.meta.guest && isLoggedIn.value) return { name: 'config' };
});

export default router;
