<template>
	<div class="form-list-page">
		<div class="list-header">
			<h1 class="list-title">Forms</h1>
			<a href="/nce/builder/new" class="new-btn">+ New Form</a>
		</div>

		<div v-if="loading" class="status-msg">Loading…</div>
		<div v-else-if="error" class="status-msg error">{{ error }}</div>
		<div v-else-if="!forms.length" class="status-msg">No forms yet. Click "+ New Form" to create one.</div>

		<table v-else class="form-table">
			<thead>
				<tr>
					<th>Title</th>
					<th>DocType</th>
					<th>Form Name</th>
					<th>Enabled</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="f in forms" :key="f.name" class="form-row">
					<td class="cell-title">{{ f.title || f.name }}</td>
					<td>{{ f.target_doctype }}</td>
					<td class="cell-mono">{{ f.name }}</td>
					<td>{{ f.enabled ? 'Yes' : 'No' }}</td>
					<td class="cell-actions">
						<button @click="editForm(f.name)" class="action-btn edit-btn">Edit</button>
						<button @click="useForm(f.name)" class="action-btn use-btn">Use</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue"

interface FormDef {
	name: string
	title: string
	target_doctype: string
	enabled: number
}

const forms = ref<FormDef[]>([])
const loading = ref(true)
const error = ref("")

async function fetchForms() {
	try {
		const res = await fetch(
			'/api/resource/NCE Form Definition?fields=["name","title","target_doctype","enabled"]&order_by=title asc&limit_page_length=0',
			{ credentials: "include" }
		)
		if (!res.ok) throw new Error(`HTTP ${res.status}`)
		const json = await res.json()
		forms.value = json.data || []
	} catch (e: any) {
		error.value = e.message || "Failed to load forms"
	} finally {
		loading.value = false
	}
}

function editForm(name: string) {
	window.location.href = `/nce/builder/${encodeURIComponent(name)}`
}

function useForm(name: string) {
	window.location.href = `/nce/form/${encodeURIComponent(name)}`
}

onMounted(fetchForms)
</script>

<style scoped>
.form-list-page {
	max-width: 800px;
	margin: 0 auto;
	padding: 32px 24px;
}
.list-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 24px;
}
.list-title {
	font-size: 22px;
	font-weight: 700;
	color: #111827;
	margin: 0;
}
.new-btn {
	padding: 6px 16px;
	background: #111827;
	color: #fff;
	border-radius: 6px;
	font-size: 13px;
	font-weight: 600;
	text-decoration: none;
	transition: background 150ms;
}
.new-btn:hover { background: #374151; }
.status-msg {
	color: #6b7280;
	font-size: 14px;
	padding: 24px 0;
}
.status-msg.error { color: #dc2626; }
.form-table {
	width: 100%;
	border-collapse: collapse;
}
.form-table th {
	text-align: left;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #9ca3af;
	padding: 8px 12px;
	border-bottom: 1px solid #e5e7eb;
}
.form-table td {
	padding: 10px 12px;
	font-size: 13px;
	color: #374151;
	border-bottom: 1px solid #f3f4f6;
}
.form-row {
	transition: background 100ms;
}
.form-row:hover { background: #f9fafb; }
.cell-title { font-weight: 500; color: #111827; }
.cell-mono { font-family: monospace; font-size: 12px; color: #6b7280; }
.cell-actions {
	display: flex;
	gap: 8px;
}
.action-btn {
	padding: 4px 12px;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 500;
	border: 1px solid;
	cursor: pointer;
	transition: all 150ms;
}
.edit-btn {
	background: #fff;
	color: #4b5563;
	border-color: #d1d5db;
}
.edit-btn:hover {
	background: #f3f4f6;
	border-color: #9ca3af;
}
.use-btn {
	background: #3b82f6;
	color: #fff;
	border-color: #3b82f6;
}
.use-btn:hover {
	background: #2563eb;
	border-color: #2563eb;
}
</style>
