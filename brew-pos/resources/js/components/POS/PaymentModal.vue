<template>
  <div class="modal-overlay" @click.self="handleOverlayClick">
    <div class="modal" style="max-width:480px">

      <!-- ═══════════════════════════════════════════
           CHECKOUT VIEW
      ═══════════════════════════════════════════ -->
      <template v-if="!receiptData">
        <div class="modal-header">
          <h3 class="font-display">Checkout</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="$emit('close')">✕</button>
        </div>

        <div class="modal-body">
          <!-- Order summary -->
          <div class="order-summary">
            <div v-for="item in pos.cart" :key="item.product_id" class="summary-row">
              <span>{{ item.product_name }} × {{ item.quantity }}</span>
              <span>{{ fmt(item.product_price * item.quantity) }}</span>
            </div>
            <div class="summary-divider" />
            <div class="summary-row">
              <span class="text-muted">Subtotal</span>
              <span>{{ fmt(pos.cartSubtotal) }}</span>
            </div>
            <div class="summary-row">
              <span class="text-muted">VAT ({{ pos.settings.tax_rate }}%)</span>
              <span>{{ fmt(pos.taxAmount) }}</span>
            </div>
            <div class="summary-row summary-total">
              <span>Total Due</span>
              <span class="text-gold" style="font-size:22px;font-family:var(--font-display)">
                {{ fmt(pos.cartTotal) }}
              </span>
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
      </template>

      <!-- ═══════════════════════════════════════════
           RECEIPT VIEW
      ═══════════════════════════════════════════ -->
      <template v-else>
        <div class="modal-header receipt-header">
          <h3 class="font-display">Receipt</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="closeReceipt">✕</button>
        </div>

        <div class="modal-body receipt-body">
          <div class="receipt-paper" id="receipt-printable">

            <!-- Store header -->
            <div class="receipt-store-header">
              <div class="receipt-logo"><span>☕</span></div>
              <div class="receipt-store-info">
                <div class="receipt-store-name">{{ pos.settings.store_name || 'Brew & Co.' }}</div>
                <div class="receipt-store-address">{{ pos.settings.store_address || '' }}</div>
              </div>
            </div>

            <div class="receipt-divider dashed" />

            <!-- Receipt meta -->
            <div class="receipt-meta-grid">
              <div class="receipt-meta-row">
                <span class="receipt-meta-label">Receipt #</span>
                <span class="receipt-meta-value">{{ receiptData.order.order_number }}</span>
              </div>
              <div class="receipt-meta-row">
                <span class="receipt-meta-label">Date</span>
                <span class="receipt-meta-value">{{ formatDate(receiptData.order.created_at) }}</span>
              </div>
              <div class="receipt-meta-row">
                <span class="receipt-meta-label">Cashier</span>
                <span class="receipt-meta-value">{{ receiptData.order.cashier?.name || '—' }}</span>
              </div>
              <div v-if="receiptData.order.table" class="receipt-meta-row">
                <span class="receipt-meta-label">Table</span>
                <span class="receipt-meta-value">{{ receiptData.order.table.name }}</span>
              </div>
              <div v-if="receiptData.order.customer_name" class="receipt-meta-row">
                <span class="receipt-meta-label">Customer</span>
                <span class="receipt-meta-value">{{ receiptData.order.customer_name }}</span>
              </div>
              <div class="receipt-meta-row">
                <span class="receipt-meta-label">Type</span>
                <span class="receipt-meta-value receipt-order-type">
                  {{ orderTypeLabel(receiptData.order.order_type) }}
                </span>
              </div>
            </div>

            <div class="receipt-divider dashed" />

            <!-- Items -->
            <div class="receipt-items-header">
              <span class="col-qty">QTY</span>
              <span class="col-desc">DESCRIPTION</span>
              <span class="col-price">PRICE</span>
              <span class="col-amount">AMOUNT</span>
            </div>
            <div class="receipt-divider solid thin" />

            <div v-for="item in receiptData.order.items" :key="item.id" class="receipt-item-row">
              <span class="col-qty">{{ item.quantity }}</span>
              <span class="col-desc">
                {{ item.product_name }}
                <span v-if="item.notes" class="item-notes">{{ item.notes }}</span>
              </span>
              <span class="col-price">{{ fmt(item.product_price) }}</span>
              <span class="col-amount">{{ fmt(item.product_price * item.quantity) }}</span>
            </div>

            <div class="receipt-divider solid thin" style="margin-top:8px" />

            <!-- Totals -->
            <div class="receipt-totals">
              <div class="receipt-total-row">
                <span>Subtotal</span>
                <span>{{ fmt(receiptData.order.subtotal) }}</span>
              </div>
              <div class="receipt-total-row">
                <span>VAT ({{ pos.settings.tax_rate }}%)</span>
                <span>{{ fmt(receiptData.order.tax_amount) }}</span>
              </div>
              <div v-if="receiptData.order.discount_amount > 0" class="receipt-total-row">
                <span>Discount</span>
                <span>-{{ fmt(receiptData.order.discount_amount) }}</span>
              </div>
            </div>

            <div class="receipt-divider solid" />

            <div class="receipt-grand-total">
              <span>TOTAL</span>
              <span>{{ fmt(receiptData.order.total_amount) }}</span>
            </div>

            <div class="receipt-divider dashed" />

            <!-- Payment info -->
            <div class="receipt-payment-info">
              <div class="receipt-total-row">
                <span>Payment Method</span>
                <span class="receipt-method-badge">
                  {{ paymentMethodLabel(receiptData.payment.method) }}
                </span>
              </div>
              <div v-if="receiptData.payment.method === 'cash'" class="receipt-total-row">
                <span>Amount Tendered</span>
                <span>{{ fmt(receiptData.payment.amount_tendered) }}</span>
              </div>
              <div v-if="receiptData.payment.method === 'cash'" class="receipt-total-row">
                <span>Change</span>
                <span class="receipt-change">{{ fmt(receiptData.payment.change_amount) }}</span>
              </div>
              <div v-if="receiptData.payment.reference_number" class="receipt-total-row">
                <span>Reference #</span>
                <span>{{ receiptData.payment.reference_number }}</span>
              </div>
            </div>

            <div class="receipt-divider dashed" />

            <!-- Footer -->
            <div class="receipt-footer">
              <div class="receipt-thank-you">Thank you!</div>
              <p class="receipt-tagline">Please come again 😊</p>
              <p class="receipt-terms">This serves as your official receipt.</p>
            </div>

          </div>
        </div>

        <div class="modal-footer receipt-footer-actions">
          <button class="btn btn-secondary" @click="printReceipt">🖨 Print</button>
          <button class="btn btn-primary" style="flex:1" @click="closeReceipt">✓ Done</button>
        </div>
      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePOSStore } from '@/store/posStore';
