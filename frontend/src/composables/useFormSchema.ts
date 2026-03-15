import { computed } from "vue"
import { createResource } from "frappe-ui"

function safeParseJson(value: string | null | undefined, fallback: any = null) {
	if (!value) return fallback
	try {
		return JSON.parse(value)
	} catch {
		return fallback
	}
}

export function useFormSchema(formName: string) {
	const resource = createResource({
		url: "frappe.client.get_list",
		params: {
			doctype: "NCE Form Definition",
			filters: { form_name: formName, enabled: 1 },
			fields: ["*"],
			limit_page_length: 1,
		},
		auto: true,
	})

	const formDef = computed(() => {
		const list = resource.data as any[] | null
		return list && list.length > 0 ? list[0] : null
	})

	const schema = computed(() =>
		safeParseJson(formDef.value?.form_schema, [])
	)

	const tabLayout = computed(() =>
		safeParseJson(formDef.value?.tab_layout)
	)

	const fieldMapping = computed(() =>
		safeParseJson(formDef.value?.field_mapping)
	)

	const targetDoctype = computed(() =>
		formDef.value?.target_doctype || ""
	)

	const submitAction = computed(() =>
		formDef.value?.on_submit_action || "save"
	)

	const customApiMethod = computed(() =>
		formDef.value?.custom_api_method || ""
	)

	return {
		formDef,
		schema,
		tabLayout,
		fieldMapping,
		targetDoctype,
		submitAction,
		customApiMethod,
		loading: resource.loading,
		error: resource.error,
	}
}
