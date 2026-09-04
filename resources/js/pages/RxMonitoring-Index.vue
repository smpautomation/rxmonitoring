<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ScanField from '@/components/Rx/ScanField.vue'
import StationTrack from '@/components/Rx/StationTrack.vue'
import LayerGrid from '@/components/Rx/LayerGrid.vue'
import '../../css/rx-monitoring.css'

const props = defineProps({
  areas: { type: Array, required: true },
  area: { type: Object, default: null },
  ovens: { type: Array, default: () => [] },
  productModels: { type: Array, default: () => [] },
  openChambers: { type: Array, default: () => [] },
  chamber: { type: Object, default: null },
  selectedChamberNumber: { type: Number, default: null },
})

/* ---------------------------------------------------------------- theme */
const theme = ref(localStorage.getItem('rx-theme') || 'dark')
watch(theme, (val) => localStorage.setItem('rx-theme', val))
function toggleTheme() { theme.value = theme.value === 'dark' ? 'light' : 'dark' }

/* ----------------------------------------------------------------- clock */
const now = ref(new Date())
let clockTimer = null

/* -------------------------------------------------------- auto-refresh */
const refreshing = ref(false)
const lastRefreshed = ref(new Date())
let pollTimer = null

function refreshNow() {
  if (!props.area) return
  refreshing.value = true
  router.reload({
    only: ['chamber', 'openChambers'],
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { refreshing.value = false; lastRefreshed.value = new Date() },
  })
}

onMounted(() => {
  clockTimer = setInterval(() => { now.value = new Date() }, 1000)
  pollTimer = setInterval(refreshNow, 8000)
})
onUnmounted(() => {
  clearInterval(clockTimer)
  clearInterval(pollTimer)
})

const clockLabel = computed(() =>
  now.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
)

/* ------------------------------------------------------ area selection */
function selectArea(code) {
  router.get('/rx-monitoring', code ? { area: code } : {}, { preserveState: false })
}

/* -------------------------------------------------- PIC gate (client) */
const locationPic = ref(null)
watch(() => props.area?.code, () => { locationPic.value = null })

/* ---------------------------------------------------- chamber selection */
const chamberNumbers = computed(() =>
  props.area ? Array.from({ length: props.area.chamber_count }, (_, i) => i + 1) : []
)
function chamberLabel(n) { return 'Chamber ' + String(n).padStart(2, '0') }
function isChamberOpen(n) { return props.openChambers.some((c) => c.chamber_number === n) }

const pendingChamberNumber = ref(props.selectedChamberNumber ?? '')

function onChamberPicked() {
  const num = Number(pendingChamberNumber.value)
  if (!num) return
  if (isChamberOpen(num)) {
    router.get('/rx-monitoring', { area: props.area.code, chamber: num })
    return
  }
  askConfirm(
    'Open new chamber',
    `Open ${chamberLabel(num)}? This reserves ${props.area.layer_count} layer slots for lots.`,
    () => createChamber(num),
    'Open chamber'
  )
}

const creatingChamber = ref(false)
function createChamber(num) {
  creatingChamber.value = true
  router.post('/rx-monitoring/chambers', {
    area_id: props.area.id,
    chamber_number: num,
    scanned_code: locationPic.value?.rawCode,
  }, {
    onFinish: () => { creatingChamber.value = false },
  })
}

function viewChamber(c) {
  router.get('/rx-monitoring', { area: props.area.code, chamber: c.chamber_number })
}

/* ---------------------------------------------------------- weight bar */
const weightPct = computed(() => {
  if (!props.chamber?.oven_capacity_kg) return 0
  return Math.min(100, (Number(props.chamber.total_weight_kg) / Number(props.chamber.oven_capacity_kg)) * 100)
})

/* ------------------------------------------------------------- step: oven */
const ovenForm = useForm({ oven_id: null })
function submitOven() {
  ovenForm.patch(`/rx-monitoring/chambers/${props.chamber.id}/oven`, { preserveScroll: true })
}

