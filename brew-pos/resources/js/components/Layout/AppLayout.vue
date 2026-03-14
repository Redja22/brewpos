<template>
  <div class="app-layout">
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <span class="sidebar-logo">☕</span>
        <transition name="fade">
          <div v-if="!sidebarCollapsed" class="sidebar-brand">
            <span class="font-display" style="color:var(--accent-gold);font-size:18px;">BrewPOS</span>
            <span class="text-muted text-sm">Coffee Shop</span>
          </div>
        </transition>
      </div>

      <nav class="sidebar-nav">
        <template v-for="group in navGroups" :key="group.label">
          <div v-if="isGroupVisible(group)" class="nav-group">
            <transition name="fade">
              <span v-if="!sidebarCollapsed" class="nav-group-label">{{ group.label }}</span>
            </transition>
            <router-link
              v-for="item in group.items.filter((i) => isVisible(i))"
              :key="item.to"
              :to="item.to"
              class="nav-item"
              :title="sidebarCollapsed ? item.label : ''"
            >
              <span class="nav-icon">{{ item.icon }}</span>
              <transition name="fade">
                <span v-if="!sidebarCollapsed" class="nav-label">{{ item.label }}</span>
              </transition>
            </router-link>
          </div>
        </template>
      </nav>

      <div class="sidebar-footer">
        <button class="btn btn-ghost btn-sm" @click="sidebarCollapsed = !sidebarCollapsed" style="width:100%;justify-content:center">
          {{ sidebarCollapsed ? '▶' : '◀' }}
        </button>
      </div>
    </aside>

    <div class="main-wrapper">
      <header class="topbar">
        <div class="topbar-left">
          <h2 class="topbar-title font-display">{{ currentTitle }}</h2>
        </div>
        <div class="topbar-right">
          <div class="topbar-time">{{ currentTime }}</div>
          <div class="user-chip">
            <div class="user-avatar">{{ auth.user?.name?.charAt(0) }}</div>
            <div v-if="!isMobile" class="user-info">
              <span class="user-name">{{ auth.user?.name }}</span>
              <span class="user-role">{{ auth.user?.role }}</span>
            </div>
            <button class="btn btn-ghost btn-icon btn-sm" @click="handleLogout" title="Logout">⏻</button>
          </div>
        </div>
      </header>

      <main class="main-content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/store/authStore';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const sidebarCollapsed = ref(false);
const currentTime = ref('');
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);

let timer = null;

onMounted(() => {
  updateTime();
  timer = setInterval(updateTime, 1000);
  window.addEventListener('resize', onResize);
});
onUnmounted(() => {
  clearInterval(timer);
  window.removeEventListener('resize', onResize);
});

function updateTime() {
  currentTime.value = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}
function onResize() {
  isMobile.value = window.innerWidth < 768;
}

const navGroups = [
  {
    label: 'Operations',
    roles: ['admin', 'manager', 'cashier', 'kitchen'],
    items: [
      { to: '/pos', icon: '🖥', label: 'POS Terminal', roles: ['admin', 'manager', 'cashier'] },
      { to: '/kitchen', icon: '👨‍🍳', label: 'Kitchen', roles: ['admin', 'manager', 'kitchen'] },
      { to: '/orders', icon: '📋', label: 'Orders', roles: ['admin', 'manager', 'cashier'] },
      { to: '/tables', icon: '🪑', label: 'Tables', roles: ['admin', 'manager', 'cashier'] },
    ],
  },
  {
    label: 'Catalog',
    roles: ['admin', 'manager'],
    items: [
      { to: '/products', icon: '☕', label: 'Products', roles: ['admin', 'manager'] },
      { to: '/categories', icon: '🏷', label: 'Categories', roles: ['admin', 'manager'] },
      { to: '/inventory', icon: '📦', label: 'Inventory', roles: ['admin', 'manager'] },
    ],
  },
  {
    label: 'Analytics',
    roles: ['admin', 'manager'],
    items: [{ to: '/reports', icon: '📊', label: 'Reports', roles: ['admin', 'manager'] }],
  },
  {
    label: 'Admin',
    roles: ['admin'],
    items: [
      { to: '/users', icon: '👥', label: 'Users', roles: ['admin'] },
      { to: '/settings', icon: '⚙️', label: 'Settings', roles: ['admin'] },
    ],
  },
];

