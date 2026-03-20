<template>
	<div class="form-page">
		<div class="form-header">
			<div>
				<h1 class="form-title">{{ formDef?.title || formName }}</h1>
				<p v-if="docName" class="form-subtitle">Editing: {{ docName }}</p>
				<p v-else-if="targetDoctype && !showRecordList" class="form-subtitle">New {{ targetDoctype }}</p>
			</div>
			<button class="back-btn" @click="router.back()">Back</button>
		</div>

		<div v-if="defLoading" class="form-message">Loading form definition...</div>
		<div v-else-if="defError" class="form-message error">Failed to load form definition "{{ formName }}".</div>

		<!-- Record selection for existing forms -->
		<div v-else-if="showRecordList && !docName" class="record-selector">
			<div class="selector-header">
				<h2 class="selector-title">Select a record or create new</h2>
				<button class="new-record-btn" @click="startNewRecord">+ Create New {{ targetDoctype }}</button>
			</div>

			<div v-if="recordsLoading" class="form-message">Loading records...</div>
			<div v-else-if="!records.length" class="form-message">
				No {{ targetDoctype }} records found.
				<button @click="startNewRecord" class="inline-btn">Create the first one</button>
			</div>

			<div v-else class="records-grid">
				<div
					v-for="record in records"
					:key="record.name"
					class="record-card"
					@click="selectRecord(record.name)"
				>
					<div class="record-name">{{ record.name }}</div>
					<div v-if="record.title && record.title !== record.name" class="record-title">
						{{ record.title }}
					</div>
					<div class="record-meta">
						Modified: {{ formatDate(record.modified) }}
					</div>
				</div>
			</div>
		</div>

		<!-- Grid mode (builder layout) -->
		<template v-else-if="useGridMode && !showRecordList">
			<div v-if="gridLoading" class="form-message">Loading form data...</div>
			<div v-else-if="gridLockBlocked" class="form-message warning">
				This record is being edited by {{ gridLockedBy }}. You are viewing in read-only mode.
			</div>
			<div v-else-if="!targetDoctype" class="form-message error">
				No target DocType configured for this form.
			</div>
			<GridFormRenderer
				v-else-if="gridReady"
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
import { ref, computed, watch, onBeforeUnmount, onMounted } from "vue"
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

console.log("[FormPage] Initial load - formName:", formName.value, "docName:", docName.value)

// Record selection state
const showRecordList = ref(false)
const records = ref<any[]>([])
const recordsLoading = ref(false)

const {
	formDef, schema, tabLayout, fieldMapping,
	targetDoctype, submitAction, customApiMethod,
	loading: defLoading, error: defError,
} = useFormSchema(formName.value)

// Debug logging for form schema
watch(defLoading, (loading) => {
	console.log("[FormPage] defLoading changed:", loading)
})

watch(defError, (error) => {
	console.log("[FormPage] defError changed:", error)
})

