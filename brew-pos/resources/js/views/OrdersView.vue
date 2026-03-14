<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Orders</h2>
      <div class="flex items-center gap-3">
        <select v-model="filters.status" class="select" style="width:140px" @change="loadOrders">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="preparing">Preparing</option>
          <option value="ready">Ready</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input v-model="filters.date" type="date" class="input" style="width:160px" @change="loadOrders" />
        <button class="btn btn-secondary btn-sm" @click="loadOrders">⟳ Refresh</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state" style="height:300px">
      <div class="spinner" style="width:32px;height:32px;border-width:3px" />
    </div>

    <div v-else class="card" style="margin:0 16px 16px">
      <table class="table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Type</th>
            <th>Table</th>
            <th>Items</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Time</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="orders.length === 0">
            <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No orders found</td>
          </tr>
          <tr v-for="order in orders" :key="order.id">
            <td><span class="font-bold text-gold">{{ order.order_number }}</span></td>
            <td>{{ orderTypeLabel(order.order_type) }}</td>
            <td>{{ order.table?.name || '—' }}</td>
            <td>
              <span class="text-muted">{{ order.items?.length }} item(s)</span>
            </td>
            <td><strong>₱{{ order.total_amount }}</strong></td>
            <td>
              <span
                v-if="order.payment"
                class="badge"
                style="background:rgba(39,174,96,0.1);color:#27ae60;border:1px solid rgba(39,174,96,0.3)"
              >
                {{ order.payment.method }}
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td><span class="badge" :class="`badge-${order.status}`">{{ order.status }}</span></td>
            <td><span class="text-muted text-sm">{{ formatTime(order.created_at) }}</span></td>
            <td>
              <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm" @click="viewOrder(order)">View</button>
                <button v-if="order.status === 'ready'" class="btn btn-success btn-sm" @click="markCompleted(order)">
                  ✓ Done
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="pagination">
        <button
          class="btn btn-secondary btn-sm"
          :disabled="pagination.current_page === 1"
          @click="goToPage(pagination.current_page - 1)"
        >
          ← Prev
        </button>
        <span class="text-muted text-sm">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button
          class="btn btn-secondary btn-sm"
          :disabled="pagination.current_page === pagination.last_page"
          @click="goToPage(pagination.current_page + 1)"
        >
          Next →
        </button>
      </div>
    </div>

    <!-- Order detail modal -->
    <div v-if="selectedOrder" class="modal-overlay" @click.self="selectedOrder = null">
      <div class="modal" style="max-width:520px">
        <div class="modal-header">
          <h3 class="font-display">Order {{ selectedOrder.order_number }}</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="selectedOrder = null">✕</button>
        </div>
        <div class="modal-body">
          <div class="order-detail-grid">
            <div><span class="text-muted text-sm">Status</span><br /><span class="badge" :class="`badge-${selectedOrder.status}`">{{ selectedOrder.status }}</span></div>
            <div><span class="text-muted text-sm">Type</span><br /><span>{{ orderTypeLabel(selectedOrder.order_type) }}</span></div>
            <div><span class="text-muted text-sm">Table</span><br /><span>{{ selectedOrder.table?.name || '—' }}</span></div>
            <div><span class="text-muted text-sm">Cashier</span><br /><span>{{ selectedOrder.cashier?.name }}</span></div>
          </div>
          <hr class="divider" />
          <div class="order-items-list">
            <div v-for="item in selectedOrder.items" :key="item.id" class="order-item-row">
              <div>
                <p style="font-weight:500">{{ item.product_name }}</p>
                <p class="text-muted text-sm" v-if="item.notes">{{ item.notes }}</p>
              </div>
              <div style="text-align:right">
                <p>× {{ item.quantity }}</p>
                <p class="text-gold font-bold">₱{{ item.subtotal }}</p>
              </div>
            </div>
          </div>
          <hr class="divider" />
          <div class="order-totals">
            <div class="total-row-sm"><span class="text-muted">Subtotal</span><span>₱{{ selectedOrder.subtotal }}</span></div>
            <div class="total-row-sm"><span class="text-muted">Tax</span><span>₱{{ selectedOrder.tax_amount }}</span></div>
            <div class="total-row-sm" style="font-weight:700;font-size:16px"><span>Total</span><span class="text-gold">₱{{ selectedOrder.total_amount }}</span></div>
          </div>
          <div v-if="selectedOrder.payment" class="payment-info">
            <span class="text-muted text-sm">Paid via</span>
            <strong>{{ selectedOrder.payment.method.toUpperCase() }}</strong>
            <span v-if="selectedOrder.payment.reference_number" class="text-muted text-sm">· Ref: {{ selectedOrder.payment.reference_number }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/api';

const orders = ref([]);
const loading = ref(false);
const selectedOrder = ref(null);
const pagination = ref({ current_page: 1, last_page: 1 });
const filters = reactive({ status: '', date: '' });

onMounted(loadOrders);

async function loadOrders(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 25, ...filters };
    const { data } = await api.get('/orders', { params });
    orders.value = data.data;
    pagination.value = { current_page: data.current_page, last_page: data.last_page };
  } finally {
    loading.value = false;
  }
}

function goToPage(page) {
  loadOrders(page);
}

function viewOrder(order) {
  selectedOrder.value = order;
}

async function markCompleted(order) {
  await api.patch(`/orders/${order.id}/status`, { status: 'completed' });
  loadOrders();
}

function formatTime(str) {
  return new Date(str).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function orderTypeLabel(type) {
  return { dine_in: '🪑 Dine In', takeout: '🥡 Takeout', delivery: '🛵 Delivery' }[type] || type;
}
</script>

<style scoped>
.page-wrap {
  padding: 16px;
}
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
}
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid var(--border);
}
.order-detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 4px;
}
.order-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.order-item-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.order-totals {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.total-row-sm {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.payment-info {
  margin-top: 16px;
  padding: 10px 14px;
  background: var(--surface-3);
  border-radius: var(--radius-md);
  display: flex;
  gap: 8px;
  align-items: center;
}
</style>
