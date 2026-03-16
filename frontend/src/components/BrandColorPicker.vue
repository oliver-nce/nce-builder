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
				:style="{ backgroundColor: modelValue || '#3B82F6' }"
			/>
			<span class="text-xs font-mono text-gray-600 truncate">{{ modelValue || '#3B82F6' }}</span>
			<span class="ml-auto text-gray-400 text-[10px]">&#9660;</span>
		</button>

		<!-- Shade strip below trigger -->
		<div v-if="showShades && shades.length" class="flex gap-px mt-1.5 rounded overflow-hidden">
			<div
				v-for="s in shades"
				:key="s.shade"
				class="flex-1 group relative"
			>
				<div class="h-5" :style="{ backgroundColor: s.hex }" />
				<div class="text-center mt-0.5 text-[8px] text-gray-400">{{ s.shade }}</div>
				<div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-10">{{ s.hex }}</div>
			</div>
		</div>

		<!-- Backdrop + Picker panel (centered in viewport) -->
		<Teleport to="body">
			<div v-if="open" class="fixed inset-0 z-40" @click="open = false" />
			<div v-if="open" class="picker-panel">
				<div class="picker-layout">
					<!-- Left: Grid + Hex row -->
					<div class="picker-left">
						<!-- Color grid -->
						<div class="color-grid">
							<!-- Top row: anchor colors -->
							<div
								v-for="(hex, i) in topRow"
								:key="'t-' + i"
								class="grid-cell"
								:class="{ selected: selectedHex === hex }"
								:style="{ backgroundColor: hex }"
								@click="selectFromGrid(hex)"
							/>
							<!-- Spacer -->
							<div v-for="i in 12" :key="'sp-' + i" class="grid-spacer" />
							<!-- Gray row -->
							<template v-for="(hex, i) in grayRow" :key="'g-' + i">
								<div
									v-if="hex === null"
									class="grid-cell no-fill"
								/>
								<div
									v-else
									class="grid-cell"
									:class="{ selected: selectedHex === hex }"
									:style="{ backgroundColor: hex }"
									@click="selectFromGrid(hex)"
								/>
							</template>
							<!-- Color rows -->
							<template v-for="(row, ri) in gridRows" :key="'r-' + ri">
								<div
									v-for="(hex, ci) in row"
									:key="'c-' + ri + '-' + ci"
									class="grid-cell"
									:class="{ selected: selectedHex === hex }"
									:style="{ backgroundColor: hex }"
									@click="selectFromGrid(hex)"
								/>
							</template>
						</div>

						<!-- Hex row -->
						<div class="hex-row">
							<input
								type="text"
								:value="currentHex"
								maxlength="7"
								class="hex-input"
								@input="onHexInput($event)"
							/>
							<button class="hex-btn" title="Copy hex" @click="copyHex">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
							</button>
							<span v-if="showCopied" class="copied-text">Copied</span>
							<button
								v-if="hasEyeDropper"
								class="hex-btn"
								title="Pick from screen"
								@click="useEyeDropper"
							>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 22 1-1h3l9-9"/><path d="M3 21v-3l9-9"/><path d="m15 6 3.4-3.4a2.1 2.1 0 1 1 3 3L18 9l.4.4a2.1 2.1 0 1 1-3 3l-3.8-3.8a2.1 2.1 0 1 1 3-3l.4.4"/></svg>
							</button>
						</div>
					</div>

					<!-- Right: Swatch + Sliders + Apply -->
					<div class="picker-right">
						<div class="picker-top-row">
							<div class="swatch-large" :style="{ backgroundColor: currentHex }" />
						</div>

						<!-- HSV Sliders -->
						<div class="hsv-sliders">
							<div class="hsv-row">
								<label>H</label>
								<input
									type="range"
									class="hue-slider"
									min="0" max="360"
									:value="hsv.h"
									@input="hsv.h = +($event.target as HTMLInputElement).value; selectedHex = ''"
								/>
								<input
									type="number"
									min="0" max="360"
									:value="hsv.h"
									@input="hsv.h = clamp(+($event.target as HTMLInputElement).value, 0, 360); selectedHex = ''"
								/>
							</div>
							<div class="hsv-row">
								<label>S</label>
								<input
									type="range"
									class="sat-slider"
									min="0" max="100"
									:value="hsv.s"
									:style="{ background: satGradient }"
									@input="hsv.s = +($event.target as HTMLInputElement).value; selectedHex = ''"
								/>
								<input
									type="number"
									min="0" max="100"
									:value="hsv.s"
									@input="hsv.s = clamp(+($event.target as HTMLInputElement).value, 0, 100); selectedHex = ''"
								/>
							</div>
							<div class="hsv-row">
								<label>V</label>
								<input
									type="range"
									class="val-slider"
									min="0" max="100"
									:value="hsv.v"
									:style="{ background: valGradient }"
									@input="hsv.v = +($event.target as HTMLInputElement).value; selectedHex = ''"
								/>
								<input
									type="number"
									min="0" max="100"
									:value="hsv.v"
									@input="hsv.v = clamp(+($event.target as HTMLInputElement).value, 0, 100); selectedHex = ''"
								/>
							</div>
						</div>

						<button class="apply-btn" @click="apply">Apply</button>
					</div>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue"
