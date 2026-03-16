<template>
	<div>
		<label class="block text-sm font-medium text-gray-700 mb-1.5">{{ label }}</label>

		<!-- Current value display -->
		<div class="flex items-center gap-2 mb-2">
			<div
				class="w-8 h-8 rounded-md border border-gray-200"
				:style="{ backgroundColor: modelValue || '#ffffff' }"
			/>
			<span class="text-xs font-mono text-gray-500">{{ modelValue || 'none' }}</span>
		</div>

		<!-- Swatch rows -->
		<div class="space-y-1.5">
			<div>
				<div class="text-[10px] text-gray-400 mb-0.5">Primary</div>
				<div class="flex gap-px rounded overflow-hidden">
					<button
						v-for="s in primaryShades"
						:key="'p-' + s.shade"
						class="swatch"
						:class="{ 'swatch-active': modelValue === s.hex }"
						:style="{ backgroundColor: s.hex }"
						:title="s.shade + ' — ' + s.hex"
						@click="$emit('update:modelValue', s.hex)"
					/>
				</div>
			</div>
			<div>
				<div class="text-[10px] text-gray-400 mb-0.5">Secondary</div>
				<div class="flex gap-px rounded overflow-hidden">
					<button
						v-for="s in secondaryShades"
						:key="'s-' + s.shade"
						class="swatch"
						:class="{ 'swatch-active': modelValue === s.hex }"
						:style="{ backgroundColor: s.hex }"
						:title="s.shade + ' — ' + s.hex"
						@click="$emit('update:modelValue', s.hex)"
					/>
				</div>
			</div>
			<div>
				<div class="text-[10px] text-gray-400 mb-0.5">Gray</div>
				<div class="flex gap-px rounded overflow-hidden">
					<button
						v-for="s in grayShades"
						:key="'g-' + s.shade"
						class="swatch"
						:class="{ 'swatch-active': modelValue === s.hex }"
						:style="{ backgroundColor: s.hex }"
						:title="s.shade + ' — ' + s.hex"
						@click="$emit('update:modelValue', s.hex)"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from "vue"
import { generateShades } from "@/utils/color-shades"

const props = defineProps<{
	label: string
	modelValue: string
	primaryColor: string
	secondaryColor: string
}>()

defineEmits<{
	"update:modelValue": [value: string]
}>()

const primaryShades = computed(() => generateShades(props.primaryColor))
const secondaryShades = computed(() => generateShades(props.secondaryColor))

const grayShades = [
	{ shade: 50, hex: "#F9FAFB" },
	{ shade: 100, hex: "#F3F4F6" },
	{ shade: 200, hex: "#E5E7EB" },
	{ shade: 300, hex: "#D1D5DB" },
	{ shade: 400, hex: "#9CA3AF" },
	{ shade: 500, hex: "#6B7280" },
	{ shade: 600, hex: "#4B5563" },
	{ shade: 700, hex: "#374151" },
	{ shade: 800, hex: "#1F2937" },
	{ shade: 900, hex: "#111827" },
	{ shade: 950, hex: "#030712" },
]
</script>

<style scoped>
.swatch {
	flex: 1;
	height: 1.5rem;
	cursor: pointer;
	border: 2px solid transparent;
	transition: transform 100ms ease, border-color 100ms ease;
	padding: 0;
	min-width: 0;
}
.swatch:hover {
	transform: scaleY(1.4);
	z-index: 1;
}
.swatch-active {
	border-color: white;
	box-shadow: 0 0 0 2px #111;
	transform: scaleY(1.3);
	z-index: 2;
	border-radius: 2px;
}
</style>
