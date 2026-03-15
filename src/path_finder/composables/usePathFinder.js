import { ref, reactive } from "vue";
import { useMetaFetcher } from "./useMetaFetcher.js";

const SKIP_FIELDTYPES = {
	"Section Break": 1, "Column Break": 1, "Tab Break": 1,
	"HTML": 1, "Fold": 1, "Heading": 1, "Button": 1,
	"Table MultiSelect": 1,
};

const SKIP_FIELDNAMES = {
	name: 1, owner: 1, creation: 1, modified: 1, modified_by: 1,
	docstatus: 1, idx: 1, parent: 1, parentfield: 1, parenttype: 1,
};

const PRONOUN_DEFS = [
	{ fieldname: "he_she",           label: "he/she",  male: "he",  female: "she" },
	{ fieldname: "he_she_cap",       label: "He/She",  male: "He",  female: "She" },
	{ fieldname: "him_her",          label: "him/her",  male: "him", female: "her" },
	{ fieldname: "his_her",          label: "His/Her",  male: "His", female: "Her" },
	{ fieldname: "his_her_lower",    label: "his/her",  male: "his", female: "her" },
];

function _generatePronounTags(meta) {
	const hasGender = (meta.fields || []).some(
		(f) => f.fieldname && f.fieldname.toLowerCase() === "gender"
	);
	if (!hasGender) return [];

	return PRONOUN_DEFS.map((d) => ({
		fieldname: d.fieldname,
		label: d.label,
		jinja_tag:
			`{% if (gender|lower) == 'male' %}${d.male}{% else %}${d.female}{% endif %}`,
		is_pronoun: true,
	}));
}

export function usePathFinder(metaFetcherOverride) {
	const { fetchMeta } = metaFetcherOverride || useMetaFetcher();
	const columns = reactive([]);
	const visited = reactive({});

	function reset() {
		columns.splice(0);
		Object.keys(visited).forEach((k) => delete visited[k]);
	}

	function loadColumn(doctype, viaField, viaType, colIdx) {
		while (columns.length > colIdx) {
			const removed = columns.pop();
			delete visited[removed.doctype];
		}
		visited[doctype] = true;

		const col = reactive({
			doctype,
			via_field: viaField,
			via_type: viaType,
			fields: [],
			activeField: null,
		});
		columns.push(col);

		return fetchMeta(doctype).then((meta) => {
			const fields = [];

			(meta.fields || []).forEach((f) => {
				if (SKIP_FIELDTYPES[f.fieldtype]) return;
				if (f.fieldtype === "Table") {
					fields.push({
						fieldname: f.fieldname,
						label: f.label || f.fieldname,
						fieldtype: f.fieldtype,
						options: f.options || "",
						is_link: false,
						is_table: true,
					});
					return;
				}
				if (SKIP_FIELDNAMES[f.fieldname]) return;
				fields.push({
					fieldname: f.fieldname,
					label: f.label || f.fieldname,
					fieldtype: f.fieldtype,
					options: f.options || "",
					is_link: f.fieldtype === "Link",
					is_table: false,
				});
			});

			if (colIdx === 0) {
				const pronounTags = _generatePronounTags(meta);
				col.fields = pronounTags.concat(fields);
			} else {
				col.fields = fields;
			}
		});
	}

	function buildTag(colIdx, field) {
		if (field.is_pronoun && field.jinja_tag) return field.jinja_tag;

		const hops = columns.slice(0, colIdx + 1);
		let tableHopIdx = -1;
		for (let j = 1; j < hops.length; j++) {
			if (hops[j].via_type === "Table") { tableHopIdx = j; break; }
		}
		return tableHopIdx === -1
			? _buildLinkChainTag(hops, field)
			: _buildTableTag(hops, field, tableHopIdx);
	}

	function buildPath(colIdx, field) {
		const parts = [];
		for (let i = 0; i <= colIdx; i++) {
			const c = columns[i];
			if (i === 0) {
				parts.push(c.doctype);
			} else {
				parts.push(`${c.via_field} (${c.via_type})`);
				parts.push(c.doctype);
			}
		}
		parts.push(field.fieldname);
		return parts.join(" \u2192 ");
	}

	function applyFilters(tag, fallback, isHtml) {
		let result = tag;
		if (fallback) {
			const safe = fallback.replace(/'/g, "\\'");
			result = result.replace(/\{\{([^}]+)\}\}/g, (m, inner) =>
				`{{ ${inner.trim()} | default('${safe}') }}`
			);
		}
		if (isHtml) {
			result = result.replace(/\{\{([^}]+)\}\}/g, (m, inner) => {
				const trimmed = inner.trim();
				return trimmed.includes("| safe") ? m : `{{ ${trimmed} | safe }}`;
			});
		}
		return result;
	}

	function _buildPathArray(colIdx) {
		const arr = [];
		for (let i = 1; i <= colIdx; i++) {
			arr.push({
				doctype: columns[i - 1].doctype,
				field: columns[i].via_field,
				fieldtype: columns[i].via_type,
				target: columns[i].doctype,
			});
		}
		return arr;
	}

	function buildResolveKey(colIdx, field) {
		if (colIdx === 0) return field.fieldname;
		const parts = [];
		for (let i = 1; i <= colIdx; i++) {
			parts.push(columns[i].via_field);
		}
		parts.push(field.fieldname);
		return parts.join(".");
	}

	function buildFormKitSchema(colIdx, field) {
		const ftypeMap = {
			Data: "text", "Small Text": "text", Link: "text",
			Int: "number", Float: "number", Currency: "number", Percent: "number",
			Check: "checkbox",
			Text: "textarea", "Long Text": "textarea", Code: "textarea", "Text Editor": "textarea",
			Date: "date",
			Select: "select",
		};
		return {
			$formkit: ftypeMap[field.fieldtype] || "text",
			name: field.fieldname,
			label: field.label,
			disabled: true,
			help: "Via: " + buildPath(colIdx, field),
		};
	}

	function buildPathObject(colIdx, field) {
		return {
			path: _buildPathArray(colIdx),
			terminal_doctype: columns[colIdx].doctype,
			terminal_field: field.fieldname,
			terminal_fieldtype: field.fieldtype,
			formkit_schema: buildFormKitSchema(colIdx, field),
			resolve_key: buildResolveKey(colIdx, field),
			tag: buildTag(colIdx, field),
		};
	}

	function buildMultiFieldObject(colIdx, fieldsArray) {
		return {
			path: _buildPathArray(colIdx),
			terminal_doctype: columns[colIdx].doctype,
			fields: fieldsArray.map((f) => ({
				terminal_field: f.fieldname,
				terminal_fieldtype: f.fieldtype,
				resolve_key: buildResolveKey(colIdx, f),
				formkit_schema: buildFormKitSchema(colIdx, f),
				tag: buildTag(colIdx, f),
			})),
		};
	}

	function buildPortalObject(colIdx) {
		return {
			path: _buildPathArray(colIdx),
			terminal_doctype: columns[colIdx].doctype,
			link_field_in_target: columns[colIdx].via_field,
			tag: null,
		};
	}

	function buildActionObject(colIdx, field) {
		const pathArray = _buildPathArray(colIdx);
		const terminalDoctype = columns[colIdx].doctype;
		const rootDoctype = columns[0].doctype;

		if (field) {
			const pathDesc = pathArray.map((p) => p.field).join(" \u2192 ");
			return {
				path: pathArray,
				terminal_doctype: terminalDoctype,
				terminal_field: field.fieldname,
				terminal_fieldtype: field.fieldtype,
				action_hint: "value",
				description: `${field.label} from ${terminalDoctype} via ${pathDesc || "root"}`,
			};
		}
		return {
			path: pathArray,
			terminal_doctype: terminalDoctype,
			link_field_in_target: columns[colIdx].via_field,
			action_hint: "list",
			description: `All ${terminalDoctype} where ${columns[colIdx].via_field} = current ${rootDoctype}`,
		};
	}

	return {
		columns, visited, reset, loadColumn,
		buildTag, buildPath, applyFilters,
		buildResolveKey, buildFormKitSchema, buildPathObject,
		buildMultiFieldObject, buildPortalObject, buildActionObject,
	};
}

