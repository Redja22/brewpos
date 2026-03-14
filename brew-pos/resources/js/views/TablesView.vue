<template>
  <div class="tables-page">
    <div class="page-header" style="padding:16px;border-bottom:1px solid var(--border)">
      <h2 class="font-display">Table & Floor Items</h2>
      <div class="legend">
        <span v-for="s in statuses" :key="s.key" class="legend-item">
          <span class="legend-dot" :style="`background:${s.color}`" />
          {{ s.label }}
        </span>
      </div>
      <button class="btn btn-secondary btn-sm" style="margin-left:8px" @click="loadTables">↻ Refresh</button>
      <button v-if="auth.isManager" class="btn btn-primary btn-sm" @click="openAdd">+ Add Table</button>
    </div>

    <div class="floor-plan" ref="floorRef">
      <!-- Decorative store tiles / zones -->
      <div
        v-for="zone in zones"
        :key="zone.key"
        class="zone"
        :class="zone.key"
        :style="zoneStyle(zone)"
        @mousedown.prevent="startZoneDrag(zone, $event)"
        @touchstart.prevent="startZoneDrag(zone, $event.touches?.[0])"
        @click="selectZone(zone)"
      >
        {{ zone.label }}
      </div>

      <div
        v-for="table in tables"
        :key="table.id"
        class="floor-table"
        :class="[`shape-${table.shape}`, `status-${tableStatus(table)}`, draggingTable?.id === table.id ? 'dragging' : '']"
        :style="tableStyle(table)"
        @mousedown.prevent="startDrag(table, $event)"
        @touchstart.prevent="startDrag(table, $event.touches?.[0])"
        @click="selectTable(table)"
      >
        <span class="table-number">{{ table.number }}</span>
        <span class="table-cap">👤 {{ table.capacity }}</span>
        <span v-if="table.active_order" class="table-order-badge">
          {{ table.active_order.order_number }}
        </span>
      </div>

      <div class="floor-label" style="top:10px;left:10px">Entrance</div>
      <div class="floor-label counter" style="bottom:10px;right:10px">☕ Counter</div>
    </div>

    <!-- Detail side panel -->
    <div v-if="selectedTable" class="table-detail-panel">
      <div class="detail-header">
        <h3 class="font-display">{{ selectedTable.name }}</h3>
        <button class="btn btn-ghost btn-icon btn-sm" @click="selectedTable = null">✕</button>
      </div>
      <div class="detail-body">
        <div class="detail-stat">
          <span class="text-muted text-sm">Capacity</span>
          <span>{{ selectedTable.capacity }} seats</span>
        </div>
        <div class="detail-stat">
          <span class="text-muted text-sm">Status</span>
          <span class="badge" :class="statusBadge(tableStatus(selectedTable))">
            {{ tableStatus(selectedTable) }}
          </span>
        </div>
        <!-- Active order info -->
        <div v-if="selectedTable.active_order" class="active-order-card">
          <p class="text-sm font-bold" style="margin-bottom:8px">Active Order</p>
          <p class="text-gold font-bold">{{ selectedTable.active_order.order_number }}</p>
          <div class="order-items-preview">
            <span
              v-for="item in selectedTable.active_order.items?.slice(0, 3)"
              :key="item.id"
              class="item-tag"
            >
              {{ item.product_name }} × {{ item.quantity }}
            </span>
          </div>
          <span
            class="badge"
            :class="`badge-${selectedTable.active_order.status}`"
            style="margin-top:8px;display:inline-block"
          >
            {{ selectedTable.active_order.status }}
          </span>
        </div>

        <!-- No active order -->
        <div v-else class="no-order-card">
          <span style="font-size:24px;opacity:.4">🪑</span>
          <p class="text-muted text-sm" style="margin-top:6px">No active order</p>
        </div>

        <div class="detail-actions">
          <!-- "Set Available" button: staff manually frees tables -->
          <button
            v-if="tableStatus(selectedTable) !== 'available' && !selectedTable.active_order"
            class="btn btn-success btn-sm"
            :disabled="settingAvailable"
            @click="setAvailable(selectedTable)"
          >
            <span v-if="settingAvailable" class="spinner" style="width:12px;height:12px" />
            ✓ Set Available
          </button>
          <button
            v-if="auth.isManager"
            class="btn btn-danger btn-sm"
            @click="deleteTable(selectedTable)"
          >
            🗑 Delete
          </button>
          <button
            class="btn btn-secondary btn-sm"
            @click="openEdit(selectedTable)"
          >
            ✏ Edit
          </button>
        </div>

        <!-- Rotation control (admin/manager) -->
        <div v-if="auth.isManager || auth.isAdmin" class="rotate-control">
          <span class="rotate-icon">⟳</span>
          <input
            type="range"
            min="0"
            max="359"
            v-model.number="rotationInput"
            @input="onRotateInput"
          />
          <span class="rotate-value">{{ Math.round(rotationInput) }}°</span>
        </div>

        <!-- Hint when table has active order -->
        <p
          v-if="selectedTable.active_order"
          class="text-muted text-sm"
          style="margin-top:8px;line-height:1.6;font-style:italic"
        >
          Table stays occupied until a staff member marks it available.
        </p>
      </div>
    </div>

    <!-- Zone detail panel (Entrance / Exit / Counter etc.) -->
    <div v-else-if="selectedZone" class="table-detail-panel">
      <div class="detail-header">
        <h3 class="font-display">{{ selectedZone.label }}</h3>
        <button class="btn btn-ghost btn-icon btn-sm" @click="selectedZone = null">✕</button>
      </div>
      <div class="detail-body">
        <div class="detail-stat">
          <span class="text-muted text-sm">Size</span>
          <span>{{ Math.round(selectedZone.w) }} × {{ Math.round(selectedZone.h) }} px</span>
        </div>
        <div class="detail-stat">
          <span class="text-muted text-sm">Rotation</span>
          <span>{{ Math.round(selectedZone.rotation || 0) }}°</span>
        </div>

        <div v-if="auth.isManager || auth.isAdmin" class="rotate-control" style="margin-top:12px">
          <span class="rotate-icon">⟳</span>
          <input
            type="range"
            min="0"
            max="359"
            v-model.number="rotationInput"
            @input="onRotateInput"
          />
          <span class="rotate-value">{{ Math.round(rotationInput) }}°</span>
        </div>

        <p class="text-muted text-sm" style="margin-top:10px;line-height:1.5">
          Drag to reposition. Rotate using the slider. Zones auto-save to this browser.
        </p>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <div v-if="showAddModal || showEditModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal" style="max-width:400px">
        <div class="modal-header">
          <h3 class="font-display">{{ showAddModal ? 'Add Table' : 'Edit Table' }}</h3>
          <button class="btn btn-ghost btn-icon btn-sm" @click="closeModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-grid form-grid-2" style="gap:14px">
          <div class="form-group">
            <label class="form-label">Item Name</label>
            <input v-model="tableForm.name" class="input" placeholder="Table 1 / Planter / Stage" />
            <p class="text-muted text-sm" style="margin-top:4px">You can add non-table items (boxes, planters, stage) too.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Number (optional)</label>
            <input v-model.number="tableForm.number" type="number" class="input" min="1" placeholder="Leave blank for non-table items" />
          </div>
          <div class="form-group">
            <label class="form-label">Capacity</label>
            <input v-model.number="tableForm.capacity" type="number" class="input" min="1" placeholder="Optional" />
          </div>
            <div class="form-group">
              <label class="form-label">Shape</label>
              <select v-model="tableForm.shape" class="select">
                <option value="square">Square</option>
                <option value="circle">Circle</option>
                <option value="rectangle">Rectangle</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">Cancel</button>
          <button class="btn btn-primary" :disabled="saving" @click="saveTable">
            <span v-if="saving" class="spinner" style="width:14px;height:14px" />
            {{ showAddModal ? 'Add Table' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import { useAuthStore } from '@/store/authStore';
import { useToast } from 'vue-toastification';
import api from '@/api';

const auth  = useAuthStore();
const toast = useToast();

const tables            = ref([]);
const selectedTable     = ref(null);
const selectedZone      = ref(null);
const showAddModal      = ref(false);
const showEditModal     = ref(false);
const saving            = ref(false);
const settingAvailable  = ref(false);
const editingTable      = ref(null);
const draggingTable     = ref(null);
const resizingTable     = ref(null);
const dragOffset        = reactive({ x: 0, y: 0 });
const resizeStart       = reactive({ w: 0, h: 0, x: 0, y: 0 });
const floorRect         = ref(null);
const zones             = reactive([
  { key: 'entrance', label: 'Entrance', x: 14, y: 12, w: 110, h: 28, rotation: 0 },
  { key: 'exit', label: 'Fire Exit', x: 760, y: 12, w: 110, h: 28, rotation: 0 },
  { key: 'counter', label: 'Bar / Counter', x: 80, y: 120, w: 240, h: 32, rotation: 0 },
  { key: 'kitchen', label: 'Kitchen', x: 620, y: 60, w: 220, h: 32, rotation: 0 },
  { key: 'storage', label: 'Storage', x: 480, y: 12, w: 140, h: 28, rotation: 0 },
  { key: 'restrooms', label: 'Restrooms', x: 260, y: 12, w: 140, h: 28, rotation: 0 },
  { key: 'patio', label: 'Patio', x: 350, y: 620, w: 110, h: 28, rotation: 0 },
]);
const draggingZone      = ref(null);
const resizingZone      = ref(null);
const rotationInput     = ref(0);
let rotationTimer = null;

const tableForm = reactive({ name: '', number: '', capacity: '', shape: 'square' });

const statuses = [
  { key: 'available', label: 'Available', color: '#27ae60' },
  { key: 'occupied',  label: 'Occupied',  color: '#e67e22' },
  { key: 'reserved',  label: 'Reserved',  color: '#4a90d9' },
  { key: 'inactive',  label: 'Inactive',  color: '#7f8c8d' },
];

onMounted(() => {
  loadZonesFromStorage();
  loadTables();
});
onMounted(() => {
  window.addEventListener('mousemove', onDragMove);
  window.addEventListener('touchmove', onDragMove, { passive: false });
  window.addEventListener('mouseup', endDrag);
  window.addEventListener('touchend', endDrag);
});

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', onDragMove);
  window.removeEventListener('touchmove', onDragMove);
  window.removeEventListener('mouseup', endDrag);
  window.removeEventListener('touchend', endDrag);
});

