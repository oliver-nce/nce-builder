<template>
	<div class="relative" ref="wrapper">
		<label class="block text-sm font-medium text-gray-700 mb-1">{{ label }}</label>

		<!-- Compact trigger -->
		<button
			type="button"
			class="flex items-center gap-2 px-2 py-1.5 rounded-md border border-gray-200 hover:border-gray-300 bg-white transition-colors w-full"
			@click="open = !open"
		>
			<span
				class="w-6 h-6 rounded shrink-0 border border-gray-100"
				:style="{ backgroundColor: modelValue || '#ffffff' }"
			/>
			<span class="text-xs font-mono text-gray-600 truncate">{{ modelValue || '—' }}</span>
			<span class="ml-auto text-gray-400 text-[10px]">&#9660;</span>
		</button>

		<!-- Popover (centered in viewport) -->
		<Teleport to="body">
			<div v-if="open" class="fixed inset-0 z-40" @click="open = false" />
			<div
				v-if="open"
				class="fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white border border-gray-200 rounded-lg shadow-xl p-3 w-72"
			>
				<!-- Primary row -->
				<div class="mb-2">
					<div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-1">Primary</div>
					<div class="grid grid-cols-11 gap-[5px]">
						<button
							v-for="s in primaryShades"
							:key="'p-' + s.shade"
							class="swatch"
							:class="{ 'swatch-active': modelValue === s.hex }"
							:style="{ backgroundColor: s.hex }"
							:title="s.shade + ' — ' + s.hex"
							@click="pick(s.hex)"
						/>
					</div>
				</div>

				<!-- Secondary row -->
				<div class="mb-2">
					<div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-1">Secondary</div>
					<div class="grid grid-cols-11 gap-[5px]">
						<button
							v-for="s in secondaryShades"
							:key="'s-' + s.shade"
							class="swatch"
							:class="{ 'swatch-active': modelValue === s.hex }"
							:style="{ backgroundColor: s.hex }"
							:title="s.shade + ' — ' + s.hex"
							@click="pick(s.hex)"
						/>
					</div>
				</div>

				<!-- Gray row -->
				<div>
					<div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-1">Gray</div>
					<div class="grid grid-cols-11 gap-[5px]">
						<button
							v-for="s in grayShades"
							:key="'g-' + s.shade"
							class="swatch"
							:class="{ 'swatch-active': modelValue === s.hex }"
							:style="{ backgroundColor: s.hex }"
							:title="s.shade + ' — ' + s.hex"
							@click="pick(s.hex)"
						/>
					</div>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue"
import { generateShades } from "@/utils/color-shades"

const props = defineProps<{
	label: string
	modelValue: string
	primaryColor: string
	secondaryColor: string
}>()

const emit = defineEmits<{
	"update:modelValue": [value: string]
}>()

const open = ref(false)

function pick(hex: string) {
	emit("update:modelValue", hex)
	open.value = false
}

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
	aspect-ratio: 1;
	width: 100%;
	border-radius: 3px;
	cursor: pointer;
	border: 2px solid transparent;
	padding: 0;
	transition: transform 80ms ease, border-color 80ms ease;
}
.swatch:hover {
	transform: scale(1.25);
	z-index: 1;
	border-color: rgba(255, 255, 255, 0.7);
	box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15);
}
.swatch-active {
	border-color: white;
	box-shadow: 0 0 0 2px #111;
	transform: scale(1.2);
	z-index: 2;
}
</style>