watch(formDef, (def) => {
	console.log("[FormPage] formDef loaded:", def)
	if (def) {
		console.log("[FormPage] - title:", def.title)
		console.log("[FormPage] - target_doctype:", def.target_doctype)
		console.log("[FormPage] - grid_layout exists:", !!def.grid_layout)
		console.log("[FormPage] - grid_config exists:", !!def.grid_config)
	}
})

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
const useGridMode = computed(() => {
	const hasGridElements = gridElements.value.length > 0
	console.log("[FormPage] useGridMode:", hasGridElements, "elements:", gridElements.value.length)
	return hasGridElements
})

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
	if (!targetDoctype.value) return

	gridLoading.value = true
	gridReady.value = false
	gridLockBlocked.value = false

	try {
		// For new records, skip locking and field resolution
		if (!docName.value) {
			gridValues.value = {}
			gridDocsCache.value = {}
			gridReadOnly.value = false
			gridReady.value = true
			gridLoading.value = false
			return
		}

		// For existing records, acquire lock and load data
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
		// For new records, create a new document
		if (!docName.value) {
			const doc: Record<string, any> = { doctype: targetDoctype.value }

			// Map form data to document fields
			for (const el of gridElements.value) {
				if (el.type !== 'field' || !el.config.fieldPath || !(el.id in formData)) continue

				// For new records, only handle direct fields (no link chains)
				const pathArr = el.config.fieldPathArray || []
				if (pathArr.length === 0) {
					const fieldName = el.config.fieldPath.replace('doc.', '')
					doc[fieldName] = formData[el.id]
				}
			}

			const saveRes = await fetch("/api/method/frappe.client.insert", {
				method: "POST",
				credentials: "include",
				headers: {
					"X-Frappe-CSRF-Token": csrfToken(),
					"Content-Type": "application/json"
				},
				body: JSON.stringify({ doc })
			})

			if (!saveRes.ok) throw new Error("Failed to create record")
			const saveData = await saveRes.json()
			const newName = saveData.message?.name

			if (newName) {
				alert("Record created successfully!")
				router.replace(`/nce/form/${formName.value}/${newName}`)
			}
			gridSaving.value = false
			return
		}

		// For existing records, use the update logic
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

watch([useGridMode, targetDoctype, docName], ([isGrid, dt]) => {
	if (isGrid && dt) loadGridData()
}, { immediate: true })

// Record selection functions
async function loadRecords() {
	if (!targetDoctype.value) {
		console.log("[FormPage] loadRecords - no targetDoctype, returning")
		return
	}

	console.log("[FormPage] loadRecords - fetching records for:", targetDoctype.value)
	recordsLoading.value = true
	try {
		const url = `/api/resource/${targetDoctype.value}?fields=["name","modified"]&order_by=modified desc&limit_page_length=20`
		console.log("[FormPage] Fetching:", url)
		const res = await fetch(url, { credentials: "include" })
		if (!res.ok) throw new Error(`HTTP ${res.status}`)
		const json = await res.json()
		records.value = json.data || []
		console.log("[FormPage] Loaded records:", records.value.length)

		// Try to get title field if it exists
		if (records.value.length > 0) {
			const metaRes = await fetch(`/api/method/frappe.desk.form.load.getdoctype?doctype=${targetDoctype.value}`, {
				credentials: "include"
			})
			if (metaRes.ok) {
				const metaData = await metaRes.json()
				const titleField = metaData.docs?.[0]?.title_field
				if (titleField && titleField !== 'name') {
					// Reload with title field
					const resWithTitle = await fetch(
						`/api/resource/${targetDoctype.value}?fields=["name","modified","${titleField}"]&order_by=modified desc&limit_page_length=20`,
						{ credentials: "include" }
					)
					if (resWithTitle.ok) {
						const jsonWithTitle = await resWithTitle.json()
						records.value = jsonWithTitle.data?.map((r: any) => ({
							...r,
							title: r[titleField]
						})) || []
					}
				}
			}
		}
	} catch (e) {
		console.error("Failed to load records:", e)
		records.value = []
	} finally {
		recordsLoading.value = false
	}
}

function selectRecord(name: string) {
	router.push(`/nce/form/${formName.value}/${name}`)
}

function startNewRecord() {
	showRecordList.value = false
}

function formatDate(dateStr: string): string {
	if (!dateStr) return ""
	const date = new Date(dateStr)
	return date.toLocaleDateString() + " " + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

// Show record list on mount if no docName specified
onMounted(() => {
	console.log("[FormPage] Mounted - docName:", docName.value, "targetDoctype:", targetDoctype.value)
	if (!docName.value && targetDoctype.value) {
		console.log("[FormPage] Showing record list")
		showRecordList.value = true
		loadRecords()
	}
})

watch(targetDoctype, (dt) => {
	console.log("[FormPage] targetDoctype changed:", dt)
	if (!docName.value && dt) {
		console.log("[FormPage] Showing record list (from watch)")
		showRecordList.value = true
		loadRecords()
	}
})
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

/* Record selector */
.record-selector { padding: 24px; }
.selector-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.selector-title { font-size: 16px; font-weight: 600; color: #111827; margin: 0; }
.new-record-btn {
	padding: 8px 16px;
	background: #3b82f6;
	color: #fff;
	border: none;
	border-radius: 6px;
	font-size: 13px;
	font-weight: 500;
	cursor: pointer;
}
.new-record-btn:hover { background: #2563eb; }
.inline-btn {
	margin-left: 8px;
	padding: 4px 12px;
	background: #3b82f6;
	color: #fff;
	border: none;
	border-radius: 4px;
	font-size: 13px;
	cursor: pointer;
}
.inline-btn:hover { background: #2563eb; }
.records-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 16px;
}
.record-card {
	padding: 16px;
	background: #f9fafb;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	cursor: pointer;
	transition: all 150ms;
}
.record-card:hover {
	background: #f3f4f6;
	border-color: #d1d5db;
	box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}
.record-name {
	font-size: 14px;
	font-weight: 600;
	color: #111827;
	margin-bottom: 4px;
}
.record-title {
	font-size: 13px;
	color: #4b5563;
	margin-bottom: 8px;
}
.record-meta {
	font-size: 11px;
	color: #9ca3af;
}
</style>