import { generateShades } from "@/utils/color-shades"

const props = withDefaults(defineProps<{
	label: string
	modelValue: string
	showShades?: boolean
}>(), {
	showShades: false,
})

const emit = defineEmits<{
	"update:modelValue": [value: string]
}>()

const open = ref(false)
const showCopied = ref(false)
const selectedHex = ref("")
const hasEyeDropper = typeof window !== "undefined" && "EyeDropper" in window

const hsv = reactive({ h: 210, s: 85, v: 94 })

// Sync HSV from modelValue on open
watch(open, (val) => {
	if (val && props.modelValue) {
		const parsed = hexToHSV(props.modelValue)
		hsv.h = parsed.h
		hsv.s = parsed.s
		hsv.v = parsed.v
		selectedHex.value = ""
	}
})

const shades = computed(() =>
	props.showShades ? generateShades(props.modelValue) : [],
)

// ─── HSV ↔ Hex ─────────────────────────────────────

function hsvToHex(h: number, s: number, v: number): string {
	s /= 100; v /= 100
	const c = v * s
	const x = c * (1 - Math.abs((h / 60) % 2 - 1))
	const m = v - c
	let r: number, g: number, b: number
	if (h < 60) { r = c; g = x; b = 0 }
	else if (h < 120) { r = x; g = c; b = 0 }
	else if (h < 180) { r = 0; g = c; b = x }
	else if (h < 240) { r = 0; g = x; b = c }
	else if (h < 300) { r = x; g = 0; b = c }
	else { r = c; g = 0; b = x }
	const toH = (n: number) => Math.round((n + m) * 255).toString(16).padStart(2, "0")
	return `#${toH(r)}${toH(g)}${toH(b)}`.toUpperCase()
}

function hexToHSV(hex: string) {
	hex = hex.replace("#", "")
	const r = parseInt(hex.substr(0, 2), 16) / 255
	const g = parseInt(hex.substr(2, 2), 16) / 255
	const b = parseInt(hex.substr(4, 2), 16) / 255
	const max = Math.max(r, g, b), min = Math.min(r, g, b)
	const d = max - min
	let h = 0
	const s = max === 0 ? 0 : d / max
	const v = max
	if (d !== 0) {
		if (max === r) h = 60 * ((g - b) / d + (g < b ? 6 : 0))
		else if (max === g) h = 60 * ((b - r) / d + 2)
		else h = 60 * ((r - g) / d + 4)
	}
	if (h < 0) h += 360
	return { h: Math.round(h), s: Math.round(s * 100), v: Math.round(v * 100) }
}

function clamp(v: number, lo: number, hi: number) { return Math.max(lo, Math.min(hi, v)) }