/* ------------------------------------------------------- step: before rx */
const loadedByScan = ref(null)
const startForm = useForm({ start_temperature_c: '', scanned_code: '' })
function submitStart() {
  askConfirm(
    'Start RX',
    `Log the start temperature for ${props.chamber.chamber_label} and begin the RX cycle?`,
    () => startForm.patch(`/rx-monitoring/chambers/${props.chamber.id}/start`, {
      preserveScroll: true,
      onSuccess: () => { loadedByScan.value = null },
    }),
    'Start RX'
  )
}

/* -------------------------------------------------------- step: peak */
const peakScan = ref(null)
const peakForm = useForm({ scanned_code: '' })
function submitPeak() {
  askConfirm(
    'Peak temperature reached',
    `Record peak temperature for ${props.chamber.chamber_label}?`,
    () => peakForm.patch(`/rx-monitoring/chambers/${props.chamber.id}/peak`, {
      preserveScroll: true,
      onSuccess: () => { peakScan.value = null },
    }),
    'Confirm peak'
  )
}

/* --------------------------------------------------------- step: stop */
const unloadedByScan = ref(null)
const stopForm = useForm({ stop_temperature_c: '', scanned_code: '' })
function submitStop() {
  askConfirm(
    'Stop RX',
    `Log the stop temperature and begin unloading ${props.chamber.chamber_label}?`,
    () => stopForm.patch(`/rx-monitoring/chambers/${props.chamber.id}/stop`, {
      preserveScroll: true,
      onSuccess: () => { unloadedByScan.value = null },
    }),
    'Stop RX'
  )
}

/* ------------------------------------------------------ step: cooling */
function submitCoolingStart() {
  router.patch(`/rx-monitoring/chambers/${props.chamber.id}/cooling-start`, {}, { preserveScroll: true })
}
function submitCoolingEnd() {
  router.patch(`/rx-monitoring/chambers/${props.chamber.id}/cooling-end`, {}, { preserveScroll: true })
}

/* -------------------------------------------------- step: confirmation */
const closeScan = ref(null)
const closeForm = useForm({ scanned_code: '' })
function submitClose() {
  askConfirm(
    'Confirm & close chamber',
    `This permanently closes ${props.chamber.chamber_label}. This cannot be undone.`,
    () => closeForm.patch(`/rx-monitoring/chambers/${props.chamber.id}/close`, { preserveScroll: true }),
    'Close chamber'
  )
}

/* ---------------------------------------------------- generic confirm */
const confirmDialog = ref(null)
function askConfirm(title, message, action, confirmLabel = 'Confirm') {
  confirmDialog.value = { title, message, action, confirmLabel }
}
function runConfirm() {
  const d = confirmDialog.value
  confirmDialog.value = null
  d?.action()
}
function cancelConfirm() { confirmDialog.value = null }

