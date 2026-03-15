<template>
	<div class="max-w-4xl mx-auto p-6">
		<div class="flex items-center justify-between mb-6">
			<div>
				<h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
					{{ formDef?.title || formName }}
				</h1>
				<p v-if="docName" class="text-sm text-gray-500 mt-1">
					Editing: {{ docName }}
				</p>
				<p v-else-if="targetDoctype" class="text-sm text-gray-500 mt-1">
					New {{ targetDoctype }}
				</p>
			</div>
			<Button variant="outline" @click="router.back()">Back</Button>
		</div>

		<div v-if="defLoading" class="text-gray-500 text-sm">
			Loading form definition...
		</div>

		<div v-else-if="defError" class="text-red-600 text-sm">
			Failed to load form definition "{{ formName }}".
		</div>

		<div v-else-if="schema.length">
			<NceForm
				:schema="schema"
				:tab-layout="tabLayout"
				:value="docData"
				:loading="saving"
				submit-label="Save"
				@submit="handleSubmit"
			/>
		</div>

		<div v-else class="text-gray-500 text-sm">
			No form schema defined for "{{ formName }}".
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { useRoute, useRouter } from "vue-router"
import { createResource } from "frappe-ui"
import { useFormSchema } from "@/composables/useFormSchema"
import { resolveFieldMapping } from "@/utils/schema-helpers"
import NceForm from "@/components/NceForm.vue"

const route = useRoute()
const router = useRouter()

const formName = computed(() => String(route.params.formName || ""))
const docName = computed(() => String(route.params.docName || ""))

const {
	formDef,
	schema,
	tabLayout,
	fieldMapping,
	targetDoctype,
	submitAction,
	customApiMethod,
	loading: defLoading,
	error: defError,
} = useFormSchema(formName.value)

const docResource = createResource({
	url: "frappe.client.get",
})

watch(
	[targetDoctype, docName],
	([dt, dn]) => {
		if (dt && dn) {
			docResource.submit({ doctype: dt, name: dn })
		}
	},
	{ immediate: true }
)

const docData = computed(() => {
	if (docName.value && docResource.data) {
		return docResource.data as Record<string, any>
	}
	return {}
})

const saving = ref(false)

const saveResource = createResource({
	url: "frappe.client.save",
})

async function handleSubmit(formData: Record<string, any>) {
	saving.value = true
	try {
		const mapped = resolveFieldMapping(formData, fieldMapping.value)
		const doc: Record<string, any> = {
			doctype: targetDoctype.value,
			...mapped,
		}
		if (docName.value) {
			doc.name = docName.value
		}

		if (submitAction.value === "save") {
			await saveResource.submit({ doc })
		} else if (submitAction.value === "submit") {
			await saveResource.submit({ doc })
			const submitRes = createResource({ url: "frappe.client.submit" })
			await submitRes.submit({
				doc: {
					doctype: targetDoctype.value,
					name: (saveResource.data as any)?.name,
				},
			})
		} else if (submitAction.value === "custom_api") {
			const customRes = createResource({ url: customApiMethod.value })
			await customRes.submit(mapped)
		}

		saving.value = false

		const savedName = (saveResource.data as any)?.name
		if (savedName && !docName.value) {
			router.replace(`/nce/form/${formName.value}/${savedName}`)
		}
	} catch {
		saving.value = false
	}
}
</script>
