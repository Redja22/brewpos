<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Products</h2>
      <div class="flex items-center gap-3">
        <input
          v-model="search"
          class="input"
          style="width:220px"
          placeholder="🔍 Search products…"
          @input="debouncedSearch"
        />
        <select v-model="filterCategory" class="select" style="width:160px" @change="loadProducts">
          <option value="">All Categories</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
        </select>
        <button class="btn btn-primary btn-sm" @click="openCreate">+ Add Product</button>
      </div>
    </div>

    <div v-if="loading" class="loading-state" style="height:300px">
      <div class="spinner" style="width:32px;height:32px;border-width:3px" />
    </div>

    <div v-else class="products-grid">
      <div v-if="products.length === 0" class="empty-state">
        <div class="icon">☕</div>
        <p>No products found. Add your first product!</p>
      </div>

      <div v-for="p in products" :key="p.id" class="product-tile">
        <div class="tile-img">
          <img v-if="p.image_url" :src="p.image_url" :alt="p.name" />
          <span v-else class="tile-emoji">{{ p.category?.icon || '☕' }}</span>
          <div class="tile-overlay">
            <button class="btn btn-secondary btn-sm" @click="openEdit(p)">✏ Edit</button>
            <button class="btn btn-danger btn-sm" @click="deleteProduct(p)">🗑</button>
          </div>
        </div>
        <div class="tile-body">
          <p class="tile-name">{{ p.name }}</p>
          <p class="text-muted text-sm">{{ p.category?.name }}</p>
          <div class="tile-footer">
            <span class="tile-price">₱{{ p.price }}</span>
            <div class="tile-badges">
              <span v-if="!p.is_available" class="badge badge-cancelled" style="font-size:10px">Unavailable</span>
              <span v-if="p.track_inventory" class="badge badge-pending" style="font-size:10px">Stock: {{ p.stock_quantity }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="max-width:540px">
        <div class="modal-header">
          <h3 class="font-display">{{ editing ? 'Edit Product' : 'New Product' }}</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="showModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div
            class="image-upload-area"
            @click="$refs.fileInput.click()"
            :style="imagePreview ? `background-image:url(${imagePreview});background-size:cover;background-position:center` : ''"
          >
            <span v-if="!imagePreview" style="font-size:32px;opacity:.4">📷</span>
            <span v-if="!imagePreview" class="text-muted text-sm" style="margin-top:6px">Click to upload image</span>
          </div>
          <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFileChange" />

          <div class="form-grid form-grid-2" style="margin-top:18px">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Product Name *</label>
              <input v-model="form.name" class="input" placeholder="e.g. Iced Caramel Latte" />
            </div>
            <div class="form-group">
              <label class="form-label">Category *</label>
              <select v-model="form.category_id" class="select">
                <option value="">Select category</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Price (₱) *</label>
              <input v-model.number="form.price" type="number" class="input" min="0" step="0.01" placeholder="0.00" />
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" class="textarea" rows="2" placeholder="Optional description…" />
            </div>
            <div class="form-group">
              <label class="form-label">SKU</label>
              <input v-model="form.sku" class="input" placeholder="Optional SKU" />
            </div>
            <div class="form-group">
              <label class="form-label">Track Inventory</label>
              <select v-model="form.track_inventory" class="select">
                <option :value="false">No</option>
                <option :value="true">Yes</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Available</label>
              <select v-model="form.is_available" class="select">
                <option :value="true">Yes</option>
                <option :value="false">No</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Active</label>
              <select v-model="form.is_active" class="select">
                <option :value="true">Yes</option>
                <option :value="false">No</option>
              </select>
            </div>
          </div>

          <p v-if="formError" class="form-error" style="margin-top:8px">{{ formError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showModal = false">Cancel</button>
          <button class="btn btn-primary" :disabled="saving" @click="saveProduct">
            <span v-if="saving" class="spinner" style="width:14px;height:14px" />
            {{ editing ? 'Save Changes' : 'Add Product' }}
          </button>
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

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const showModal = ref(false);
const saving = ref(false);
const editing = ref(null);
const formError = ref('');
const imagePreview = ref(null);
const selectedFile = ref(null);
const search = ref('');
const filterCategory = ref('');

const form = reactive({
  name: '',
  category_id: '',
  price: '',
  description: '',
  sku: '',
  is_active: true,
  is_available: true,
  track_inventory: false,
});

let searchTimer = null;
function debouncedSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadProducts, 400);
}

onMounted(async () => {
  const { data } = await api.get('/categories?active=1');
  categories.value = data;
  await loadProducts();
});

async function loadProducts() {
  loading.value = true;
  try {
    const params = {};
    if (search.value) params.search = search.value;
    if (filterCategory.value) params.category_id = filterCategory.value;
    const { data } = await api.get('/products', { params });
    products.value = data;
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  Object.assign(form, {
    name: '',
    category_id: '',
    price: '',
    description: '',
    sku: '',
    is_active: true,
    is_available: true,
    track_inventory: false,
  });
  editing.value = null;
  imagePreview.value = null;
  selectedFile.value = null;
  formError.value = '';
  showModal.value = true;
}

function openEdit(p) {
  Object.assign(form, {
    name: p.name,
    category_id: p.category_id,
    price: p.price,
    description: p.description || '',
    sku: p.sku || '',
    is_active: p.is_active,
    is_available: p.is_available,
    track_inventory: p.track_inventory,
  });
  editing.value = p;
  imagePreview.value = p.image_url || null;
  selectedFile.value = null;
  formError.value = '';
  showModal.value = true;
}

function onFileChange(e) {
  const file = e.target.files[0];
  if (!file) return;
  selectedFile.value = file;
  imagePreview.value = URL.createObjectURL(file);
}

async function saveProduct() {
  formError.value = '';
  if (!form.name || !form.category_id || !form.price) {
    formError.value = 'Name, category and price are required.';
    return;
  }
  saving.value = true;
  try {
    const fd = new FormData();
    Object.entries(form).forEach(([k, v]) => fd.append(k, v));
    if (selectedFile.value) fd.append('image', selectedFile.value);

    if (editing.value) {
      fd.append('_method', 'PUT');
      await api.post(`/products/${editing.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success('Product updated!');
    } else {
      await api.post('/products', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      toast.success('Product added!');
    }

    showModal.value = false;
    await loadProducts();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Error saving product.';
  } finally {
    saving.value = false;
  }
}

async function deleteProduct(p) {
  if (!confirm(`Delete "${p.name}"?`)) return;
  try {
    await api.delete(`/products/${p.id}`);
    toast.success('Product deleted');
    await loadProducts();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Cannot delete product');
  }
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
  gap: 12px;
  flex-wrap: wrap;
}
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
}

.product-tile {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: all var(--transition);
}
.product-tile:hover {
  border-color: var(--accent-gold);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.tile-img {
  height: 130px;
  background: var(--surface-3);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.tile-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.tile-emoji {
  font-size: 44px;
}

.tile-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  opacity: 0;
  transition: opacity var(--transition);
}
.product-tile:hover .tile-overlay {
  opacity: 1;
}

.tile-body {
  padding: 12px;
}
.tile-name {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 2px;
}
.tile-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8px;
  flex-wrap: wrap;
  gap: 6px;
}
.tile-price {
  font-size: 15px;
  font-weight: 700;
  color: var(--accent-gold);
}
.tile-badges {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.image-upload-area {
  height: 140px;
  background: var(--surface-3);
  border: 2px dashed var(--border);
  border-radius: var(--radius-md);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color var(--transition);
}
.image-upload-area:hover {
  border-color: var(--accent-gold);
}
</style>
