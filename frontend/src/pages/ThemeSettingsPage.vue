<template>
	<div class="max-w-6xl mx-auto px-6 py-4">
		<!-- Header -->
		<div class="flex items-center justify-between mb-5">
			<div>
				<h1 class="text-xl font-semibold text-gray-900">Theme Settings</h1>
				<p class="text-sm text-gray-500 mt-0.5">
					Configure site-wide colours, typography, and layout
				</p>
			</div>
			<div class="flex gap-2">
				<Button
					variant="outline"
					:loading="regenerating"
					@click="regenerateCSS"
				>
					Regenerate CSS
				</Button>
				<Button
					variant="solid"
					:loading="saving"
					@click="handleSave"
				>
					Save Changes
				</Button>
			</div>
		</div>

		<!-- Loading -->
		<div v-if="themeDoc.loading" class="py-12 text-center text-gray-400 text-sm">
			Loading theme settings…
		</div>

		<!-- Error -->
		<div
			v-else-if="!themeDoc.data"
			class="py-12 text-center text-red-600 text-sm"
		>
			Failed to load theme settings. Make sure the NCE Theme Settings
			DocType exists and you have System Manager permissions.
		</div>

		<!-- Main content -->
		<template v-else>
			<!-- Tab bar -->
			<nav class="flex gap-1 border-b border-gray-200 mb-6">
				<button
					v-for="tab in tabs"
					:key="tab.id"
					class="px-4 pb-2.5 pt-1 text-sm font-medium border-b-2 transition-colors"
					:class="
						activeTab === tab.id
							? 'border-blue-600 text-blue-600'
							: 'border-transparent text-gray-500 hover:text-gray-700'
					"
					@click="activeTab = tab.id"
				>
					{{ tab.label }}
				</button>
			</nav>

			<!-- ==================== COLORS TAB ==================== -->
			<div v-show="activeTab === 'colors'" class="space-y-8">
				<!-- Brand colours -->
				<section>
					<h2 class="section-title">Brand Colours</h2>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<ColorField
							v-for="c in brandColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
							show-shades
						/>
					</div>
				</section>

				<!-- Status colours -->
				<section>
					<h2 class="section-title">Status Colours</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<ColorField
							v-for="c in statusColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
							show-shades
						/>
					</div>
				</section>

				<!-- Text colours -->
				<section>
					<h2 class="section-title">Text Colours</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<ColorField
							v-for="c in textColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
						/>
					</div>
				</section>

				<!-- Surface colours -->
				<section>
					<h2 class="section-title">Surfaces</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<ColorField
							v-for="c in surfaceColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
						/>
					</div>
				</section>
			</div>

			<!-- ==================== TYPOGRAPHY TAB ==================== -->
			<div v-show="activeTab === 'typography'" class="space-y-8">
				<section>
					<h2 class="section-title">Fonts</h2>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<SelectField
							label="Body Font"
							:options="fontOptions"
							v-model="form.font_family"
						/>
						<SelectField
							label="Heading Font"
							:options="fontOptions"
							v-model="form.heading_font_family"
						/>
					</div>

					<!-- Live font preview -->
					<div class="mt-4 rounded-lg border border-gray-200 p-5 bg-white">
						<p
							class="text-2xl font-semibold mb-1"
							:style="{ fontFamily: fontCSS(form.heading_font_family) }"
						>
							The quick brown fox jumps over the lazy dog
						</p>
						<p
							class="text-base text-gray-600"
							:style="{
								fontFamily: fontCSS(form.font_family),
								fontSize: form.font_size || '14px',
								lineHeight: lineHeightCSS,
								fontWeight: form.font_weight_body || '400',
							}"
						>
							Pack my box with five dozen liquor jugs. How vexingly quick
							daft zebras jump! The five boxing wizards jump quickly.
						</p>
					</div>
				</section>

				<section>
					<h2 class="section-title">Size &amp; Weight</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<SelectField
							label="Base Font Size"
							:options="sizeOptions"
							v-model="form.font_size"
						/>
						<SelectField
							label="Line Height"
							:options="lineHeightOptions"
							v-model="form.line_height"
						/>
						<SelectField
							label="Body Weight"
							:options="weightOptions"
							v-model="form.font_weight_body"
						/>
					</div>
				</section>
			</div>

			<!-- ==================== LAYOUT TAB ==================== -->
			<div v-show="activeTab === 'layout'" class="space-y-8">
				<section>
					<h2 class="section-title">Corners &amp; Spacing</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<SelectField
							label="Border Radius"
							:options="radiusOptions"
							v-model="form.border_radius"
						/>
						<SelectField
							label="Spacing Scale"
							:options="spacingOptions"
							v-model="form.spacing_scale"
						/>
						<SelectField
							label="Shadow Depth"
							:options="shadowOptions"
							v-model="form.shadow"
						/>
					</div>

					<!-- Radius preview -->
					<div class="mt-4 flex gap-4">
						<div
							v-for="r in ['none','sm','md','lg','full']"
							:key="r"
							class="w-16 h-16 border-2 transition-all"
							:class="form.border_radius === r ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50'"
							:style="{ borderRadius: radiusMap[r] }"
							@click="form.border_radius = r"
						>
							<span class="flex items-center justify-center h-full text-xs text-gray-500">{{ r }}</span>
						</div>
					</div>
				</section>

				<section>
					<h2 class="section-title">Dimensions</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<SelectField
							label="Sidebar Width"
							:options="sidebarOptions"
							v-model="form.sidebar_width"
						/>
						<SelectField
							label="Container Max Width"
							:options="containerOptions"
							v-model="form.container_max_width"
						/>
						<SelectField
							label="Transition Speed"
							:options="transitionOptions"
							v-model="form.transition_speed"
						/>
					</div>
				</section>

				<section>
					<div class="flex items-center gap-3">
						<input
							type="checkbox"
							id="dark_mode"
							v-model="form.dark_mode"
							class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
						/>
						<label for="dark_mode" class="text-sm font-medium text-gray-700">
							Enable Dark Mode
						</label>
					</div>
				</section>
			</div>

			<!-- ==================== ADVANCED TAB ==================== -->
			<div v-show="activeTab === 'advanced'" class="space-y-8">
				<section>
					<h2 class="section-title">Custom CSS</h2>
					<textarea
						v-model="form.custom_css"
						class="w-full font-mono text-sm border border-gray-200 rounded-lg p-3 bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
						rows="8"
						placeholder="/* Your custom CSS rules */"
					/>
				</section>

				<section>
					<h2 class="section-title">Extra CSS Variables (JSON)</h2>
					<textarea
						v-model="form.tailwind_overrides"
						class="w-full font-mono text-sm border border-gray-200 rounded-lg p-3 bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
						rows="5"
						placeholder='{ "my-var": "value" }'
					/>
					<p class="text-xs text-gray-400 mt-1">
						Each key becomes <code>--nce-{key}</code> in the stylesheet
					</p>
				</section>

				<section>
					<h2 class="section-title">Generated CSS</h2>
					<pre
						v-if="themeDoc.data?.compiled_css"
						class="text-xs font-mono bg-gray-900 text-green-400 rounded-lg p-4 overflow-x-auto max-h-64"
					>{{ themeDoc.data.compiled_css }}</pre>
					<p v-else class="text-sm text-gray-400">
						Save the theme to generate CSS output
					</p>
				</section>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from "vue"
