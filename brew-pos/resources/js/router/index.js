import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/store/authStore';

const routes = [
  { path: '/login', name: 'Login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },

  {
    path: '/',
    component: () => import('@/components/Layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/pos' },
      { path: 'pos', name: 'POS', component: () => import('@/views/POSView.vue'), meta: { roles: ['admin', 'manager', 'cashier'] } },
      { path: 'kitchen', name: 'Kitchen', component: () => import('@/views/KitchenView.vue'), meta: { roles: ['admin', 'manager', 'kitchen'] } },
      { path: 'orders', name: 'Orders', component: () => import('@/views/OrdersView.vue'), meta: { roles: ['admin', 'manager', 'cashier'] } },
      { path: 'tables', name: 'Tables', component: () => import('@/views/TablesView.vue'), meta: { roles: ['admin', 'manager', 'cashier'] } },
      { path: 'products', name: 'Products', component: () => import('@/views/ProductsView.vue'), meta: { roles: ['admin', 'manager'] } },
      { path: 'categories', name: 'Categories', component: () => import('@/views/CategoriesView.vue'), meta: { roles: ['admin', 'manager'] } },
      { path: 'inventory', name: 'Inventory', component: () => import('@/views/InventoryView.vue'), meta: { roles: ['admin', 'manager'] } },
      { path: 'reports', name: 'Reports', component: () => import('@/views/ReportsView.vue'), meta: { roles: ['admin', 'manager'] } },
      { path: 'settings', name: 'Settings', component: () => import('@/views/SettingsView.vue'), meta: { roles: ['admin'] } },
      { path: 'users', name: 'Users', component: () => import('@/views/UsersView.vue'), meta: { roles: ['admin'] } },
    ],
  },

  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isLoggedIn) return next('/login');
  if (to.meta.guest && auth.isLoggedIn) return next('/');
  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) return next('/');
  next();
});

export default router;
