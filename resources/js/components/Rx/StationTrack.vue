<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentStep: { type: String, required: true }, // matches Chamber::getCurrentStepAttribute()
})

const stations = [
  { key: 'oven_setup', label: 'Oven Setup' },
  { key: 'before_rx', label: 'Before RX' },
  { key: 'peak_temp', label: 'Peak Temp' },
  { key: 'after_rx', label: 'After RX' },
  { key: 'cooling', label: 'Cooling' },
  { key: 'confirmation', label: 'Confirm' },
]

const currentIndex = computed(() => {
  if (props.currentStep === 'closed') return stations.length
  return stations.findIndex((s) => s.key === props.currentStep)
})

function statusOf(i) {
  if (i < currentIndex.value) return 'done'
  if (i === currentIndex.value) return 'active'
  return 'locked'
}
</script>

<template>
  <div class="station-track">
    <div
      v-for="(s, i) in stations"
      :key="s.key"
      class="station rx-stagger"
      :class="`station--${statusOf(i)}`"
      :style="{ animationDelay: `${i * 70}ms` }"
    >
      <div class="station__connector" v-if="i > 0" :class="{ 'station__connector--done': i <= currentIndex }" />
      <div class="station__light">
        <svg v-if="statusOf(i) === 'done'" width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span v-else class="station__number rx-mono">{{ i + 1 }}</span>
      </div>
      <span class="station__label">{{ s.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.station-track {
  display: flex;
  align-items: flex-start;
  gap: 0;
  padding: 0.25rem 0.25rem 0.75rem;
  overflow-x: auto;
}

.station {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  min-width: 84px;
}

.station__connector {
  position: absolute;
  top: 19px;
  right: 50%;
  width: 100%;
  height: 2px;
  background: var(--rx-locked);
  z-index: 0;
}
.station__connector--done { background: var(--rx-heat); }

.station__light {
  z-index: 1;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--rx-locked);
  color: var(--rx-locked-text);
  background: var(--rx-panel);
  margin-bottom: 0.5rem;
  transition: all 0.25s ease;
}

.station--done .station__light {
  border-color: var(--rx-heat);
  background: var(--rx-heat);
  color: var(--rx-heat-ink);
}

.station--active .station__light {
  border-color: var(--rx-heat);
  color: var(--rx-heat);
  box-shadow: 0 0 0 4px var(--rx-heat-glow);
}

.station__label {
  font-size: 0.78rem;
  font-weight: 600;
  text-align: center;
  color: var(--rx-text-faint);
}
.station--active .station__label,
.station--done .station__label { color: var(--rx-text); }
</style>
