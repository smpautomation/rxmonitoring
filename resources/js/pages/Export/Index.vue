<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import '../../../css/rx-monitoring.css'

const props = defineProps({
  areas: { type: Array, required: true },
  filters: { type: Object, default: () => ({}) },
  rows: { type: Array, default: () => [] },
  truncated: { type: Boolean, default: false },
})

const theme = ref(localStorage.getItem('rx-theme') || 'dark')

const form = ref({
  area: props.filters.area ?? '',
  status: props.filters.status ?? 'all',
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
  keyword: props.filters.keyword ?? '',
})
const searching = ref(false)

function search() {
  searching.value = true
  router.get('/rx-monitoring/export', { ...form.value }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => { searching.value = false },
  })
}

function resetFilters() {
  form.value = { area: '', status: 'all', date_from: '', date_to: '', keyword: '' }
  search()
}

const downloadUrl = computed(() => {
  const params = new URLSearchParams()
  Object.entries(form.value).forEach(([k, v]) => { if (v) params.set(k, v) })
  const qs = params.toString()
  return '/rx-monitoring/export/download' + (qs ? '?' + qs : '')
})

function fmt(v) { return v ?? '—' }
function fmtNum(v, digits = 2) { return v === null || v === undefined ? '—' : Number(v).toFixed(digits) }
</script>

<template>
  <Head title="Export — RX Monitoring" />

  <div class="rx-app" :data-rx-theme="theme">
    <header class="rx-header">
      <div class="rx-header__brand">
        <a href="/" class="rx-display rx-header__wordmark rx-header__wordmark--link">RX MONITORING</a>
        <span class="rx-header__area rx-mono">EXPORT</span>
      </div>
      <a href="/" class="rx-btn">← Back to monitoring</a>
    </header>

    <main class="export-main">
      <section class="rx-panel rx-stagger filter-panel">
        <h2 class="step-panel__title">Filter records</h2>
        <p class="step-panel__hint">Search across closed and in-progress chambers, then export the matching rows.</p>

        <div class="filter-grid">
          <div class="field-group">
            <label class="field-label">Area</label>
            <select v-model="form.area" class="rx-field">
              <option value="">All areas</option>
              <option v-for="a in areas" :key="a.code" :value="a.code">{{ a.name }}</option>
            </select>
          </div>
          <div class="field-group">
            <label class="field-label">Status</label>
            <select v-model="form.status" class="rx-field">
              <option value="all">All</option>
              <option value="open">Open</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <div class="field-group">
            <label class="field-label">Shift date from</label>
            <input v-model="form.date_from" type="date" class="rx-field rx-mono" />
          </div>
          <div class="field-group">
            <label class="field-label">Shift date to</label>
            <input v-model="form.date_to" type="date" class="rx-field rx-mono" />
          </div>
          <div class="field-group filter-grid__keyword">
            <label class="field-label">Keyword — model, lot no., remarks, or work order</label>
            <input
              v-model="form.keyword"
              type="text"
              class="rx-field"
              placeholder="Search…"
              @keydown.enter.prevent="search"
            />
          </div>
        </div>

        <div class="filter-actions">
          <button type="button" class="rx-btn" @click="resetFilters">Reset</button>
          <button type="button" class="rx-btn rx-btn--primary" :disabled="searching" @click="search">
            {{ searching ? 'Searching…' : 'Search' }}
          </button>
          <a :href="downloadUrl" class="rx-btn rx-btn--done">⬇ Export CSV</a>
        </div>
      </section>

      <section class="rx-panel rx-stagger results-panel" style="animation-delay:60ms">
        <div class="results-panel__header">
          <h2 class="step-panel__title">Results ({{ rows.length }}{{ truncated ? '+' : '' }})</h2>
          <p v-if="truncated" class="results-panel__note">
            Showing the first {{ rows.length }} rows on screen — the CSV export includes every matching row.
          </p>
        </div>

        <div v-if="!rows.length" class="empty-note">No records match these filters.</div>

        <div v-else class="table-scroll">
          <table class="export-table rx-mono">
            <thead>
              <tr>
                <th>Shift date</th><th>Area</th><th>Chamber</th><th>Oven</th><th>Layer</th>
                <th>Model</th><th>Lot no.</th><th>Qty</th><th>Category</th><th>Wt (kg)</th>
                <th>Loaded by</th><th>Start</th><th>Unloaded by</th><th>Stop</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in rows" :key="i">
                <td>{{ fmt(r.shift_date) }}</td>
                <td>{{ r.area }}</td>
                <td>{{ String(r.chamber_number).padStart(2, '0') }}</td>
                <td>{{ fmt(r.oven_no) }}</td>
                <td>{{ r.layer_no }}</td>
                <td>{{ fmt(r.model_name) }}</td>
                <td>{{ fmt(r.lot_no) }}</td>
                <td>{{ fmt(r.lot_quantity) }}</td>
                <td>{{ fmt(r.rx_type) }}</td>
                <td>{{ fmtNum(r.weight_per_tray_kg) }}</td>
                <td>{{ fmt(r.loaded_by) }}</td>
                <td>{{ fmt(r.start_time) }}</td>
                <td>{{ fmt(r.unloaded_by) }}</td>
                <td>{{ fmt(r.stop_time) }}</td>
                <td><span class="status-chip" :class="`status-chip--${r.status}`">{{ r.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.rx-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--rx-border);
}
.rx-header__brand { display: flex; align-items: baseline; gap: 0.6rem; }
.rx-header__wordmark { font-size: 1.35rem; font-weight: 700; letter-spacing: 0.02em; }
.rx-header__wordmark--link { text-decoration: none; color: inherit; }
.rx-header__area {
  font-size: 0.8rem;
  color: var(--rx-cool);
  border: 1px solid var(--rx-cool);
  border-radius: 5px;
  padding: 0.1rem 0.45rem;
}

