<template>
	<div
		class="pf-dialog"
		:style="{ top: top + 'px', left: left + 'px', zIndex: z }"
		@mousedown="bringToFront"
	>
		<div class="pf-dialog-header" @mousedown.prevent="startDrag">
			<span>{{ field.label }}</span>
			<button class="pf-close" @click="$emit('close')">&times;</button>
		</div>

		<div class="pf-tabs">
			<button
				v-for="tab in activeTabs"
				:key="tab.key"
				class="pf-tab-btn"
				:class="{ 'pf-tab-active': activeTab === tab.key }"
				@click="activeTab = tab.key"
			>{{ tab.label }}</button>
		</div>

		<div class="pf-dialog-body">

			<!-- Jinja Tag -->
			<div v-if="activeTab === 'tag'" class="pf-panel">
				<div class="pf-lbl">Field</div>
				<div class="pf-val">{{ field.label }} <span class="pf-muted">({{ field.fieldname }})</span></div>

				<div class="pf-lbl">Path</div>
				<div class="pf-val pf-path-text">{{ path }}</div>

				<div class="pf-lbl">Fallback Value</div>
				<input v-model="fallback" type="text" class="pf-input" placeholder="e.g. Student (leave empty for none)">

				<div class="pf-lbl">Tag</div>
				<pre class="pf-pre" @click="selectPre">{{ displayTag }}</pre>

				<div class="pf-row">
					<label class="pf-check-label">
						<input v-model="isHtml" type="checkbox"> Is this HTML?
					</label>
					<span class="pf-btn-group">
						<button class="pf-btn pf-btn-default" @click="copyText(displayTag)">Copy</button>
						<button class="pf-btn pf-btn-primary" @click="insertAtCursor">Insert</button>
					</span>
				</div>
			</div>

			<!-- Text Block ($resolve) -->
			<div v-if="activeTab === 'textblock'" class="pf-panel">
				<div class="pf-lbl">Field</div>
				<div class="pf-val">{{ field.label }} <span class="pf-muted">({{ field.fieldname }})</span></div>

				<div class="pf-lbl">Path</div>
				<div class="pf-val pf-path-text">{{ path }}</div>

				<div class="pf-lbl">$resolve() Reference</div>
				<pre class="pf-pre" @click="selectPre">{{ resolveRef }}</pre>

				<div class="pf-row">
					<span></span>
					<span class="pf-btn-group">
						<button class="pf-btn pf-btn-default" @click="copyText(resolveRef)">Copy</button>
						<button class="pf-btn pf-btn-primary" @click="emitSelect('textblock', { resolve_key: resolveKey, text: resolveRef })">Insert</button>
					</span>
				</div>
			</div>

			<!-- Field Path -->
			<div v-if="activeTab === 'field'" class="pf-panel">
				<div class="pf-lbl">Field</div>
				<div class="pf-val">{{ field.label }} <span class="pf-muted">({{ field.fieldname }})</span></div>

				<div class="pf-lbl">Path</div>
				<div class="pf-val pf-path-text">{{ path }}</div>

				<div class="pf-lbl">Resolve Key</div>
				<code class="pf-resolve-key">{{ resolveKey }}</code>

				<div class="pf-lbl">FormKit Schema</div>
				<pre class="pf-pre">{{ formkitSchemaJSON }}</pre>

				<div class="pf-row">
					<span></span>
					<button class="pf-btn pf-btn-primary" @click="emitSelect('field', pathObject)">Use as Form Field</button>
				</div>
			</div>

			<!-- Related List (Portal) -->
			<div v-if="activeTab === 'portal'" class="pf-panel">
				<div class="pf-lbl">Target DocType</div>
				<div class="pf-val">{{ portalObject?.terminal_doctype || '—' }}</div>

				<div class="pf-lbl">Link Field in Target</div>
				<div class="pf-val">{{ portalObject?.link_field_in_target || '—' }}</div>

				<div class="pf-lbl">Path</div>
				<div class="pf-val pf-path-text">{{ path }}</div>

				<div class="pf-row">
					<span></span>
					<button class="pf-btn pf-btn-primary" @click="emitSelect('portal', portalObject)">Use as Portal</button>
				</div>
			</div>

			<!-- Button Action -->
			<div v-if="activeTab === 'action'" class="pf-panel">
				<div class="pf-lbl">Description</div>
				<div class="pf-val">{{ actionObject?.description || '—' }}</div>

				<div class="pf-lbl">Action Type</div>
				<div class="pf-val">{{ actionObject?.action_hint || '—' }}</div>

				<div class="pf-lbl">Path</div>
				<div class="pf-val pf-path-text">{{ path }}</div>

				<div class="pf-row">
					<span></span>
					<button class="pf-btn pf-btn-primary" @click="emitSelect('action', actionObject)">Use for Button</button>
				</div>
			</div>

		</div>
	</div>
</template>

<script setup>
import { ref, computed } from "vue";

const TAB_DEFS = [
	{ key: "tag",       label: "Jinja Tag" },
	{ key: "textblock", label: "Text Block" },
	{ key: "field",     label: "Field Path" },
	{ key: "portal",    label: "Related List" },
	{ key: "action",    label: "Button Action" },
];

const props = defineProps({
	field:        { type: Object, required: true },
	baseTag:      { type: String, required: true },
	path:         { type: String, default: "" },
	pathObject:   { type: Object, default: null },
	portalObject: { type: Object, default: null },
	actionObject: { type: Object, default: null },
	resolveKey:   { type: String, default: "" },
	applyFilters: { type: Function, required: true },
	initTop:      { type: Number, default: 100 },
	initLeft:     { type: Number, default: 160 },
	visibleTabs:  { type: Array, default: () => ["tag", "textblock", "field", "portal", "action"] },
});

