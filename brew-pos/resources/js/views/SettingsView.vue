<template>
  <div class="page-wrap" style="max-width:700px">
    <h2 class="font-display" style="margin-bottom:24px">Settings</h2>

    <div v-if="loading" class="loading-state" style="height:200px">
      <div class="spinner" style="width:32px;height:32px;border-width:3px" />
    </div>

    <div v-else>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3 style="font-size:15px">☕ Restaurant Information</h3></div>
        <div class="card-body">
          <div class="form-grid form-grid-2" style="gap:14px">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Restaurant Name</label>
              <input v-model="form.restaurant_name" class="input" placeholder="BrewPOS Coffee" />
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Address</label>
              <input v-model="form.restaurant_address" class="input" placeholder="123 Coffee St." />
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input v-model="form.restaurant_phone" class="input" placeholder="+63 9XX XXX XXXX" />
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input v-model="form.restaurant_email" class="input" type="email" placeholder="hello@brewpos.com" />
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3 style="font-size:15px">🧾 Tax & Currency</h3></div>
        <div class="card-body">
          <div class="form-grid form-grid-2" style="gap:14px">
            <div class="form-group">
              <label class="form-label">Enable Tax</label>
              <select v-model="form.tax_enabled" class="select">
                <option :value="true">Yes</option>
                <option :value="false">No</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Tax Rate (%)</label>
              <input
                v-model.number="form.tax_rate"
                type="number"
                class="input"
                min="0"
                max="100"
                step="0.1"
                :disabled="!form.tax_enabled"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Tax Label</label>
              <input v-model="form.tax_label" class="input" placeholder="VAT" />
            </div>
            <div class="form-group">
              <label class="form-label">Currency Symbol</label>
              <input v-model="form.currency_symbol" class="input" placeholder="₱" maxlength="5" />
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:24px">
        <div class="card-header"><h3 style="font-size:15px">🖨 Receipt Settings</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Receipt Footer Message</label>
            <textarea
              v-model="form.receipt_footer"
              class="textarea"
              rows="3"
              placeholder="Thank you for visiting BrewPOS Coffee!"
            />
          </div>
        </div>
      </div>

      <div class="flex gap-3 justify-end">
        <button class="btn btn-secondary" @click="loadSettings">↺ Reset</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveSettings">
          <span v-if="saving" class="spinner" style="width:14px;height:14px" />
          💾 Save Settings
        </button>
      </div>

      <p v-if="saved" class="save-notice">✅ Settings saved successfully!</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/api';

const loading = ref(false);
const saving = ref(false);
const saved = ref(false);

const form = reactive({
  restaurant_name: '',
  restaurant_address: '',
  restaurant_phone: '',
  restaurant_email: '',
  currency: 'PHP',
  currency_symbol: '₱',
  tax_rate: 12,
  tax_enabled: true,
  tax_label: 'VAT',
  receipt_footer: 'Thank you for visiting!',
});

onMounted(loadSettings);

async function loadSettings() {
  loading.value = true;
  try {
    const { data } = await api.get('/settings');
    Object.assign(form, data);
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  saved.value = false;
  try {
    await api.put('/settings', form);
    saved.value = true;
    setTimeout(() => (saved.value = false), 3000);
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.page-wrap {
  padding: 16px;
}
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
}
.justify-end {
  justify-content: flex-end;
}
.save-notice {
  margin-top: 16px;
  text-align: right;
  color: #27ae60;
  font-size: 13px;
}
</style>