async function loadTables() {
  const { data } = await api.get('/tables');
  tables.value = data;
  // Update selectedTable if still open
  if (selectedTable.value) {
    selectedTable.value = data.find((t) => t.id === selectedTable.value.id) ?? null;
    if (selectedTable.value) rotationInput.value = selectedTable.value.rotation || 0;
  }
}

function loadZonesFromStorage() {
  const saved = localStorage.getItem('brewpos_zones');
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      if (Array.isArray(parsed)) {
        parsed.forEach((pz) => {
          const zone = zones.find((z) => z.key === pz.key);
          if (zone) Object.assign(zone, pz);
        });
      }
    } catch (e) {
      // ignore
    }
  }
}

function saveZonesToStorage() {
  localStorage.setItem('brewpos_zones', JSON.stringify(zones));
}

// ─── Status helpers ──────────────────────────────────────────
/**
 * Prefer computed_status from the backend (always correct).
 * Falls back to active_order check, then raw DB status.
 */
function tableStatus(t) {
  return t.computed_status ?? (t.active_order ? 'occupied' : t.status);
}

function tableStyle(t) {
  const base = {
    left:   `${t.position_x}px`,
    top:    `${t.position_y}px`,
    width:  `${t.width || (t.shape === 'rectangle' ? 120 : 80)}px`,
    height: `${t.height || (t.shape === 'rectangle' ? 60 : 80)}px`,
    cursor: draggingTable.value?.id === t.id ? 'grabbing' : 'grab',
    transform: `rotate(${t.rotation || 0}deg)`,
    transformOrigin: 'center center',
  };
  if (draggingTable.value?.id === t.id) {
    return { ...base, opacity: 0.9, boxShadow: '0 0 0 2px var(--accent-gold)' };
  }
  return base;
}