function _buildLinkChainTag(hops, field) {
	const depth = hops.length - 1;
	if (depth === 0) return `{{ doc.${field.fieldname} }}`;
	if (depth === 1) return `{{ frappe.db.get_value('${hops[1].doctype}', doc.${hops[1].via_field}, '${field.fieldname}') }}`;
	if (depth === 2) return `{{ frappe.db.get_value('${hops[2].doctype}', frappe.db.get_value('${hops[1].doctype}', doc.${hops[1].via_field}, '${hops[2].via_field}'), '${field.fieldname}') }}`;

	const lines = [];
	lines.push(`{% set hop1 = frappe.get_doc('${hops[1].doctype}', doc.${hops[1].via_field}) %}`);
	for (let k = 2; k < hops.length; k++) {
		lines.push(`{% set hop${k} = frappe.get_doc('${hops[k].doctype}', hop${k - 1}.${hops[k].via_field}) %}`);
	}
	lines.push(`{{ hop${hops.length - 1}.${field.fieldname} }}`);
	return lines.join("\n");
}

function _buildTableTag(hops, field, tableHopIdx) {
	const pre = hops.slice(0, tableHopIdx);
	const tableField = hops[tableHopIdx].via_field;
	const post = hops.slice(tableHopIdx);
	const preDepth = pre.length - 1;
	const lines = [];
	let tableAccessor;

	if (preDepth === 0) {
		tableAccessor = `doc.${tableField}`;
	} else if (preDepth === 1) {
		lines.push(`{% set parent_doc = frappe.get_doc('${pre[1].doctype}', doc.${pre[1].via_field}) %}`);
		tableAccessor = `parent_doc.${tableField}`;
	} else {
		lines.push(`{% set hop1 = frappe.get_doc('${pre[1].doctype}', doc.${pre[1].via_field}) %}`);
		for (let p = 2; p < pre.length; p++) {
			lines.push(`{% set hop${p} = frappe.get_doc('${pre[p].doctype}', hop${p - 1}.${pre[p].via_field}) %}`);
		}
		tableAccessor = `hop${pre.length - 1}.${tableField}`;
	}

	const postDepth = post.length - 1;
	let inner;
	if (postDepth === 0) {
		inner = `{{ row.${field.fieldname} }}`;
	} else if (postDepth === 1) {
		inner = `{{ frappe.db.get_value('${post[1].doctype}', row.${post[1].via_field}, '${field.fieldname}') }}`;
	} else {
		const il = [];
		il.push(`{% set rh1 = frappe.get_doc('${post[1].doctype}', row.${post[1].via_field}) %}`);
		for (let r = 2; r < post.length; r++) {
			il.push(`{% set rh${r} = frappe.get_doc('${post[r].doctype}', rh${r - 1}.${post[r].via_field}) %}`);
		}
		il.push(`{{ rh${post.length - 1}.${field.fieldname} }}`);
		inner = il.join("\n");
	}

	lines.push(`{% for row in ${tableAccessor} %}`);
	lines.push(inner);
	lines.push("{% endfor %}");
	return lines.join("\n");
}
