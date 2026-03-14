<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="font-display">Reports & Analytics</h2>
      <div class="flex gap-3 items-center">
        <div class="period-tabs">
          <button
            v-for="p in periods"
            :key="p.value"
            class="period-tab"
            :class="{ active: period === p.value }"
            @click="period = p.value; loadData()"
          >
            {{ p.label }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading-state" style="height:300px">
      <div class="spinner" style="width:40px;height:40px;border-width:3px" />
    </div>

    <div v-else>
      <div class="summary-grid">
        <div class="summary-card">
          <div class="summary-icon">💰</div>
          <div class="summary-info">
            <p class="summary-label">Total Revenue</p>
            <p class="summary-value">₱{{ formatNum(data.summary?.total_revenue) }}</p>
          </div>
        </div>
        <div class="summary-card">
          <div class="summary-icon">📋</div>
          <div class="summary-info">
            <p class="summary-label">Total Orders</p>
            <p class="summary-value">{{ data.summary?.total_orders }}</p>
          </div>
        </div>
        <div class="summary-card">
          <div class="summary-icon">🧾</div>
          <div class="summary-info">
            <p class="summary-label">Avg Order Value</p>
            <p class="summary-value">₱{{ formatNum(data.summary?.avg_order_value) }}</p>
          </div>
        </div>
        <div class="summary-card">
          <div class="summary-icon">📈</div>
          <div class="summary-info">
            <p class="summary-label">Peak Hour</p>
            <p class="summary-value">{{ peakHour }}</p>
          </div>
        </div>
      </div>

      <div class="charts-grid">
        <div class="card chart-card">
          <div class="card-header"><h3 class="font-display" style="font-size:16px">Daily Sales (Last 7 Days)</h3></div>
          <div class="card-body">
            <canvas ref="salesChartRef" height="200" />
          </div>
        </div>

        <div class="card chart-card">
          <div class="card-header"><h3 class="font-display" style="font-size:16px">Today's Hourly Sales</h3></div>
          <div class="card-body">
            <canvas ref="hourlyChartRef" height="200" />
          </div>
        </div>
      </div>

      <div class="bottom-grid">
        <div class="card">
          <div class="card-header"><h3 class="font-display" style="font-size:16px">🏆 Top Products</h3></div>
          <div class="card-body" style="padding:0">
            <table class="table">
              <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
              <tbody>
                <tr v-for="(p, i) in data.top_products" :key="p.product_id">
                  <td><span class="rank-badge">{{ i + 1 }}</span></td>
                  <td><strong>{{ p.product_name }}</strong></td>
                  <td>{{ p.total_qty }}</td>
                  <td class="text-gold font-bold">₱{{ formatNum(p.total_revenue) }}</td>
                </tr>
                <tr v-if="!data.top_products?.length">
                  <td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">No data</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="font-display" style="font-size:16px">💳 Payment Methods</h3></div>
          <div class="card-body">
            <div v-if="data.payment_breakdown?.length" class="payment-bars">
              <div v-for="p in data.payment_breakdown" :key="p.method" class="pay-bar-row">
                <div class="pay-bar-label">
                  <span>{{ payIcon(p.method) }} {{ p.method.toUpperCase() }}</span>
                  <span class="text-gold font-bold">₱{{ formatNum(p.total) }}</span>
                </div>
                <div class="pay-bar-track">
                  <div class="pay-bar-fill" :style="`width:${barWidth(p.total)}%`" />
                </div>
                <span class="text-muted text-sm">{{ p.count }} orders</span>
              </div>
            </div>
            <div v-else class="empty-state" style="padding:24px">
              <p>No payment data</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';
import api from '@/api';

Chart.register(...registerables);

const data = ref({ summary: {}, hourly_sales: [], top_products: [], payment_breakdown: [], daily_sales: [] });
const loading = ref(false);
const period = ref('today');

const salesChartRef = ref(null);
const hourlyChartRef = ref(null);
let salesChart = null;
let hourlyChart = null;

const periods = [
  { value: 'today', label: 'Today' },
  { value: 'week', label: 'This Week' },
  { value: 'month', label: 'This Month' },
];

const peakHour = computed(() => {
  if (!data.value.hourly_sales?.length) return '—';
  const peak = data.value.hourly_sales.reduce((a, b) => (b.revenue > a.revenue ? b : a));
  return `${peak.hour}:00`;
});

const maxPayment = computed(() => Math.max(...(data.value.payment_breakdown?.map((p) => p.total) || [1])));
function barWidth(v) {
  return (v / maxPayment.value) * 100;
}
function payIcon(m) {
  return { cash: '💵', card: '💳', gcash: '📱' }[m] || '💰';
}
function formatNum(v) {
  return parseFloat(v || 0).toFixed(2);
}

onMounted(loadData);

async function loadData() {
  loading.value = true;
  try {
    const { data: d } = await api.get('/reports/dashboard', { params: { period: period.value } });
    data.value = d;
    await nextTick();
    renderCharts();
  } finally {
    loading.value = false;
  }
}

function chartDefaults() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(139,94,60,0.15)' }, ticks: { color: '#8a7060', font: { family: 'DM Sans' } } },
      y: { grid: { color: 'rgba(139,94,60,0.15)' }, ticks: { color: '#8a7060', font: { family: 'DM Sans' } } },
    },
  };
}

