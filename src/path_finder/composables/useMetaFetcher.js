import { ref } from "vue";

export function useMetaFetcher() {
	const mode = ref("frappe-ui");
	if (window.frappe?.model?.with_doctype) {
		mode.value = "desk";
	}

	async function fetchMeta(doctype) {
		if (mode.value === "desk") {
			return new Promise((resolve, reject) => {
				frappe.model.with_doctype(doctype, () => {
					try {
						resolve(frappe.get_meta(doctype));
					} catch (e) {
						reject(e);
					}
				});
			});
		}

		const csrfToken = window.csrf_token
			|| document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)?.[1]
			|| "";
		const response = await fetch("/api/method/frappe.client.get", {
			method: "POST",
			headers: {
				"X-Frappe-CSRF-Token": csrfToken,
				"Content-Type": "application/json",
			},
			body: JSON.stringify({ doctype: "DocType", name: doctype }),
		});

		if (!response.ok) {
			throw new Error(`Failed to fetch meta for ${doctype}: ${response.status}`);
		}

		const result = await response.json();
		return result.message || { fields: [] };
	}

	return { fetchMeta, mode };
}
