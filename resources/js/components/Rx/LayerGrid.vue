<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import ScanField from './ScanField.vue'

const props = defineProps({
  chamberId: { type: Number, required: true },
  layers: { type: Array, required: true },
  productModels: { type: Array, required: true },
  disabled: { type: Boolean, default: false }, // true once RX has started
})

const rxTypes = [
  'ML (2nd RX)', 'ML (3rd RX)', 'ML (4th RX)',
  'RE-RX (2nd RX)', 'RE-RX (3rd RX)', 'RE-RX (4th RX)',
]

const editing = ref(null) // the layer object currently open in the modal
const woCode = ref('')
const woResolving = ref(false)
const woError = ref('')
const scannedAuthPic = ref(null)

const form = useForm({
  product_model_id: null,
  lot_no: '',
  lot_quantity: '',
  rx_type: '',
  remarks: '',
  work_order_id: '',
  scanned_code: '',
})

const selectedModelWeight = computed(() => {
  const m = props.productModels.find((pm) => pm.id === form.product_model_id)
  return m ? Number(m.unit_weight_grams) : null
})

const estimatedTrayWeight = computed(() => {
  if (!selectedModelWeight.value || !form.lot_quantity) return null
  return ((selectedModelWeight.value * Number(form.lot_quantity)) / 1000).toFixed(3)
})

function openLayer(layer) {
  editing.value = layer
  woCode.value = ''
  woError.value = ''
  scannedAuthPic.value = layer.authorized_pic ? { name: layer.authorized_pic.name } : null
  form.clearErrors()
  form.product_model_id = layer.product_model?.id ?? null
  form.lot_no = layer.lot_no ?? ''
  form.lot_quantity = layer.lot_quantity ?? ''
  form.rx_type = layer.rx_type ?? ''
  form.remarks = layer.remarks ?? ''
  form.work_order_id = layer.work_order_id ?? ''
  form.scanned_code = ''
}

function closeModal() {
  editing.value = null
}

async function lookupWorkOrder() {
  if (!woCode.value.trim()) return
  woResolving.value = true
  woError.value = ''
  try {
    const { data } = await axios.post('/rx-monitoring/scan/work-order', {
      work_order_id: woCode.value.trim(),
    })
    const match = props.productModels.find(
      (pm) => pm.model_name.toUpperCase() === String(data.model_name ?? '').toUpperCase()
    )
    if (match) form.product_model_id = match.id
    form.lot_no = data.lot_no ?? form.lot_no
    form.lot_quantity = data.quantity ?? form.lot_quantity
    form.work_order_id = data.work_order_id ?? woCode.value.trim()
  } catch (e) {
    woError.value = e.response?.data?.message ?? 'No work order found for that ID.'
  } finally {
    woResolving.value = false
  }
}

