<template>
	<div
		ref="rootRef"
		class="builder-element"
		:class="{ 'is-selected': selected }"
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
				<div class="field-mock-input">
					<span class="field-placeholder">{{ element.config.placeholder || '' }}</span>
				</div>
				<span v-if="!element.config.editable" class="readonly-tag">read-only</span>
			</div>
		</div>

		<!-- Resize handles (visible when selected) -->
		<template v-if="selected">
			<div class="handle handle-br" @mousedown.stop="onResizeDown" />
			<div class="handle handle-tl" />
			<div class="handle handle-tr" />
			<div class="handle handle-bl" />
		</template>
	</div>
</template>

<script setup lang="ts">
import { ref } from "vue"
import type { BuilderElement, GridConfig } from "@/composables/useBuilderState"

const props = defineProps<{
	element: BuilderElement
	selected: boolean
	gridConfig: GridConfig
}>()

const emit = defineEmits<{
	select: []
	move: [x: number, y: number]
	resize: [w: number, h: number]
	contextmenu: [event: MouseEvent]
}>()

const rootRef = ref<HTMLElement | null>(null)

function getCellSize(): { cellW: number; cellH: number } {
	const el = rootRef.value
	if (!el?.parentElement) return { cellW: 60, cellH: props.gridConfig.rowHeight }
	const pw = el.parentElement.clientWidth - 32 // subtract canvas padding
	const cellW = (pw - (props.gridConfig.columns - 1) * props.gridConfig.gap) / props.gridConfig.columns
	return { cellW, cellH: props.gridConfig.rowHeight }
}

// ── Move (mousedown on body, not corners) ──
function onMouseDown(e: MouseEvent) {
	if ((e.target as HTMLElement).classList.contains("handle")) return

	const startX = e.clientX
	const startY = e.clientY
	const origX = props.element.x
	const origY = props.element.y
	const { cellW, cellH } = getCellSize()
	const gap = props.gridConfig.gap

	function onMove(ev: MouseEvent) {
		const dx = Math.round((ev.clientX - startX) / (cellW + gap))
		const dy = Math.round((ev.clientY - startY) / (cellH + gap))
		let nx = origX + dx
		let ny = origY + dy
		nx = Math.max(0, Math.min(nx, props.gridConfig.columns - props.element.w))
		ny = Math.max(0, ny)
		emit("move", nx, ny)
	}

	function onUp() {
		document.removeEventListener("mousemove", onMove)
		document.removeEventListener("mouseup", onUp)
	}

	document.addEventListener("mousemove", onMove)
	document.addEventListener("mouseup", onUp)
	e.preventDefault()
}

// ── Resize (bottom-right handle only for PoC) ──
function onResizeDown(e: MouseEvent) {
	const startX = e.clientX
	const startY = e.clientY
	const origW = props.element.w
	const origH = props.element.h
	const { cellW, cellH } = getCellSize()
	const gap = props.gridConfig.gap

	function onMove(ev: MouseEvent) {
		const dw = Math.round((ev.clientX - startX) / (cellW + gap))
		const dh = Math.round((ev.clientY - startY) / (cellH + gap))
		let nw = Math.max(1, origW + dw)
		let nh = Math.max(1, origH + dh)
		nw = Math.min(nw, props.gridConfig.columns - props.element.x)
		emit("resize", nw, nh)
	}

	function onUp() {
		document.removeEventListener("mousemove", onMove)
		document.removeEventListener("mouseup", onUp)
	}

	document.addEventListener("mousemove", onMove)
	document.addEventListener("mouseup", onUp)
	e.preventDefault()
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
.readonly-tag {
	font-size: 9px;
	color: #9ca3af;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-top: 2px;
}

/* Resize handles */
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
.handle-tl { left: -5px; top: -5px; cursor: nwse-resize; }
.handle-tr { right: -5px; top: -5px; cursor: nesw-resize; }
.handle-bl { left: -5px; bottom: -5px; cursor: nesw-resize; }
</style>