function fmtTime(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <Head title="RX Monitoring" />

  <div class="rx-app" :data-rx-theme="theme">
    <!-- ============================================================ header -->
    <header class="rx-header">
      <div class="rx-header__brand">
        <span class="rx-display rx-header__wordmark">RX MONITORING</span>
        <span v-if="area" class="rx-header__area rx-mono">{{ area.code }}</span>
      </div>

      <div class="rx-header__right">
        <button type="button" class="live-pill" @click="refreshNow" :title="'Last updated ' + lastRefreshed.toLocaleTimeString()">
          <span class="live-pill__dot" :class="{ 'live-pill__dot--spin': refreshing }" />
          Live
        </button>
        <span class="rx-header__clock rx-mono">{{ clockLabel }}</span>
        <button type="button" class="theme-toggle" @click="toggleTheme" aria-label="Toggle light/dark theme">
          <svg v-if="theme === 'dark'" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.8"/></svg>
          <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        </button>
      </div>
    </header>

    <div class="rx-layout">
      <!-- ======================================================== sidebar -->
      <aside class="rx-sidebar">
        <section class="rx-panel rx-stagger sidebar-section">
          <h2 class="sidebar-section__title">Location parameter</h2>

          <div class="field-group">
            <label class="field-label">Area</label>
            <select class="rx-field" :value="area?.code ?? ''" @change="selectArea($event.target.value)">
              <option value="" disabled>Select area…</option>
              <option v-for="a in areas" :key="a.id" :value="a.code">{{ a.name }}</option>
            </select>
          </div>

          <div v-if="area" class="field-group">
            <ScanField
              label="Scan PIC badge to unlock chamber selection"
              require-pic
              @scanned="(p) => (locationPic = p)"
            />
          </div>

          <div v-if="area" class="field-group">
            <label class="field-label">Chamber no.</label>
            <select
              v-model="pendingChamberNumber"
              class="rx-field"
              :disabled="!locationPic || creatingChamber"
              @change="onChamberPicked"
            >
              <option value="" disabled>{{ locationPic ? 'Select chamber…' : 'Scan PIC badge first' }}</option>
              <option v-for="n in chamberNumbers" :key="n" :value="n">
                {{ chamberLabel(n) }}{{ isChamberOpen(n) ? ' — in progress' : ' — start new' }}
              </option>
            </select>
          </div>
        </section>

        <section v-if="area" class="rx-panel rx-stagger sidebar-section" style="animation-delay:60ms">
          <h2 class="sidebar-section__title">Open chambers ({{ openChambers.length }})</h2>
          <p v-if="!openChambers.length" class="empty-note">No chambers currently in progress for this area.</p>
          <ul v-else class="open-chamber-list">
            <li
              v-for="c in openChambers"
              :key="c.id"
              class="open-chamber-item"
              :class="{ 'open-chamber-item--active': chamber?.id === c.id }"
              @click="viewChamber(c)"
            >
              <span class="open-chamber-item__label">{{ c.chamber_label }}</span>
              <span class="open-chamber-item__meta rx-mono">
                {{ c.oven?.oven_no ?? 'no oven' }} · {{ Number(c.total_weight_kg).toFixed(1) }} kg
              </span>
              <span
                class="open-chamber-item__status"
                :class="`status--${c.weight_status}`"
              >{{ c.weight_status === 'overweight' ? 'Overweight' : c.current_step?.replaceAll('_', ' ') }}</span>
            </li>
          </ul>
        </section>
      </aside>

      <!-- ========================================================== main -->
      <main class="rx-main">
        <div v-if="!area" class="empty-state rx-panel">
          <p class="rx-display empty-state__title">Select an area to begin</p>
          <p class="empty-state__body">Choose the production area from the panel on the left.</p>
        </div>

        <div v-else-if="!chamber" class="empty-state rx-panel">
          <p class="rx-display empty-state__title">No chamber selected</p>
          <p class="empty-state__body">
            {{ locationPic ? 'Pick a chamber number to resume or start a new RX cycle.' : 'Scan a PIC badge, then pick a chamber number.' }}
          </p>
        </div>

        <template v-else>
          <!-- chamber summary -->
          <section class="rx-panel rx-stagger chamber-summary">
            <div class="chamber-summary__top">
              <div>
                <h1 class="rx-display chamber-summary__title">{{ chamber.chamber_label }}</h1>
                <p class="chamber-summary__meta rx-mono">
                  {{ area.name }} · Shift {{ chamber.shift_date }} · Authorized by {{ chamber.authorized_by?.name ?? '—' }}
                </p>
              </div>
              <div v-if="chamber.oven" class="chamber-summary__oven">
                <span class="chamber-summary__oven-label">Oven</span>
                <span class="rx-display chamber-summary__oven-no">{{ chamber.oven.oven_no }}</span>
              </div>
            </div>

            <div class="weight-bar-wrap">
              <div class="weight-bar-labels">
                <span class="rx-mono">{{ Number(chamber.total_weight_kg).toFixed(2) }} kg</span>
                <span class="rx-mono weight-bar-labels__cap">
                  {{ chamber.oven_capacity_kg ? Number(chamber.oven_capacity_kg).toFixed(0) + ' kg capacity' : 'awaiting oven setup' }}
                </span>
              </div>
              <div class="weight-bar">
                <div
                  class="weight-bar__fill"
                  :class="`weight-bar__fill--${chamber.weight_status}`"
                  :style="{ width: weightPct + '%' }"
                />
              </div>
              <p v-if="chamber.weight_status === 'overweight'" class="weight-warning">
                Oven capacity exceeded — reduce load quantity before starting RX.
              </p>
            </div>

            <StationTrack :current-step="chamber.current_step" />
          </section>

          <!-- active step panel -->
          <section class="rx-panel rx-stagger step-panel">
            <template v-if="chamber.current_step === 'oven_setup'">
              <h3 class="step-panel__title">1 · RX oven setup</h3>
              <p class="step-panel__hint">Choose which oven this chamber will load into.</p>
              <div class="field-group">
                <select v-model="ovenForm.oven_id" class="rx-field">
                  <option :value="null" disabled>Select oven…</option>
                  <option v-for="o in ovens" :key="o.id" :value="o.id">
                    {{ o.oven_no }} — {{ Number(o.capacity_kg).toFixed(0) }} kg capacity
                  </option>
                </select>
              </div>
              <button class="rx-btn rx-btn--primary" :disabled="!ovenForm.oven_id || ovenForm.processing" @click="submitOven">
                {{ ovenForm.processing ? 'Setting…' : 'Set oven' }}
              </button>
            </template>

            <template v-else-if="chamber.current_step === 'before_rx'">
              <h3 class="step-panel__title">2 · Before RX (after chamber loading)</h3>
              <p class="step-panel__hint">Encode every lot in the layer grid below, then log the start temperature.</p>
              <div class="field-row">
                <div class="field-group">
                  <label class="field-label">Start temperature (°C)</label>
                  <input v-model="startForm.start_temperature_c" type="number" min="0" max="500" class="rx-field rx-mono" placeholder="0" />
                  <p v-if="startForm.errors.start_temperature_c" class="field-error">{{ startForm.errors.start_temperature_c }}</p>
                </div>
                <div class="field-group">
                  <ScanField label="Loaded by" @scanned="(p) => { loadedByScan = p; startForm.scanned_code = p.rawCode }" />
                </div>
              </div>
              <p v-if="startForm.errors.start" class="field-error">{{ startForm.errors.start }}</p>
              <button
                class="rx-btn rx-btn--primary"
                :disabled="!startForm.start_temperature_c || !startForm.scanned_code || startForm.processing"
                @click="submitStart"
              >
                {{ startForm.processing ? 'Starting…' : 'Start RX' }}
              </button>
            </template>

            <template v-else-if="chamber.current_step === 'peak_temp'">
              <h3 class="step-panel__title">3 · Peak temperature reached</h3>
              <p class="step-panel__hint">
                Target peak: <span class="rx-mono">{{ chamber.peak_temp_target_c ? Number(chamber.peak_temp_target_c).toFixed(0) + '°C' : '—' }}</span>
                · Started {{ fmtTime(chamber.start_time) }}
              </p>
              <ScanField label="Checked by" @scanned="(p) => { peakScan = p; peakForm.scanned_code = p.rawCode }" />
              <button class="rx-btn rx-btn--primary" :disabled="!peakForm.scanned_code || peakForm.processing" @click="submitPeak">
                {{ peakForm.processing ? 'Recording…' : 'Mark peak reached' }}
              </button>
            </template>

            <template v-else-if="chamber.current_step === 'after_rx'">
              <h3 class="step-panel__title">4 · After RX (before chamber unloading)</h3>
              <p class="step-panel__hint">Peak checked by {{ chamber.peak_checked_by?.name }} at {{ fmtTime(chamber.peak_temp_time) }}</p>
              <div class="field-row">
                <div class="field-group">
                  <label class="field-label">Stop temperature (°C)</label>
                  <input v-model="stopForm.stop_temperature_c" type="number" min="0" max="500" class="rx-field rx-mono" placeholder="0" />
                </div>
                <div class="field-group">
                  <ScanField label="Unloaded by" @scanned="(p) => { unloadedByScan = p; stopForm.scanned_code = p.rawCode }" />
                </div>
              </div>
              <button
                class="rx-btn rx-btn--cool"
                :disabled="!stopForm.stop_temperature_c || !stopForm.scanned_code || stopForm.processing"
                @click="submitStop"
              >
                {{ stopForm.processing ? 'Stopping…' : 'Stop RX' }}
              </button>
            </template>

            <template v-else-if="chamber.current_step === 'cooling'">
              <h3 class="step-panel__title">5 · Chamber cooling process</h3>
              <p class="step-panel__hint">
                Stopped {{ fmtTime(chamber.stop_time) }} · Unloaded by {{ chamber.unloaded_by?.name }}
              </p>
              <div v-if="!chamber.cooling_start_time" class="cooling-actions">
                <button class="rx-btn rx-btn--cool" @click="submitCoolingStart">Start cooling</button>
              </div>
              <div v-else-if="!chamber.cooling_end_time" class="cooling-actions">
                <p class="step-panel__hint">Cooling started {{ fmtTime(chamber.cooling_start_time) }}</p>
                <button class="rx-btn rx-btn--cool" @click="submitCoolingEnd">End cooling</button>
              </div>
            </template>

            <template v-else-if="chamber.current_step === 'confirmation'">
              <h3 class="step-panel__title">6 · Confirmation</h3>
              <dl class="confirm-summary">
                <div><dt>Loaded by</dt><dd>{{ chamber.loaded_by?.name ?? '—' }}</dd></div>
                <div><dt>Unloaded by</dt><dd>{{ chamber.unloaded_by?.name ?? '—' }}</dd></div>
                <div><dt>Cooling</dt><dd class="rx-mono">{{ fmtTime(chamber.cooling_start_time) }} → {{ fmtTime(chamber.cooling_end_time) }}</dd></div>
                <div><dt>Total weight</dt><dd class="rx-mono">{{ Number(chamber.total_weight_kg).toFixed(2) }} kg</dd></div>
              </dl>
              <ScanField label="Scan PIC badge to close this chamber" require-pic @scanned="(p) => { closeScan = p; closeForm.scanned_code = p.rawCode }" />
              <button class="rx-btn rx-btn--done" :disabled="!closeForm.scanned_code || closeForm.processing" @click="submitClose">
                {{ closeForm.processing ? 'Closing…' : 'Mark as closed' }}
              </button>
            </template>
          </section>

          <!-- layers -->
          <section class="rx-panel rx-stagger layers-section" style="animation-delay:90ms">
            <h3 class="step-panel__title">Layers ({{ chamber.layers.filter(l => l.is_filled).length }}/{{ chamber.layers.length }} loaded)</h3>
            <LayerGrid
              :chamber-id="chamber.id"
              :layers="chamber.layers"
              :product-models="productModels"
              :disabled="!!chamber.start_time"
            />
          </section>
        </template>
      </main>
    </div>

    <!-- ===================================================== confirm dialog -->
    <div v-if="confirmDialog" class="confirm-overlay" @click.self="cancelConfirm">
      <div class="confirm-dialog rx-panel-raised rx-scan-confirm-anim">
        <h3 class="rx-display">{{ confirmDialog.title }}</h3>
        <p>{{ confirmDialog.message }}</p>
        <div class="confirm-dialog__actions">
          <button class="rx-btn" @click="cancelConfirm">Cancel</button>
          <button class="rx-btn rx-btn--primary" @click="runConfirm">{{ confirmDialog.confirmLabel }}</button>
        </div>
      </div>
    </div>
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
.rx-header__area {
  font-size: 0.8rem;
  color: var(--rx-heat);
  border: 1px solid var(--rx-heat);
  border-radius: 5px;
  padding: 0.1rem 0.45rem;
}
.rx-header__right { display: flex; align-items: center; gap: 0.9rem; }
.rx-header__clock { font-size: 0.9rem; color: var(--rx-text-dim); }

.live-pill {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--rx-text-dim);
  background: none;
  border: 1px solid var(--rx-border);
  border-radius: 20px;
  padding: 0.3rem 0.7rem;
  cursor: pointer;
}
.live-pill__dot { width: 7px; height: 7px; border-radius: 50%; background: var(--rx-done); }
.live-pill__dot--spin { animation: rx-pulse-glow 0.8s ease-in-out infinite; }