// ─── Computed ───────────────────────────────────────

const currentHex = computed(() => hsvToHex(hsv.h, hsv.s, hsv.v))

const satGradient = computed(
	() => `linear-gradient(to right, #888, hsl(${hsv.h},100%,50%))`,
)
const valGradient = computed(
	() => `linear-gradient(to right, #000, hsl(${hsv.h},100%,50%))`,
)

// ─── Grid data (Apple NSColorPanel) ─────────────────

const topRow = [
	"#FF2600", "#FF9300", "#FFFB00", "#00F900", "#00FDFF", "#0433FF",
	"#FF40FF", "#942192", "#AA7942", "#FFFFFF", "#8E8E93", "#000000",
]

const grayRow: Array<string | null> = [
	null, "#FFFFFF", "#EBEBEB", "#D6D6D6", "#C0C0C0", "#ABABAB",
	"#939393", "#7A7A7A", "#5F5F5F", "#444444", "#232323", "#000000",
]

const gridRows = [
	["#00313F","#001D4C","#12013B","#2E043E","#3D071C","#5C0700","#5B1B01","#573501","#563D01","#666101","#4F5604","#263D0F"],
	["#014D63","#002F7B","#1B0853","#430E59","#56102A","#821100","#7C2A01","#7B4A02","#775801","#8C8700","#707607","#375819"],
	["#026E8E","#0142A9","#2C1276","#61187C","#781A3E","#B61A01","#AD3F00","#A96801","#A77B01","#C4BC01","#9BA60E","#4F7A28"],
	["#018DB4","#0157D7","#371A96","#7B209E","#9A234E","#E22400","#DA5100","#D48601","#D29F01","#F5EC00","#C5D117","#679C33"],
	["#00A2D7","#0062FE","#4E22B3","#992ABD","#BF2E66","#FF4112","#FF6A01","#FEAA00","#FEC802","#FFFC40","#DAEB38","#77BB40"],
	["#00C7FC","#3A8AFC","#5E30EA","#BD39F3","#E53C7A","#FF6251","#FF8548","#FEB440","#FECA3E","#FFF86B","#E4EF65","#97D25F"],
	["#52D4FD","#74A7FF","#864EFE","#D258FE","#EC719F","#FF8D81","#FEA57D","#FFC879","#FFD876","#FFF894","#EAF48F","#B1DE8B"],
	["#93D9F7","#A4C7FF","#B18CFF","#DF90FC","#F4A4C1","#FFB5AE","#FFC4AA","#FED9A8","#FFE4A9","#FEFBB8","#F2F8B8","#CBE8B5"],
	["#D1E6F1","#D4E4FE","#D7CEFD","#F0CAFD","#F9D2E2","#FFDBD9","#FEE2D5","#FFEDD6","#FFF2D4","#FEFCDD","#F7FADB","#E0EDD4"],
]

// ─── Actions ────────────────────────────────────────

function selectFromGrid(hex: string) {
	const parsed = hexToHSV(hex)
	hsv.h = parsed.h
	hsv.s = parsed.s
	hsv.v = parsed.v
	selectedHex.value = hex
}

function onHexInput(e: Event) {
	const val = (e.target as HTMLInputElement).value
	if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
		const parsed = hexToHSV(val)
		hsv.h = parsed.h
		hsv.s = parsed.s
		hsv.v = parsed.v
	}
}

function copyHex() {
	navigator.clipboard.writeText(currentHex.value)
	showCopied.value = true
	setTimeout(() => { showCopied.value = false }, 2000)
}

async function useEyeDropper() {
	try {
		const dropper = new (window as any).EyeDropper()
		const result = await dropper.open()
		const hex = result.sRGBHex.toUpperCase()
		const parsed = hexToHSV(hex)
		hsv.h = parsed.h
		hsv.s = parsed.s
		hsv.v = parsed.v
		selectedHex.value = ""
	} catch { /* user cancelled */ }
}

