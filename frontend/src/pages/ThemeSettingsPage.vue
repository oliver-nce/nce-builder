<template>
	<div class="max-w-4xl mx-auto p-6">
		<div class="flex items-center justify-between mb-6">
			<h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
				Theme Settings
			</h1>
			<div class="flex gap-2">
				<Button
					variant="outline"
					:loading="regenerating"
					@click="regenerateCSS"
				>
					Regenerate CSS
				</Button>
			</div>
		</div>

		<div v-if="themeDoc.loading" class="text-gray-500 text-sm">
			Loading theme settings...
		</div>

		<div v-else-if="themeDoc.data" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<div>
				<FormKit
					type="form"
					:actions="false"
					:value="formValues"
					@submit="handleSave"
				>
					<FormKit
						type="text"
						name="theme_name"
						label="Theme Name"
						help="Human-readable label, e.g. Corporate Blue"
						validation="required"
					/>

					<div class="grid grid-cols-2 gap-4">
						<FormKit
							type="color"
							name="primary_color"
							label="Primary Color"
						/>
						<FormKit
							type="text"
							name="font_family"
							label="Font Family"
							help="e.g. Inter, Source Sans 3"
						/>
					</div>

					<div class="grid grid-cols-2 gap-4">
						<FormKit
							type="select"
							name="border_radius"
							label="Border Radius"
							:options="['none', 'sm', 'md', 'lg', 'full']"
						/>
						<FormKit
							type="select"
							name="spacing_scale"
							label="Spacing Scale"
							:options="['tight', 'normal', 'relaxed']"
						/>
					</div>

					<FormKit
						type="checkbox"
						name="dark_mode"
						label="Enable Dark Mode"
					/>

					<FormKit
						type="textarea"
						name="custom_css"
						label="Custom CSS"
						help="Additional CSS rules (escape hatch)"
						:rows="4"
					/>

					<div class="mt-4">
						<Button
							variant="solid"
							type="submit"
							:loading="saving"
						>
							Save Theme
						</Button>
					</div>
				</FormKit>
			</div>

			<div>
				<ThemePreview :settings="formValues" />

				<div v-if="themeDoc.data.compiled_css" class="mt-4">
					<h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
						Generated CSS
					</h3>
					<pre class="bg-gray-50 dark:bg-gray-800 border rounded-md p-3 text-xs overflow-x-auto max-h-48">{{ themeDoc.data.compiled_css }}</pre>
				</div>
			</div>
		</div>

		<div v-else class="text-red-600 text-sm">
			Failed to load theme settings. Make sure the NCE Theme Settings DocType exists.
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue"
import { createResource } from "frappe-ui"
import ThemePreview from "@/components/ThemePreview.vue"

const saving = ref(false)
const regenerating = ref(false)

const themeDoc = createResource({
	url: "frappe.client.get",
	params: { doctype: "NCE Theme Settings", name: "NCE Theme Settings" },
	auto: true,
})

const formValues = computed(() => {
	const d = themeDoc.data
	if (!d) return {}
	return {
		theme_name: d.theme_name || "Default",
		primary_color: d.primary_color || "#3B82F6",
		font_family: d.font_family || "Inter",
		border_radius: d.border_radius || "md",
		spacing_scale: d.spacing_scale || "normal",
		dark_mode: d.dark_mode ? true : false,
		custom_css: d.custom_css || "",
	}
})

const saveDoc = createResource({
	url: "frappe.client.save",
	onSuccess() {
		saving.value = false
		themeDoc.reload()
	},
	onError() {
		saving.value = false
	},
})

function handleSave(data: Record<string, any>) {
	saving.value = true
	saveDoc.submit({
		doc: {
			doctype: "NCE Theme Settings",
			name: "NCE Theme Settings",
			...data,
			dark_mode: data.dark_mode ? 1 : 0,
		},
	})
}

const regenerateResource = createResource({
	url: "nce_builder.api.regenerate_theme_css",
	onSuccess() {
		regenerating.value = false
		themeDoc.reload()
	},
	onError() {
		regenerating.value = false
	},
})

function regenerateCSS() {
	regenerating.value = true
	regenerateResource.submit({})
}
</script>