.theme-toggle {
  background: none;
  border: 1px solid var(--rx-border);
  border-radius: 8px;
  padding: 0.45rem;
  color: var(--rx-text);
  cursor: pointer;
  display: flex;
}

.rx-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 1.25rem;
  padding: 1.25rem 1.5rem 3rem;
  max-width: 1280px;
  margin: 0 auto;
}

.rx-sidebar { display: flex; flex-direction: column; gap: 1rem; }
.sidebar-section { padding: 1.1rem; }
.sidebar-section__title {
  font-size: 0.98rem;
  font-weight: 700;
  color: var(--rx-text);
  margin: 0 0 0.9rem;
  padding-left: 0.6rem;
  border-left: 3px solid var(--rx-heat);
}

.field-group { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.9rem; }
.field-group:last-child { margin-bottom: 0; }
.field-label { font-size: 0.78rem; font-weight: 600; color: var(--rx-text-dim); }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 0.9rem; }
.field-error { font-size: 0.78rem; color: var(--rx-danger); }

.empty-note { font-size: 0.85rem; color: var(--rx-text-faint); }

.open-chamber-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.open-chamber-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.6rem 0.7rem;
  border-radius: 8px;
  border: 1px solid var(--rx-border);
  cursor: pointer;
}
.open-chamber-item:hover { border-color: var(--rx-heat); }
.open-chamber-item--active { border-color: var(--rx-heat); background: var(--rx-heat-glow); }
.open-chamber-item__label { font-weight: 600; font-size: 0.9rem; }
.open-chamber-item__meta { font-size: 0.75rem; color: var(--rx-text-dim); }
.open-chamber-item__status { font-size: 0.72rem; text-transform: capitalize; color: var(--rx-cool); }
.status--overweight { color: var(--rx-danger); }