const emit = defineEmits(["close", "select"]);

const fallback = ref("");
const isHtml = ref(false);
const top = ref(props.initTop);
const left = ref(props.initLeft);
const z = ref(100050);

const activeTabs = computed(() =>
	TAB_DEFS.filter((t) => props.visibleTabs.includes(t.key))
);
const activeTab = ref(props.visibleTabs[0] || "tag");

const displayTag = computed(() =>
	props.applyFilters(props.baseTag, fallback.value, isHtml.value)
);

const resolveRef = computed(() =>
	props.resolveKey ? `$resolve('${props.resolveKey}')` : ""
);

const formkitSchemaJSON = computed(() =>
	props.pathObject?.formkit_schema
		? JSON.stringify(props.pathObject.formkit_schema, null, 2)
		: "\u2014"
);

function bringToFront() { z.value = z.value + 1; }

function startDrag(e) {
	bringToFront();
	const sx = e.clientX, sy = e.clientY;
	const ot = top.value, ol = left.value;
	function onMove(ev) {
		top.value = Math.max(0, ot + ev.clientY - sy);
		left.value = ol + ev.clientX - sx;
	}
	function onUp() {
		document.removeEventListener("mousemove", onMove);
		document.removeEventListener("mouseup", onUp);
	}
	document.addEventListener("mousemove", onMove);
	document.addEventListener("mouseup", onUp);
}

function selectPre(e) {
	const range = document.createRange();
	range.selectNodeContents(e.target);
	const sel = window.getSelection();
	sel.removeAllRanges();
	sel.addRange(range);
}

function copyText(text) {
	if (navigator.clipboard) {
		navigator.clipboard.writeText(text).then(() => {
			if (window.frappe?.show_alert) {
				frappe.show_alert({ message: "Copied", indicator: "green" });
			}
		});
	}
}

function insertAtCursor() {
	if (window.frappe?.show_alert) {
		frappe.show_alert({ message: "Use Copy and paste into your editor", indicator: "orange" });
	}
}

function emitSelect(mode, data) {
	emit("select", { mode, data });
}
</script>

<style scoped>
.pf-dialog {
	position: fixed;
	width: 420px;
	background: #fff;
	border: 1px solid #b0b8c0;
	border-radius: 6px;
	box-shadow: 0 4px 16px rgba(0,0,0,0.18);
	font-family: Arial, sans-serif;
	display: flex;
	flex-direction: column;
	max-height: 80vh;
}

.pf-dialog-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 12px;
	background: #126BC4;
	color: #fff;
	font-weight: 600;
	font-size: 13px;
	border-radius: 6px 6px 0 0;
	cursor: move;
	user-select: none;
}

.pf-close {
	background: none;
	border: none;
	color: #fff;
	font-size: 18px;
	cursor: pointer;
	opacity: 0.8;
}
.pf-close:hover { opacity: 1; }

.pf-tabs {
	display: flex;
	border-bottom: 1px solid #d1d8dd;
	background: #f8f9fa;
	overflow-x: auto;
}

.pf-tab-btn {
	padding: 6px 12px;
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	cursor: pointer;
	font-size: 11px;
	color: #666;
	white-space: nowrap;
}
.pf-tab-btn:hover { color: #126BC4; }
.pf-tab-btn.pf-tab-active {
	color: #126BC4;
	border-bottom-color: #126BC4;
	font-weight: 600;
}

.pf-dialog-body {
	padding: 12px;
	overflow-y: auto;
}

.pf-panel { display: flex; flex-direction: column; gap: 2px; }

.pf-lbl {
	font-size: 10px;
	font-weight: 600;
	text-transform: uppercase;
	color: #8D949A;
	margin-top: 8px;
}
.pf-lbl:first-child { margin-top: 0; }

.pf-val { font-size: 12px; color: #333; margin-top: 2px; }
.pf-muted { color: #8D949A; }
.pf-path-text { font-size: 11px; word-break: break-word; }

.pf-input {
	width: 100%;
	padding: 4px 8px;
	font-size: 12px;
	border: 1px solid #d1d8dd;
	border-radius: 4px;
	margin-top: 4px;
	box-sizing: border-box;
}

.pf-pre {
	background: #f5f7fa;
	border: 1px solid #d1d8dd;
	border-radius: 4px;
	padding: 8px;
	font-size: 11px;
	white-space: pre-wrap;
	word-break: break-all;
	margin-top: 4px;
	cursor: pointer;
	max-height: 120px;
	overflow-y: auto;
}

.pf-resolve-key {
	display: inline-block;
	padding: 3px 8px;
	background: #e6f7ff;
	border: 1px solid #91d5ff;
	border-radius: 4px;
	font-size: 12px;
	font-family: monospace;
	color: #0050b3;
	margin-top: 4px;
}

.pf-row {
	margin-top: 10px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 6px;
}

.pf-check-label { font-size: 11px; cursor: pointer; }

.pf-btn-group { display: flex; gap: 6px; }

.pf-btn {
	padding: 5px 14px;
	border-radius: 4px;
	font-size: 12px;
	cursor: pointer;
	border: 1px solid #d1d8dd;
}
.pf-btn-default { background: #f5f7fa; color: #333; }
.pf-btn-default:hover { background: #e8eaed; }
.pf-btn-primary { background: #126BC4; color: #fff; border-color: #126BC4; }
.pf-btn-primary:hover { background: #0f5baa; }
</style>