import { createResource } from "frappe-ui"
import { generateShades, isDark, type ColorShade } from "@/utils/color-shades"

// ─── Sub-components defined inline ────────────────────────────────

const ColorField = {
	name: "ColorField",
	props: {
		label: String,
		modelValue: String,
		showShades: Boolean,
	},
	emits: ["update:modelValue"],
	setup(props: any, { emit }: any) {
		const shades = computed(() =>
			props.showShades ? generateShades(props.modelValue) : [],
		)
		return { props, emit, shades, isDark }
	},
	template: `
		<div>
			<label class="block text-sm font-medium text-gray-700 mb-1.5">{{ props.label }}</label>
			<div class="flex items-center gap-2">
				<label
					class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer shrink-0 overflow-hidden relative"
					:style="{ backgroundColor: props.modelValue || '#ffffff' }"
				>
					<input
						type="color"
						:value="props.modelValue"
						@input="emit('update:modelValue', $event.target.value)"
						class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
					/>
				</label>
				<input
					type="text"
					:value="props.modelValue"
					@change="emit('update:modelValue', $event.target.value)"
					class="w-24 text-sm font-mono border border-gray-200 rounded-md px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
					placeholder="#000000"
				/>
			</div>
			<div v-if="shades.length" class="flex gap-px mt-2 rounded-md overflow-hidden">
				<div
					v-for="s in shades"
					:key="s.shade"
					class="flex-1 group relative"
				>
					<div
						class="h-7"
						:style="{ backgroundColor: s.hex }"
					/>
					<div
						class="text-center mt-0.5"
						:class="'text-[9px] text-gray-400'"
					>{{ s.shade }}</div>
					<div
						class="absolute -top-7 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap transition-opacity"
					>{{ s.hex }}</div>
				</div>
			</div>
		</div>
	`,
}

const SelectField = {
	name: "SelectField",
	props: {
		label: String,
		options: Array,
		modelValue: String,
	},
	emits: ["update:modelValue"],
	template: `
		<div>
			<label class="block text-sm font-medium text-gray-700 mb-1.5">{{ label }}</label>
			<select
				:value="modelValue"
				@change="$emit('update:modelValue', $event.target.value)"
				class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
			>
				<option v-for="opt in options" :key="opt" :value="opt">{{ opt }}</option>
			</select>
		</div>
	`,
}

// ─── State ────────────────────────────────────────────────────────

const activeTab = ref("colors")

const tabs = [
	{ id: "colors", label: "Colours" },
	{ id: "typography", label: "Typography" },
	{ id: "layout", label: "Layout" },
	{ id: "advanced", label: "Advanced" },
]

