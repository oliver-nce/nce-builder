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
				<Button variant="outline" @click="openPreview">
					Open Preview ↗
				</Button>
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
						<SwatchPicker
							v-for="c in statusColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
							:primary-color="form.primary_color"
							:secondary-color="form.secondary_color"
						/>
					</div>
				</section>

				<!-- Text colours -->
				<section>
					<h2 class="section-title">Text Colours</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<SwatchPicker
							v-for="c in textColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
							:primary-color="form.primary_color"
							:secondary-color="form.secondary_color"
						/>
					</div>
				</section>

				<!-- Surface colours -->
				<section>
					<h2 class="section-title">Surfaces</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
						<SwatchPicker
							v-for="c in surfaceColors"
							:key="c.key"
							:label="c.label"
							:model-value="form[c.key]"
							@update:model-value="form[c.key] = $event"
							:primary-color="form.primary_color"
							:secondary-color="form.secondary_color"
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
					<h2 class="section-title">Corners</h2>
					<div class="flex gap-3 mb-6">
						<div
							v-for="r in ['none','sm','md','lg','full']"
							:key="r"
							class="w-16 h-16 border-2 cursor-pointer transition-all"
							:class="form.border_radius === r ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300'"
							:style="{ borderRadius: radiusMap[r] }"
							@click="form.border_radius = r"
						>
							<span class="flex items-center justify-center h-full text-xs text-gray-500 font-medium">{{ r }}</span>
						</div>
					</div>

					<h2 class="section-title">Spacing &amp; Shadows</h2>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
						<SwatchPicker
							label="Shadow Color"
							:model-value="form.shadow_color"
							@update:model-value="form.shadow_color = $event"
							:primary-color="form.primary_color"
							:secondary-color="form.secondary_color"
						/>
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
import { ref, reactive, watch, computed, onUnmounted } from "vue"
import { createResource } from "frappe-ui"
import { generateShades, isDark, type ColorShade } from "@/utils/color-shades"
import ColorField from "@/components/ColorField.vue"
import SelectField from "@/components/SelectField.vue"
import SwatchPicker from "@/components/SwatchPicker.vue"

// ─── Preview window ───────────────────────────────────────────────

let previewWin: Window | null = null

function openPreview() {
	if (previewWin && !previewWin.closed) {
		previewWin.focus()
		return
	}
	previewWin = window.open(
		"/nce/preview",
		"nce-preview",
		"width=1200,height=900,scrollbars=yes",
	)
}

const COLOR_VAR_MAP: Record<string, string> = {
	primary_color: "--nce-color-primary",
	secondary_color: "--nce-color-secondary",
	accent_color: "--nce-color-accent",
	success_color: "--nce-color-success",
	info_color: "--nce-color-info",
	warning_color: "--nce-color-warning",
	danger_color: "--nce-color-danger",
	text_color: "--nce-color-text",
	heading_color: "--nce-color-heading",
	muted_color: "--nce-color-muted",
	link_color: "--nce-color-link",
	focus_color: "--nce-color-focus",
	background_color: "--nce-color-bg",
	surface_color: "--nce-color-surface",
	border_color: "--nce-color-border",
	row_alt_color: "--nce-color-row-alt",
}

const BORDER_RADIUS_CSS: Record<string, string> = {
	none: "0", sm: "0.125rem", md: "0.375rem", lg: "0.5rem", full: "9999px",
}

const SHADOW_DEFS: Record<string, Array<[number,number,number,number,number]>> = {
	none: [],
	sm: [[0,1,2,0,0.05]],
	md: [[0,4,6,-1,0.1],[0,2,4,-2,0.1]],
	lg: [[0,10,15,-3,0.1],[0,4,6,-4,0.1]],
	xl: [[0,20,25,-5,0.1],[0,8,10,-6,0.1]],
	"2xl": [[0,25,50,-12,0.25]],
	"3xl": [[0,35,60,-15,0.3]],
}

