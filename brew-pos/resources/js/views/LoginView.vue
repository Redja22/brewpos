<template>
  <div class="login-page">
    <div class="login-bg">
      <div class="bean" v-for="n in 8" :key="n" :style="beanStyle(n)" />
    </div>

    <div class="login-card">
      <div class="login-logo">
        <span class="logo-icon">☕</span>
        <h1 class="font-display">BrewPOS</h1>
        <p class="text-muted text-sm">Coffee Shop Management System</p>
      </div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="input"
            placeholder="admin@brewpos.com"
            autocomplete="email"
            required
          />
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-eye">
            <input
              v-model="form.password"
              :type="showPass ? 'text' : 'password'"
              class="input"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            />
            <button type="button" class="eye-btn" @click="showPass = !showPass">
              {{ showPass ? '🙈' : '👁' }}
            </button>
          </div>
        </div>

        <p v-if="error" class="login-error">{{ error }}</p>

        <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="loading">
          <span v-if="loading" class="spinner" style="width:16px;height:16px;" />
          <span>{{ loading ? 'Signing in…' : 'Sign In' }}</span>
        </button>
      </form>

      <div class="login-hints">
        <p class="text-muted text-sm" style="margin-bottom:6px">Demo credentials:</p>
        <div class="hint-grid">
          <button v-for="u in demoUsers" :key="u.email" class="hint-btn" @click="fillDemo(u)">
            <span class="hint-role">{{ u.role }}</span>
            <span class="hint-email">{{ u.email }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/store/authStore';

const router = useRouter();
const auth = useAuthStore();

const form = ref({ email: '', password: 'password' });
const loading = ref(false);
const error = ref('');
const showPass = ref(false);

const demoUsers = [
  { role: 'Admin', email: 'admin@brewpos.com' },
  { role: 'Manager', email: 'manager@brewpos.com' },
  { role: 'Cashier', email: 'cashier@brewpos.com' },
  { role: 'Kitchen', email: 'kitchen@brewpos.com' },
];

function fillDemo(u) {
  form.value.email = u.email;
  form.value.password = 'password';
}

function beanStyle(n) {
  const angle = (n / 8) * 360;
  const r = 280 + (n % 3) * 60;
  return {
    left: `calc(50% + ${Math.cos((angle * Math.PI) / 180) * r}px)`,
    top: `calc(50% + ${Math.sin((angle * Math.PI) / 180) * r}px)`,
    opacity: 0.03 + (n % 4) * 0.02,
    transform: `rotate(${angle + 30}deg) scale(${0.8 + (n % 3) * 0.4})`,
    animationDelay: `${n * 0.7}s`,
  };
}

async function handleLogin() {
  error.value = '';
  loading.value = true;
  try {
    const user = await auth.login(form.value.email, form.value.password);
    const dest = user.role === 'kitchen' ? '/kitchen' : '/pos';
    router.push(dest);
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid credentials. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--brew-espresso);
  position: relative;
  overflow: hidden;
  padding: 24px;
}
.login-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}
.bean {
  position: absolute;
  width: 80px;
  height: 48px;
  background: var(--brew-caramel);
  border-radius: 50%;
  animation: float 8s ease-in-out infinite alternate;
}
@keyframes float {
  0% {
    transform: translateY(0) rotate(0deg);
  }
  100% {
    transform: translateY(-20px) rotate(15deg);
  }
}

.login-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  padding: 40px 36px;
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-lg), var(--shadow-glow);
  position: relative;
  z-index: 1;
  animation: slideUp 0.4s ease;
}

.login-logo {
  text-align: center;
  margin-bottom: 32px;
}
.logo-icon {
  font-size: 48px;
  display: block;
  margin-bottom: 8px;
}
.login-logo h1 {
  font-size: 28px;
  color: var(--accent-gold);
  margin-bottom: 4px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
  margin-bottom: 24px;
}

.input-eye {
  position: relative;
}
.input-eye .input {
  padding-right: 40px;
}
.eye-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  opacity: 0.6;
}
.eye-btn:hover {
  opacity: 1;
}

.login-error {
  padding: 10px 14px;
  background: rgba(231, 76, 60, 0.1);
  border: 1px solid rgba(231, 76, 60, 0.3);
  border-radius: var(--radius-md);
  color: #e74c3c;
  font-size: 13px;
}

.login-hints {
  border-top: 1px solid var(--border);
  padding-top: 20px;
}
.hint-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-top: 8px;
}
.hint-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 8px 10px;
  background: var(--surface-3);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all var(--transition);
}
.hint-btn:hover {
  border-color: var(--accent-gold);
  background: var(--surface-4);
}
.hint-role {
  font-size: 10px;
  font-weight: 600;
  color: var(--accent-gold);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.hint-email {
  font-size: 11px;
  color: var(--text-muted);
}
</style>
