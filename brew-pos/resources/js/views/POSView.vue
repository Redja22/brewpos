<template>
  <div class="pos-screen">
    <!-- Left: Product Panel -->
    <div class="pos-products">
      <!-- Category bar -->
      <div class="category-bar">
        <button class="cat-btn" :class="{ active: pos.activeCategoryId === null }" @click="pos.activeCategoryId = null">
          All
        </button>
        <button
          v-for="cat in pos.categories"
          :key="cat.id"
          class="cat-btn"
          :class="{ active: pos.activeCategoryId === cat.id }"
          @click="pos.activeCategoryId = cat.id"
        >
          <span>{{ cat.icon }}</span> {{ cat.name }}
        </button>
      </div>

      <!-- Search -->
      <div class="product-search">
        <span class="search-icon">🔍</span>
        <input v-model="pos.searchQuery" class="input" placeholder="Search drinks, pastries…" />
        <button v-if="pos.searchQuery" class="btn btn-ghost btn-icon btn-sm" @click="pos.searchQuery = ''">✕</button>
      </div>

      <!-- Grid -->
      <div v-if="loading" class="loading-state">
        <div class="spinner" style="width:32px;height:32px;border-width:3px" />
        <p class="text-muted" style="margin-top:12px">Loading menu…</p>
      </div>

      <div v-else-if="pos.filteredProducts.length === 0" class="empty-state">
        <div class="icon">☕</div>
        <p>No products found</p>
      </div>

      <div v-else class="product-grid">
        <button
          v-for="p in pos.filteredProducts"
          :key="p.id"
          class="product-card"
          :class="{ 'out-of-stock': !p.is_available }"
          @click="addProduct(p)"
        >
          <div class="product-img">
            <img v-if="p.image_url" :src="p.image_url" :alt="p.name" />
            <span v-else class="product-emoji">{{ getCategoryIcon(p.category_id) }}</span>
          </div>
          <div class="product-info">
            <p class="product-name">{{ p.name }}</p>
            <p class="product-price">{{ formatCurrency(p.price) }}</p>
          </div>
          <div v-if="cartQty(p.id)" class="product-badge">{{ cartQty(p.id) }}</div>
        </button>
      </div>
    </div>

    <!-- Right: Cart Panel -->
    <div class="pos-cart">
      <!-- Cart header -->
      <div class="cart-header">
        <div class="cart-title-row">
          <h3 class="font-display" style="font-size:18px">Order</h3>
          <div class="order-meta">
            <select v-model="pos.orderType" class="select" style="width:auto;font-size:12px;padding:5px 28px 5px 8px">
              <option value="dine_in">🪑 Dine In</option>
              <option value="takeout">🥡 Takeout</option>
              <option value="delivery">🛵 Delivery</option>
            </select>
            <button
              v-if="pos.orderType === 'dine_in'"
              class="btn btn-secondary btn-sm"
              @click="showTableModal = true"
            >
              {{ pos.selectedTable ? `T${pos.selectedTable.number}` : '+ Table' }}
            </button>
          </div>
        </div>

        <!-- Show selected table chip -->
        <div v-if="pos.selectedTable" class="selected-table-chip">
          <span>🪑 {{ pos.selectedTable.name }}</span>
          <button class="chip-remove" @click="clearTable">✕</button>
        </div>

        <input
          v-model="pos.customerName"
          class="input"
          style="margin-top:8px;font-size:12px"
          placeholder="Customer name (optional)"
        />
      </div>

      <!-- Items -->
      <div class="cart-items">
        <div v-if="pos.cart.length === 0" class="cart-empty">
          <span style="font-size:40px;opacity:.3">🛒</span>
          <p class="text-muted text-sm" style="margin-top:8px">Cart is empty</p>
        </div>

        <transition-group name="cart-item" tag="div">
          <div v-for="item in pos.cart" :key="item.product_id" class="cart-item">
            <div class="cart-item-info">
              <p class="cart-item-name">{{ item.product_name }}</p>
              <p class="cart-item-price">{{ formatCurrency(item.product_price) }}</p>
            </div>
            <div class="cart-item-controls">
              <button class="qty-btn" @click="pos.updateQty(item.product_id, item.quantity - 1)">−</button>
              <span class="qty-value">{{ item.quantity }}</span>
              <button class="qty-btn" @click="pos.updateQty(item.product_id, item.quantity + 1)">+</button>
              <span class="cart-item-subtotal">{{ formatCurrency(item.product_price * item.quantity) }}</span>
              <button class="btn btn-ghost btn-icon btn-sm remove-btn" @click="pos.removeFromCart(item.product_id)">
                ✕
              </button>
            </div>
          </div>
        </transition-group>
      </div>

      <!-- Totals -->
      <div class="cart-totals">
        <div class="total-row">
          <span class="text-secondary">Subtotal</span>
          <span>{{ formatCurrency(pos.cartSubtotal) }}</span>
        </div>
        <div class="total-row">
          <span class="text-secondary">VAT ({{ pos.settings.tax_rate }}%)</span>
          <span>{{ formatCurrency(pos.taxAmount) }}</span>
        </div>
        <div class="total-row total-grand">
          <span>Total</span>
          <span class="total-amount">{{ formatCurrency(pos.cartTotal) }}</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="cart-actions">
        <button class="btn btn-secondary" :disabled="pos.cart.length === 0" @click="clearCurrentOrder">
          🗑 Clear
        </button>
        <button
          class="btn btn-primary"
          style="flex:1"
          :disabled="pos.cart.length === 0"
          @click="showPaymentModal = true"
        >
          💳 Checkout — {{ formatCurrency(pos.cartTotal) }}
        </button>
      </div>
    </div>

    <!-- Table picker modal -->
    <div v-if="showTableModal" class="modal-overlay" @click.self="showTableModal = false">
      <div class="modal" style="max-width:440px">
        <div class="modal-header">
          <h3 class="font-display">Select Table</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="showTableModal = false">✕</button>
        </div>
        <div class="modal-body">
          <!-- Legend -->
          <div class="table-legend">
            <span class="tleg tleg-available">● Available</span>
            <span class="tleg tleg-occupied">● Occupied</span>
          </div>
          <div class="table-picker-grid">
            <button
              v-for="t in tables"
              :key="t.id"
              class="table-pill"
              :class="{
                active:    pos.selectedTable?.id === t.id,
                occupied:  tableStatus(t) === 'occupied',
                available: tableStatus(t) === 'available',
              }"
              :disabled="tableStatus(t) === 'occupied'"
              @click="selectTable(t)"
            >
              <span style="font-size:18px">🪑</span>
              <span class="table-pill-name">{{ t.name }}</span>
              <span class="text-muted text-sm">{{ t.capacity }} seats</span>
              <span
                class="table-status-dot"
                :class="tableStatus(t) === 'occupied' ? 'dot-occupied' : 'dot-available'"
              />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment modal -->
    <PaymentModal v-if="showPaymentModal" @close="showPaymentModal = false" @paid="onOrderPlaced" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePOSStore } from '@/store/posStore';