function hexToRgb(hex: string): string {
	if (!hex || hex.length < 7) return "0,0,0"
	const r = parseInt(hex.slice(1,3), 16)
	const g = parseInt(hex.slice(3,5), 16)
	const b = parseInt(hex.slice(5,7), 16)
	return `${r},${g},${b}`
}

function buildShadow(level: string, color: string): string {
	const defs = SHADOW_DEFS[level] || SHADOW_DEFS.md
	if (!defs.length) return "none"
	const rgb = hexToRgb(color)
	return defs.map(([x,y,b,s,a]) => `${x}px ${y}px ${b}px ${s}px rgba(${rgb},${a})`).join(", ")
}

const TRANSITION_CSS: Record<string, string> = { fast: "150ms", normal: "200ms", slow: "300ms" }
const LINE_HEIGHT_CSS: Record<string, string> = { tight: "1.25", snug: "1.375", normal: "1.5", relaxed: "1.625", loose: "2" }

function computeCSSVariables(): Record<string, string> {
	const vars: Record<string, string> = {}
	for (const [field, cssVar] of Object.entries(COLOR_VAR_MAP)) {
		if (form[field as FormKey]) vars[cssVar] = form[field as FormKey]
	}
	const ff = form.font_family
	if (ff && ff !== "System Default") vars["--nce-font-family"] = `'${ff}', sans-serif`
	else if (ff === "System Default") vars["--nce-font-family"] = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"

	const hf = form.heading_font_family
	if (hf && hf !== "System Default") vars["--nce-font-heading"] = `'${hf}', sans-serif`
	else if (hf === "System Default") vars["--nce-font-heading"] = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"

	if (form.font_size) vars["--nce-font-size"] = form.font_size
	if (form.font_weight_body) vars["--nce-font-weight"] = form.font_weight_body
	if (form.line_height) vars["--nce-line-height"] = LINE_HEIGHT_CSS[form.line_height] || "1.5"
	if (form.border_radius) vars["--nce-border-radius"] = BORDER_RADIUS_CSS[form.border_radius] || "0.375rem"
	if (form.spacing_scale) {
		const m: Record<string, string> = { tight: "0.75rem", normal: "1rem", relaxed: "1.5rem" }
		vars["--nce-spacing-base"] = m[form.spacing_scale] || "1rem"
	}
	if (form.shadow_color) vars["--nce-shadow-color"] = form.shadow_color
	if (form.shadow) vars["--nce-shadow"] = buildShadow(form.shadow, form.shadow_color || "#000000")
	if (form.transition_speed) vars["--nce-transition-speed"] = TRANSITION_CSS[form.transition_speed] || "200ms"
	if (form.sidebar_width) vars["--nce-sidebar-width"] = form.sidebar_width
	if (form.container_max_width) {
		vars["--nce-container-max-width"] = form.container_max_width === "full" ? "100%" : form.container_max_width
	}
	return vars
}

function pushToPreview() {
	if (!previewWin || previewWin.closed) return
	previewWin.postMessage({ type: "nce-theme-update", variables: computeCSSVariables() }, "*")
}

onUnmounted(() => {
	if (previewWin && !previewWin.closed) previewWin.close()
})

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
	"row_alt_color",
	"font_family",
	"heading_font_family",
	"font_size",
	"line_height",
	"font_weight_body",
	"border_radius",
	"spacing_scale",
	"shadow",
	"shadow_color",
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
	row_alt_color: "#F3F4F6",
	font_family: "Inter",
	heading_font_family: "Inter",
	font_size: "14px",
	line_height: "normal",
	font_weight_body: "400",
	border_radius: "md",
	spacing_scale: "normal",
	shadow: "md",
	shadow_color: "#000000",
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
	{ key: "row_alt_color", label: "Alternate Row" },
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
const shadowOptions = ["none", "sm", "md", "lg", "xl", "2xl", "3xl"]
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

// Push live changes to the preview window (debounced)
let pushTimer: ReturnType<typeof setTimeout> | null = null
watch(form, () => {
	if (pushTimer) clearTimeout(pushTimer)
	pushTimer = setTimeout(pushToPreview, 80)
}, { deep: true })
</script>

<style scoped>
.section-title {
	@apply text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3;
}
</style>
