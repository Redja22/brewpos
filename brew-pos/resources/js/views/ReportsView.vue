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
            @click="switchPeriod(p.value)"
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
            <p class="summary-value">{{ data.summary?.total_orders ?? 0 }}</p>
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
          <div class="card-header">
            <h3 class="font-display" style="font-size:16px">{{ data.daily_sales_label ?? 'Daily Sales' }}</h3>
          </div>
          <div class="card-body chart-body">
            <canvas ref="salesChartRef" />
          </div>
        </div>

        <div class="card chart-card">
          <div class="card-header">
            <h3 class="font-display" style="font-size:16px">
              Today's Hourly Sales
              <span v-if="hasHourlyData" class="peak-badge">Peak: {{ peakHour }}</span>
              <span v-else class="no-data-badge">No sales today yet</span>
            </h3>
          </div>
          <div class="card-body chart-body">
            <canvas ref="hourlyChartRef" />
          </div>
        </div>
      </div>

      <div class="bottom-grid">
        <div class="card">
          <div class="card-header">
            <h3 class="font-display" style="font-size:16px">🏆 Top Products</h3>
          </div>
          <div class="card-body" style="padding:0">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Product</th>
                  <th>Qty Sold</th>
                  <th>Revenue</th>
                </tr>
              </thead>
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
          <div class="card-header">
            <h3 class="font-display" style="font-size:16px">💳 Payment Methods</h3>
          </div>
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';
import api from '@/api';

Chart.register(...registerables);

const data = ref({
  summary: {},
  hourly_sales: [],
  top_products: [],
  payment_breakdown: [],
  daily_sales: [],
  daily_sales_label: 'Daily Sales (Last 7 Days)',
});
const loading = ref(false);
const period  = ref('today');

const salesChartRef  = ref(null);
const hourlyChartRef = ref(null);

const periods = [
  { value: 'today', label: 'Today' },
  { value: 'week',  label: 'This Week' },
  { value: 'month', label: 'This Month' },
];

// Only count hours that actually have revenue > 0
const hasHourlyData = computed(() =>
  data.value.hourly_sales?.some((h) => parseFloat(h.revenue) > 0)
);

// Peak hour: find the hour with the highest revenue, ignore hours with 0
const peakHour = computed(() => {
  const sales = data.value.hourly_sales;
  if (!sales?.length) return '—';

  const withRevenue = sales.filter((h) => parseFloat(h.revenue) > 0);
  if (!withRevenue.length) return '—';

  const peak = withRevenue.reduce((a, b) =>
    parseFloat(b.revenue) > parseFloat(a.revenue) ? b : a
  );

  const h       = parseInt(peak.hour);
  const suffix  = h >= 12 ? 'PM' : 'AM';
  const display = h % 12 === 0 ? 12 : h % 12;
  return `${display}:00 ${suffix}`;
});

const maxPayment = computed(() =>
  Math.max(...(data.value.payment_breakdown?.map((p) => parseFloat(p.total)) || [1]))
);

function barWidth(v) {
  return (parseFloat(v) / maxPayment.value) * 100;
}

function payIcon(m) {
  return { cash: '💵', card: '💳', gcash: '📱' }[m] || '💰';
}

function formatNum(v) {
  return parseFloat(v || 0).toFixed(2);
}

function switchPeriod(val) {
  period.value = val;
  loadData();
}

onMounted(loadData);
onUnmounted(destroyCharts);

async function loadData() {
  loading.value = true;
  try {
    const { data: d } = await api.get('/reports/dashboard', {
      params: { period: period.value },
    });
    data.value = d;

    await nextTick();
    await nextTick();

    setTimeout(() => {
      renderCharts();
    }, 50);
  } finally {
    loading.value = false;
  }
}

function destroyCharts() {
  if (salesChartRef.value) {
    const c = Chart.getChart(salesChartRef.value);
    if (c) c.destroy();
  }
  if (hourlyChartRef.value) {
    const c = Chart.getChart(hourlyChartRef.value);
    if (c) c.destroy();
  }
}

