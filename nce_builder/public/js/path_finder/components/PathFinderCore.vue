<template>
	<div class="pf-core">
		<div ref="bodyEl" class="pf-columns">
			<PathColumn
				v-for="(col, ci) in finder.columns"
				:key="ci"
				:col="col"
				:visited="finder.visited"
				:multi-select="multiSelect"
				@navigate="(f) => onNavigate(f, ci)"
				@select-field="(f) => onSelectField(f, ci)"
				@multi-select="(fields) => onMultiSelect(fields, ci)"
			/>
		</div>

		<PathDialog
			v-for="(pd, pi) in pathDialogs"
			:key="pi"
			:field="pd.field"
			:base-tag="pd.baseTag"
			:path="pd.path"
			:path-object="pd.pathObject"
			:portal-object="pd.portalObject"
			:action-object="pd.actionObject"
			:resolve-key="pd.resolveKey"
			:visible-tabs="visibleTabs"
			:apply-filters="finder.applyFilters"
			:init-top="100 + pi * 24"
			:init-left="160 + pi * 24"
			@close="pathDialogs.splice(pi, 1)"
			@select="(payload) => emit('select', payload)"
		/>
	</div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from "vue";
import { usePathFinder } from "../composables/usePathFinder.js";
import PathColumn from "./PathColumn.vue";
import PathDialog from "./PathDialog.vue";

const props = defineProps({
	rootDoctype:  { type: String, required: true },
	multiSelect:  { type: Boolean, default: false },
	metaFetcher:  { type: Object, default: null },
	visibleTabs:  { type: Array, default: () => ["tag", "textblock", "field", "portal", "action"] },
});

const emit = defineEmits(["select"]);

const finder = usePathFinder(props.metaFetcher);
const pathDialogs = reactive([]);
const bodyEl = ref(null);

onMounted(() => {
	finder.loadColumn(props.rootDoctype, null, null, 0);
});

async function onNavigate(field, colIdx) {
	finder.columns[colIdx].activeField = field.fieldname;
	await finder.loadColumn(
		field.options,
		field.fieldname,
		field.is_table ? "Table" : "Link",
		colIdx + 1,
	);
	await nextTick();
	if (bodyEl.value) bodyEl.value.scrollLeft = bodyEl.value.scrollWidth;
}

function onSelectField(field, colIdx) {
	const baseTag = finder.buildTag(colIdx, field);
	const path = field.is_pronoun
		? `${finder.columns[0]?.doctype || ""} \u2192 ${field.fieldname} (pronoun)`
		: finder.buildPath(colIdx, field);
	pathDialogs.push({
		field,
		baseTag,
		path,
		pathObject: finder.buildPathObject(colIdx, field),
		portalObject: finder.buildPortalObject(colIdx),
		actionObject: finder.buildActionObject(colIdx, field),
		resolveKey: finder.buildResolveKey(colIdx, field),
	});
}

function onMultiSelect(fields, colIdx) {
	const result = finder.buildMultiFieldObject(colIdx, fields);
	emit("select", { mode: "multi-field", data: result });
}

defineExpose({ finder, pathDialogs });
</script>

<style scoped>
.pf-core {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
}

.pf-columns {
	flex: 1;
	display: flex;
	overflow-x: auto;
	overflow-y: hidden;
}
</style>