function saveLayer() {
  if (!editing.value) return
  form.put(`/rx-monitoring/chambers/${props.chamberId}/layers/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => closeModal(),
  })
}

function clearLayer() {
  if (!editing.value) return
  router.delete(`/rx-monitoring/chambers/${props.chamberId}/layers/${editing.value.id}`, {
    preserveScroll: true,
    onSuccess: () => closeModal(),
  })
}
</script>

<template>
  <div>
    <div class="layer-grid">
      <button
        v-for="layer in layers"
        :key="layer.id"
        type="button"
        class="layer-tile rx-stagger"
        :class="{ 'layer-tile--filled': layer.is_filled, 'layer-tile--disabled': disabled }"
        :style="{ animationDelay: `${layer.layer_no * 18}ms` }"
        @click="!disabled && openLayer(layer)"
      >
        <span class="layer-tile__no rx-mono">{{ String(layer.layer_no).padStart(2, '0') }}</span>
        <template v-if="layer.is_filled">
          <span class="layer-tile__model">{{ layer.product_model?.model_name }}</span>
          <span class="layer-tile__qty rx-mono">{{ layer.lot_quantity }} pcs</span>
        </template>
        <span v-else class="layer-tile__empty">{{ disabled ? '— empty —' : '+ Add lot' }}</span>
      </button>
    </div>

    <!-- Edit modal -->
    <div v-if="editing" class="layer-modal-overlay" @click.self="closeModal">
      <div class="layer-modal rx-panel-raised">
        <div class="layer-modal__header">
          <h3 class="rx-display">Layer {{ String(editing.layer_no).padStart(2, '0') }}</h3>
          <button type="button" class="layer-modal__close" @click="closeModal" aria-label="Close">✕</button>
        </div>

        <div class="layer-modal__body">
          <div class="field-group">
            <label class="field-label">Scan work order (optional)</label>
            <div class="wo-scan-row">
              <input
                v-model="woCode"
                type="text"
                class="rx-field rx-mono"
                placeholder="Scan work order ID…"
                @keydown.enter.prevent="lookupWorkOrder"
              />
              <button type="button" class="rx-btn" :disabled="woResolving" @click="lookupWorkOrder">
                {{ woResolving ? '…' : 'Fetch' }}
              </button>
            </div>
            <p v-if="woError" class="field-error">{{ woError }}</p>
          </div>

          <div class="field-row">
            <div class="field-group">
              <label class="field-label">Model</label>
              <select v-model="form.product_model_id" class="rx-field">
                <option :value="null" disabled>Select model…</option>
                <option v-for="m in productModels" :key="m.id" :value="m.id">{{ m.model_name }}</option>
              </select>
              <p v-if="form.errors.product_model_id" class="field-error">{{ form.errors.product_model_id }}</p>
            </div>
            <div class="field-group">
              <label class="field-label">Lot no.</label>
              <input v-model="form.lot_no" type="text" class="rx-field" placeholder="Lot number" />
            </div>
          </div>

          <div class="field-row">
            <div class="field-group">
              <label class="field-label">Quantity</label>
              <input v-model="form.lot_quantity" type="number" min="1" class="rx-field rx-mono" placeholder="0" />
              <p v-if="form.errors.lot_quantity" class="field-error">{{ form.errors.lot_quantity }}</p>
            </div>
            <div class="field-group">
              <label class="field-label">Baking category</label>
              <select v-model="form.rx_type" class="rx-field">
                <option value="">—</option>
                <option v-for="t in rxTypes" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
          </div>

          <p v-if="estimatedTrayWeight" class="tray-weight rx-mono">
            Estimated tray weight: {{ estimatedTrayWeight }} kg
          </p>

          <div class="field-group">
            <label class="field-label">Remarks</label>
            <textarea v-model="form.remarks" class="rx-field" rows="2" placeholder="Optional notes" />
          </div>

          <ScanField
            label="Encoded / authorized by"
            :locked-name="scannedAuthPic?.name ?? ''"
            @scanned="(p) => { scannedAuthPic = p; form.scanned_code = p.rawCode }"
          />
          <p v-if="form.errors.scanned_code" class="field-error">{{ form.errors.scanned_code }}</p>
        </div>

        <div class="layer-modal__footer">
          <button type="button" class="rx-btn" @click="clearLayer" v-if="editing.is_filled">Clear layer</button>
          <div class="spacer" />
          <button type="button" class="rx-btn" @click="closeModal">Cancel</button>
          <button
            type="button"
            class="rx-btn rx-btn--primary"
            :disabled="form.processing || !form.product_model_id || !form.lot_quantity || !form.scanned_code"
            @click="saveLayer"
          >
            {{ form.processing ? 'Saving…' : 'Save layer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.layer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 0.6rem;
}

.layer-tile {
  font-family: var(--font-body);
  border: 1px solid var(--rx-border);
  background: var(--rx-bg);
  border-radius: 8px;
  padding: 0.7rem 0.6rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
  cursor: pointer;
  text-align: left;
  min-height: 84px;
  transition: border-color 0.15s ease, transform 0.1s ease;
}
.layer-tile:hover:not(.layer-tile--disabled) { border-color: var(--rx-heat); transform: translateY(-1px); }
.layer-tile--filled { border-color: var(--rx-border); background: var(--rx-panel); }
.layer-tile--disabled { cursor: default; opacity: 0.7; }

.layer-tile__no { font-size: 0.72rem; color: var(--rx-text-faint); }
.layer-tile__model { font-size: 0.85rem; font-weight: 600; color: var(--rx-text); line-height: 1.2; }
.layer-tile__qty { font-size: 0.78rem; color: var(--rx-text-dim); }
.layer-tile__empty { font-size: 0.8rem; color: var(--rx-text-faint); margin-top: auto; }

.layer-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 50;
}
.layer-modal {
  width: 100%;
  max-width: 460px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 1.25rem;
}
.layer-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}
.layer-modal__header h3 { font-size: 1.4rem; margin: 0; }
.layer-modal__close {
  background: none;
  border: none;
  color: var(--rx-text-dim);
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0.25rem;
}

.layer-modal__body { display: flex; flex-direction: column; gap: 0.9rem; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.7rem; }
.field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.field-label { font-size: 0.78rem; font-weight: 600; color: var(--rx-text-dim); }
.field-error { font-size: 0.78rem; color: var(--rx-danger); }

.wo-scan-row { display: flex; gap: 0.5rem; }
.wo-scan-row .rx-field { flex: 1; }

.tray-weight { font-size: 0.85rem; color: var(--rx-cool); }

.layer-modal__footer {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-top: 1.1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--rx-border);
}
.layer-modal__footer .spacer { flex: 1; }

@media (max-width: 420px) {
  .field-row { grid-template-columns: 1fr; }
}
</style>
