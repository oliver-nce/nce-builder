<template>
	<div>
		<div v-if="!element">
			<div class="panel-heading">Form Settings</div>
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
				<div v-if="element.config.fieldPath" class="binding-value">
					{{ element.config.fieldPath }}
				</div>
				<div v-else class="binding-value not-connected">
					Not connected
				</div>
				<div v-if="element.config.fieldPath" class="binding-meta">
					<div>Terminal: {{ element.config.terminalDoctype }}</div>
					<div>Type: {{ element.config.fieldType }}</div>
				</div>
				<div v-else class="binding-help">
					<span class="binding-help-link" @click="emit('open-pathfinder', element.id)">Right-click element to bind data</span>
				</div>
				<div v-if="element.config.fieldPath" class="binding-actions">
					<button class="clear-btn" @click="emit('update', element.id, { fieldPath: '', fieldType: '', terminalDoctype: '' })">Clear Binding</button>
					<button class="change-btn" @click="emit('open-pathfinder', element.id)">Change binding...</button>
				</div>
			</div>

			<button class="delete-btn" @click="onDelete">Delete Element</button>
		</template>
	</div>
</template>

<script setup lang="ts">
import type { BuilderElement, ElementConfig } from "@/composables/useBuilderState"
import SwatchPicker from "@/components/SwatchPicker.vue"

const props = defineProps<{
	element: BuilderElement | null
	primaryColor: string
	secondaryColor: string
}>()

const emit = defineEmits<{
	update: [id: string, changes: Partial<ElementConfig>]
	delete: [id: string]
	"open-pathfinder": [id: string]
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
.binding-value:not(.not-connected) {
	background: #eff6ff;
}
.binding-meta {
	font-size: 11px;
	color: #6b7280;
	margin-top: 4px;
}
.binding-meta div {
	line-height: 1.5;
}
.binding-help {
	font-size: 11px;
	color: #9ca3af;
	margin-top: 4px;
}
.binding-help-link {
	cursor: pointer;
	color: #2563eb;
	text-decoration: underline;
}
.binding-actions {
	display: flex;
	gap: 8px;
	margin-top: 6px;
}
.clear-btn {
	font-size: 11px;
	color: #dc2626;
	background: none;
	border: none;
	cursor: pointer;
	padding: 0;
	text-decoration: underline;
}
.change-btn {
	font-size: 11px;
	color: #2563eb;
	background: none;
	border: none;
	cursor: pointer;
	padding: 0;
	text-decoration: underline;
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
.hint-text {
	color: #9ca3af;
	font-size: 12px;
	margin-top: 24px;
	line-height: 1.5;
}
</style>
