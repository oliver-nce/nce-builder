<template>
	<div class="builder-page">
		<!-- Toolbar -->
		<header class="toolbar">
			<a href="/nce" class="home-btn" title="Back to NCE Builder">
				<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M3 10.5L10 3.5L17 10.5"/>
					<path d="M5 9v7a1 1 0 001 1h3v-4h2v4h3a1 1 0 001-1V9"/>
				</svg>
			</a>
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
			/>

			<aside class="panel-right">
				<PropertyPanel
					:element="selectedElement"
					:primary-color="primaryColor"
					:secondary-color="secondaryColor"
					@update="(id: string, changes: any) => updateElement(id, changes)"
					@delete="removeElement"
				/>
			</aside>
		</div>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue"
import { useRoute, useRouter } from "vue-router"
import { useBuilderState } from "@/composables/useBuilderState"
import ElementPalette from "@/components/builder/ElementPalette.vue"
import BuilderCanvas from "@/components/builder/BuilderCanvas.vue"
import PropertyPanel from "@/components/builder/PropertyPanel.vue"

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
	height: 100vh;
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
.home-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border-radius: 6px;
	color: #6b7280;
	text-decoration: none;
	transition: background 150ms, color 150ms;
	flex-shrink: 0;
}
.home-btn:hover { background: #f3f4f6; color: #111827; }
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