.rx-main { display: flex; flex-direction: column; gap: 1.1rem; min-width: 0; }

.empty-state { padding: 3rem 2rem; text-align: center; }
.empty-state__title { font-size: 1.6rem; margin: 0 0 0.5rem; }
.empty-state__body { color: var(--rx-text-dim); margin: 0; }

.chamber-summary { padding: 1.3rem 1.4rem; }
.chamber-summary__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.chamber-summary__title { font-size: 2rem; margin: 0; line-height: 1; }
.chamber-summary__meta { font-size: 0.8rem; color: var(--rx-text-dim); margin: 0.35rem 0 0; }
.chamber-summary__oven { text-align: right; }
.chamber-summary__oven-label { display: block; font-size: 0.72rem; color: var(--rx-text-faint); }
.chamber-summary__oven-no { font-size: 1.4rem; color: var(--rx-heat); }

.weight-bar-wrap { margin-bottom: 0.5rem; }
.weight-bar-labels { display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 0.35rem; color: var(--rx-text-dim); }
.weight-bar { height: 10px; border-radius: 5px; background: var(--rx-bg); border: 1px solid var(--rx-border); overflow: hidden; }
.weight-bar__fill { height: 100%; border-radius: 5px; transition: width 0.4s ease; background: var(--rx-locked); }
.weight-bar__fill--ok { background: var(--rx-cool); }
.weight-bar__fill--overweight { background: var(--rx-danger); }
.weight-warning { color: var(--rx-danger); font-size: 0.8rem; margin: 0.4rem 0 0; }

