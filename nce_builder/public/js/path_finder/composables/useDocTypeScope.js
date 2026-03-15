import { ref, reactive, readonly } from "vue";
import { useMetaFetcher } from "./useMetaFetcher.js";

const SCOPE_DOCTYPE = "PathFinder Scope";

export function useDocTypeScope(metaFetcherOverride) {
	const { fetchMeta } = metaFetcherOverride || useMetaFetcher();

	const scopedDocTypes = ref([]);
	const relationshipMatrix = reactive({});
	const loading = ref(false);
	const error = ref(null);

	function _csrfToken() {
		return window.csrf_token
			|| document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)?.[1]
			|| "";
	}

	async function _fetchDoc(doctype, name) {
		if (window.frappe?.call) {
			const r = await frappe.call({
				method: "frappe.client.get",
				args: { doctype, name },
			});
			return r.message;
		}
		const response = await fetch("/api/method/frappe.client.get", {
			method: "POST",
			headers: {
				"X-Frappe-CSRF-Token": _csrfToken(),
				"Content-Type": "application/json",
			},
			body: JSON.stringify({ doctype, name }),
		});
		if (!response.ok) throw new Error(`Failed to fetch ${doctype}/${name}: ${response.status}`);
		const result = await response.json();
		return result.message;
	}

	async function loadScope() {
		loading.value = true;
		error.value = null;
		try {
			const doc = await _fetchDoc(SCOPE_DOCTYPE, SCOPE_DOCTYPE);
			scopedDocTypes.value = (doc.scoped_doctypes || []).map((row) => row.doctype_name || row.name);

			if (doc.relationship_matrix) {
				const parsed = typeof doc.relationship_matrix === "string"
					? JSON.parse(doc.relationship_matrix)
					: doc.relationship_matrix;
				Object.assign(relationshipMatrix, parsed);
			}
		} catch (e) {
			error.value = e;
			console.error("[useDocTypeScope] Failed to load scope:", e);
		} finally {
			loading.value = false;
		}
	}

	function isInScope(doctype) {
		return scopedDocTypes.value.includes(doctype);
	}

	function getRelationships(doctype) {
		return relationshipMatrix[doctype] || { forward: [], reverse: [], table: [] };
	}

	function getReverseLinks(doctype) {
		const rel = getRelationships(doctype);
		return rel.reverse || [];
	}

	async function buildMatrix() {
		const matrix = {};
		for (const dt of scopedDocTypes.value) {
			matrix[dt] = { forward: [], reverse: [], table: [] };
		}
		const scopeSet = new Set(scopedDocTypes.value);

		for (const dt of scopedDocTypes.value) {
			let meta;
			try { meta = await fetchMeta(dt); } catch { continue; }

			for (const f of meta.fields || []) {
				if (!f.options || !scopeSet.has(f.options)) continue;
				if (f.fieldtype === "Link") {
					matrix[dt].forward.push({
						fieldname: f.fieldname,
						target: f.options,
						label: f.label,
					});
					matrix[f.options].reverse.push({
						source_doctype: dt,
						fieldname: f.fieldname,
						label: f.label,
					});
				} else if (f.fieldtype === "Table") {
					matrix[dt].table.push({
						fieldname: f.fieldname,
						target: f.options,
						label: f.label,
					});
				}
			}
		}

		Object.keys(relationshipMatrix).forEach((k) => delete relationshipMatrix[k]);
		Object.assign(relationshipMatrix, matrix);
		return matrix;
	}

	return {
		scopedDocTypes: readonly(scopedDocTypes),
		relationshipMatrix: readonly(relationshipMatrix),
		loading: readonly(loading),
		error: readonly(error),
		loadScope,
		isInScope,
		getRelationships,
		getReverseLinks,
		buildMatrix,
	};
}