function zoneStyle(z) {
  return {
    left: `${z.x}px`,
    top: `${z.y}px`,
    width: z.w ? `${z.w}px` : 'auto',
    height: z.h ? `${z.h}px` : '28px',
    transform: `rotate(${z.rotation || 0}deg)`,
    transformOrigin: 'center center',
    cursor: auth.isManager || auth.isAdmin ? 'grab' : 'default',
  };
}

function statusBadge(s) {
  const map = {
    available: 'badge-ready',
    occupied:  'badge-preparing',
    reserved:  'badge-pending',
    inactive:  'badge-completed',
  };
  return map[s] || 'badge-pending';
}

// Size helpers
function defaultDimsForTable(t) {
  return t.shape === 'rectangle'
    ? { w: 120, h: 60 }
    : { w: 80, h: 80 };
}

// ─── Table actions ───────────────────────────────────────────
function selectTable(t) {
  selectedTable.value = t;
  rotationInput.value = t.rotation || 0;
  selectedZone.value = null;
}

function selectZone(z) {
  selectedZone.value = z;
  selectedTable.value = null;
  rotationInput.value = z.rotation || 0;
}

// Rotate selected table (admin/manager)
async function rotateSelected(deg) {
  if (!auth.isManager && !auth.isAdmin) return;
  if (selectedTable.value) {
    const next = ((selectedTable.value.rotation || 0) + deg + 360) % 360;
    await api.put(`/tables/${selectedTable.value.id}`, { rotation: next });
    await loadTables();
    rotationInput.value = next;
    toast.success(`Rotated ${selectedTable.value.name} to ${Math.round(next)}°`);
  } else if (selectedZone.value) {
    const next = ((selectedZone.value.rotation || 0) + deg + 360) % 360;
    selectedZone.value.rotation = next;
    rotationInput.value = next;
    saveZonesToStorage();
    toast.success(`Rotated ${selectedZone.value.label} to ${Math.round(next)}°`);
  }
}

