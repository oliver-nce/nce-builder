<template>
	<div>
		<div v-if="!element">
			<div class="panel-heading">Form Settings</div>

			<div class="field-group">
				<label>Grid Square Size</label>
				<div class="range-row">
					<input
						type="range"
						:value="gridConfig.cellSize"
						min="16"
						max="80"
						step="2"
						@input="emit('update-grid', { cellSize: +($event.target as HTMLInputElement).value })"
					/>
					<span class="range-value">{{ gridConfig.cellSize }}px</span>
				</div>
			</div>

			<div class="field-group">
				<label>Grid Gap</label>
				<div class="range-row">
					<input
						type="range"
						:value="gridConfig.gap"
						min="0"
						max="8"
						step="1"
						@input="emit('update-grid', { gap: +($event.target as HTMLInputElement).value })"
					/>
					<span class="range-value">{{ gridConfig.gap }}px</span>
				</div>
			</div>

			<div class="hint-text">
				Drag elements from the palette onto the grid. Click an element to edit it.
			</div>
		</div>
		<template v-else>
			<div class="panel-heading">Properties</div>

			<div class="field-group">
				<label>Label</label>
				<input
					type="text"
					:value="element.config.label"
					@input="emit('update', element.id, { label: ($event.target as HTMLInputElement).value })"
				/>
			</div>

			<div v-if="element.type === 'field'" class="field-group">
				<label>Placeholder</label>
				<input
					type="text"
					:value="element.config.placeholder"
					@input="emit('update', element.id, { placeholder: ($event.target as HTMLInputElement).value })"
				/>
			</div>

			<div v-if="element.type === 'field'" class="field-group">
				<label>
					<input
						type="checkbox"
						:checked="element.config.editable"
						@change="emit('update', element.id, { editable: ($event.target as HTMLInputElement).checked })"
					/>
					Editable
				</label>
			</div>

			<div class="field-group">
				<SwatchPicker
					label="Frame Colour"
					:model-value="element.config.frameColor"
					@update:model-value="v => emit('update', element.id, { frameColor: v })"
					:primary-color="primaryColor"
					:secondary-color="secondaryColor"
				/>
			</div>

			<div class="field-group">
				<label>Data Binding</label>
				<div class="binding-value">
					{{ element.config.fieldPath || 'Not connected' }}
				</div>
				<div class="binding-help">Right-click element to bind data</div>
			</div>

			<button class="delete-btn" @click="onDelete">Delete Element</button>
		</template>
	</div>
</template>

<script setup lang="ts">
import type { BuilderElement, ElementConfig, GridConfig } from "@/composables/useBuilderState"
import SwatchPicker from "@/components/SwatchPicker.vue"

const props = defineProps<{
	element: BuilderElement | null
	gridConfig: GridConfig
	primaryColor: string
	secondaryColor: string
}>()

const emit = defineEmits<{
	update: [id: string, changes: Partial<ElementConfig>]
	"update-grid": [changes: Partial<GridConfig>]
	delete: [id: string]
}>()

function onDelete() {
	if (props.element && window.confirm("Delete this element?")) {
		emit("delete", props.element.id)
	}
}
</script>

<style scoped>
.panel-heading {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #9ca3af;
	margin-bottom: 12px;
}
.field-group {
	margin-bottom: 12px;
}
.field-group label {
	display: block;
	font-size: 12px;
	font-weight: 500;
	color: #374151;
	margin-bottom: 4px;
}
.field-group input[type="text"] {
	width: 100%;
	padding: 6px 8px;
	border: 1px solid #d1d5db;
	border-radius: 4px;
	font-size: 13px;
}
.field-group input[type="checkbox"] {
	margin-right: 6px;
}
.binding-value {
	font-family: monospace;
	font-size: 12px;
	color: #374151;
	background: #f3f4f6;
	padding: 4px 8px;
	border-radius: 4px;
}
.binding-help {
	font-size: 11px;
	color: #9ca3af;
	margin-top: 4px;
}
.delete-btn {
	width: 100%;
	padding: 8px;
	background: #fee2e2;
	color: #dc2626;
	border: 1px solid #fecaca;
	border-radius: 6px;
	font-size: 13px;
	font-weight: 500;
	cursor: pointer;
	margin-top: 24px;
}
.delete-btn:hover {
	background: #fecaca;
}
.range-row {
	display: flex;
	align-items: center;
	gap: 8px;
}
.range-row input[type="range"] {
	flex: 1;
}
.range-value {
	font-size: 12px;
	font-family: monospace;
	color: #6b7280;
	min-width: 36px;
	text-align: right;
}
.hint-text {
	color: #9ca3af;
	font-size: 12px;
	margin-top: 24px;
	line-height: 1.5;
}
</style>
