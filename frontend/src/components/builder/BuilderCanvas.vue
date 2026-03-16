<template>
	<div class="canvas-wrapper">
		<div
			class="canvas-grid"
			ref="canvasRef"
			:style="gridStyle"
			@click.self="emit('select', null)"
			@dragover.prevent="onDragOver"
			@drop="onDrop"
		>
			<BuilderElement
				v-for="el in state.elements"
				:key="el.id"
				:element="el"
				:selected="el.id === state.selectedId"
				:grid-config="state.gridConfig"
				@select="emit('select', el.id)"
				@move="(x: number, y: number) => emit('move', el.id, x, y)"
				@resize="(w: number, h: number) => emit('resize', el.id, w, h)"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue"
import type { BuilderState } from "@/composables/useBuilderState"
import BuilderElement from "./BuilderElement.vue"

const props = defineProps<{ state: BuilderState }>()
const emit = defineEmits<{
	select: [id: string | null]
	move: [id: string, x: number, y: number]
	resize: [id: string, w: number, h: number]
	"drop-new": [type: string, x: number, y: number]
}>()

const canvasRef = ref<HTMLDivElement | null>(null)

const gridStyle = computed(() => ({
	display: "grid",
	gridTemplateColumns: `repeat(auto-fill, ${props.state.gridConfig.cellSize}px)`,
	gridAutoRows: `${props.state.gridConfig.cellSize}px`,
	gap: `${props.state.gridConfig.gap}px`,
	padding: "16px",
	minHeight: "100%",
	position: "relative" as const,
	"--cell": `${props.state.gridConfig.cellSize}px`,
	"--gap": `${props.state.gridConfig.gap}px`,
}))

function onDragOver(e: DragEvent) {
	if (e.dataTransfer) e.dataTransfer.dropEffect = "copy"
}

function onDrop(e: DragEvent) {
	const type = e.dataTransfer?.getData("element-type")
	if (!type) return

	const canvas = canvasRef.value
	if (!canvas) return

	const rect = canvas.getBoundingClientRect()
	const relX = e.clientX - rect.left - 16
	const relY = e.clientY - rect.top - 16

	const step = props.state.gridConfig.cellSize + props.state.gridConfig.gap
	const gridX = Math.max(0, Math.floor(relX / step))
	const gridY = Math.max(0, Math.floor(relY / step))

	emit("drop-new", type, gridX, gridY)
}
</script>

<style scoped>
.canvas-wrapper {
	flex: 1;
	overflow-y: auto;
	background: #fafafa;
}

.canvas-grid {
	min-height: 100%;
	background-image:
		repeating-linear-gradient(90deg, #e8e8e8 0px, #e8e8e8 1px, transparent 1px, transparent 100%),
		repeating-linear-gradient(0deg, #e8e8e8 0px, #e8e8e8 1px, transparent 1px, transparent 100%);
	background-size: calc(var(--cell, 30px) + var(--gap, 1px)) calc(var(--cell, 30px) + var(--gap, 1px));
}
</style>