function startDrag(table, evt) {
  if (!auth.isManager && !auth.isAdmin) return;
  draggingTable.value = table;
  const floor = document.querySelector('.floor-plan');
  floorRect.value = floor?.getBoundingClientRect();
  if (!floorRect.value) return;
  dragOffset.x = evt.clientX - floorRect.value.left - table.position_x;
  dragOffset.y = evt.clientY - floorRect.value.top - table.position_y;
}

function startResize(table, evt) {
  if (!auth.isManager && !auth.isAdmin) return;
  evt.stopPropagation();
  resizingTable.value = table;
  const floor = document.querySelector('.floor-plan');
  floorRect.value = floor?.getBoundingClientRect();
  resizeStart.w = table.width || (table.shape === 'rectangle' ? 120 : 80);
  resizeStart.h = table.height || (table.shape === 'rectangle' ? 60 : 80);
  resizeStart.x = evt.clientX;
  resizeStart.y = evt.clientY;
}

function onDragMove(evt) {
  if (!floorRect.value) return;
  if (!draggingTable.value && !draggingZone.value && !resizingTable.value) return;
  if (evt.touches?.length) evt = evt.touches[0];
  const rawX = evt.clientX - floorRect.value.left - dragOffset.x;
  const rawY = evt.clientY - floorRect.value.top - dragOffset.y;
  if (draggingTable.value) {
    const x = Math.max(0, rawX);
    const y = Math.max(0, rawY);
    draggingTable.value.position_x = Math.round(x);
    draggingTable.value.position_y = Math.round(y);
  }
  if (resizingTable.value) {
    const minSize = 40;
    let newW = resizeStart.w + (evt.clientX - resizeStart.x);
    let newH = resizeStart.h + (evt.clientY - resizeStart.y);
    if (resizingTable.value.shape === 'circle' || resizingTable.value.shape === 'square') {
      const size = Math.max(minSize, Math.min(newW, newH));
      newW = newH = size;
    }
    resizingTable.value.width = Math.max(minSize, Math.min(600, Math.round(newW)));
    resizingTable.value.height = Math.max(minSize, Math.min(600, Math.round(newH)));
  }
  if (draggingZone.value) {
    const w = draggingZone.value.w || 120;
    const h = draggingZone.value.h || 28;
    const maxX = Math.max(0, (floorRect.value.width || 0) - w);
    const maxY = Math.max(0, (floorRect.value.height || 0) - h);
    const x = Math.min(Math.max(0, rawX), maxX);
    const y = Math.min(Math.max(0, rawY), maxY);
    draggingZone.value.x = Math.round(x);
    draggingZone.value.y = Math.round(y);
  }
}

async function endDrag() {
  if (!auth.isManager && !auth.isAdmin) {
    draggingTable.value = null;
    draggingZone.value = null;
    resizingZone.value = null;
    resizingTable.value = null;
    floorRect.value = null;
    return;
  }

  if (draggingTable.value) {
    try {
      await api.put(`/tables/${draggingTable.value.id}`, {
        position_x: draggingTable.value.position_x,
        position_y: draggingTable.value.position_y,
        rotation: draggingTable.value.rotation || 0,
        width: draggingTable.value.width || null,
        height: draggingTable.value.height || null,
      });
    } catch (e) {
      toast.error(e.response?.data?.message || 'Failed to save position');
    }
  }
  if (resizingTable.value) {
    try {
      await api.put(`/tables/${resizingTable.value.id}`, {
        width: resizingTable.value.width || null,
        height: resizingTable.value.height || null,
      });
    } catch (e) {
      toast.error(e.response?.data?.message || 'Failed to save size');
    }
  }
  if (draggingZone.value || resizingZone.value) {
    saveZonesToStorage();
  }

  draggingTable.value = null;
  draggingZone.value = null;
  resizingZone.value = null;
  resizingTable.value = null;
  floorRect.value = null;
}

