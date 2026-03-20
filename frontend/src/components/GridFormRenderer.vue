<template>
	<div class="grid-form-wrapper">
		<div
			class="grid-form"
			:style="{
				display: 'grid',
				gridTemplateColumns: `repeat(auto-fill, ${gridConfig.cellSize}px)`,
				gridAutoRows: `${gridConfig.cellSize}px`,
				gap: `${gridConfig.gap}px`,
				padding: '16px',
				'--cell': gridConfig.cellSize + 'px',
				'--gap': gridConfig.gap + 'px',
			}"
		>
			<div
				v-for="el in elements"
				:key="el.id"
				class="gf-element"
				:style="{
					gridColumn: `${el.x + 1} / span ${el.w}`,
					gridRow: `${el.y + 1} / span ${el.h}`,
				}"
			>
				<div v-if="el.type === 'caption'" class="gf-caption">
					{{ el.config.label }}
				</div>

				<div v-else-if="el.type === 'field'" class="gf-field">
					<div class="gf-label">{{ el.config.label }}</div>

					<div v-if="isCheckbox(el.config.fieldType)" class="gf-checkbox-wrap">
						<input
							v-model="formData[el.id]"
							type="checkbox"
							:disabled="isDisabled(el)"
						/>
					</div>

					<textarea
						v-else-if="isTextarea(el.config.fieldType)"
						v-model="formData[el.id]"
						class="gf-textarea"
						:disabled="isDisabled(el)"
						:placeholder="el.config.placeholder || ''"
					/>

					<input
						v-else
						v-model="formData[el.id]"
						:type="getInputType(el.config.fieldType)"
						:step="getStep(el.config.fieldType)"
						class="gf-input"
						:disabled="isDisabled(el)"
						:placeholder="el.config.placeholder || ''"
					/>
				</div>
			</div>
		</div>

		<div v-if="!readOnly" class="gf-submit-row">
			<button class="gf-submit-btn" :disabled="saving" @click="emit('submit', { ...formData })">
				{{ saving ? 'Saving...' : 'Save' }}
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { reactive, watch, onMounted } from "vue"
import type { BuilderElement, GridConfig } from "@/composables/useBuilderState"

const props = withDefaults(defineProps<{
	elements: BuilderElement[]
	gridConfig: GridConfig
	initialValues: Record<string, any>
	readOnly?: boolean
	saving?: boolean
}>(), {
	readOnly: false,
	saving: false,
})

const emit = defineEmits<{
	submit: [formData: Record<string, any>]
}>()

const formData = reactive<Record<string, any>>({})

function seedFormData() {
	for (const el of props.elements) {
		if (el.type === 'field' && el.config.fieldPath) {
			formData[el.id] = props.initialValues[el.id] ?? ''
		}
	}
}

onMounted(seedFormData)
watch(() => props.initialValues, seedFormData, { deep: true })

function getInputType(ft: string): string {
	if (ft === 'Date') return 'date'
	if (['Int', 'Float', 'Currency', 'Percent'].includes(ft)) return 'number'
	return 'text'
}

function getStep(ft: string): string | undefined {
	if (ft === 'Int') return '1'
	if (['Float', 'Currency', 'Percent'].includes(ft)) return 'any'
	return undefined
}

function isTextarea(ft: string): boolean {
	return ['Text', 'Long Text', 'Text Editor'].includes(ft)
}

function isCheckbox(ft: string): boolean {
	return ft === 'Check'
}

function isDisabled(el: BuilderElement): boolean {
	return props.readOnly || el.config.editable === false
}

defineExpose({
	getFormData() { return { ...formData } }
})
</script>

<style scoped>
.grid-form-wrapper { flex: 1; overflow-y: auto; background: #fafafa; }
.grid-form { min-height: 100%; }
.gf-element { display: flex; flex-direction: column; justify-content: center; padding: 6px 8px; }
.gf-caption { font-weight: 600; font-size: 14px; color: #374151; display: flex; align-items: center; height: 100%; }
.gf-field { display: flex; flex-direction: column; justify-content: center; height: 100%; }
.gf-label { font-size: 11px; font-weight: 500; color: #6b7280; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.03em; }
.gf-input { width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; background: #fff; color: #111827; box-sizing: border-box; }
.gf-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
.gf-input:disabled { background: #f3f4f6; color: #6b7280; cursor: not-allowed; }
.gf-textarea { width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; background: #fff; color: #111827; resize: vertical; min-height: 60px; box-sizing: border-box; }
.gf-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
.gf-textarea:disabled { background: #f3f4f6; color: #6b7280; cursor: not-allowed; }
.gf-checkbox-wrap { display: flex; align-items: center; gap: 8px; }
.gf-checkbox-wrap input { width: 16px; height: 16px; }
.gf-submit-row { padding: 16px; display: flex; justify-content: flex-end; }
.gf-submit-btn { padding: 8px 24px; background: #111827; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.gf-submit-btn:hover { background: #374151; }
.gf-submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