import { useToast } from 'vue-toastification';
import api from '@/api';
import PaymentModal from '@/components/POS/PaymentModal.vue';

const pos   = usePOSStore();
const toast = useToast();

const loading          = ref(false);
const showPaymentModal = ref(false);
const showTableModal   = ref(false);
const tables           = ref([]);

onMounted(async () => {
  loading.value = true;
  try {
    await pos.loadInitialData();
    await refreshTables();
  } finally {
    loading.value = false;
  }
});

// ─── Helpers ────────────────────────────────────────────────
function getCategoryIcon(catId) {
  return pos.categories.find((c) => c.id === catId)?.icon || '☕';
}

function cartQty(productId) {
  return pos.cart.find((i) => i.product_id === productId)?.quantity ?? 0;
}

function formatCurrency(v) {
  return `${pos.settings.currency_symbol || '₱'}${parseFloat(v || 0).toFixed(2)}`;
}

/**
 * Use computed_status from the API (reliable source of truth).
 * Falls back to checking active_order, then the raw status field.
 */
function tableStatus(t) {
  return t.computed_status ?? (t.active_order ? 'occupied' : t.status);
}

// ─── Table actions ───────────────────────────────────────────
async function refreshTables() {
  const { data } = await api.get('/tables');
  tables.value = data;
}

async function selectTable(t) {
  if (tableStatus(t) === 'occupied') {
    toast.warning(`${t.name} is already occupied.`);
    return;
  }
  try {
    await api.put(`/tables/${t.id}`, { status: 'occupied' });
    pos.selectedTable = t;
    showTableModal.value = false;
    await refreshTables();
    toast.success(`${t.name} marked occupied and selected ✔`);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to reserve table');
  }
}

function clearTable() {
  pos.selectedTable = null;
}

// ─── Cart actions ────────────────────────────────────────────
function addProduct(p) {
  if (!p.is_available) {
    toast.warning(`${p.name} is unavailable`);
    return;
  }
  pos.addToCart(p);
}

async function clearCurrentOrder() {
  pos.clearCart();
  // Refresh table list so status is recalculated from active orders
  await refreshTables();
}

// ─── Order placed (after payment) ───────────────────────────
async function onOrderPlaced() {
  showPaymentModal.value = false;
  pos.clearCart();
  // Refresh tables — backend already set the table to 'occupied'
  await refreshTables();
  toast.success('Order placed successfully! 🎉');
}
</script>

<style scoped>
.pos-screen {
  display: grid;
  grid-template-columns: 1fr 360px;
  height: calc(100vh - 60px);
  overflow: hidden;
}

/* Products panel */
.pos-products {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-right: 1px solid var(--border);
}