function onRotateInput() {
  if (!auth.isManager && !auth.isAdmin) return;
  const val = rotationInput.value ?? 0;
  if (selectedTable.value) {
    selectedTable.value.rotation = val;
    if (rotationTimer) clearTimeout(rotationTimer);
    rotationTimer = setTimeout(async () => {
      try {
        await api.put(`/tables/${selectedTable.value.id}`, { rotation: val });
        await loadTables();
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to rotate table');
      }
    }, 200);
  } else if (selectedZone.value) {
    selectedZone.value.rotation = val;
    saveZonesToStorage();
  }
}

function startZoneDrag(zone, evt) {
  if (!auth.isManager && !auth.isAdmin) return;
  draggingZone.value = zone;
  selectedZone.value = zone;
  selectedTable.value = null;
  const floor = document.querySelector('.floor-plan');
  floorRect.value = floor?.getBoundingClientRect();
  if (!floorRect.value) return;
  dragOffset.x = evt.clientX - floorRect.value.left - zone.x;
  dragOffset.y = evt.clientY - floorRect.value.top - zone.y;
}

function startZoneResize(zone, evt) {
  if (!auth.isManager && !auth.isAdmin) return;
  resizingZone.value = zone;
  selectedZone.value = zone;
  selectedTable.value = null;
  resizeStart.w = zone.w || 120;
  resizeStart.h = zone.h || 28;
  resizeStart.x = evt.clientX;
  resizeStart.y = evt.clientY;
}

// Manually mark a table as available (staff-controlled)
async function setAvailable(table) {
  settingAvailable.value = true;
  try {
    await api.put(`/tables/${table.id}`, { status: 'available' });
    toast.success(`${table.name} is now available`);
    selectedTable.value = null;
    await loadTables();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to update table status');
  } finally {
    settingAvailable.value = false;
  }
}

function openAdd() {
  Object.assign(tableForm, { name: '', number: '', capacity: 4, shape: 'square' });
  showAddModal.value  = true;
  showEditModal.value = false;
}

function openEdit(table) {
  Object.assign(tableForm, {
    name:     table.name,
    number:   table.number,
    capacity: table.capacity,
    shape:    table.shape,
  });
  editingTable.value  = table;
  showEditModal.value = true;
  showAddModal.value  = false;
}

function closeModal() {
  showAddModal.value  = false;
  showEditModal.value = false;
}

async function saveTable() {
  saving.value = true;
  try {
    const payload = {
      name: tableForm.name,
      number: tableForm.number || null,
      capacity: tableForm.capacity || null,
      shape: tableForm.shape,
    };
    if (showAddModal.value) {
      await api.post('/tables', payload);
      toast.success('Table added');
    } else {
      await api.put(`/tables/${editingTable.value.id}`, payload);
      toast.success('Table updated');
    }
    closeModal();
    await loadTables();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error saving table');
  } finally {
    saving.value = false;
  }
}

async function deleteTable(table) {
  if (!confirm(`Delete ${table.name}?`)) return;
  try {
    await api.delete(`/tables/${table.id}`);
    selectedTable.value = null;
    toast.success('Table deleted');
    await loadTables();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error deleting table');
  }
}
</script>

<style scoped>
.tables-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 60px);
  overflow: hidden;
}
.page-header {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}
.legend { display: flex; gap: 16px; margin-left: auto; }
.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text-muted);
}
.legend-dot { width: 10px; height: 10px; border-radius: 50%; }

