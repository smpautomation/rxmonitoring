<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ScanField from '@/Components/Rx/ScanField.vue'
import '../../../css/rx-monitoring.css'

const props = defineProps({
  locked: { type: Boolean, default: true },
  unlockedBy: { type: String, default: null },
  areas: { type: Array, required: true },
  ovens: { type: Array, required: true },
  productModels: { type: Array, required: true },
})

const theme = ref(localStorage.getItem('rx-theme') || 'dark')
const activeTab = ref('areas') // 'areas' | 'ovens' | 'models'
const areaFilter = ref('')
const deleteError = ref('')

/* --------------------------------------------------------------- access gate */
const unlocking = ref(false)
const unlockError = ref('')

function handleUnlockScan(scan) {
  unlocking.value = true
  unlockError.value = ''
  router.post('/rx-monitoring/manage/unlock', { scanned_code: scan.rawCode }, {
    preserveScroll: true,
    onError: (errors) => { unlockError.value = errors.scanned_code ?? errors.unlock ?? 'Could not unlock Manage.' },
    onFinish: () => { unlocking.value = false },
  })
}

const showPasswordFallback = ref(false)
const passwordInput = ref('')
const passwordUnlocking = ref(false)
const passwordError = ref('')

function submitPasswordUnlock() {
  if (!passwordInput.value) return
  passwordUnlocking.value = true
  passwordError.value = ''
  router.post('/rx-monitoring/manage/unlock', { password: passwordInput.value }, {
    preserveScroll: true,
    onError: (errors) => { passwordError.value = errors.password ?? errors.unlock ?? 'Incorrect password.' },
    onSuccess: () => { passwordInput.value = '' },
    onFinish: () => { passwordUnlocking.value = false },
  })
}

function lockNow() {
  router.post('/rx-monitoring/manage/lock', {}, { preserveScroll: true })
}

const filteredOvens = computed(() =>
  props.ovens.filter((o) => !areaFilter.value || o.area?.code === areaFilter.value)
)
const filteredModels = computed(() =>
  props.productModels.filter((m) => !areaFilter.value || m.area?.code === areaFilter.value)
)

/* --------------------------------------------------------- generic confirm */
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

/* ---------------------------------------------------------------- areas */
const areaModalOpen = ref(false)
const editingArea = ref(null)
const areaForm = useForm({
  code: '', name: '', chamber_count: 10, layer_count: 20, is_active: true,
})

function openCreateArea() {
  editingArea.value = null
  areaForm.reset()
  areaForm.clearErrors()
  areaForm.chamber_count = 10
  areaForm.layer_count = 20
  areaForm.is_active = true
  areaModalOpen.value = true
}

function openEditArea(area) {
  editingArea.value = area
  areaForm.clearErrors()
  areaForm.code = area.code
  areaForm.name = area.name
  areaForm.chamber_count = area.chamber_count
  areaForm.layer_count = area.layer_count
  areaForm.is_active = area.is_active
  areaModalOpen.value = true
}

function closeAreaModal() { areaModalOpen.value = false }

function saveArea() {
  if (editingArea.value) {
    areaForm.patch(`/rx-monitoring/areas/${editingArea.value.id}`, { preserveScroll: true, onSuccess: closeAreaModal })
  } else {
    areaForm.post('/rx-monitoring/areas', { preserveScroll: true, onSuccess: closeAreaModal })
  }
}

function deleteArea(area) {
  askConfirm(
    'Delete area',
    `Permanently delete ${area.name}? This can't be undone.`,
    () => router.delete(`/rx-monitoring/areas/${area.id}`, {
      preserveScroll: true,
      onError: (errors) => { deleteError.value = errors.area ?? 'Could not delete this area.' },
      onSuccess: () => { deleteError.value = '' },
    }),
    'Delete'
  )
}

/* ---------------------------------------------------------------- ovens */
const ovenModalOpen = ref(false)
const editingOven = ref(null)
const ovenForm = useForm({
  area_id: null, oven_no: '', capacity_kg: '', peak_temp_target_c: '', is_active: true,
})

function openCreateOven() {
  editingOven.value = null
  ovenForm.reset()
  ovenForm.clearErrors()
  ovenForm.is_active = true
  if (areaFilter.value) {
    const a = props.areas.find((a) => a.code === areaFilter.value)
    ovenForm.area_id = a?.id ?? null
  }
  ovenModalOpen.value = true
}