.category-bar {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  overflow-x: auto;
  border-bottom: 1px solid var(--border);
  background: var(--surface-1);
  flex-shrink: 0;
}
.category-bar::-webkit-scrollbar { height: 3px; }
.cat-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 99px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: all var(--transition);
}
.cat-btn:hover { border-color: var(--accent-gold); color: var(--text-primary); }
.cat-btn.active {
  background: var(--accent-gold);
  color: var(--brew-espresso);
  border-color: var(--accent-gold);
  font-weight: 600;
}

.product-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  background: var(--surface-1);
  flex-shrink: 0;
}
.search-icon { font-size: 14px; opacity: 0.5; }
.product-search .input { border: none; background: transparent; padding: 4px 0; }
.product-search .input:focus { box-shadow: none; }

.loading-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.product-grid {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
  align-content: start;
}

.product-card {
  position: relative;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  transition: all var(--transition);
  text-align: left;
  display: flex;
  flex-direction: column;
}
.product-card:hover {
  border-color: var(--accent-gold);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}
.product-card.out-of-stock { opacity: 0.45; cursor: not-allowed; }

.product-img {
  height: 110px;
  background: var(--surface-3);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.product-img img { width: 100%; height: 100%; object-fit: cover; }
.product-emoji { font-size: 40px; }

.product-info { padding: 10px 12px; }
.product-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 4px;
  line-height: 1.3;
}
.product-price { font-size: 14px; font-weight: 600; color: var(--accent-gold); }

.product-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: var(--accent-gold);
  color: var(--brew-espresso);
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
}

/* Cart panel */
.pos-cart {
  display: flex;
  flex-direction: column;
  background: var(--surface-1);
  overflow: hidden;
}

.cart-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.cart-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
.order-meta { display: flex; gap: 8px; align-items: center; }

/* Selected table chip */
.selected-table-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  padding: 4px 10px 4px 10px;
  background: rgba(212,168,83,0.12);
  border: 1px solid rgba(212,168,83,0.4);
  border-radius: 99px;
  font-size: 12px;
  font-weight: 500;
  color: var(--accent-gold);
}
.chip-remove {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--accent-gold);
  opacity: 0.6;
  font-size: 11px;
  line-height: 1;
  padding: 0;
}
.chip-remove:hover { opacity: 1; }

.cart-items { flex: 1; overflow-y: auto; padding: 8px; }
.cart-empty {
  height: 160px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.cart-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-light);
  background: var(--surface-2);
  margin-bottom: 6px;
  gap: 8px;
}
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-name {
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cart-item-price { font-size: 11px; color: var(--text-muted); }
.cart-item-controls { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.qty-btn {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 1px solid var(--border);
  background: var(--surface-3);
  color: var(--text-primary);
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition);
}
.qty-btn:hover {
  background: var(--accent-gold);
  color: var(--brew-espresso);
  border-color: var(--accent-gold);
}
.qty-value { font-size: 13px; font-weight: 600; min-width: 20px; text-align: center; }
.cart-item-subtotal { font-size: 12px; font-weight: 600; color: var(--accent-gold); min-width: 52px; text-align: right; }
.remove-btn { opacity: 0; transition: opacity var(--transition); }
.cart-item:hover .remove-btn { opacity: 1; }

/* Totals */
.cart-totals {
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex-shrink: 0;
}
.total-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.total-grand { font-size: 16px; font-weight: 600; padding-top: 8px; border-top: 1px solid var(--border); }
.total-amount { font-size: 20px; color: var(--accent-gold); font-family: var(--font-display); }

.cart-actions {
  display: flex;
  gap: 10px;
  padding: 14px 16px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}

/* Table picker */
.table-legend {
  display: flex;
  gap: 16px;
  margin-bottom: 12px;
  font-size: 12px;
}
.tleg { display: flex; align-items: center; gap: 5px; }
.tleg-available { color: #27ae60; }
.tleg-occupied  { color: #e67e22; }

.table-picker-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 10px;
}
.table-pill {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 14px 10px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-3);
  cursor: pointer;
  transition: all var(--transition);
  font-size: 12px;
  font-weight: 500;
}
.table-pill:hover:not(:disabled) { border-color: var(--accent-gold); }
.table-pill.active {
  border-color: var(--accent-gold);
  background: rgba(212,168,83,0.1);
  color: var(--accent-gold);
}
.table-pill.occupied { opacity: 0.45; cursor: not-allowed; }
.table-pill-name { font-weight: 600; font-size: 13px; }

.table-status-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.dot-available { background: #27ae60; }
.dot-occupied  { background: #e67e22; }

/* Cart item transition */
.cart-item-enter-active, .cart-item-leave-active { transition: all 0.25s ease; }
.cart-item-enter-from { opacity: 0; transform: translateX(20px); }
.cart-item-leave-to   { opacity: 0; transform: translateX(-20px); }
</style>
