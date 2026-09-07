<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  label: { type: String, required: true },
  requirePic: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  // Lets a parent show an already-resolved name after a page reload
  // (e.g. loadedBy.name once the chamber has moved past this step).
  lockedName: { type: String, default: '' },
})

const emit = defineEmits(['scanned', 'cleared'])

const code = ref('')
const resolving = ref(false)
const error = ref('')
const resolved = ref(null)
const inputEl = ref(null)

watch(() => props.lockedName, (val) => {
  if (val) resolved.value = { name: val, locked: true }
}, { immediate: true })

async function submitScan() {
  if (!code.value.trim() || props.disabled) return
  resolving.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/rx-monitoring/scan/personnel', {
      code: code.value.trim(),
      require_pic: props.requirePic,
    })
    resolved.value = data
    emit('scanned', { ...data, rawCode: code.value.trim() })
    code.value = ''
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not read that badge. Please scan again.'
  } finally {
    resolving.value = false
  }
}

function reset() {
  if (resolved.value?.locked) return
  resolved.value = null
  code.value = ''
  error.value = ''
  emit('cleared')
}

defineExpose({ reset, focus: () => inputEl.value?.focus() })
</script>

<template>
  <div class="scan-field" :class="{ 'is-disabled': disabled }">
    <label class="scan-field__label">{{ label }}</label>

    <div v-if="resolved" class="scan-field__confirmed rx-scan-confirm-anim">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span class="scan-field__name rx-mono">{{ resolved.name }}</span>
      <button
        v-if="!resolved.locked"
        type="button"
        class="scan-field__rescan"
        :disabled="disabled"
        @click="reset"
      >
        Rescan
      </button>
    </div>

    <div v-else class="scan-field__input-row">
      <svg class="scan-field__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3M8 12h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
      </svg>
      <input
        ref="inputEl"
        v-model="code"
        type="text"
        class="scan-field__input rx-mono"
        :placeholder="requirePic ? 'Scan PIC badge…' : 'Scan operator badge…'"
        :disabled="disabled || resolving"
        autocomplete="off"
        @keydown.enter.prevent="submitScan"
      />
      <button
        type="button"
        class="rx-btn scan-field__btn"
        :disabled="disabled || resolving || !code.trim()"
        @click="submitScan"
      >
        {{ resolving ? '…' : 'Confirm' }}
      </button>
    </div>
    <p v-if="error" class="scan-field__error">{{ error }}</p>
  </div>
</template>

<style scoped>
.scan-field__label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--rx-text-dim);
  margin-bottom: 0.4rem;
}
.scan-field.is-disabled .scan-field__label { color: var(--rx-locked-text); }

.scan-field__input-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid var(--rx-border);
  border-radius: 8px;
  background: var(--rx-bg);
  padding: 0 0.5rem 0 0.75rem;
}
.scan-field__icon { color: var(--rx-text-faint); flex-shrink: 0; }
.scan-field__input {
  flex: 1;
  border: none;
  background: transparent;
  color: var(--rx-text);
  font-size: 1rem;
  padding: 0.7rem 0;
  min-width: 0;
}
.scan-field__input:focus { outline: none; }
.scan-field__input:disabled { opacity: 0.5; }
.scan-field__btn { padding: 0.55rem 1rem; min-height: 40px; white-space: nowrap; }

.scan-field__confirmed {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  border: 1px solid var(--rx-done);
  background: var(--rx-done-glow);
  border-radius: 8px;
  padding: 0.65rem 0.9rem;
  color: var(--rx-done);
}
.scan-field__name { color: var(--rx-text); font-weight: 600; flex: 1; }
.scan-field__rescan {
  border: none;
  background: none;
  color: var(--rx-done);
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.scan-field__error {
  color: var(--rx-danger);
  font-size: 0.82rem;
  margin-top: 0.4rem;
}
</style>
