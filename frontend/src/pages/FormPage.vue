<template>
	<div class="form-page">
		<div class="form-header">
			<div>
				<h1 class="form-title">{{ formDef?.title || formName }}</h1>
				<p v-if="docName" class="form-subtitle">Editing: {{ docName }}</p>
				<p v-else-if="targetDoctype" class="form-subtitle">New {{ targetDoctype }}</p>
			</div>
			<button class="back-btn" @click="router.back()">Back</button>
		</div>

		<div v-if="defLoading" class="form-message">Loading form definition...</div>
		<div v-else-if="defError" class="form-message error">Failed to load form definition "{{ formName }}".</div>

		<!-- Grid mode (builder layout) -->
		<template v-else-if="useGridMode">
			<div v-if="gridLoading" class="form-message">Loading form data...</div>
			<div v-else-if="gridLockBlocked" class="form-message warning">
				This record is being edited by {{ gridLockedBy }}. You are viewing in read-only mode.
			</div>
			<GridFormRenderer
				v-if="gridReady"
				ref="rendererRef"
				:elements="gridElements"
				:grid-config="gridConfig"
				:initial-values="gridValues"
				:read-only="gridReadOnly"
				:saving="gridSaving"
				@submit="handleGridSave"
			/>
		</template>

		<!-- Legacy FormKit mode -->
		<template v-else-if="schema.length">
			<NceForm
				:schema="schema"
				:tab-layout="tabLayout"
				:value="docData"
				:loading="saving"
				submit-label="Save"
				@submit="handleSubmit"
			/>
		</template>

		<div v-else class="form-message">No form schema defined for "{{ formName }}".</div>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from "vue"
import { useRoute, useRouter } from "vue-router"
import { createResource } from "frappe-ui"
import { useFormSchema } from "@/composables/useFormSchema"
import { resolveFieldMapping } from "@/utils/schema-helpers"
import NceForm from "@/components/NceForm.vue"
import GridFormRenderer from "@/components/GridFormRenderer.vue"
import type { BuilderElement, GridConfig } from "@/composables/useBuilderState"

const route = useRoute()
const router = useRouter()
const formName = computed(() => String(route.params.formName || ""))
const docName = computed(() => String(route.params.docName || ""))

const {
	formDef, schema, tabLayout, fieldMapping,
	targetDoctype, submitAction, customApiMethod,
	loading: defLoading, error: defError,
} = useFormSchema(formName.value)

// --- Legacy mode state ---
const docResource = createResource({ url: "frappe.client.get" })
watch([targetDoctype, docName], ([dt, dn]) => {
	if (dt && dn && !useGridMode.value) docResource.submit({ doctype: dt, name: dn })
}, { immediate: true })

const docData = computed(() =>
	docName.value && docResource.data ? docResource.data as Record<string, any> : {}
)
const saving = ref(false)
const saveResource = createResource({ url: "frappe.client.save" })

async function handleSubmit(formData: Record<string, any>) {
	saving.value = true
	try {
		const mapped = resolveFieldMapping(formData, fieldMapping.value)
		const doc: Record<string, any> = { doctype: targetDoctype.value, ...mapped }
		if (docName.value) doc.name = docName.value

		if (submitAction.value === "save") {
			await saveResource.submit({ doc })
		} else if (submitAction.value === "submit") {
			await saveResource.submit({ doc })
			const submitRes = createResource({ url: "frappe.client.submit" })
			await submitRes.submit({ doc: { doctype: targetDoctype.value, name: (saveResource.data as any)?.name } })
		} else if (submitAction.value === "custom_api") {
			const customRes = createResource({ url: customApiMethod.value })
			await customRes.submit(mapped)
		}

		saving.value = false
		const savedName = (saveResource.data as any)?.name
		if (savedName && !docName.value) router.replace(`/nce/form/${formName.value}/${savedName}`)
	} catch { saving.value = false }
}

// --- Grid mode state ---
const gridElements = computed<BuilderElement[]>(() => {
	try { return formDef.value?.grid_layout ? JSON.parse(formDef.value.grid_layout) : [] }
	catch { return [] }
})
const gridConfig = computed<GridConfig>(() => {
	try { return formDef.value?.grid_config ? JSON.parse(formDef.value.grid_config) : { cellSize: 10, gap: 1 } }
	catch { return { cellSize: 10, gap: 1 } }
})
const useGridMode = computed(() => gridElements.value.length > 0)

const gridLoading = ref(false)
const gridReady = ref(false)
const gridReadOnly = ref(false)
const gridLockBlocked = ref(false)
const gridLockedBy = ref("")
const gridValues = ref<Record<string, any>>({})
const gridDocsCache = ref<Record<string, any>>({})
const gridSaving = ref(false)
const rendererRef = ref<InstanceType<typeof GridFormRenderer> | null>(null)
const lockAcquired = ref(false)

function csrfToken(): string {
	return (window as any).csrf_token
		|| document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)?.[1]
		|| ""
}

