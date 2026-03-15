<template>
	<div>
		<label class="block text-sm font-medium text-gray-700 mb-1.5">{{ label }}</label>
		<div class="flex items-center gap-2">
			<label
				class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer shrink-0 overflow-hidden relative"
				:style="{ backgroundColor: modelValue || '#ffffff' }"
			>
				<input
					type="color"
					:value="modelValue"
					@input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
					class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
				/>
			</label>
			<input
				type="text"
				:value="modelValue"
				@change="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
				class="w-24 text-sm font-mono border border-gray-200 rounded-md px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
				placeholder="#000000"
			/>
		</div>
		<div v-if="showShades && shades.length" class="flex gap-px mt-2 rounded-md overflow-hidden">
			<div
				v-for="s in shades"
				:key="s.shade"
				class="flex-1 group relative"
			>
				<div class="h-7" :style="{ backgroundColor: s.hex }" />
				<div class="text-center mt-0.5 text-[9px] text-gray-400">{{ s.shade }}</div>
				<div
					class="absolute -top-7 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap transition-opacity"
				>{{ s.hex }}</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from "vue"
import { generateShades } from "@/utils/color-shades"

const props = withDefaults(defineProps<{
	label: string
	modelValue: string
	showShades?: boolean
}>(), {
	showShades: false,
})

defineEmits<{
	"update:modelValue": [value: string]
}>()

const shades = computed(() =>
	props.showShades ? generateShades(props.modelValue) : [],
)
</script>
