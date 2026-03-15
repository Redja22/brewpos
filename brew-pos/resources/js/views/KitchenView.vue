<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Kitchen Display</h2>
      <div class="flex items-center gap-3">
        <div class="filter-tabs">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            class="tab-btn"
            :class="{ active: activeTab === tab.value }"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="tab-count" :class="tab.countClass">
              {{ tab.count }}
            </span>
          </button>
        </div>
        <button class="btn btn-secondary btn-sm" @click="loadOrders">⟳ Refresh</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state" style="height:240px">
      <div class="spinner" style="width:32px;height:32px;border-width:3px" />
    </div>

    <div v-else class="kitchen-grid">
      <template v-if="filteredOrders.length > 0">
        <div
          v-for="order in filteredOrders"
          :key="order.id"
          class="kitchen-card"
          :class="`status-${order.status}`"
        >
          <!-- Card header -->
          <div class="card-head">
            <span class="order-num">#{{ order.order_number }}</span>
            <span class="badge" :class="`badge-${order.status}`">{{ order.status }}</span>
          </div>

          <!-- Order meta -->
          <div class="order-meta">
            <span class="text-muted text-sm">{{ orderTypeLabel(order.order_type) }}</span>
            <span v-if="order.table" class="text-muted text-sm">🪑 {{ order.table.name }}</span>
          </div>

          <!-- Elapsed time — hide for completed -->
          <div v-if="order.status !== 'completed'" class="elapsed" :class="elapsedClass(order.created_at)">
            ⏱ {{ elapsed(order.created_at) }}
          </div>
          <div v-else class="elapsed elapsed-done">
            ✓ Done {{ elapsed(order.created_at) }} ago
          </div>

          <!-- Items list -->
          <div class="items">
            <div v-for="item in order.items" :key="item.id" class="item-row" :class="{ 'item-done': order.status === 'completed' }">
              <span>{{ item.product_name }}</span>
              <strong>× {{ item.quantity }}</strong>
            </div>
            <div v-if="order.notes" class="order-notes">
              📝 {{ order.notes }}
            </div>
          </div>

          <!-- Action buttons -->
          <div class="card-actions">
            <button
              v-if="order.status === 'preparing'"
              class="btn btn-primary btn-sm w-full"
              :disabled="updating === order.id"
              @click="markReady(order)"
            >
              {{ updating === order.id ? '...' : '✓ Mark Ready' }}
            </button>
            <button
              v-else-if="order.status === 'ready'"
              class="btn btn-success btn-sm w-full"
              :disabled="updating === order.id"
              @click="markCompleted(order)"
            >
              {{ updating === order.id ? '...' : '✓ Complete' }}
            </button>
            <div v-else class="completed-label">
              ✓ Completed
            </div>
          </div>
        </div>
      </template>

      <div v-else class="empty-state">
        <p>No {{ activeTab === 'all' ? 'active' : activeTab }} orders.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '@/api';

const orders   = ref([]);
const loading  = ref(false);
const updating = ref(null);
const activeTab = ref('all');
const now = ref(new Date());

let refreshTimer = null;
let clockTimer   = null;

onMounted(() => {
  loadOrders();
  refreshTimer = setInterval(loadOrders, 30_000);
  clockTimer   = setInterval(() => { now.value = new Date(); }, 10_000);
});

onUnmounted(() => {
  clearInterval(refreshTimer);
  clearInterval(clockTimer);
});

// ─── Tabs ─────────────────────────────────────────────────────────────────────

const statusTabs = computed(() => [
  {
    value: 'all',
    label: 'All Active',
    count: countByStatus('preparing') + countByStatus('ready'),
    countClass: 'count-gold',
  },
  {
    value: 'preparing',
    label: 'Preparing',
    count: countByStatus('preparing'),
    countClass: 'count-gold',
  },
  {
    value: 'ready',
    label: 'Ready',
    count: countByStatus('ready'),
    countClass: 'count-green',
  },
  {
    value: 'completed',
    label: 'Completed',
    count: countByStatus('completed'),
    countClass: 'count-muted',
  },
]);