.export-main {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  max-width: 1280px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem;
}

.filter-panel { padding: 1.3rem 1.4rem; }
.step-panel__title { font-size: 1.1rem; margin: 0 0 0.3rem; }
.step-panel__hint { font-size: 0.85rem; color: var(--rx-text-dim); margin: 0 0 1rem; }

.filter-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.9rem;
  margin-bottom: 1.1rem;
}
.filter-grid__keyword { grid-column: 1 / -1; }
.field-group { display: flex; flex-direction: column; gap: 0.4rem; }
.field-label { font-size: 0.78rem; font-weight: 600; color: var(--rx-text-dim); }

.filter-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }

.results-panel { padding: 1.3rem 1.4rem; }
.results-panel__header { margin-bottom: 0.9rem; }
.results-panel__note { font-size: 0.8rem; color: var(--rx-text-dim); margin: 0.2rem 0 0; }

.empty-note { font-size: 0.9rem; color: var(--rx-text-faint); padding: 1.5rem 0; text-align: center; }

.table-scroll { overflow-x: auto; border: 1px solid var(--rx-border); border-radius: 8px; }
.export-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; white-space: nowrap; }
.export-table thead th {
  position: sticky;
  top: 0;
  background: var(--rx-panel-raised);
  text-align: left;
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid var(--rx-border);
  font-weight: 600;
  color: var(--rx-text-dim);
}
.export-table tbody td {
  padding: 0.55rem 0.75rem;
  border-bottom: 1px solid var(--rx-border-soft);
}
.export-table tbody tr:nth-child(even) { background: var(--rx-panel-raised); }

.status-chip {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
  padding: 0.15rem 0.5rem;
  border-radius: 12px;
}
.status-chip--open { background: var(--rx-heat-glow); color: var(--rx-heat); }
.status-chip--closed { background: var(--rx-done-glow); color: var(--rx-done); }

@media (max-width: 900px) {
  .filter-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
  .filter-grid { grid-template-columns: 1fr; }
}
</style>