.step-panel { padding: 1.3rem 1.4rem; }
.step-panel__title { font-size: 1.1rem; margin: 0 0 0.3rem; }
.step-panel__hint { font-size: 0.85rem; color: var(--rx-text-dim); margin: 0 0 0.9rem; }
.cooling-actions { display: flex; flex-direction: column; gap: 0.6rem; align-items: flex-start; }

.confirm-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 0.7rem 1.2rem; margin: 0 0 1rem; }
.confirm-summary div { display: flex; flex-direction: column; gap: 0.15rem; }
.confirm-summary dt { font-size: 0.72rem; color: var(--rx-text-faint); text-transform: uppercase; letter-spacing: 0.04em; }
.confirm-summary dd { margin: 0; font-size: 0.9rem; }

.layers-section { padding: 1.3rem 1.4rem; }

.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 60;
}
.confirm-dialog { width: 100%; max-width: 400px; padding: 1.4rem; }
.confirm-dialog h3 { margin: 0 0 0.5rem; font-size: 1.3rem; }
.confirm-dialog p { color: var(--rx-text-dim); font-size: 0.9rem; margin: 0 0 1.1rem; }
.confirm-dialog__actions { display: flex; justify-content: flex-end; gap: 0.6rem; }

@media (max-width: 860px) {
  .rx-layout { grid-template-columns: 1fr; }
  .field-row { grid-template-columns: 1fr; }
  .confirm-summary { grid-template-columns: 1fr; }
}
</style>
