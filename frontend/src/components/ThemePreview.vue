<template>
	<div class="border rounded-lg overflow-hidden">
		<div class="px-4 py-3 bg-gray-100 dark:bg-gray-800 border-b">
			<h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
				Theme Preview
			</h3>
		</div>

		<div
			ref="previewEl"
			class="p-4 space-y-4"
			:style="previewStyles"
		>
			<FormKit type="text" label="Sample Text Input" value="Hello World" />
			<FormKit
				type="select"
				label="Sample Select"
				:options="['Option A', 'Option B', 'Option C']"
				value="Option A"
			/>
			<FormKit type="checkbox" label="Sample Checkbox" :value="true" />
			<FormKit type="textarea" label="Sample Textarea" value="Lorem ipsum dolor sit amet..." :rows="2" />

			<div class="pt-3 border-t">
				<h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">
					Active CSS Variables
				</h4>
				<div class="grid grid-cols-2 gap-1 text-xs font-mono">
					<div v-for="v in cssVars" :key="v.name" class="flex items-center gap-2">
						<span
							v-if="v.isColor"
							class="w-3 h-3 rounded-sm border border-gray-300 flex-shrink-0"
							:style="{ backgroundColor: v.value }"
						/>
						<span class="text-gray-500">{{ v.name }}:</span>
						<span class="text-gray-800 dark:text-gray-200">{{ v.value }}</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from "vue"

const RADIUS_MAP: Record<string, string> = {
	none: "0",
	sm: "0.125rem",
	md: "0.375rem",
	lg: "0.5rem",
	full: "9999px",
}

const SPACING_MAP: Record<string, string> = {
	tight: "0.75rem",
	normal: "1rem",
	relaxed: "1.5rem",
}

const props = defineProps<{
	settings: {
		primary_color?: string
		font_family?: string
		border_radius?: string
		spacing_scale?: string
		dark_mode?: boolean
	}
}>()

const previewStyles = computed(() => {
	const s = props.settings
	return {
		"--nce-color-primary": s.primary_color || "#3B82F6",
		"--nce-font-family": s.font_family ? `'${s.font_family}', sans-serif` : "'Inter', sans-serif",
		"--nce-border-radius": RADIUS_MAP[s.border_radius || "md"] || "0.375rem",
		"--nce-spacing-base": SPACING_MAP[s.spacing_scale || "normal"] || "1rem",
		fontFamily: s.font_family ? `'${s.font_family}', sans-serif` : undefined,
	}
})

const cssVars = computed(() => {
	const style = previewStyles.value as Record<string, string>
	return Object.entries(style)
		.filter(([k]) => k.startsWith("--"))
		.map(([name, value]) => ({
			name,
			value,
			isColor: name.includes("color"),
		}))
})
</script>