function apply() {
	emit("update:modelValue", currentHex.value)
	open.value = false
}
</script>

<style scoped>
.picker-panel {
	position: fixed;
	z-index: 50;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: white;
	border: 1px solid rgba(0,0,0,0.12);
	border-radius: 12px;
	padding: 16px;
	box-shadow: 0 2px 24px rgba(0,0,0,0.08), 0 1px 6px rgba(0,0,0,0.05);
	width: max-content;
}

.picker-layout {
	display: flex;
	gap: 20px;
}

.picker-left {
	display: flex;
	flex-direction: column;
}

.picker-right {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-width: 200px;
}

.picker-top-row {
	display: flex;
	gap: 12px;
	margin-bottom: 12px;
}

/* Grid */
.color-grid {
	display: grid;
	grid-template-columns: repeat(12, 20px);
	grid-template-rows: 20px 6px repeat(10, 20px);
	gap: 2px;
}

.grid-cell {
	width: 20px;
	height: 20px;
	border-radius: 3px;
	cursor: pointer;
	border: 1px solid rgba(0,0,0,0.1);
	transition: transform 0.1s, box-shadow 0.1s;
	padding: 0;
}
.grid-cell:hover {
	transform: scale(1.15);
	z-index: 1;
	box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.grid-cell.selected {
	outline: 2px solid #333;
	outline-offset: 1px;
}
.grid-spacer {
	height: 6px;
}
.grid-cell.no-fill {
	background: #fff linear-gradient(
		to bottom left,
		transparent calc(50% - 1px),
		#FF3B30 calc(50% - 1px),
		#FF3B30 calc(50% + 1px),
		transparent calc(50% + 1px)
	) !important;
	cursor: default;
}

/* Swatch */
.swatch-large {
	width: 60px;
	height: 60px;
	border-radius: 8px;
	border: 1px solid rgba(0,0,0,0.15);
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* HSV Sliders */
.hsv-sliders {
	display: flex;
	flex-direction: column;
	gap: 6px;
	margin-bottom: 12px;
}
.hsv-row {
	display: flex;
	align-items: center;
	gap: 8px;
}
.hsv-row label {
	width: 14px;
	font-size: 11px;
	font-weight: 600;
	color: #666;
}
.hsv-row input[type="range"] {
	flex: 1;
	height: 10px;
	border-radius: 5px;
	-webkit-appearance: none;
	cursor: pointer;
}
.hsv-row input[type="range"]::-webkit-slider-thumb {
	-webkit-appearance: none;
	width: 16px;
	height: 16px;
	border-radius: 50%;
	background: white;
	border: 2px solid #666;
	cursor: pointer;
	box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.hsv-row input[type="number"] {
	width: 48px;
	padding: 3px 4px;
	border: 1px solid #ccc;
	border-radius: 4px;
	font-size: 11px;
	text-align: center;
}
.hue-slider {
	background: linear-gradient(to right,
		hsl(0,100%,50%), hsl(60,100%,50%), hsl(120,100%,50%),
		hsl(180,100%,50%), hsl(240,100%,50%), hsl(300,100%,50%), hsl(360,100%,50%));
}

/* Hex row */
.hex-row {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-top: 10px;
}
.hex-input {
	flex: 1;
	padding: 5px 7px;
	border: 1px solid #ccc;
	border-radius: 4px;
	font-family: monospace;
	font-size: 12px;
}
.hex-btn {
	width: 28px;
	height: 28px;
	padding: 0;
	border: none;
	border-radius: 4px;
	background: transparent;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #666;
}
.hex-btn:hover {
	background: #e5e5e5;
	color: #333;
}
.copied-text {
	font-size: 10px;
	color: #10b981;
	white-space: nowrap;
}

/* Apply */
.apply-btn {
	width: 100%;
	padding: 7px 0;
	background: #111;
	color: white;
	border: none;
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	transition: background 0.15s;
}
.apply-btn:hover {
	background: #333;
}
</style>
