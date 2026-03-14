<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Categories</h2>
      <button class="btn btn-primary btn-sm" @click="openCreate">+ Add Category</button>
    </div>

    <div class="card" style="margin:0">
      <table class="table">
        <thead>
          <tr>
            <th>Icon</th>
            <th>Name</th>
            <th>Products</th>
            <th>Sort Order</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="categories.length === 0">
            <td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No categories yet.</td>
          </tr>
          <tr v-for="cat in categories" :key="cat.id">
            <td>
              <span class="cat-icon-badge" :style="`background:${cat.color}22;border-color:${cat.color}44`">
                {{ cat.icon || '🏷' }}
              </span>
            </td>
            <td><strong>{{ cat.name }}</strong></td>
            <td><span class="text-muted">{{ cat.active_products_count ?? '—' }}</span></td>
            <td><span class="text-muted">{{ cat.sort_order }}</span></td>
            <td>
              <span class="badge" :class="cat.is_active ? 'badge-ready' : 'badge-cancelled'">
                {{ cat.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm" @click="openEdit(cat)">✏ Edit</button>
                <button class="btn btn-danger btn-sm" @click="deleteCategory(cat)">🗑</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="max-width:420px">
        <div class="modal-header">
          <h3 class="font-display">{{ editing ? 'Edit Category' : 'New Category' }}</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="showModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div style="display:flex;flex-direction:column;gap:14px">
            <div class="form-group">
              <label class="form-label">Name *</label>
              <input v-model="form.name" class="input" placeholder="e.g. Hot Coffee" />
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label class="form-label">Icon (emoji)</label>
                <input v-model="form.icon" class="input" placeholder="☕" maxlength="4" />
              </div>
              <div class="form-group">
                <label class="form-label">Color</label>
                <div class="flex gap-2 items-center">
                  <input v-model="form.color" class="input" placeholder="#8B5E3C" />
                  <input
                    type="color"
                    v-model="form.color"
                    style="width:36px;height:36px;border:none;border-radius:var(--radius-sm);cursor:pointer;background:none;padding:2px;"
                  />
                </div>
              </div>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input v-model.number="form.sort_order" type="number" class="input" min="0" />
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select v-model="form.is_active" class="select">
                  <option :value="true">Active</option>
                  <option :value="false">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <p v-if="formError" class="form-error" style="margin-top:8px">{{ formError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showModal = false">Cancel</button>
          <button class="btn btn-primary" :disabled="saving" @click="save">
            <span v-if="saving" class="spinner" style="width:14px;height:14px" />
            {{ editing ? 'Save Changes' : 'Create Category' }}
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

const categories = ref([]);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref('');

const form = reactive({ name: '', icon: '', color: '#8B5E3C', sort_order: 0, is_active: true });

onMounted(loadCategories);

async function loadCategories() {
  const { data } = await api.get('/categories');
  categories.value = data;
}

function openCreate() {
  Object.assign(form, { name: '', icon: '', color: '#8B5E3C', sort_order: 0, is_active: true });
  editing.value = null;
  formError.value = '';
  showModal.value = true;
}

function openEdit(cat) {
  Object.assign(form, {
    name: cat.name,
    icon: cat.icon || '',
    color: cat.color || '#8B5E3C',
    sort_order: cat.sort_order,
    is_active: cat.is_active,
  });
  editing.value = cat;
  formError.value = '';
  showModal.value = true;
}

async function save() {
  formError.value = '';
  if (!form.name) {
    formError.value = 'Name is required.';
    return;
  }
  saving.value = true;
  try {
    if (editing.value) {
      await api.put(`/categories/${editing.value.id}`, form);
      toast.success('Category updated!');
    } else {
      await api.post('/categories', form);
      toast.success('Category created!');
    }
    showModal.value = false;
    await loadCategories();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Error saving category.';
  } finally {
    saving.value = false;
  }
}

async function deleteCategory(cat) {
  if (!confirm(`Delete "${cat.name}"? This cannot be undone.`)) return;
  try {
    await api.delete(`/categories/${cat.id}`);
    toast.success('Category deleted');
    await loadCategories();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Cannot delete category');
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
}
.cat-icon-badge {
  font-size: 20px;
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  border: 1px solid;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