import api from '@/api';

// ── IMPORTANT ──────────────────────────────────────────────────────────────
// emit('paid') is called ONLY when the cashier clicks "Done" on the receipt.
// This keeps the modal open so the receipt stays visible after payment.
// ───────────────────────────────────────────────────────────────────────────
const emit = defineEmits(['close', 'paid']);
const pos  = usePOSStore();

const method      = ref('cash');
const tendered    = ref(0);
const reference   = ref('');
const loading     = ref(false);
const error       = ref('');
const receiptData = ref(null);  // null = checkout view  |  object = receipt view
const paidOrder   = ref(null);  // saved so we can emit it when Done is clicked

const methods = [
  { id: 'cash',  icon: '💵', label: 'Cash'  },
  { id: 'card',  icon: '💳', label: 'Card'  },
  { id: 'gcash', icon: '📱', label: 'GCash' },
];

const quickAmounts = computed(() => {
  const t = pos.cartTotal;
  return [Math.ceil(t), Math.ceil(t / 100) * 100 + (t % 100 > 0 ? 100 : 0), 500, 1000]
    .filter((v, i, a) => v >= t && a.indexOf(v) === i)
    .slice(0, 4);
});

const canPay = computed(() => {
  if (method.value === 'cash') return tendered.value >= pos.cartTotal;
  return true;
});

// ── Helpers ──────────────────────────────────────────────────
function fmt(v) {
  return `${pos.settings.currency_symbol || '₱'}${parseFloat(v || 0).toFixed(2)}`;
}

function formatDate(dateStr) {
  const d = dateStr ? new Date(dateStr) : new Date();
  return d.toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
}

function orderTypeLabel(type) {
  return { dine_in: 'Dine In', takeout: 'Takeout', delivery: 'Delivery' }[type] ?? type;
}

function paymentMethodLabel(m) {
  return { cash: 'Cash', card: 'Credit / Debit Card', gcash: 'GCash' }[m] ?? m;
}