const ALL_FIELDS = [
	"theme_name",
	"primary_color",
	"secondary_color",
	"accent_color",
	"success_color",
	"info_color",
	"warning_color",
	"danger_color",
	"text_color",
	"heading_color",
	"muted_color",
	"link_color",
	"focus_color",
	"background_color",
	"surface_color",
	"border_color",
	"font_family",
	"heading_font_family",
	"font_size",
	"line_height",
	"font_weight_body",
	"border_radius",
	"spacing_scale",
	"shadow",
	"sidebar_width",
	"container_max_width",
	"transition_speed",
	"dark_mode",
	"custom_css",
	"tailwind_overrides",
] as const

type FormKey = (typeof ALL_FIELDS)[number]

const DEFAULTS: Record<FormKey, any> = {
	theme_name: "Default",
	primary_color: "#3B82F6",
	secondary_color: "#10B981",
	accent_color: "#8B5CF6",
	success_color: "#10B981",
	info_color: "#3B82F6",
	warning_color: "#F59E0B",
	danger_color: "#EF4444",
	text_color: "#1F2937",
	heading_color: "#111827",
	muted_color: "#6B7280",
	link_color: "#3B82F6",
	focus_color: "#3B82F6",
	background_color: "#FFFFFF",
	surface_color: "#F9FAFB",
	border_color: "#E5E7EB",
	font_family: "Inter",
	heading_font_family: "Inter",
	font_size: "14px",
	line_height: "normal",
	font_weight_body: "400",
	border_radius: "md",
	spacing_scale: "normal",
	shadow: "md",
	sidebar_width: "240px",
	container_max_width: "1280px",
	transition_speed: "normal",
	dark_mode: false,
	custom_css: "",
	tailwind_overrides: "",
}

const form = reactive<Record<FormKey, any>>({ ...DEFAULTS })

// ─── Colour group definitions ─────────────────────────────────────

const brandColors = [
	{ key: "primary_color", label: "Primary" },
	{ key: "secondary_color", label: "Secondary" },
]

const statusColors = [
	{ key: "accent_color", label: "Accent" },
	{ key: "success_color", label: "Success" },
	{ key: "info_color", label: "Info" },
	{ key: "warning_color", label: "Warning" },
	{ key: "danger_color", label: "Danger" },
]

const textColors = [
	{ key: "text_color", label: "Body Text" },
	{ key: "heading_color", label: "Heading" },
	{ key: "muted_color", label: "Muted" },
	{ key: "link_color", label: "Links" },
	{ key: "focus_color", label: "Focus Ring" },
]

const surfaceColors = [
	{ key: "background_color", label: "Page Background" },
	{ key: "surface_color", label: "Card / Panel" },
	{ key: "border_color", label: "Borders" },
]

// ─── Select options ───────────────────────────────────────────────

const fontOptions = [
	"Inter",
	"Source Sans 3",
	"Open Sans",
	"Roboto",
	"Lato",
	"Poppins",
	"Nunito",
	"System Default",
]
const sizeOptions = ["12px", "13px", "14px", "15px", "16px", "18px"]
const lineHeightOptions = ["tight", "snug", "normal", "relaxed", "loose"]
const weightOptions = ["300", "400", "500", "600"]
const radiusOptions = ["none", "sm", "md", "lg", "full"]
const spacingOptions = ["tight", "normal", "relaxed"]
const shadowOptions = ["none", "sm", "md", "lg", "xl"]
const sidebarOptions = ["200px", "220px", "240px", "260px", "280px"]
const containerOptions = ["960px", "1024px", "1152px", "1280px", "1440px", "full"]
const transitionOptions = ["fast", "normal", "slow"]

const radiusMap: Record<string, string> = {
	none: "0",
	sm: "0.125rem",
	md: "0.375rem",
	lg: "0.5rem",
	full: "9999px",
}

const lineHeightMap: Record<string, string> = {
	tight: "1.25",
	snug: "1.375",
	normal: "1.5",
	relaxed: "1.625",
	loose: "2",
}

// ─── Computed helpers ─────────────────────────────────────────────

function fontCSS(name: string): string {
	if (!name || name === "System Default") {
		return "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
	}
	return `'${name}', sans-serif`
}

const lineHeightCSS = computed(
	() => lineHeightMap[form.line_height] || "1.5",
)

// ─── Data fetching ────────────────────────────────────────────────

const saving = ref(false)
const regenerating = ref(false)

const themeDoc = createResource({
	url: "frappe.client.get",
	params: { doctype: "NCE Theme Settings", name: "NCE Theme Settings" },
	auto: true,
	onSuccess(data: any) {
		if (!data) return
		for (const key of ALL_FIELDS) {
			const val = data[key]
			if (key === "dark_mode") {
				form[key] = !!val
			} else if (val !== undefined && val !== null) {
				form[key] = val
			}
		}
	},
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

function handleSave() {
	saving.value = true
	const doc: Record<string, any> = {
		doctype: "NCE Theme Settings",
		name: "NCE Theme Settings",
	}
	for (const key of ALL_FIELDS) {
		doc[key] = key === "dark_mode" ? (form[key] ? 1 : 0) : form[key]
	}
	saveDoc.submit({ doc })
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

<style scoped>
.section-title {
	@apply text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3;
}
</style>
