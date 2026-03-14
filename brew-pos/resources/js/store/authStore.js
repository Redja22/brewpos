import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/api';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('brewpos_user') || 'null'));
  const token = ref(localStorage.getItem('brewpos_token') || null);

  const isLoggedIn = computed(() => !!token.value && !!user.value);
  const isAdmin = computed(() => user.value?.role === 'admin');
  const isManager = computed(() => ['admin', 'manager'].includes(user.value?.role));
  const isCashier = computed(() => user.value?.role === 'cashier');
  const isKitchen = computed(() => user.value?.role === 'kitchen');

  async function login(email, password) {
    const { data } = await api.post('/login', { email, password });
    user.value = data.user;
    token.value = data.token;
    localStorage.setItem('brewpos_token', data.token);
    localStorage.setItem('brewpos_user', JSON.stringify(data.user));
    return data.user;
  }

  async function logout() {
    try {
      await api.post('/logout');
    } catch {}
    user.value = null;
    token.value = null;
    localStorage.removeItem('brewpos_token');
    localStorage.removeItem('brewpos_user');
  }

  return { user, token, isLoggedIn, isAdmin, isManager, isCashier, isKitchen, login, logout };
});