function isVisible(item) {
  return item.roles.includes(auth.user?.role);
}
function isGroupVisible(group) {
  return group.roles.includes(auth.user?.role);
}

const currentTitle = computed(() => {
  const titles = {
    '/pos': 'POS Terminal',
    '/kitchen': 'Kitchen Display',
    '/orders': 'Orders',
    '/tables': 'Table Management',
    '/products': 'Products',
    '/categories': 'Categories',
    '/inventory': 'Inventory',
    '/reports': 'Reports',
    '/users': 'Users',
    '/settings': 'Settings',
  };
  return titles[route.path] || 'BrewPOS';
});

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.sidebar {
  width: 220px;
  min-width: 220px;
  background: var(--surface-1);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease, min-width 0.25s ease;
  overflow: hidden;
}
.sidebar.collapsed {
  width: 64px;
  min-width: 64px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px 14px 16px;
  border-bottom: 1px solid var(--border);
  min-height: 70px;
  overflow: hidden;
}
.sidebar-logo {
  font-size: 28px;
  flex-shrink: 0;
}
.sidebar-brand {
  display: flex;
  flex-direction: column;
  white-space: nowrap;
  overflow: hidden;
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 8px;
}
.nav-group-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  padding: 4px 10px;
  white-space: nowrap;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: var(--radius-md);
  color: var(--text-secondary);
  text-decoration: none;
  transition: all var(--transition);
  white-space: nowrap;
  overflow: hidden;
  font-size: 13px;
  font-weight: 500;
}
.nav-item:hover {
  background: var(--surface-3);
  color: var(--text-primary);
}
.nav-item.router-link-active {
  background: var(--surface-4);
  color: var(--accent-gold);
  box-shadow: inset 2px 0 0 var(--accent-gold);
}
.nav-icon {
  font-size: 17px;
  flex-shrink: 0;
  width: 20px;
  text-align: center;
}
.nav-label {
  white-space: nowrap;
  overflow: hidden;
}

.sidebar-footer {
  padding: 10px 8px;
  border-top: 1px solid var(--border);
}

.main-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.topbar {
  height: 60px;
  min-height: 60px;
  background: var(--surface-1);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  gap: 16px;
}
.topbar-title {
  font-size: 18px;
  color: var(--text-primary);
}
.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar-time {
  font-size: 13px;
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.05em;
}

.user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 4px 4px 10px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 99px;
}
.user-avatar {
  width: 28px;
  height: 28px;
  background: var(--accent-gold);
  color: var(--brew-espresso);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  flex-shrink: 0;
}
.user-info {
  display: flex;
  flex-direction: column;
}
.user-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.2;
}
.user-role {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: capitalize;
}

.main-content {
  flex: 1;
  overflow-y: auto;
  background:
    radial-gradient(circle at 20% 20%, rgba(212,168,83,0.12), transparent 26%),
    radial-gradient(circle at 80% 10%, rgba(255,255,255,0.05), transparent 22%),
    linear-gradient(0deg, rgba(0,0,0,0.35), rgba(0,0,0,0.35)),
    repeating-linear-gradient(90deg,
      rgba(0,0,0,0.16) 0px,
      rgba(0,0,0,0.16) 18px,
      rgba(255,255,255,0.03) 18px,
      rgba(255,255,255,0.03) 22px),
    linear-gradient(180deg, #1b0f08 0%, #0f0805 100%);
  background-size: 100% 100%, 100% 100%, 100% 100%, 140px 100%, 100% 100%;
  position: relative;
}
</style>