async function apiCall(method: string, args: Record<string, any>) {
	const res = await fetch(`/api/method/nce_builder.api.${method}`, {
		method: "POST",
		credentials: "include",
		headers: { "X-Frappe-CSRF-Token": csrfToken(), "Content-Type": "application/json" },
		body: JSON.stringify(args),
	})
	if (!res.ok) throw new Error(`API ${method} failed: ${res.status}`)
	const json = await res.json()
	return json.message
}

async function loadGridData() {
	if (!docName.value || !targetDoctype.value) return
	gridLoading.value = true
	gridReady.value = false
	gridLockBlocked.value = false

	try {
		const lockStatus = await apiCall("check_edit_lock", {
			target_doctype: targetDoctype.value,
			target_docname: docName.value,
		})

		if (lockStatus.locked) {
			const viewAnyway = confirm(`${lockStatus.locked_by} is editing this record. View read-only?`)
			if (!viewAnyway) { gridLoading.value = false; router.back(); return }
			gridReadOnly.value = true
			gridLockBlocked.value = true
			gridLockedBy.value = lockStatus.locked_by
		} else {
			const acquired = await apiCall("acquire_edit_lock", {
				target_doctype: targetDoctype.value,
				target_docname: docName.value,
			})
			if (!acquired.ok) {
				gridReadOnly.value = true
				gridLockBlocked.value = true
				gridLockedBy.value = acquired.locked_by || "another user"
			} else {
				lockAcquired.value = true
				gridReadOnly.value = false
			}
		}

		const boundElements = gridElements.value.filter(el => el.config.fieldPath)
		const fieldsConfig = boundElements.map(el => ({
			element_id: el.id,
			path: el.config.fieldPathArray || [],
			terminal_field: el.config.fieldPath.split(".").pop() || "",
		}))

		const resolved = await apiCall("resolve_fields", {
			doctype: targetDoctype.value,
			docname: docName.value,
			fields_config: JSON.stringify(fieldsConfig),
		})

		gridValues.value = resolved.values || {}
		gridDocsCache.value = resolved.docs || {}
		gridReady.value = true
	} catch (e: any) {
		alert(e.message || "Failed to load form data")
	} finally {
		gridLoading.value = false
	}
}

async function handleGridSave(formData: Record<string, any>) {
	gridSaving.value = true
	try {
		const updatesByChain: Record<string, {
			chain_key: string
			target_doctype: string
			target_name: string
			modified: string
			fields: Record<string, any>
		}> = {}

		for (const el of gridElements.value) {
			if (!el.config.fieldPath || !(el.id in formData)) continue

			const pathArr = el.config.fieldPathArray || []
			const chainKey = pathArr.map(hop => hop.field).join(".")
			const terminalField = el.config.fieldPath.split(".").pop() || ""

			if (!updatesByChain[chainKey]) {
				const cachedDoc = gridDocsCache.value[chainKey]
				const dt = pathArr.length > 0
					? pathArr[pathArr.length - 1].target
					: targetDoctype.value

				updatesByChain[chainKey] = {
					chain_key: chainKey,
					target_doctype: dt,
					target_name: cachedDoc?.name || docName.value,
					modified: String(cachedDoc?.modified || ""),
					fields: {},
				}
			}

			updatesByChain[chainKey].fields[terminalField] = formData[el.id]
		}

		const updates = Object.values(updatesByChain)
		const result = await apiCall("save_resolved_fields", {
			doctype: targetDoctype.value,
			docname: docName.value,
			updates: JSON.stringify(updates),
		})

		if (result.ok) {
			lockAcquired.value = false
			alert("Saved!")
		} else if (result.conflicts?.length) {
			const msgs = result.conflicts.map((c: any) =>
				`${c.target_doctype} "${c.target_name}" was modified since you loaded it.`
			)
			alert("Save conflict:\n" + msgs.join("\n") + "\n\nPlease reload and try again.")
		} else {
			alert("Save failed.")
		}
	} catch (e: any) {
		alert(e.message || "Save failed")
	} finally {
		gridSaving.value = false
	}
}

onBeforeUnmount(() => {
	if (lockAcquired.value && targetDoctype.value && docName.value) {
		apiCall("release_edit_lock", {
			target_doctype: targetDoctype.value,
			target_docname: docName.value,
		}).catch(() => {})
	}
})

watch([useGridMode, targetDoctype, docName], ([isGrid, dt, dn]) => {
	if (isGrid && dt && dn) loadGridData()
}, { immediate: true })
</script>

<style scoped>
.form-page { display: flex; flex-direction: column; min-height: 100%; background: #fff; }
.form-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
.form-title { font-size: 18px; font-weight: 600; color: #111827; margin: 0; }
.form-subtitle { font-size: 13px; color: #6b7280; margin: 4px 0 0; }
.back-btn { padding: 6px 16px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; cursor: pointer; color: #374151; }
.back-btn:hover { background: #e5e7eb; }
.form-message { padding: 24px; font-size: 14px; color: #6b7280; }
.form-message.error { color: #dc2626; }
.form-message.warning { color: #d97706; background: #fffbeb; border-bottom: 1px solid #fde68a; }
</style>
