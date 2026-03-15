<template>
	<div>
		<label class="block text-sm font-medium text-gray-700 mb-1.5">{{ label }}</label>
		<select
			:value="modelValue"
			@change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
			class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
		>
			<option v-for="opt in options" :key="opt" :value="opt">{{ opt }}</option>
		</select>
	</div>
</template>

<script setup lang="ts">
defineProps<{
	label: string
	options: string[]
	modelValue: string
}>()

defineEmits<{
	"update:modelValue": [value: string]
}>()
</script>
