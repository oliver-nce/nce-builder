<template>
	<div
		ref="rootRef"
		class="builder-element"
		:class="{ 'is-selected': selected, 'is-bound': element.config.fieldPath }"
		:style="{
			gridColumn: `${element.x + 1} / span ${element.w}`,
			gridRow: `${element.y + 1} / span ${element.h}`,
		}"
		@mousedown="onMouseDown"
		@click.stop="emit('select')"
		@contextmenu.prevent="emit('contextmenu', $event)"
	>
		<div class="el-content">
			<!-- Caption -->
			<div v-if="element.type === 'caption'" class="caption-text">
				{{ element.config.label }}
			</div>

			<!-- Editable field -->
			<div v-else>
				<div class="field-label">{{ element.config.label }}</div>
				<div class="field-mock-input" :class="{ 'has-preview': previewValue != null }">
					<span v-if="previewValue != null" class="field-preview-value">{{ previewValue }}</span>
					<span v-else class="field-placeholder">{{ element.config.placeholder || '' }}</span>
				</div>
				<div v-if="element.config.fieldPath" class="binding-chip">
					<span class="binding-dot"></span>
					{{ element.config.fieldPath }}
				</div>
				<span v-if="!element.config.editable" class="readonly-tag">read-only</span>
			</div>
		</div>

		<!-- Resize handle (bottom-right only) -->
		<div v-if="selected" class="handle handle-br" @mousedown.stop="onResizeDown" />
	</div>
</template>

<script setup lang="ts">
import { ref } from "vue"
import type { BuilderElement, GridConfig } from "@/composables/useBuilderState"

const props = defineProps<{
	element: BuilderElement
	selected: boolean
	gridConfig: GridConfig
	previewValue?: any
}>()

const emit = defineEmits<{
	select: []
	move: [x: number, y: number]
	resize: [w: number, h: number]
	contextmenu: [event: MouseEvent]
}>()

const rootRef = ref<HTMLElement | null>(null)

function step(): number {
	return props.gridConfig.cellSize + props.gridConfig.gap
}

function onMouseDown(e: MouseEvent) {
	if ((e.target as HTMLElement).classList.contains("handle")) return
	e.preventDefault()
	e.stopPropagation()

	const startMouse = { x: e.clientX, y: e.clientY }
	const orig = { x: props.element.x, y: props.element.y }
	const s = step()

	function onMove(ev: MouseEvent) {
		const dx = Math.round((ev.clientX - startMouse.x) / s)
		const dy = Math.round((ev.clientY - startMouse.y) / s)
		const nx = Math.max(0, orig.x + dx)
		const ny = Math.max(0, orig.y + dy)
		if (nx !== props.element.x || ny !== props.element.y) {
			emit("move", nx, ny)
		}
	}

	function onUp() {
		document.removeEventListener("mousemove", onMove)
		document.removeEventListener("mouseup", onUp)
	}

	document.addEventListener("mousemove", onMove)
	document.addEventListener("mouseup", onUp)
}

function onResizeDown(e: MouseEvent) {
	e.preventDefault()
	e.stopPropagation()

	const startMouse = { x: e.clientX, y: e.clientY }
	const orig = { w: props.element.w, h: props.element.h }
	const s = step()

	function onMove(ev: MouseEvent) {
		const dw = Math.round((ev.clientX - startMouse.x) / s)
		const dh = Math.round((ev.clientY - startMouse.y) / s)
		const nw = Math.max(1, orig.w + dw)
		const nh = Math.max(1, orig.h + dh)
		if (nw !== props.element.w || nh !== props.element.h) {
			emit("resize", nw, nh)
		}
	}

	function onUp() {
		document.removeEventListener("mousemove", onMove)
		document.removeEventListener("mouseup", onUp)
	}

	document.addEventListener("mousemove", onMove)
	document.addEventListener("mouseup", onUp)
}
</script>

<style scoped>
.builder-element {
	position: relative;
	background: var(--nce-color-surface, #ffffff);
	border: 2px solid transparent;
	border-radius: var(--nce-border-radius, 6px);
	box-shadow: var(--nce-shadow, 0 1px 3px rgba(0,0,0,0.1));
	cursor: move;
	user-select: none;
	z-index: 1;
	transition: box-shadow 150ms ease, border-color 150ms ease;
}
.builder-element:hover {
	box-shadow: var(--nce-shadow, 0 1px 3px rgba(0,0,0,0.1)), 0 0 0 1px rgba(59, 130, 246, 0.25);
}
.is-selected {
	border-color: #3b82f6;
	box-shadow: var(--nce-shadow, 0 1px 3px rgba(0,0,0,0.1)), 0 0 0 2px rgba(59, 130, 246, 0.3);
}
.is-bound {
	border-left: 3px solid #059669;
}

.el-content {
	padding: 6px 8px;
	overflow: hidden;
	height: 100%;
	display: flex;
	flex-direction: column;
	justify-content: center;
}

.caption-text {
	font-weight: 600;
	font-size: 13px;
	color: #374151;
}

.field-label {
	font-size: 11px;
	font-weight: 500;
	color: #6b7280;
	margin-bottom: 3px;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}
.field-mock-input {
	background: #f3f4f6;
	border-radius: 4px;
	height: 28px;
	display: flex;
	align-items: center;
	padding: 0 8px;
}
.field-placeholder {
	color: #9ca3af;
	font-size: 12px;
}
.field-mock-input.has-preview {
	background: #fefce8;
	border: 1px solid #fde68a;
}
.field-preview-value {
	color: #92400e;
	font-size: 12px;
	font-weight: 500;
}

.binding-chip {
	font-size: 9px;
	color: #059669;
	background: #ecfdf5;
	border-radius: 3px;
	padding: 1px 6px;
	margin-top: 2px;
	display: inline-flex;
	align-items: center;
	gap: 3px;
	max-width: 100%;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.binding-dot {
	width: 5px;
	height: 5px;
	border-radius: 50%;
	background: #059669;
	flex-shrink: 0;
}

.readonly-tag {
	font-size: 9px;
	color: #9ca3af;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-top: 2px;
}

.handle {
	position: absolute;
	width: 8px;
	height: 8px;
	background: #fff;
	border: 2px solid #3b82f6;
	border-radius: 2px;
	z-index: 3;
}
.handle-br { right: -5px; bottom: -5px; cursor: nwse-resize; }
</style>
