<template>
	<div class="builder-page">
		<!-- Toolbar -->
		<header class="toolbar">
			<input
				v-model="state.title"
				class="title-input"
				placeholder="Form title..."
			/>
			<div class="toolbar-spacer" />

			<label class="toolbar-label">DocType:</label>
			<select
				v-model="state.targetDoctype"
				class="toolbar-select"
				:disabled="!doctypeOptions.length"
			>
				<option value="" disabled>
					{{ doctypeOptions.length ? '— pick a DocType —' : 'Loading…' }}
				</option>
				<option v-for="dt in doctypeOptions" :key="dt" :value="dt">
					{{ dt }}
				</option>
			</select>

			<button class="save-btn" :disabled="saving" @click="onSave">
				{{ saving ? 'Saving...' : 'Save' }}
			</button>
		</header>

		<!-- Three panels -->
		<div class="panels">
			<aside class="panel-left">
				<ElementPalette />
			</aside>

			<BuilderCanvas
				:state="state"
				@select="selectElement"
				@move="moveElement"
				@resize="resizeElement"
				@drop-new="(type: string, x: number, y: number) => addElement(type as 'field' | 'caption', x, y)"
				@element-contextmenu="onElementContextMenu"
			/>

			<aside class="panel-right">
				<PropertyPanel
					:element="selectedElement"
					:primary-color="primaryColor"
					:secondary-color="secondaryColor"
					@update="(id: string, changes: any) => updateElement(id, changes)"
					@delete="removeElement"
					@open-pathfinder="openPathFinder"
				/>
			</aside>
		</div>

		<PathFinder
			v-if="showPathFinder && state.targetDoctype"
			:root-doctype="state.targetDoctype"
			mode="float"
			:visible-tabs="['field']"
			:meta-fetcher="spaMetaFetcher"
			@select="onPathFinderSelect"
			@close="onPathFinderClose"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useBuilderState } from "@/composables/useBuilderState"
import ElementPalette from "@/components/builder/ElementPalette.vue"
import BuilderCanvas from "@/components/builder/BuilderCanvas.vue"
import PropertyPanel from "@/components/builder/PropertyPanel.vue"
import PathFinder from "@pathfinder/components/PathFinder.vue"

const route = useRoute()
const router = useRouter()
const formName = route.params.formName as string

const {
	state,
	selectedElement,
	addElement,
	removeElement,
	updateElement,
	moveElement,
	resizeElement,
	selectElement,
	save,
	load,
} = useBuilderState(formName)

const saving = ref(false)
const primaryColor = ref("#0242A8")
const secondaryColor = ref("#F5D06C")
const doctypeOptions = ref<string[]>([])
const showPathFinder = ref(false)
const pathFinderTargetId = ref<string | null>(null)

const spaMetaFetcher = {
	async fetchMeta(doctype: string) {
		const csrfToken = (window as any).csrf_token
			|| document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)?.[1]
			|| ""
		const res = await fetch("/api/method/frappe.client.get", {
			method: "POST",
			credentials: "include",
			headers: {
				"X-Frappe-CSRF-Token": csrfToken,
				"Content-Type": "application/json",
			},
			body: JSON.stringify({ doctype: "DocType", name: doctype }),
		})
		if (!res.ok) throw new Error(`Failed to fetch meta for ${doctype}: ${res.status}`)
		const result = await res.json()
		return result.message || { fields: [] }
	}
}

async function fetchDoctypeOptions() {
	try {
		const res = await fetch(
			'/api/resource/WP Tables?fields=["name"]&order_by=name asc&limit_page_length=0',
			{ credentials: 'include' }
		)
		if (!res.ok) return
		const json = await res.json()
		doctypeOptions.value = (json.data || []).map((r: any) => r.name)
	} catch {
		// WP Tables may not exist yet — leave empty
	}
}

function onElementContextMenu(id: string, event: MouseEvent) {
	if (!state.targetDoctype) {
		alert('Select a root DocType first')
		return
	}
	pathFinderTargetId.value = id
	selectElement(id)
	showPathFinder.value = true
}

function openPathFinder(id: string) {
	if (!state.targetDoctype) {
		alert('Select a root DocType first')
		return
	}
	pathFinderTargetId.value = id
	selectElement(id)
	showPathFinder.value = true
}

function onPathFinderSelect(payload: { mode: string, data: any }) {
	if (payload.mode === 'field' && pathFinderTargetId.value) {
		updateElement(pathFinderTargetId.value, {
			fieldPath: payload.data.resolve_key,
			fieldType: payload.data.terminal_fieldtype,
			terminalDoctype: payload.data.terminal_doctype,
			label: payload.data.formkit_schema?.label || payload.data.terminal_field
		})
	}
	showPathFinder.value = false
	pathFinderTargetId.value = null
}

function onPathFinderClose() {
	showPathFinder.value = false
	pathFinderTargetId.value = null
}

async function onSave() {
	if (!state.title) { alert("Please enter a form title."); return }
	if (!state.targetDoctype) { alert("Please enter a target DocType."); return }

	saving.value = true
	try {
		const savedName = await save()
		if (formName === "new" && savedName !== "new") {
			router.replace({ name: "FormBuilder", params: { formName: savedName } })
		}
		alert("Saved!")
	} catch (e: any) {
		alert(e.message || "Save failed")
	} finally {
		saving.value = false
	}
}

onMounted(async () => {
	fetchDoctypeOptions()
	if (formName !== "new") {
		await load()
	}
})
</script>

<style scoped>
.builder-page {
	display: flex;
	flex-direction: column;
	height: 100%;
	background: #ffffff;
}
.toolbar {
	height: 56px;
	border-bottom: 1px solid #e5e7eb;
	display: flex;
	align-items: center;
	padding: 0 16px;
	gap: 12px;
	flex-shrink: 0;
	background: #fff;
}
.title-input {
	font-size: 16px;
	font-weight: 600;
	border: none;
	outline: none;
	color: #111827;
	min-width: 200px;
}
.title-input::placeholder { color: #d1d5db; }
.toolbar-spacer { flex: 1; }
.toolbar-label { font-size: 12px; color: #6b7280; }
.toolbar-select {
	font-size: 13px;
	border: 1px solid #d1d5db;
	border-radius: 4px;
	padding: 4px 8px;
	min-width: 180px;
	background: #fff;
	color: #111827;
	cursor: pointer;
}
.toolbar-select:disabled { color: #9ca3af; cursor: wait; }
.save-btn {
	padding: 6px 20px;
	background: #111827;
	color: #fff;
	border: none;
	border-radius: 6px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	transition: background 150ms;
}
.save-btn:hover { background: #374151; }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.panels {
	display: flex;
	flex: 1;
	overflow: hidden;
}
.panel-left {
	width: 180px;
	border-right: 1px solid #e5e7eb;
	background: #f9fafb;
	padding: 12px;
	flex-shrink: 0;
}
.panel-right {
	width: 280px;
	border-left: 1px solid #e5e7eb;
	background: #f9fafb;
	padding: 12px;
	flex-shrink: 0;
	overflow-y: auto;
}
</style>