.floor-plan {
  flex: 1;
  width: 100%;
  height: calc(100vh - 140px);
  position: relative;
  overflow: auto;
  background:
    linear-gradient(180deg, rgba(255,255,255,0.04), rgba(0,0,0,0.25)),
    repeating-linear-gradient(0deg, #e6d5c0 0px, #e6d5c0 12px, #d9c3ab 12px, #d9c3ab 24px),
    linear-gradient(180deg, #c9b196 0%, #bda484 100%);
  min-height: 520px;
  min-width: 100%;
  border-radius: var(--radius-lg);
  box-shadow: inset 0 0 0 1px rgba(0,0,0,0.2);
}

.zone {
  position: absolute;
  border-radius: var(--radius-md);
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 6px 10px;
  pointer-events: auto;
  backdrop-filter: blur(4px);
  border: 1px dashed var(--border);
  user-select: none;
}
.zone.entrance { top: 12px; left: 14px; background: rgba(46, 204, 113, 0.12); border-color: rgba(46, 204, 113, 0.35);}
.zone.exit { top: 12px; right: 14px; background: rgba(231, 76, 60, 0.12); border-color: rgba(231, 76, 60, 0.35);}
.zone.counter { top: 120px; left: 80px; width: 240px; text-align: center; background: rgba(212,168,83,0.18); border-color: rgba(212,168,83,0.5);}
.zone.kitchen { top: 60px; right: 40px; width: 220px; text-align: center; background: rgba(74, 144, 217, 0.18); border-color: rgba(74, 144, 217, 0.5);}
.zone.storage { top: 12px; right: 280px; background: rgba(149, 165, 166, 0.2); border-color: rgba(149, 165, 166, 0.4);}
.zone.restrooms { top: 12px; left: 320px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3);}
.zone.patio { bottom: 12px; left: 50%; transform: translateX(-50%); width: 110px; text-align: center; background: rgba(46, 204, 113, 0.14); border-color: rgba(46, 204, 113, 0.4);}

.floor-table {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  cursor: pointer;
  border: 2px solid rgba(212,168,83,0.9);
  transition: all var(--transition);
  user-select: none;
  touch-action: none;
  background: rgba(0,0,0,0.25);
  color: var(--text-primary);
  box-shadow: 0 6px 16px rgba(0,0,0,0.35);
  box-sizing: border-box;
}
.floor-table.dragging {
  z-index: 5;
  box-shadow: 0 10px 22px rgba(0,0,0,0.45);
}
.shape-square    { border-radius: var(--radius-md); }
.shape-circle    { border-radius: 50%; }
.shape-rectangle { border-radius: var(--radius-md); }

.status-available { background: rgba(39,174,96,0.12);  border-color: rgba(39,174,96,0.5); }
.status-occupied  { background: rgba(230,126,34,0.12); border-color: rgba(230,126,34,0.5); }
.status-reserved  { background: rgba(74,144,217,0.12); border-color: rgba(74,144,217,0.5); }
.status-inactive  { background: rgba(127,140,141,0.08); border-color: rgba(127,140,141,0.3); opacity: 0.5; }

.floor-table:hover { transform: scale(1.05); box-shadow: 0 8px 18px rgba(0,0,0,0.4); }

.table-number { font-size: 16px; font-weight: 800; color: var(--text-primary); }
.table-cap    { font-size: 11px; color: var(--text-secondary); }

.rotate-control {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
}
.rotate-control input[type="range"] {
  flex: 1;
  accent-color: var(--accent-gold);
}
.rotate-icon {
  font-size: 22px;
  opacity: 0.85;
  line-height: 1;
}
.rotate-value {
  font-size: 12px;
  color: var(--text-secondary);
  min-width: 40px;
  text-align: right;
}
.table-order-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: var(--accent-gold);
  color: var(--brew-espresso);
  font-size: 9px;
  font-weight: 700;
  padding: 2px 5px;
  border-radius: 99px;
  white-space: nowrap;
}

.floor-label {
  position: absolute;
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  pointer-events: none;
}
.counter { color: var(--accent-gold); }

/* Detail panel */
.table-detail-panel {
  position: fixed;
  right: 0; top: 60px; bottom: 0;
  width: 280px;
  background: var(--surface-2);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 100;
  animation: slideInRight 0.2s ease;
}
@keyframes slideInRight {
  from { transform: translateX(100%); }
  to   { transform: translateX(0); }
}

.detail-header {
  padding: 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.detail-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: auto;
}
.detail-stat   { display: flex; flex-direction: column; gap: 4px; }
.detail-actions { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }

.active-order-card {
  background: var(--surface-3);
  border-radius: var(--radius-md);
  padding: 12px;
  border: 1px solid var(--border);
}
.no-order-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  background: var(--surface-3);
  border-radius: var(--radius-md);
  border: 1px dashed var(--border);
}
.order-items-preview { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.item-tag {
  font-size: 11px;
  padding: 3px 8px;
  background: var(--surface-4);
  border-radius: 99px;
  color: var(--text-secondary);
}
</style>
const rotationInput     = ref(0);
let rotationTimer = null;
