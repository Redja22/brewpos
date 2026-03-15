<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Staff Users</h2>
      <button class="btn btn-primary btn-sm" @click="openCreate">+ Add User</button>
    </div>

    <div class="card" style="margin:0">
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id">
            <td>
              <div class="user-row">
                <div class="user-av">{{ u.name?.charAt(0) }}</div>
                <strong>{{ u.name }}</strong>
              </div>
            </td>
            <td><span class="text-muted">{{ u.email }}</span></td>
            <td><span class="role-badge" :class="`role-${u.role}`">{{ u.role }}</span></td>
            <td>
              <span class="badge" :class="u.is_active ? 'badge-ready' : 'badge-cancelled'">
                {{ u.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <button class="btn btn-secondary btn-sm" @click="openEdit(u)">✏ Edit</button>
              <button class="btn btn-danger btn-sm" style="margin-left:6px" @click="deleteUser(u)">🗑 Delete</button>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">No users</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="max-width:440px">
        <div class="modal-header">
          <h3 class="font-display">{{ editing ? 'Edit User' : 'Add Staff User' }}</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="showModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div style="display:flex;flex-direction:column;gap:14px">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input v-model="form.name" class="input" placeholder="Juan dela Cruz" />
            </div>
            <div class="form-group">
              <label class="form-label">Email *</label>
              <input v-model="form.email" type="email" class="input" placeholder="juan@brewpos.com" />
            </div>
            <div class="form-group">
              <label class="form-label">Password {{ editing ? '(leave blank to keep)' : '*' }}</label>
              <input v-model="form.password" type="password" class="input" placeholder="••••••••" />
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-group">
                <label class="form-label">Role *</label>
                <select v-model="form.role" class="select">
                  <option value="admin">Admin</option>
                  <option value="manager">Manager</option>
                  <option value="cashier">Cashier</option>
                  <option value="kitchen">Kitchen</option>
                </select>
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
            {{ editing ? 'Save Changes' : 'Add User' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal" style="max-width:380px">
        <div class="modal-header">
          <h3 class="font-display">Delete User</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="showDeleteModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p style="color:var(--text-secondary);line-height:1.6">
            Are you sure you want to delete <strong>{{ userToDelete?.name }}</strong>?
            This action cannot be undone.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showDeleteModal = false">Cancel</button>
          <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
            <span v-if="deleting" class="spinner" style="width:14px;height:14px" />
            Delete User
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

const users = ref([]);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref('');

const showDeleteModal = ref(false);
const userToDelete = ref(null);
const deleting = ref(false);

const form = reactive({ name: '', email: '', password: '', role: 'cashier', is_active: true });

onMounted(loadUsers);

async function loadUsers() {
  const { data } = await api.get('/users');
  users.value = data.data || data;
}

function openCreate() {
  Object.assign(form, { name: '', email: '', password: '', role: 'cashier', is_active: true });
  editing.value = null;
  formError.value = '';
  showModal.value = true;
}

function openEdit(u) {
  Object.assign(form, { name: u.name, email: u.email, password: '', role: u.role, is_active: u.is_active });
  editing.value = u;
  formError.value = '';
  showModal.value = true;
}

async function save() {
  formError.value = '';
  if (!form.name || !form.email) {
    formError.value = 'Name and email are required.';
    return;
  }
  if (!editing.value && !form.password) {
    formError.value = 'Password is required.';
    return;
  }
  saving.value = true;
  try {
    if (editing.value) {
      await api.put(`/users/${editing.value.id}`, form);
      toast.success('User updated!');
    } else {
      await api.post('/users', form);
      toast.success('User added!');
    }
    showModal.value = false;
    await loadUsers();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Error saving user.';
  } finally {
    saving.value = false;
  }
}

function deleteUser(u) {
  userToDelete.value = u;
  showDeleteModal.value = true;
}

async function confirmDelete() {
  if (!userToDelete.value) return;
  deleting.value = true;
  try {
    await api.delete(`/users/${userToDelete.value.id}`);
    toast.success('User deleted!');
    showDeleteModal.value = false;
    userToDelete.value = null;
    await loadUsers();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error deleting user.');
  } finally {
    deleting.value = false;
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
.user-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.user-av {
  width: 30px;
  height: 30px;
  background: var(--accent-gold);
  color: var(--brew-espresso);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  flex-shrink: 0;
}
.role-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}
.role-admin {
  background: rgba(212, 168, 83, 0.15);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 83, 0.3);
}
.role-manager {
  background: rgba(155, 89, 182, 0.15);
  color: #9b59b6;
  border: 1px solid rgba(155, 89, 182, 0.3);
}
.role-cashier {
  background: rgba(74, 144, 217, 0.15);
  color: #4a90d9;
  border: 1px solid rgba(74, 144, 217, 0.3);
}
.role-kitchen {
  background: rgba(230, 126, 34, 0.15);
  color: var(--status-preparing);
  border: 1px solid rgba(230, 126, 34, 0.3);
}
.btn-danger {
  background: rgba(231, 76, 60, 0.15);
  color: #e74c3c;
  border: 1px solid rgba(231, 76, 60, 0.3);
}
.btn-danger:hover {
  background: rgba(231, 76, 60, 0.25);
}
.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