function chartDefaults() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx) => ` ₱${parseFloat(ctx.raw).toFixed(2)}`,
        },
      },
    },
    scales: {
      x: {
        grid: { color: 'rgba(139,94,60,0.15)' },
        ticks: { color: '#8a7060', font: { family: 'DM Sans' } },
      },
      y: {
        beginAtZero: true,
        grace: '10%',
        grid: { color: 'rgba(139,94,60,0.15)' },
        ticks: {
          color: '#8a7060',
          font: { family: 'DM Sans' },
          callback: (v) => `₱${v}`,
        },
      },
    },
  };
}

function renderCharts() {
  destroyCharts();

  // --- Daily Sales Bar Chart (period-aware labels) ---
  if (salesChartRef.value) {
    const dailyData = data.value.daily_sales || [];

    // For month view: abbreviate labels so they don't crowd (e.g. "Mar 1")
    const labels = dailyData.map((d) => {
      if (period.value === 'month') {
        // d.date is a month number (1–12)
        return new Date(2000, parseInt(d.date) - 1, 1)
          .toLocaleDateString('en-PH', { month: 'short' }); // "Jan", "Feb", ...
      }
      if (period.value === 'week') {
        const dt = new Date(d.date + 'T00:00:00');
        return dt.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' });
      }
      // today / last 7 days
      return d.date;
    });

    const values = dailyData.map((d) => parseFloat(d.revenue) || 0);

    new Chart(salesChartRef.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Revenue',
          data: values,
          backgroundColor: 'rgba(212,168,83,0.6)',
          borderColor: '#d4a853',
          borderWidth: 2,
          borderRadius: 6,
        }],
      },
      options: {
        ...chartDefaults(),
        scales: {
          ...chartDefaults().scales,
          x: {
            ...chartDefaults().scales.x,
            ticks: {
              ...chartDefaults().scales.x.ticks,
            },
          },
        },
      },
    });
  }

  // --- Hourly Sales Line Chart ---
  if (hourlyChartRef.value) {
    const hourlyData = data.value.hourly_sales || [];

    const labels = hourlyData.map((h) => {
      const hr = parseInt(h.hour);
      if (hr % 2 !== 0) return '';
      const suffix  = hr >= 12 ? 'PM' : 'AM';
      const display = hr % 12 === 0 ? 12 : hr % 12;
      return `${display}${suffix}`;
    });

    const values    = hourlyData.map((h) => parseFloat(h.revenue) || 0);
    const peakIndex = values.indexOf(Math.max(...values));

    new Chart(hourlyChartRef.value, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Revenue',
          data: values,
          borderColor: '#d4a853',
          backgroundColor: 'rgba(212,168,83,0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: values.map((v, i) =>
            i === peakIndex && v > 0 ? '#ff9800' : '#d4a853'
          ),
          pointRadius: values.map((v, i) =>
            i === peakIndex && v > 0 ? 7 : v > 0 ? 3 : 2
          ),
          pointBorderColor: values.map((v, i) =>
            i === peakIndex && v > 0 ? '#fff' : 'transparent'
          ),
          pointBorderWidth: 2,
        }],
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

/* Summary */
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

.summary-icon { font-size: 32px; }

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

/* Charts */
.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 16px;
}

.chart-body {
  position: relative;
  height: 220px;
  overflow: hidden;
}

/* Peak/no-data badges inside chart header */
.peak-badge {
  font-size: 11px;
  font-weight: 600;
  background: rgba(212,168,83,0.15);
  color: var(--accent-gold);
  border: 1px solid rgba(212,168,83,0.3);
  border-radius: 20px;
  padding: 2px 10px;
  margin-left: 8px;
  vertical-align: middle;
}

.no-data-badge {
  font-size: 11px;
  font-weight: 400;
  color: var(--text-muted);
  margin-left: 8px;
  vertical-align: middle;
}

/* Bottom grid */
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

/* Payment bars */
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
