import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/api';

export const usePOSStore = defineStore('pos', () => {
  const categories = ref([]);
  const products   = ref([]);
  const cart       = ref([]);
  const settings   = ref({
    tax_enabled:     true,
    tax_rate:        12,
    currency_symbol: '₱',
    tax_label:       'VAT',
  });
  const orderType      = ref('dine_in');
  const selectedTable  = ref(null);
  const customerName   = ref('');
  const searchQuery    = ref('');
  const activeCategoryId = ref(null);

  // ── Computed ──────────────────────────────────────────────
  const filteredProducts = computed(() => {
    let list = products.value.filter((p) => p.is_active && p.is_available);
    if (activeCategoryId.value) {
      list = list.filter((p) => p.category_id === activeCategoryId.value);
    }
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      list = list.filter(
        (p) => p.name.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q),
      );
    }
    return list;
  });

  const cartSubtotal = computed(() =>
    cart.value.reduce((sum, item) => sum + Number(item.product_price || 0) * item.quantity, 0),
  );

  const taxAmount = computed(() =>
    settings.value.tax_enabled
      ? (cartSubtotal.value * Number(settings.value.tax_rate || 0)) / 100
      : 0,
  );

  const cartTotal      = computed(() => cartSubtotal.value + taxAmount.value);
  const cartItemCount  = computed(() => cart.value.reduce((s, i) => s + i.quantity, 0));

  // ── Cart actions ─────────────────────────────────────────
  function addToCart(product, qty = 1) {
    const existing = cart.value.find((i) => i.product_id === product.id);
    if (existing) {
      existing.quantity += qty;
    } else {
      cart.value.push({
        product_id:    product.id,
        product_name:  product.name,
        product_price: product.price,
        quantity:      qty,
        image_url:     product.image_url,
      });
    }
  }

  function updateQty(productId, qty) {
    const item = cart.value.find((i) => i.product_id === productId);
    if (!item) return;
    if (qty <= 0) {
      cart.value = cart.value.filter((i) => i.product_id !== productId);
    } else {
      item.quantity = qty;
    }
  }

  function removeFromCart(productId) {
    cart.value = cart.value.filter((i) => i.product_id !== productId);
  }

  function clearCart() {
    cart.value         = [];
    selectedTable.value  = null;
    customerName.value   = '';
    // Keep orderType so cashier doesn't have to re-select
  }

  // ── Data loading ──────────────────────────────────────────
  async function loadInitialData() {
    const [catsRes, prodRes, settingsRes] = await Promise.all([
      api.get('/categories'),
      api.get('/products'),
      api.get('/settings'),
    ]);
    categories.value = catsRes.data || [];
    products.value   = prodRes.data || [];
    if (settingsRes.data) settings.value = settingsRes.data;
  }

  // ── Submit order ──────────────────────────────────────────
  async function submitOrder() {
    if (cart.value.length === 0) throw new Error('Cart is empty');

    const payload = {
      order_type:    orderType.value,
      customer_name: customerName.value || null,
      table_id:      selectedTable.value?.id || null,
      items: cart.value.map((i) => ({
        product_id: i.product_id,
        quantity:   i.quantity,
      })),
    };

    const { data } = await api.post('/orders', payload);
    // Backend automatically sets table status to 'occupied' on create
    return data;
  }

  return {
    categories, products, cart, settings,
    orderType, selectedTable, customerName,
    searchQuery, activeCategoryId,
    filteredProducts, cartSubtotal, taxAmount, cartTotal, cartItemCount,
    addToCart, updateQty, removeFromCart, clearCart,
    loadInitialData, submitOrder,
  };
});