function openEditOven(oven) {
  editingOven.value = oven
  ovenForm.clearErrors()
  ovenForm.area_id = oven.area_id
  ovenForm.oven_no = oven.oven_no
  ovenForm.capacity_kg = oven.capacity_kg ?? ''
  ovenForm.peak_temp_target_c = oven.peak_temp_target_c ?? ''
  ovenForm.is_active = oven.is_active
  ovenModalOpen.value = true
}

function closeOvenModal() { ovenModalOpen.value = false }

function saveOven() {
  if (editingOven.value) {
    ovenForm.patch(`/rx-monitoring/ovens/${editingOven.value.id}`, { preserveScroll: true, onSuccess: closeOvenModal })
  } else {
    ovenForm.post('/rx-monitoring/ovens', { preserveScroll: true, onSuccess: closeOvenModal })
  }
}

function deleteOven(oven) {
  askConfirm(
    'Delete oven',
    `Permanently delete ${oven.oven_no}? This can't be undone.`,
    () => router.delete(`/rx-monitoring/ovens/${oven.id}`, {
      preserveScroll: true,
      onError: (errors) => { deleteError.value = errors.oven ?? 'Could not delete this oven.' },
      onSuccess: () => { deleteError.value = '' },
    }),
    'Delete'
  )
}

/* --------------------------------------------------------------- models */
const modelModalOpen = ref(false)
const editingModel = ref(null)
const scannedCheckedByName = ref('')
const modelForm = useForm({
  area_id: null, model_name: '', unit_weight_grams: '', checked_by_scanned_code: '', is_active: true,
})

function openCreateModel() {
  editingModel.value = null
  modelForm.reset()
  modelForm.clearErrors()
  modelForm.is_active = true
  scannedCheckedByName.value = ''
  if (areaFilter.value) {
    const a = props.areas.find((a) => a.code === areaFilter.value)
    modelForm.area_id = a?.id ?? null
  }
  modelModalOpen.value = true
}

function openEditModel(model) {
  editingModel.value = model
  modelForm.clearErrors()
  modelForm.area_id = model.area_id
  modelForm.model_name = model.model_name
  modelForm.unit_weight_grams = model.unit_weight_grams
  modelForm.checked_by_scanned_code = ''
  modelForm.is_active = model.is_active
  scannedCheckedByName.value = model.checked_by?.name ?? ''
  modelModalOpen.value = true
}

function closeModelModal() { modelModalOpen.value = false }

function saveModel() {
  if (editingModel.value) {
    modelForm.patch(`/rx-monitoring/product-models/${editingModel.value.id}`, { preserveScroll: true, onSuccess: closeModelModal })
  } else {
    modelForm.post('/rx-monitoring/product-models', { preserveScroll: true, onSuccess: closeModelModal })
  }
}

function deleteModel(model) {
  askConfirm(
    'Delete model',
    `Permanently delete ${model.model_name}? This can't be undone.`,
    () => router.delete(`/rx-monitoring/product-models/${model.id}`, {
      preserveScroll: true,
      onError: (errors) => { deleteError.value = errors.model ?? 'Could not delete this model.' },
      onSuccess: () => { deleteError.value = '' },
    }),
    'Delete'
  )
}
</script>

