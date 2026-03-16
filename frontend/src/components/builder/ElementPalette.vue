<template>
  <div>
    <div class="palette-heading">Elements</div>
    <div
      v-for="item in elements"
      :key="item.type"
      class="palette-item"
      draggable="true"
      @dragstart="onDragStart($event, item.type)"
    >
      <span class="palette-icon">{{ item.icon }}</span>
      <span>{{ item.label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
const elements = [
  { type: 'field', icon: '\u25a2', label: 'Editable Field' },
  { type: 'caption', icon: 'T', label: 'Caption' },
]

function onDragStart(e: DragEvent, type: string) {
  if (e.dataTransfer) {
    e.dataTransfer.setData('element-type', type)
    e.dataTransfer.effectAllowed = 'copy'
  }
}
</script>

<style scoped>
.palette-heading {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9ca3af;
  margin-bottom: 8px;
}
.palette-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: grab;
  font-size: 13px;
  color: #374151;
  margin-bottom: 6px;
  transition: background 150ms;
  user-select: none;
}
.palette-item:hover { background: #f3f4f6; }
.palette-item:active { cursor: grabbing; }
.palette-icon {
  font-size: 18px;
  width: 24px;
  text-align: center;
  color: #6b7280;
}
</style>
