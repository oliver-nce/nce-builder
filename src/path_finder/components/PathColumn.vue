<template>
	<div class="pf-column">
		<div class="pf-col-header">
			{{ col.doctype }}
			<span class="pf-col-count">{{ col.fields.length }} fields</span>
		</div>
		<div v-if="multiSelect" class="pf-multi-bar">
			<span class="pf-multi-count">{{ selected.size }} selected</span>
			<button class="pf-multi-done" :disabled="selected.size === 0" @click="emitMulti">Done</button>
		</div>
		<div class="pf-tiles">
			<div
				v-for="f in col.fields"
				:key="f.fieldname"
				:class="tileClass(f)"
				:title="isCircular(f) ? `Circular: ${f.options} already visited` : ''"
				@click="onTileClick(f)"
			>
				<div class="pf-tile-top">
					<input
						v-if="multiSelect && !f.is_link && !f.is_table"
						type="checkbox"
						:checked="selected.has(f.fieldname)"
						class="pf-tile-check"
						@click.stop="toggleSelect(f)"
					>
					<span class="pf-tile-label">{{ f.label }}</span>
					<span v-if="(f.is_link || f.is_table) && !isCircular(f)" class="pf-tile-arrow">&#9654;</span>
				</div>
				<div class="pf-tile-meta">
					<span class="pf-tile-fieldname">{{ f.fieldname }}</span>
					<span class="pf-tile-badge">{{ badgeText(f) }}</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { reactive } from "vue";

const props = defineProps({
	col:         { type: Object, required: true },
	visited:     { type: Object, default: () => ({}) },
	multiSelect: { type: Boolean, default: false },
});

const emit = defineEmits(["navigate", "select-field", "multi-select"]);

const selected = reactive(new Set());

function isCircular(f) {
	return (f.is_link || f.is_table) && f.options && props.visited[f.options];
}

function tileClass(f) {
	const cls = ["pf-tile"];
	if (f.is_pronoun) cls.push("pf-tile-pronoun");
	else if (isCircular(f)) cls.push("pf-tile-circular");
	else if (f.is_link) cls.push("pf-tile-link");
	else if (f.is_table) cls.push("pf-tile-table");
	if (props.col.activeField === f.fieldname) cls.push("pf-tile-active");
	if (selected.has(f.fieldname)) cls.push("pf-tile-selected");
	return cls.join(" ");
}

function badgeText(f) {
	if (f.is_pronoun) return "pronoun";
	let text = f.fieldtype;
	if ((f.is_link || f.is_table) && f.options) text += ` \u2192 ${f.options}`;
	return text;
}

function toggleSelect(f) {
	if (selected.has(f.fieldname)) {
		selected.delete(f.fieldname);
	} else {
		selected.add(f.fieldname);
	}
}

function emitMulti() {
	const fields = props.col.fields.filter((f) => selected.has(f.fieldname));
	emit("multi-select", fields);
}

function onTileClick(f) {
	if (isCircular(f)) return;
	if (props.multiSelect && !f.is_link && !f.is_table) {
		toggleSelect(f);
		return;
	}
	if (f.is_link || f.is_table) {
		emit("navigate", f);
	} else {
		emit("select-field", f);
	}
}
</script>

<style scoped>
.pf-column {
	min-width: 220px;
	max-width: 260px;
	flex-shrink: 0;
	border-right: 1px solid #d1d8dd;
	display: flex;
	flex-direction: column;
}

.pf-col-header {
	padding: 8px 10px;
	background: #E3F0FC;
	color: #105EAD;
	font-weight: 600;
	font-size: 12px;
	border-bottom: 2px solid #A2CCF6;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.pf-col-count {
	font-weight: 400;
	font-size: 10px;
	opacity: 0.7;
}

.pf-multi-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 4px 10px;
	background: #fff8e1;
	border-bottom: 1px solid #ffe082;
	font-size: 11px;
}
.pf-multi-count { color: #666; }
.pf-multi-done {
	padding: 2px 10px;
	font-size: 11px;
	border: 1px solid #126BC4;
	background: #126BC4;
	color: #fff;
	border-radius: 3px;
	cursor: pointer;
}
.pf-multi-done:disabled { opacity: 0.4; cursor: not-allowed; }

.pf-tiles {
	flex: 1;
	overflow-y: auto;
	padding: 4px;
}

.pf-tile {
	padding: 6px 8px;
	margin: 2px 0;
	border-radius: 4px;
	cursor: pointer;
	border: 1px solid transparent;
	transition: background 0.1s;
}
.pf-tile:hover { background: #EAF3FD; }

.pf-tile-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.pf-tile-label { font-size: 12px; font-weight: 500; color: #333; }
.pf-tile-arrow { color: #126BC4; font-size: 10px; }

.pf-tile-check {
	margin-right: 6px;
	cursor: pointer;
	flex-shrink: 0;
}

.pf-tile-meta {
	display: flex;
	justify-content: space-between;
	margin-top: 2px;
}
.pf-tile-fieldname { font-size: 10px; color: #8D949A; }
.pf-tile-badge { font-size: 9px; color: #8D949A; background: #f0f2f4; padding: 1px 4px; border-radius: 3px; }

.pf-tile-link { border-left: 3px solid #126BC4; }
.pf-tile-table { border-left: 3px solid #e67e22; }
.pf-tile-pronoun { border-left: 3px solid #9b59b6; }
.pf-tile-circular { opacity: 0.4; cursor: not-allowed; }
.pf-tile-active { background: #D4E8FC; border-color: #126BC4; }
.pf-tile-selected { background: #e8f5e9; border-color: #66bb6a; }
</style>