<template>
  <Head title="Manage — RX Monitoring" />

  <div class="rx-app" :data-rx-theme="theme">
    <header class="rx-header">
      <div class="rx-header__brand">
        <a href="/" class="rx-display rx-header__wordmark rx-header__wordmark--link">RX MONITORING</a>
        <span class="rx-header__area rx-mono">MANAGE</span>
      </div>
      <div class="rx-header__right">
        <span v-if="!locked && unlockedBy" class="rx-header__unlocked-by rx-mono">
          Unlocked {{ unlockedBy === 'password' ? 'via password' : 'by ' + unlockedBy }}
        </span>
        <button v-if="!locked" type="button" class="rx-btn" @click="lockNow">🔒 Lock</button>
        <a href="/" class="rx-btn">← Back to monitoring</a>
      </div>
    </header>

    <!-- ============================================================= locked gate -->
    <main v-if="locked" class="manage-main manage-main--gate">
      <section class="rx-panel rx-stagger gate-panel">
        <p class="rx-display gate-panel__title">Manage is locked</p>
        <p class="gate-panel__body">Scan a PIC badge to edit areas, ovens, and models.</p>
        <ScanField label="Scan PIC badge to unlock" require-pic :disabled="unlocking" @scanned="handleUnlockScan" />
        <p v-if="unlockError" class="field-error">{{ unlockError }}</p>

        <button type="button" class="gate-panel__toggle" @click="showPasswordFallback = !showPasswordFallback">
          {{ showPasswordFallback ? 'Use a PIC badge instead' : "Don't have a badge? Use a password instead" }}
        </button>

        <div v-if="showPasswordFallback" class="gate-panel__password">
          <input
            v-model="passwordInput"
            type="password"
            class="rx-field"
            placeholder="Manage password"
            autocomplete="off"
            :disabled="passwordUnlocking"
            @keydown.enter.prevent="submitPasswordUnlock"
          />
          <button
            type="button"
            class="rx-btn rx-btn--primary"
            :disabled="passwordUnlocking || !passwordInput"
            @click="submitPasswordUnlock"
          >
            {{ passwordUnlocking ? 'Checking…' : 'Unlock' }}
          </button>
        </div>
        <p v-if="passwordError" class="field-error">{{ passwordError }}</p>
      </section>
    </main>

    <main v-else class="manage-main">
      <div class="manage-toolbar rx-stagger">
        <div class="tab-switch">
          <button type="button" class="tab-switch__btn" :class="{ 'tab-switch__btn--active': activeTab === 'areas' }" @click="activeTab = 'areas'">
            Areas ({{ areas.length }})
          </button>
          <button type="button" class="tab-switch__btn" :class="{ 'tab-switch__btn--active': activeTab === 'ovens' }" @click="activeTab = 'ovens'">
            Ovens ({{ ovens.length }})
          </button>
          <button type="button" class="tab-switch__btn" :class="{ 'tab-switch__btn--active': activeTab === 'models' }" @click="activeTab = 'models'">
            Models ({{ productModels.length }})
          </button>
        </div>

        <div class="toolbar-right">
          <select v-if="activeTab !== 'areas'" v-model="areaFilter" class="rx-field toolbar-area-select">
            <option value="">All areas</option>
            <option v-for="a in areas" :key="a.code" :value="a.code">{{ a.name }}</option>
          </select>
          <button v-if="activeTab === 'areas'" type="button" class="rx-btn rx-btn--primary" @click="openCreateArea">+ Add area</button>
          <button v-else-if="activeTab === 'ovens'" type="button" class="rx-btn rx-btn--primary" @click="openCreateOven">+ Add oven</button>
          <button v-else type="button" class="rx-btn rx-btn--primary" @click="openCreateModel">+ Add model</button>
        </div>
      </div>

      <p v-if="deleteError" class="delete-error">{{ deleteError }}</p>

      <!-- ================================================================ areas -->
      <section v-if="activeTab === 'areas'" class="rx-panel rx-stagger table-panel" style="animation-delay:60ms">
        <div v-if="!areas.length" class="empty-note">No areas yet.</div>
        <div v-else class="table-scroll">
          <table class="manage-table rx-mono">
            <thead>
              <tr><th>Code</th><th>Name</th><th>Chambers</th><th>Layers</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="a in areas" :key="a.id" :class="{ 'row--inactive': !a.is_active }">
                <td>{{ a.code }}</td>
                <td>{{ a.name }}</td>
                <td>{{ a.chamber_count }}</td>
                <td>{{ a.layer_count }}</td>
                <td><span class="status-chip" :class="a.is_active ? 'status-chip--active' : 'status-chip--inactive'">{{ a.is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="table-actions">
                  <button type="button" class="link-btn" @click="openEditArea(a)">Edit</button>
                  <button type="button" class="link-btn link-btn--danger" @click="deleteArea(a)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ================================================================ ovens -->
      <section v-else-if="activeTab === 'ovens'" class="rx-panel rx-stagger table-panel" style="animation-delay:60ms">
        <div v-if="!filteredOvens.length" class="empty-note">No ovens match this filter.</div>
        <div v-else class="table-scroll">
          <table class="manage-table rx-mono">
            <thead>
              <tr><th>Area</th><th>Oven no.</th><th>Capacity (kg)</th><th>Peak temp (°C)</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="o in filteredOvens" :key="o.id" :class="{ 'row--inactive': !o.is_active }">
                <td>{{ o.area?.code }}</td>
                <td>{{ o.oven_no }}</td>
                <td>{{ o.capacity_kg ?? '—' }}</td>
                <td>{{ o.peak_temp_target_c ?? '—' }}</td>
                <td><span class="status-chip" :class="o.is_active ? 'status-chip--active' : 'status-chip--inactive'">{{ o.is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="table-actions">
                  <button type="button" class="link-btn" @click="openEditOven(o)">Edit</button>
                  <button type="button" class="link-btn link-btn--danger" @click="deleteOven(o)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- =============================================================== models -->
      <section v-else class="rx-panel rx-stagger table-panel" style="animation-delay:60ms">
        <div v-if="!filteredModels.length" class="empty-note">No models match this filter.</div>
        <div v-else class="table-scroll">
          <table class="manage-table rx-mono">
            <thead>
              <tr><th>Area</th><th>Model</th><th>Unit weight (g)</th><th>Checked by</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="m in filteredModels" :key="m.id" :class="{ 'row--inactive': !m.is_active }">
                <td>{{ m.area?.code }}</td>
                <td>{{ m.model_name }}</td>
                <td>{{ Number(m.unit_weight_grams).toFixed(3) }}</td>
                <td>{{ m.checked_by?.name ?? '—' }}</td>
                <td><span class="status-chip" :class="m.is_active ? 'status-chip--active' : 'status-chip--inactive'">{{ m.is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="table-actions">
                  <button type="button" class="link-btn" @click="openEditModel(m)">Edit</button>
                  <button type="button" class="link-btn link-btn--danger" @click="deleteModel(m)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <!-- ===================================================== area modal -->
    <div v-if="areaModalOpen" class="crud-modal-overlay" @click.self="closeAreaModal">
      <div class="crud-modal rx-panel-raised">
        <div class="crud-modal__header">
          <h3 class="rx-display">{{ editingArea ? 'Edit area' : 'Add area' }}</h3>
          <button type="button" class="crud-modal__close" @click="closeAreaModal" aria-label="Close">✕</button>
        </div>

        <div class="crud-modal__body">
          <div class="field-group">
            <label class="field-label">Code</label>
            <input v-model="areaForm.code" type="text" class="rx-field rx-mono" placeholder="e.g. NCP2" />
            <p v-if="areaForm.errors.code" class="field-error">{{ areaForm.errors.code }}</p>
          </div>

          <div class="field-group">
            <label class="field-label">Name</label>
            <input v-model="areaForm.name" type="text" class="rx-field" placeholder="e.g. NCP Line 2" />
            <p v-if="areaForm.errors.name" class="field-error">{{ areaForm.errors.name }}</p>
          </div>

          <div class="field-row">
            <div class="field-group">
              <label class="field-label">Chambers</label>
              <input v-model="areaForm.chamber_count" type="number" min="1" max="50" class="rx-field rx-mono" />
              <p v-if="areaForm.errors.chamber_count" class="field-error">{{ areaForm.errors.chamber_count }}</p>
            </div>
            <div class="field-group">
              <label class="field-label">Layers per chamber</label>
              <input v-model="areaForm.layer_count" type="number" min="1" max="50" class="rx-field rx-mono" />
              <p v-if="areaForm.errors.layer_count" class="field-error">{{ areaForm.errors.layer_count }}</p>
            </div>
          </div>
          <p class="field-hint">Changing these doesn't affect chambers that are already open.</p>

          <label class="checkbox-row">
            <input v-model="areaForm.is_active" type="checkbox" />
            Active (selectable on the main monitoring page)
          </label>
        </div>

        <div class="crud-modal__footer">
          <button type="button" class="rx-btn" @click="closeAreaModal">Cancel</button>
          <button
            type="button"
            class="rx-btn rx-btn--primary"
            :disabled="areaForm.processing || !areaForm.code || !areaForm.name"
            @click="saveArea"
          >
            {{ areaForm.processing ? 'Saving…' : 'Save area' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ===================================================== oven modal -->
    <div v-if="ovenModalOpen" class="crud-modal-overlay" @click.self="closeOvenModal">
      <div class="crud-modal rx-panel-raised">
        <div class="crud-modal__header">
          <h3 class="rx-display">{{ editingOven ? 'Edit oven' : 'Add oven' }}</h3>
          <button type="button" class="crud-modal__close" @click="closeOvenModal" aria-label="Close">✕</button>
        </div>

        <div class="crud-modal__body">
          <div class="field-group">
            <label class="field-label">Area</label>
            <select v-model="ovenForm.area_id" class="rx-field">
              <option :value="null" disabled>Select area…</option>
              <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <p v-if="ovenForm.errors.area_id" class="field-error">{{ ovenForm.errors.area_id }}</p>
          </div>

          <div class="field-group">
            <label class="field-label">Oven no.</label>
            <input v-model="ovenForm.oven_no" type="text" class="rx-field" placeholder="e.g. RX-06 or OV-20 (150 °C)" />
            <p v-if="ovenForm.errors.oven_no" class="field-error">{{ ovenForm.errors.oven_no }}</p>
          </div>

          <div class="field-row">
            <div class="field-group">
              <label class="field-label">Capacity (kg)</label>
              <input v-model="ovenForm.capacity_kg" type="number" min="0" step="0.01" class="rx-field rx-mono" placeholder="Optional" />
              <p v-if="ovenForm.errors.capacity_kg" class="field-error">{{ ovenForm.errors.capacity_kg }}</p>
            </div>
            <div class="field-group">
              <label class="field-label">Peak temp target (°C)</label>
              <input v-model="ovenForm.peak_temp_target_c" type="number" min="0" max="300" class="rx-field rx-mono" placeholder="Optional" />
              <p v-if="ovenForm.errors.peak_temp_target_c" class="field-error">{{ ovenForm.errors.peak_temp_target_c }}</p>
            </div>
          </div>

          <label class="checkbox-row">
            <input v-model="ovenForm.is_active" type="checkbox" />
            Active (selectable for new chambers)
          </label>
        </div>

        <div class="crud-modal__footer">
          <button type="button" class="rx-btn" @click="closeOvenModal">Cancel</button>
          <button type="button" class="rx-btn rx-btn--primary" :disabled="ovenForm.processing || !ovenForm.area_id || !ovenForm.oven_no" @click="saveOven">
            {{ ovenForm.processing ? 'Saving…' : 'Save oven' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ==================================================== model modal -->
    <div v-if="modelModalOpen" class="crud-modal-overlay" @click.self="closeModelModal">
      <div class="crud-modal rx-panel-raised">
        <div class="crud-modal__header">
          <h3 class="rx-display">{{ editingModel ? 'Edit model' : 'Add model' }}</h3>
          <button type="button" class="crud-modal__close" @click="closeModelModal" aria-label="Close">✕</button>
        </div>

        <div class="crud-modal__body">
          <div class="field-group">
            <label class="field-label">Area</label>
            <select v-model="modelForm.area_id" class="rx-field">
              <option :value="null" disabled>Select area…</option>
              <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <p v-if="modelForm.errors.area_id" class="field-error">{{ modelForm.errors.area_id }}</p>
          </div>

          <div class="field-group">
            <label class="field-label">Model name</label>
            <input v-model="modelForm.model_name" type="text" class="rx-field" placeholder="e.g. LAM-100" />
            <p v-if="modelForm.errors.model_name" class="field-error">{{ modelForm.errors.model_name }}</p>
          </div>

          <div class="field-group">
            <label class="field-label">Unit weight (g)</label>
            <input v-model="modelForm.unit_weight_grams" type="number" min="0.001" step="0.001" class="rx-field rx-mono" />
            <p v-if="modelForm.errors.unit_weight_grams" class="field-error">{{ modelForm.errors.unit_weight_grams }}</p>
          </div>

          <div class="field-group">
            <p v-if="scannedCheckedByName" class="checked-by-current rx-mono">Currently verified by: {{ scannedCheckedByName }}</p>
            <ScanField
              label="Scan to (re)verify this weight"
              @scanned="(p) => { modelForm.checked_by_scanned_code = p.rawCode; scannedCheckedByName = p.name }"
            />
          </div>

          <label class="checkbox-row">
            <input v-model="modelForm.is_active" type="checkbox" />
            Active (selectable for new lots)
          </label>
        </div>

        <div class="crud-modal__footer">
          <button type="button" class="rx-btn" @click="closeModelModal">Cancel</button>
          <button
            type="button"
            class="rx-btn rx-btn--primary"
            :disabled="modelForm.processing || !modelForm.area_id || !modelForm.model_name || !modelForm.unit_weight_grams"
            @click="saveModel"
          >
            {{ modelForm.processing ? 'Saving…' : 'Save model' }}
          </button>
        </div>
      </div>
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
.rx-header__wordmark--link { text-decoration: none; color: inherit; }
.rx-header__area {
  font-size: 0.8rem;
  color: var(--rx-cool);
  border: 1px solid var(--rx-cool);
  border-radius: 5px;
  padding: 0.1rem 0.45rem;
}
.rx-header__right { display: flex; align-items: center; gap: 0.7rem; }
.rx-header__unlocked-by { font-size: 0.78rem; color: var(--rx-text-faint); }

.manage-main { max-width: 1200px; margin: 0 auto; padding: 1.25rem 1.5rem 3rem; display: flex; flex-direction: column; gap: 1rem; }
.manage-main--gate { max-width: 420px; padding-top: 4rem; }
.gate-panel { padding: 2rem 1.75rem; text-align: center; display: flex; flex-direction: column; gap: 1rem; align-items: stretch; }
.gate-panel__title { font-size: 1.6rem; margin: 0; }
.gate-panel__body { color: var(--rx-text-dim); font-size: 0.9rem; margin: -0.5rem 0 0; }
.gate-panel__toggle {
  border: none;
  background: none;
  color: var(--rx-text-dim);
  font-size: 0.8rem;
  text-decoration: underline;
  cursor: pointer;
  padding: 0.2rem 0;
}
.gate-panel__password { display: flex; gap: 0.5rem; }
.gate-panel__password .rx-field { flex: 1; }

.manage-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.tab-switch { display: flex; gap: 0.4rem; background: var(--rx-panel); border: 1px solid var(--rx-border); border-radius: 8px; padding: 0.25rem; }
.tab-switch__btn {
  border: none;
  background: none;
  color: var(--rx-text-dim);
  font-weight: 600;
  font-size: 0.88rem;
  padding: 0.5rem 0.9rem;
  border-radius: 6px;
  cursor: pointer;
}
.tab-switch__btn--active { background: var(--rx-heat); color: var(--rx-heat-ink); }
.toolbar-right { display: flex; align-items: center; gap: 0.6rem; }
.toolbar-area-select { min-width: 180px; }

.delete-error { color: var(--rx-danger); font-size: 0.85rem; }

.table-panel { padding: 1.1rem; }
.empty-note { font-size: 0.9rem; color: var(--rx-text-faint); padding: 1.5rem 0; text-align: center; }

.table-scroll { overflow-x: auto; }
.manage-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.manage-table thead th {
  text-align: left;
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid var(--rx-border);
  font-weight: 600;
  color: var(--rx-text-dim);
  white-space: nowrap;
}
.manage-table tbody td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--rx-border-soft); white-space: nowrap; }
.manage-table tbody tr.row--inactive { opacity: 0.5; }

.status-chip { font-size: 0.72rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 12px; }
.status-chip--active { background: var(--rx-done-glow); color: var(--rx-done); }
.status-chip--inactive { background: var(--rx-locked); color: var(--rx-locked-text); }

.table-actions { display: flex; gap: 0.7rem; }
.link-btn { border: none; background: none; color: var(--rx-cool); font-size: 0.82rem; font-weight: 600; cursor: pointer; padding: 0; text-decoration: underline; }
.link-btn--danger { color: var(--rx-danger); }

.crud-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 50;
}
.crud-modal { width: 100%; max-width: 440px; max-height: 90vh; overflow-y: auto; padding: 1.25rem; }
.crud-modal__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.crud-modal__header h3 { font-size: 1.3rem; margin: 0; }
.crud-modal__close { background: none; border: none; color: var(--rx-text-dim); font-size: 1.1rem; cursor: pointer; padding: 0.25rem; }
.crud-modal__body { display: flex; flex-direction: column; gap: 0.9rem; }
.crud-modal__footer { display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: 1.1rem; padding-top: 1rem; border-top: 1px solid var(--rx-border); }

.field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.7rem; }
.field-label { font-size: 0.78rem; font-weight: 600; color: var(--rx-text-dim); }
.field-error { font-size: 0.78rem; color: var(--rx-danger); margin: 0; }
.field-hint { font-size: 0.78rem; color: var(--rx-text-faint); margin: -0.4rem 0 0; }
.checked-by-current { font-size: 0.8rem; color: var(--rx-text-dim); margin: 0 0 0.3rem; }

.checkbox-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: var(--rx-text); cursor: pointer; }

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

@media (max-width: 560px) {
  .field-row { grid-template-columns: 1fr; }
}
</style>