// ── Pay ──────────────────────────────────────────────────────
async function pay() {
  error.value   = '';
  loading.value = true;

  try {
    // 1) Create order
    const order = await pos.submitOrder();
    console.log('✅ Order created:', order);

    // 2) Process payment
    const { data: paymentData } = await api.post('/payments', {
      order_id:         order.id,
      method:           method.value,
      amount_tendered:  method.value === 'cash' ? tendered.value : pos.cartTotal,
      reference_number: reference.value || null,
    });
    console.log('✅ Payment response:', paymentData);

    // 3) Fetch full order with all relationships for the receipt
    const { data: freshOrder } = await api.get(`/orders/${order.id}`);
    console.log('✅ Fresh order:', freshOrder);

    // 4) Save order then switch to receipt view
    paidOrder.value   = order;
    receiptData.value = {
      order:   freshOrder,
      payment: paymentData.payment,
      change:  paymentData.change,
    };
    console.log('✅ receiptData set — receipt should now show');

    // ⚠️  DO NOT emit('paid') here — the parent's onOrderPlaced() calls
    //     pos.clearCart() + emit('close'), which would destroy this modal
    //     before the cashier even sees the receipt.
    //     We emit('paid') in closeReceipt() instead.

  } catch (e) {
    console.error('❌ Payment error:', e);
    error.value = e.response?.data?.message || 'Payment failed. Please try again.';
  } finally {
    loading.value = false;
  }
}

// ── Receipt actions ───────────────────────────────────────────

// Cashier clicked "Done" — NOW notify parent and close
function closeReceipt() {
  emit('paid', paidOrder.value);
  emit('close');
}

// Clicking the dark overlay only closes when on checkout view
function handleOverlayClick() {
  if (!receiptData.value) {
    emit('close');
  }
}

function printReceipt() {
  const el = document.getElementById('receipt-printable');
  if (!el) return;

  const w = window.open('', '_blank', 'width=400,height=680');
  w.document.write(`
    <html>
      <head>
        <title>Receipt — ${receiptData.value?.order?.order_number ?? ''}</title>
        <style>
          * { margin:0; padding:0; box-sizing:border-box; }
          body { font-family:'Courier New',monospace; font-size:12px; padding:16px; color:#1a1a1a; }
          .receipt-store-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
          .receipt-logo { width:44px; height:44px; background:#1a1a2e; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; }
          .receipt-store-name { font-family:Georgia,serif; font-size:16px; font-weight:700; }
          .receipt-store-address { font-size:10px; color:#666; margin-top:2px; }
          .dashed { border-top:1px dashed #bbb; margin:8px 0; }
          .solid  { border-top:2px solid #1a1a2e; margin:6px 0; }
          .thin   { border-top:1px solid #ddd; }
          .receipt-meta-grid { display:flex; flex-direction:column; gap:3px; margin:6px 0; }
          .receipt-meta-row { display:flex; justify-content:space-between; font-size:11px; }
          .receipt-meta-label { color:#666; }
          .receipt-meta-value { font-weight:600; }
          .receipt-order-type { background:#1a1a2e; color:#fff; padding:1px 6px; border-radius:3px; font-size:10px; text-transform:uppercase; }
          .receipt-items-header { display:grid; grid-template-columns:28px 1fr 60px 60px; font-size:10px; font-weight:700; text-transform:uppercase; color:#555; margin-bottom:4px; }
          .receipt-item-row { display:grid; grid-template-columns:28px 1fr 60px 60px; font-size:11px; margin-bottom:4px; }
          .col-price,.col-amount { text-align:right; }
          .col-amount { font-weight:600; }
          .item-notes { display:block; font-size:10px; color:#888; font-style:italic; }
          .receipt-totals { display:flex; flex-direction:column; gap:3px; margin:6px 0; }
          .receipt-total-row { display:flex; justify-content:space-between; font-size:11px; color:#444; }
          .receipt-grand-total { display:flex; justify-content:space-between; font-size:16px; font-weight:800; font-family:Georgia,serif; padding:4px 0; }
          .receipt-payment-info { display:flex; flex-direction:column; gap:3px; margin:6px 0; }
          .receipt-method-badge { background:#f0f0f0; padding:1px 6px; border-radius:3px; font-size:10px; font-weight:700; text-transform:uppercase; }
          .receipt-change { font-weight:700; color:#27ae60; }
          .receipt-footer { text-align:center; padding-top:10px; }
          .receipt-thank-you { font-family:'Brush Script MT',cursive; font-size:26px; }
          .receipt-tagline { font-size:11px; color:#666; margin-top:4px; }
          .receipt-terms { font-size:10px; color:#aaa; margin-top:6px; border-top:1px solid #eee; padding-top:6px; }
        </style>
      </head>
      <body>${el.innerHTML}</body>
    </html>
  `);
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); w.close(); }, 300);
}
</script>

