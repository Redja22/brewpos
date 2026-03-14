<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal" style="max-width:480px">
      <div class="modal-header">
        <h3 class="font-display">Checkout</h3>
        <button class="btn btn-ghost btn-icon btn-sm" @click="$emit('close')">✕</button>
      </div>

      <!-- Order summary -->
      <div class="modal-body">
        <div class="order-summary">
          <div v-for="item in pos.cart" :key="item.product_id" class="summary-row">
            <span>{{ item.product_name }} × {{ item.quantity }}</span>
            <span>{{ fmt(item.product_price * item.quantity) }}</span>
          </div>
          <div class="summary-divider" />
          <div class="summary-row">
            <span class="text-muted">Subtotal</span><span>{{ fmt(pos.cartSubtotal) }}</span>
          </div>
          <div class="summary-row">
            <span class="text-muted">VAT ({{ pos.settings.tax_rate }}%)</span>
            <span>{{ fmt(pos.taxAmount) }}</span>
          </div>
          <div class="summary-row summary-total">
            <span>Total Due</span>
            <span class="text-gold" style="font-size:22px;font-family:var(--font-display)">{{ fmt(pos.cartTotal) }}</span>
          </div>
        </div>

        <!-- Payment method -->
        <div class="pay-methods">
          <button
            v-for="m in methods"
            :key="m.id"
            class="method-btn"
            :class="{ active: method === m.id }"
            @click="method = m.id; reference = ''"
          >
            <span class="method-icon">{{ m.icon }}</span>
            <span>{{ m.label }}</span>
          </button>
        </div>

        <!-- Cash: tender + change -->
        <div v-if="method === 'cash'" class="pay-fields">
          <div class="form-group">
            <label class="form-label">Amount Tendered</label>
            <input
              v-model.number="tendered"
              type="number"
              class="input"
              :placeholder="`${pos.settings.currency_symbol || '₱'}${pos.cartTotal.toFixed(2)}`"
              min="0"
              step="0.01"
              @focus="$event.target.select()"
            />
          </div>
          <div class="quick-amounts">
            <button v-for="q in quickAmounts" :key="q" class="quick-btn" @click="tendered = q">
              {{ fmt(q) }}
            </button>
          </div>
          <div v-if="tendered >= pos.cartTotal" class="change-display">
            <span class="text-muted">Change</span>
            <span class="change-amount">{{ fmt(tendered - pos.cartTotal) }}</span>
          </div>
        </div>

        <!-- Card / GCash: reference -->
        <div v-else class="pay-fields">
          <div class="form-group">
            <label class="form-label">Reference / Approval Number</label>
            <input
              v-model="reference"
              class="input"
              :placeholder="method === 'gcash' ? 'GCash reference no.' : 'Card approval no.'"
            />
          </div>
        </div>

        <p v-if="error" style="color:#e74c3c;font-size:12px;margin-top:8px">{{ error }}</p>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="$emit('close')">Cancel</button>
        <button class="btn btn-primary btn-lg" :disabled="!canPay || loading" @click="pay">
          <span v-if="loading" class="spinner" style="width:16px;height:16px" />
          <span>{{ loading ? 'Processing…' : `Confirm Payment — ${fmt(pos.cartTotal)}` }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePOSStore } from '@/store/posStore';
import api from '@/api';

const emit = defineEmits(['close', 'paid']);
const pos = usePOSStore();

const method = ref('cash');
const tendered = ref(0);
const reference = ref('');
const loading = ref(false);
const error = ref('');

const methods = [
  { id: 'cash', icon: '💵', label: 'Cash' },
  { id: 'card', icon: '💳', label: 'Card' },
  { id: 'gcash', icon: '📱', label: 'GCash' },
];

const quickAmounts = computed(() => {
  const t = pos.cartTotal;
  const round = (v) => Math.ceil(v);
  return [round(t), round(t / 100) * 100 + (t % 100 > 0 ? 100 : 0), 500, 1000]
    .filter((v, i, a) => v >= t && a.indexOf(v) === i)
    .slice(0, 4);
});

const canPay = computed(() => {
  if (method.value === 'cash') return tendered.value >= pos.cartTotal;
  return true;
});

function fmt(v) {
  return `${pos.settings.currency_symbol || '₱'}${parseFloat(v || 0).toFixed(2)}`;
}

async function pay() {
  error.value = '';
  loading.value = true;
  try {
    const order = await pos.submitOrder();

    await api.post('/payments', {
      order_id: order.id,
      method: method.value,
      amount_tendered: method.value === 'cash' ? tendered.value : pos.cartTotal,
      reference_number: reference.value || null,
    });

    emit('paid', order);
  } catch (e) {
    error.value = e.response?.data?.message || 'Payment failed. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.order-summary {
  background: var(--surface-3);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow-y: auto;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.summary-total {
  font-size: 15px;
  font-weight: 600;
  padding-top: 8px;
  border-top: 1px solid var(--border);
}
.summary-divider {
  border-top: 1px solid var(--border);
  margin: 4px 0;
}

.pay-methods {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}
.method-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 14px 8px;
  border-radius: var(--radius-md);
  border: 2px solid var(--border);
  background: var(--surface-3);
  cursor: pointer;
  transition: all var(--transition);
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
}
.method-btn:hover {
  border-color: var(--accent-gold);
  color: var(--text-primary);
}
.method-btn.active {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 83, 0.1);
  color: var(--accent-gold);
}
.method-icon {
  font-size: 24px;
}

.pay-fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quick-amounts {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.quick-btn {
  padding: 6px 14px;
  border-radius: var(--radius-sm);
  background: var(--surface-3);
  border: 1px solid var(--border);
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 12px;
  transition: all var(--transition);
}
.quick-btn:hover {
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.change-display {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(39, 174, 96, 0.1);
  border: 1px solid rgba(39, 174, 96, 0.3);
  border-radius: var(--radius-md);
  padding: 12px 16px;
}
.change-amount {
  font-size: 22px;
  font-weight: 700;
  color: #27ae60;
  font-family: var(--font-display);
}
</style>
