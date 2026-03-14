<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Inventory</h2>
      <div class="flex gap-3 items-center">
        <label class="flex items-center gap-2 text-sm" style="cursor:pointer">
          <input type="checkbox" v-model="showLowStock" @change="loadInventory" />
          <span>Low stock only</span>
        </label>
        <button class="btn btn-secondary btn-sm" @click="openLogs">📋 View Logs</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state" style="height:300px">
      <div class="spinner" style="width:32px;height:32px;border-width:3px" />
    </div>

    <div v-else class="card" style="margin:0">
      <table class="table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Stock</th>
            <th>Low Stock Threshold</th>
            <th>Status</th>
            <th>Last Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in inventory" :key="inv.id" :class="{ 'low-stock-row': isLow(inv) }">
            <td>
              <div class="flex items-center gap-2">
                <span>{{ inv.product?.category?.icon || '☕' }}</span>
                <strong>{{ inv.product?.name }}</strong>
              </div>
            </td>
            <td><span class="text-muted">{{ inv.product?.category?.name }}</span></td>
            <td>
              <span class="stock-pill" :class="isLow(inv) ? 'low' : 'ok'">
                {{ inv.quantity }} {{ inv.unit }}
              </span>
            </td>
            <td><span class="text-muted">{{ inv.low_stock_threshold }}</span></td>
            <td>
              <span class="badge" :class="isLow(inv) ? 'badge-pending' : 'badge-ready'">
                {{ isLow(inv) ? '⚠ Low' : '✓ OK' }}
              </span>
            </td>
            <td><span class="text-muted text-sm">{{ formatDate(inv.updated_at) }}</span></td>
            <td>
              <button class="btn btn-secondary btn-sm" @click="openAdjust(inv)">Adjust</button>
            </td>
          </tr>
          <tr v-if="inventory.length === 0">
            <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No inventory records</td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.last_page > 1" class="pagination">
        <button class="btn btn-secondary btn-sm" :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">
          ← Prev
        </button>
        <span class="text-muted text-sm">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="btn btn-secondary btn-sm" :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)">
          Next →
        </button>
      </div>
    </div>

    <div v-if="adjustTarget" class="modal-overlay" @click.self="adjustTarget = null">
      <div class="modal" style="max-width:380px">
        <div class="modal-header">
          <h3 class="font-display">Adjust Stock</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="adjustTarget = null">✕</button>
        </div>
        <div class="modal-body">
          <p style="margin-bottom:16px"><strong>{{ adjustTarget.product?.name }}</strong></p>
          <p class="text-muted text-sm" style="margin-bottom:16px">
            Current stock: <strong>{{ adjustTarget.quantity }}</strong> {{ adjustTarget.unit }}
          </p>
          <div style="display:flex;flex-direction:column;gap:14px">
            <div class="form-group">
              <label class="form-label">Type</label>
              <select v-model="adjustForm.type" class="select">
                <option value="restock">📦 Restock (add)</option>
                <option value="adjustment">🔧 Adjustment</option>
                <option value="waste">🗑 Waste (remove)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Quantity Change <span class="text-muted">(use negative to remove)</span></label>
              <input v-model.number="adjustForm.quantity_change" type="number" class="input" placeholder="e.g. 50 or -10" />
            </div>
            <div class="form-group">
              <label class="form-label">Notes</label>
              <input v-model="adjustForm.notes" class="input" placeholder="Reason for adjustment" />
            </div>
          </div>
          <div class="new-qty-preview" style="margin-top:16px">
            New qty:
            <strong class="text-gold">{{ Math.max(0, adjustTarget.quantity + (adjustForm.quantity_change || 0)) }} {{ adjustTarget.unit }}</strong>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="adjustTarget = null">Cancel</button>
          <button class="btn btn-primary" :disabled="saving" @click="doAdjust">
            <span v-if="saving" class="spinner" style="width:14px;height:14px" />
            Confirm Adjustment
          </button>
        </div>
      </div>
    </div>

    <div v-if="showLogs" class="modal-overlay" @click.self="showLogs = false">
      <div class="modal" style="max-width:680px">
        <div class="modal-header">
          <h3 class="font-display">Inventory Logs</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="showLogs = false">✕</button>
        </div>
        <div class="modal-body" style="padding:0">
          <table class="table">
            <thead>
              <tr><th>Product</th><th>Type</th><th>Change</th><th>Before</th><th>After</th><th>Notes</th><th>Time</th></tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td>{{ log.product?.name }}</td>
                <td><span class="badge badge-pending" style="font-size:10px">{{ log.type }}</span></td>
                <td :class="log.quantity_change < 0 ? 'text-danger' : 'text-success'">
                  {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }}
                </td>
                <td class="text-muted">{{ log.quantity_before }}</td>
                <td>{{ log.quantity_after }}</td>
                <td class="text-muted text-sm">{{ log.notes || '—' }}</td>
                <td class="text-muted text-sm">{{ formatDate(log.created_at) }}</td>
              </tr>
              <tr v-if="logs.length === 0"><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted)">No logs yet</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/api';

const toast = useToast();

const inventory = ref([]);
const logs = ref([]);
const loading = ref(false);
const showLowStock = ref(false);
const showLogs = ref(false);
const adjustTarget = ref(null);
const saving = ref(false);
const pagination = ref({ current_page: 1, last_page: 1 });

const adjustForm = reactive({ type: 'restock', quantity_change: 0, notes: '' });

onMounted(loadInventory);

async function loadInventory(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 30 };
    if (showLowStock.value) params.low_stock = 1;
    const { data } = await api.get('/inventory', { params });
    inventory.value = data.data;
    pagination.value = { current_page: data.current_page, last_page: data.last_page };
  } finally {
    loading.value = false;
  }
}

async function loadLogs() {
  const { data } = await api.get('/inventory/logs');
  logs.value = data.data || data;
}

function openAdjust(inv) {
  adjustTarget.value = inv;
  Object.assign(adjustForm, { type: 'restock', quantity_change: 0, notes: '' });
}

async function doAdjust() {
  saving.value = true;
  try {
    await api.patch(`/inventory/${adjustTarget.value.id}/adjust`, adjustForm);
    toast.success('Stock adjusted!');
    adjustTarget.value = null;
    await loadInventory();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error adjusting stock');
  } finally {
    saving.value = false;
  }
}

async function openLogs() {
  showLogs.value = true;
  await loadLogs();
}

function goToPage(p) {
  loadInventory(p);
}
function isLow(inv) {
  return inv.quantity <= inv.low_stock_threshold;
}
function formatDate(s) {
  return new Date(s).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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
.low-stock-row td {
  background: rgba(243, 156, 18, 0.04);
}
.stock-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 13px;
  font-weight: 600;
}
.stock-pill.ok {
  background: rgba(39, 174, 96, 0.1);
  color: #27ae60;
}
.stock-pill.low {
  background: rgba(243, 156, 18, 0.12);
  color: var(--status-pending);
}
.new-qty-preview {
  font-size: 13px;
  color: var(--text-secondary);
}
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid var(--border);
}
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