<style scoped>
/* ── Checkout styles ─────────────────────────────────────── */
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
.summary-row { display: flex; justify-content: space-between; font-size: 13px; }
.summary-total { font-size: 15px; font-weight: 600; padding-top: 8px; border-top: 1px solid var(--border); }
.summary-divider { border-top: 1px solid var(--border); margin: 4px 0; }

.pay-methods { display: flex; gap: 10px; margin-bottom: 20px; }
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
.method-btn:hover { border-color: var(--accent-gold); color: var(--text-primary); }
.method-btn.active {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 83, 0.1);
  color: var(--accent-gold);
}
.method-icon { font-size: 24px; }

.pay-fields { display: flex; flex-direction: column; gap: 12px; }

.quick-amounts { display: flex; gap: 8px; flex-wrap: wrap; }
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
.quick-btn:hover { border-color: var(--accent-gold); color: var(--accent-gold); }

.change-display {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(39, 174, 96, 0.1);
  border: 1px solid rgba(39, 174, 96, 0.3);
  border-radius: var(--radius-md);
  padding: 12px 16px;
}
.change-amount { font-size: 22px; font-weight: 700; color: #27ae60; font-family: var(--font-display); }

/* ── Receipt styles ──────────────────────────────────────── */
.receipt-body {
  padding: 0 !important;
  max-height: 70vh;
  overflow-y: auto;
}

.receipt-paper {
  background: #fff;
  color: #1a1a1a;
  font-family: 'Courier New', Courier, monospace;
  padding: 24px 28px 20px;
  font-size: 12px;
  line-height: 1.5;
}

.receipt-store-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}
.receipt-logo {
  width: 52px;
  height: 52px;
  background: #1a1a2e;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  flex-shrink: 0;
}
.receipt-store-info { display: flex; flex-direction: column; }
.receipt-store-name {
  font-family: Georgia, serif;
  font-size: 17px;
  font-weight: 700;
  color: #1a1a2e;
  letter-spacing: 0.5px;
}
.receipt-store-address { font-size: 10px; color: #666; margin-top: 2px; }

.receipt-divider.dashed { border-top: 1px dashed #bbb; margin: 10px 0; }
.receipt-divider.solid  { border-top: 2px solid #1a1a2e; margin: 8px 0; }
.receipt-divider.thin   { border-top: 1px solid #ddd; margin: 6px 0; }

.receipt-meta-grid { display: flex; flex-direction: column; gap: 3px; }
.receipt-meta-row { display: flex; justify-content: space-between; font-size: 11px; }
.receipt-meta-label { color: #666; }
.receipt-meta-value { font-weight: 600; color: #1a1a2e; }
.receipt-order-type {
  background: #1a1a2e;
  color: #fff;
  padding: 1px 7px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.receipt-items-header {
  display: grid;
  grid-template-columns: 28px 1fr 60px 60px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #555;
  margin-bottom: 6px;
}
.receipt-item-row {
  display: grid;
  grid-template-columns: 28px 1fr 60px 60px;
  font-size: 11.5px;
  margin-bottom: 5px;
  align-items: start;
}
.col-qty    { color: #555; }
.col-desc   { color: #1a1a2e; }
.col-price  { text-align: right; color: #555; }
.col-amount { text-align: right; font-weight: 600; color: #1a1a2e; }
.item-notes { display: block; font-size: 10px; color: #888; font-style: italic; }

.receipt-totals { display: flex; flex-direction: column; gap: 3px; margin: 6px 0; }
.receipt-total-row {
  display: flex;
  justify-content: space-between;
  font-size: 11.5px;
  color: #444;
}

.receipt-grand-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 17px;
  font-weight: 800;
  color: #1a1a2e;
  padding: 4px 0;
  font-family: Georgia, serif;
  letter-spacing: 0.5px;
}

.receipt-payment-info { display: flex; flex-direction: column; gap: 4px; }
.receipt-method-badge {
  background: #f0f0f0;
  color: #333;
  padding: 1px 8px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.receipt-change { font-weight: 700; color: #27ae60; }

.receipt-footer { text-align: center; padding-top: 10px; }
.receipt-thank-you {
  font-family: 'Brush Script MT', cursive;
  font-size: 28px;
  color: #1a1a2e;
  line-height: 1.2;
}
.receipt-tagline { font-size: 11px; color: #666; margin-top: 4px; }
.receipt-terms {
  font-size: 10px;
  color: #aaa;
  margin-top: 6px;
  border-top: 1px solid #eee;
  padding-top: 6px;
}

.receipt-footer-actions { gap: 10px; }
</style>