function renderCharts() {
  if (salesChart) salesChart.destroy();
  if (salesChartRef.value) {
    const labels = data.value.daily_sales?.map((d) => d.date) || [];
    const values = data.value.daily_sales?.map((d) => d.revenue) || [];
    salesChart = new Chart(salesChartRef.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Revenue',
            data: values,
            backgroundColor: 'rgba(212,168,83,0.6)',
            borderColor: '#d4a853',
            borderWidth: 2,
            borderRadius: 6,
          },
        ],
      },
      options: chartDefaults(),
    });
  }

  if (hourlyChart) hourlyChart.destroy();
  if (hourlyChartRef.value) {
    const labels = data.value.hourly_sales?.map((h) => `${h.hour}:00`) || [];
    const values = data.value.hourly_sales?.map((h) => h.revenue) || [];
    hourlyChart = new Chart(hourlyChartRef.value, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Revenue',
            data: values,
            borderColor: '#d4a853',
            backgroundColor: 'rgba(212,168,83,0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#d4a853',
            pointRadius: 4,
          },
        ],
      },
      options: chartDefaults(),
    });
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
  flex-wrap: wrap;
  gap: 12px;
}
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
}

.period-tabs {
  display: flex;
  gap: 4px;
  background: var(--surface-2);
  border-radius: var(--radius-md);
  padding: 4px;
  border: 1px solid var(--border);
}
.period-tab {
  padding: 6px 16px;
  border-radius: var(--radius-sm);
  border: none;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 13px;
  font-family: var(--font-body);
  transition: all var(--transition);
}
.period-tab.active {
  background: var(--accent-gold);
  color: var(--brew-espresso);
  font-weight: 600;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}
.summary-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all var(--transition);
}
.summary-card:hover {
  border-color: var(--accent-gold);
  transform: translateY(-2px);
}
.summary-icon {
  font-size: 32px;
}
.summary-label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 4px;
}
.summary-value {
  font-size: 22px;
  font-weight: 700;
  font-family: var(--font-display);
  color: var(--accent-gold);
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 16px;
}
.chart-card .card-body {
  height: 220px;
  position: relative;
}

.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.rank-badge {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--surface-4);
  color: var(--accent-gold);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
}

.payment-bars {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pay-bar-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.pay-bar-label {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  font-weight: 500;
}
.pay-bar-track {
  height: 8px;
  background: var(--surface-3);
  border-radius: 99px;
  overflow: hidden;
}
.pay-bar-fill {
  height: 100%;
  background: var(--accent-gold);
  border-radius: 99px;
  transition: width 0.8s ease;
}

@media (max-width: 900px) {
  .charts-grid,
  .bottom-grid {
    grid-template-columns: 1fr;
  }
}
</style>
