<template>
	<!-- Float mode (default): draggable floating panel -->
	<div
		v-if="mode === 'float'"
		class="pf-float"
		:style="floatStyle"
		@mousedown="bringToFront"
	>
		<div class="pf-header" @mousedown.prevent="startDrag">
			<span>PathFinder: {{ rootDoctype }}</span>
			<button class="pf-close" @click="$emit('close')">&times;</button>
		</div>

		<PathFinderCore
			:root-doctype="rootDoctype"
			:multi-select="multiSelect"
			:meta-fetcher="metaFetcher"
			:visible-tabs="visibleTabs"
			@select="(p) => $emit('select', p)"
		/>

		<div class="pf-footer" @mousedown.prevent="startDrag">
			PathFinder: {{ rootDoctype }}
		</div>
	</div>

	<!-- Embedded mode: no float/drag, fills parent container -->
	<div v-else-if="mode === 'embedded'" class="pf-embedded">
		<PathFinderCore
			:root-doctype="rootDoctype"
			:multi-select="multiSelect"
			:meta-fetcher="metaFetcher"
			:visible-tabs="visibleTabs"
			@select="(p) => $emit('select', p)"
		/>
	</div>
</template>

<script setup>
import { ref, computed } from "vue";
import PathFinderCore from "./PathFinderCore.vue";

defineProps({
	rootDoctype:  { type: String, required: true },
	mode:         { type: String, default: "float", validator: (v) => ["float", "embedded"].includes(v) },
	multiSelect:  { type: Boolean, default: false },
	metaFetcher:  { type: Object, default: null },
	context:      { type: Object, default: null },
	visibleTabs:  { type: Array, default: () => ["tag", "textblock", "field", "portal", "action"] },
});

defineEmits(["close", "select"]);

const x = ref(typeof window !== "undefined" ? window.innerWidth - 560 : 200);
const y = ref(80);
const z = ref(10060);

const floatStyle = computed(() => ({
	left: x.value + "px",
	top: y.value + "px",
	zIndex: z.value,
}));

function bringToFront() { z.value = z.value + 1; }

function startDrag(e) {
	bringToFront();
	const sx = e.clientX, sy = e.clientY;
	const ox = x.value, oy = y.value;
	function onMove(ev) {
		x.value = ox + ev.clientX - sx;
		y.value = Math.max(0, oy + ev.clientY - sy);
	}
	function onUp() {
		document.removeEventListener("mousemove", onMove);
		document.removeEventListener("mouseup", onUp);
	}
	document.addEventListener("mousemove", onMove);
	document.addEventListener("mouseup", onUp);
}
</script>

<style scoped>
.pf-float {
	position: fixed;
	width: 520px;
	max-height: 70vh;
	background: var(--bg-surface, #ffffff);
	border: 1px solid var(--border-color, #d1d8dd);
	border-radius: var(--border-radius, 6px);
	box-shadow: var(--shadow, 0 4px 16px rgba(0,0,0,0.12));
	display: flex;
	flex-direction: column;
}

.pf-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 12px;
	background: var(--bg-header, #126BC4);
	color: var(--text-header, #ffffff);
	font-weight: 600;
	font-size: var(--font-size-base, 13px);
	border-radius: var(--border-radius, 6px) var(--border-radius, 6px) 0 0;
	cursor: move;
	user-select: none;
}

.pf-close {
	background: none;
	border: none;
	color: var(--text-header, #ffffff);
	font-size: 18px;
	cursor: pointer;
	opacity: 0.8;
}
.pf-close:hover { opacity: 1; }

.pf-footer {
	padding: 4px 12px;
	background: var(--portal-header-bg, #f5f7fa);
	font-size: var(--font-size-sm, 11px);
	color: var(--text-muted, #8D949A);
	text-align: center;
	cursor: move;
	user-select: none;
	border-radius: 0 0 var(--border-radius, 6px) var(--border-radius, 6px);
}

.pf-embedded {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
	border: 1px solid var(--border-color, #d1d8dd);
	border-radius: var(--border-radius, 6px);
	background: var(--bg-surface, #ffffff);
	overflow: hidden;
}
</style>