// ─── Data ─────────────────────────────────────────────────────────────────────

async function loadOrders() {
  loading.value = true;
  try {
    const { data } = await api.get('/kitchen/orders');
    orders.value = Array.isArray(data) ? data : (data.data ?? []);
  } finally {
    loading.value = false;
  }
}

async function markReady(order) {
  updating.value = order.id;
  try {
    await api.patch(`/kitchen/orders/${order.id}/ready`);
    await loadOrders();
  } finally {
    updating.value = null;
  }
}

async function markCompleted(order) {
  updating.value = order.id;
  try {
    await api.patch(`/kitchen/orders/${order.id}/complete`);
    await loadOrders();
  } finally {
    updating.value = null;
  }
}

// ─── Computed ─────────────────────────────────────────────────────────────────

const filteredOrders = computed(() => {
  if (activeTab.value === 'all') {
    // "All Active" = preparing + ready only, not completed
    return orders.value.filter(o => ['preparing', 'ready'].includes(o.status));
  }
  return orders.value.filter(o => o.status === activeTab.value);
});

function countByStatus(status) {
  return orders.value.filter(o => o.status === status).length;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function orderTypeLabel(type) {
  return { dine_in: '🪑 Dine In', takeout: '🥡 Takeout', delivery: '🛵 Delivery' }[type] || type;
}

function elapsed(createdAt) {
  const diff = Math.floor((now.value - new Date(createdAt)) / 1000);
  if (diff < 60)   return `${diff}s`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m`;
  return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m`;
}

function elapsedClass(createdAt) {
  const mins = Math.floor((now.value - new Date(createdAt)) / 60_000);
  if (mins >= 15) return 'elapsed-danger';
  if (mins >= 8)  return 'elapsed-warn';
  return 'elapsed-ok';
}
</script>

<style scoped>
.page-wrap   { padding: 16px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }

/* Tabs */
.filter-tabs { display: flex; gap: 4px; background: var(--surface-3); padding: 3px; border-radius: var(--radius-md); }
.tab-btn {
  padding: 4px 12px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 500;
  border: none;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: background 0.15s, color 0.15s;
}
.tab-btn.active { background: var(--surface-1); color: var(--text-primary); }

.tab-count {
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  min-width: 18px;
  text-align: center;
  padding: 0 5px;
}
.count-gold  { background: var(--accent-gold); color: #000; }
.count-green { background: rgba(39,174,96,0.2); color: #27ae60; }
.count-muted { background: var(--surface-3); color: var(--text-muted); }

/* Grid */
.kitchen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }

/* Card */
.kitchen-card {
  background: var(--surface-2);
  border: 2px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: border-color 0.2s, opacity 0.2s;
}
.kitchen-card.status-ready     { border-color: var(--accent-gold); }
.kitchen-card.status-completed { opacity: 0.55; border-color: var(--border); }

.card-head  { display: flex; justify-content: space-between; align-items: center; }
.order-num  { font-weight: 700; font-size: 16px; color: var(--accent-gold); }
.order-meta { display: flex; gap: 8px; flex-wrap: wrap; }

/* Elapsed */
.elapsed         { font-size: 12px; font-weight: 600; }
.elapsed-ok      { color: var(--text-muted); }
.elapsed-warn    { color: #f39c12; }
.elapsed-danger  { color: #e74c3c; }
.elapsed-done    { color: var(--text-muted); font-size: 12px; }

/* Items */
.items      { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.item-row   { display: flex; justify-content: space-between; font-size: 13px; }
.item-done  { opacity: 0.5; text-decoration: line-through; }
.order-notes { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-style: italic; }

/* Actions */
.card-actions   { margin-top: 4px; }
.w-full         { width: 100%; }
.completed-label {
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-muted);
  padding: 6px 0;
}

.empty-state    { grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--text-muted); }
.loading-state  { display: flex; align-items: center; justify-content: center; }
</style>
