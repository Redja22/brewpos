<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Kitchen Display</h2>
      <button class="btn btn-secondary btn-sm" @click="loadOrders">⟳ Refresh</button>
    </div>

    <div v-if="loading" class="loading-state" style="height:240px">
      <div class="spinner" style="width:32px;height:32px;border-width:3px" />
    </div>

    <div v-else class="kitchen-grid">
      <div v-for="order in orders" :key="order.id" class="kitchen-card" :class="`status-${order.status}`">
        <div class="card-head">
          <span class="order-num">#{{ order.order_number }}</span>
          <span class="badge" :class="`badge-${order.status}`">{{ order.status }}</span>
        </div>
        <p class="text-muted text-sm">{{ orderTypeLabel(order.order_type) }}</p>
        <div class="items">
          <div v-for="item in order.items" :key="item.id" class="item-row">
            <span>{{ item.product_name }}</span>
            <strong>× {{ item.quantity }}</strong>
          </div>
        </div>
        <div class="card-actions">
          <button v-if="order.status === 'pending'" class="btn btn-secondary btn-sm" @click="updateStatus(order, 'preparing')">Start</button>
          <button v-else-if="order.status === 'preparing'" class="btn btn-primary btn-sm" @click="updateStatus(order, 'ready')">Mark Ready</button>
          <button v-else-if="order.status === 'ready'" class="btn btn-success btn-sm" @click="updateStatus(order, 'completed')">Complete</button>
        </div>
      </div>
      <div v-if="orders.length === 0" class="empty-state">
        <p>No kitchen orders.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';

const orders = ref([]);
const loading = ref(false);

onMounted(loadOrders);

async function loadOrders() {
  loading.value = true;
  try {
    const { data } = await api.get('/orders', { params: { status: 'pending,preparing,ready' } });
    orders.value = data.data || data;
  } finally {
    loading.value = false;
  }
}

async function updateStatus(order, status) {
  await api.patch(`/orders/${order.id}/status`, { status });
  await loadOrders();
}

function orderTypeLabel(type) {
  return { dine_in: '🪑 Dine In', takeout: '🥡 Takeout', delivery: '🛵 Delivery' }[type] || type;
}
</script>

<style scoped>
.page-wrap { padding: 16px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.kitchen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.kitchen-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.card-head { display: flex; justify-content: space-between; align-items: center; }
.order-num { font-weight: 700; color: var(--accent-gold); }
.items { display: flex; flex-direction: column; gap: 4px; }
.item-row { display: flex; justify-content: space-between; font-size: 13px; }
.card-actions { display: flex; gap: 6px; }
.loading-state { display: flex; align-items: center; justify-content: center; }
</style>
