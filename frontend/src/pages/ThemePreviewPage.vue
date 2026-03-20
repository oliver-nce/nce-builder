<template>
	<div class="preview-page" :style="pageStyle">
		<!-- Floating banner -->
		<div class="sticky top-0 z-50 bg-gray-900 text-white text-center text-xs py-1.5 tracking-wide">
			LIVE THEME PREVIEW — changes from Theme Settings appear here instantly
		</div>

		<div class="max-w-5xl mx-auto px-8 py-10 space-y-12">

			<!-- ═══════ TYPOGRAPHY ═══════ -->
			<section>
				<h2 class="section-label">Typography</h2>
				<div class="preview-surface rounded-xl p-8 space-y-4">
					<h1 class="preview-heading text-4xl font-bold">Main Page Heading</h1>
					<h2 class="preview-heading text-2xl font-semibold">Section Heading</h2>
					<h3 class="preview-heading text-xl font-medium">Subsection Heading</h3>
					<h4 class="preview-heading text-lg font-medium">Card Title</h4>
					<p class="preview-text">
						The quick brown fox jumps over the lazy dog. Pack my box with five dozen
						liquor jugs. How vexingly quick daft zebras jump! Sphinx of black quartz,
						judge my vow. Two driven jocks help fax my big quiz.
					</p>
					<p class="preview-muted text-sm">
						This is muted helper text — used for descriptions, captions, and secondary information
						that shouldn't compete with primary content for attention.
					</p>
					<p>
						<a href="#" class="preview-link" @click.prevent>This is a hyperlink</a>
						&nbsp;·&nbsp;
						<a href="#" class="preview-link font-semibold" @click.prevent>Bold link variant</a>
					</p>
				</div>
			</section>

			<!-- ═══════ COLOUR SWATCHES ═══════ -->
			<section>
				<h2 class="section-label">Colour Palette</h2>
				<div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
					<div v-for="c in paletteColors" :key="c.var" class="text-center">
						<div
							class="h-14 rounded-lg mb-1.5"
							:style="{ backgroundColor: `var(${c.var}, #ccc)` }"
						/>
						<div class="text-xs font-medium preview-text">{{ c.label }}</div>
						<div class="text-[10px] preview-muted font-mono">{{ c.var }}</div>
					</div>
				</div>
			</section>

			<!-- ═══════ BUTTONS ═══════ -->
			<section>
				<h2 class="section-label">Buttons</h2>
				<div class="preview-surface rounded-xl p-8 space-y-4">
					<div class="flex flex-wrap gap-3">
						<button class="btn btn-primary">Primary Action</button>
						<button class="btn btn-secondary">Secondary</button>
						<button class="btn btn-accent">Accent</button>
						<button class="btn btn-success">Success</button>
						<button class="btn btn-warning">Warning</button>
						<button class="btn btn-danger">Danger</button>
						<button class="btn btn-info">Info</button>
					</div>
					<div class="flex flex-wrap gap-3">
						<button class="btn btn-outline-primary">Outline Primary</button>
						<button class="btn btn-outline-secondary">Outline Secondary</button>
						<button class="btn btn-outline-danger">Outline Danger</button>
						<button class="btn btn-ghost">Ghost Button</button>
					</div>
					<div class="flex flex-wrap gap-3 items-center">
						<button class="btn btn-primary text-xs px-3 py-1">Small</button>
						<button class="btn btn-primary">Medium</button>
						<button class="btn btn-primary text-lg px-6 py-3">Large</button>
						<button class="btn btn-primary opacity-50 cursor-not-allowed">Disabled</button>
					</div>
				</div>
			</section>

			<!-- ═══════ FORM ELEMENTS ═══════ -->
			<section>
				<h2 class="section-label">Form Elements</h2>
				<div class="preview-surface rounded-xl p-8">
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<div>
							<label class="field-label">Full Name</label>
							<input type="text" class="field-input" value="Alex Johnson" />
						</div>
						<div>
							<label class="field-label">Email Address</label>
							<input type="email" class="field-input" value="alex@example.com" />
						</div>
						<div>
							<label class="field-label">Department</label>
							<select class="field-input">
								<option>Engineering</option>
								<option>Design</option>
								<option>Marketing</option>
								<option>Sales</option>
							</select>
						</div>
						<div>
							<label class="field-label">Priority</label>
							<select class="field-input">
								<option>Low</option>
								<option selected>Medium</option>
								<option>High</option>
								<option>Urgent</option>
							</select>
						</div>
						<div class="md:col-span-2">
							<label class="field-label">Description</label>
							<textarea class="field-input" rows="3">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</textarea>
						</div>
						<div class="flex items-center gap-6">
							<label class="flex items-center gap-2 text-sm preview-text">
								<input type="checkbox" class="field-checkbox" checked /> Active
							</label>
							<label class="flex items-center gap-2 text-sm preview-text">
								<input type="checkbox" class="field-checkbox" /> Featured
							</label>
							<label class="flex items-center gap-2 text-sm preview-text">
								<input type="checkbox" class="field-checkbox" /> Notify
							</label>
						</div>
						<div class="flex items-center gap-6">
							<label class="flex items-center gap-2 text-sm preview-text">
								<input type="radio" name="status" class="field-radio" checked /> Draft
							</label>
							<label class="flex items-center gap-2 text-sm preview-text">
								<input type="radio" name="status" class="field-radio" /> Published
							</label>
							<label class="flex items-center gap-2 text-sm preview-text">
								<input type="radio" name="status" class="field-radio" /> Archived
							</label>
						</div>
					</div>
				</div>
			</section>

			<!-- ═══════ BADGES & TAGS ═══════ -->
			<section>
				<h2 class="section-label">Badges &amp; Tags</h2>
				<div class="preview-surface rounded-xl p-8 space-y-4">
					<div class="flex flex-wrap gap-2">
						<span class="badge badge-primary">Primary</span>
						<span class="badge badge-secondary">Secondary</span>
						<span class="badge badge-accent">Accent</span>
						<span class="badge badge-success">Approved</span>
						<span class="badge badge-warning">Pending</span>
						<span class="badge badge-danger">Rejected</span>
						<span class="badge badge-info">Information</span>
					</div>
					<div class="flex flex-wrap gap-2">
						<span class="pill pill-primary">v2.4.1</span>
						<span class="pill pill-success">Active</span>
						<span class="pill pill-warning">Beta</span>
						<span class="pill pill-danger">Deprecated</span>
						<span class="pill pill-muted">Draft</span>
					</div>
				</div>
			</section>

			<!-- ═══════ ALERTS ═══════ -->
			<section>
				<h2 class="section-label">Alerts &amp; Banners</h2>
				<div class="space-y-3">
					<div class="alert alert-success">
						<strong>Success!</strong> Your changes have been saved successfully.
					</div>
					<div class="alert alert-info">
						<strong>Info:</strong> A new software update is available for download.
					</div>
					<div class="alert alert-warning">
						<strong>Warning:</strong> Your session will expire in 5 minutes.
					</div>
					<div class="alert alert-danger">
						<strong>Error:</strong> Unable to process your request. Please try again later.
					</div>
				</div>
			</section>

			<!-- ═══════ DATA TABLE ═══════ -->
			<section>
				<h2 class="section-label">Data Table</h2>
				<div class="preview-surface rounded-xl overflow-hidden">
					<table class="w-full text-sm">
						<thead>
							<tr class="table-header">
								<th class="text-left px-4 py-3 font-semibold">Name</th>
								<th class="text-left px-4 py-3 font-semibold">Email</th>
								<th class="text-left px-4 py-3 font-semibold">Role</th>
								<th class="text-left px-4 py-3 font-semibold">Status</th>
								<th class="text-right px-4 py-3 font-semibold">Score</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="(row, i) in tableRows"
								:key="i"
								class="table-row"
								:class="i % 2 === 0 ? 'table-row-even' : 'table-row-odd'"
							>
								<td class="px-4 py-3 font-medium preview-text">{{ row.name }}</td>
								<td class="px-4 py-3 preview-muted">{{ row.email }}</td>
								<td class="px-4 py-3 preview-text">{{ row.role }}</td>
								<td class="px-4 py-3">
									<span
										class="badge text-xs"
										:class="'badge-' + row.statusType"
									>{{ row.status }}</span>
								</td>
								<td class="px-4 py-3 text-right font-mono preview-text">{{ row.score }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- ═══════ CARDS ═══════ -->
			<section>
				<h2 class="section-label">Cards</h2>
				<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
					<div class="card">
						<div class="text-3xl font-bold preview-heading">1,247</div>
						<div class="text-sm preview-muted mt-1">Total Registrations</div>
						<div class="text-xs mt-2" style="color: var(--nce-color-success)">↑ 12% from last month</div>
					</div>
					<div class="card">
						<div class="text-3xl font-bold preview-heading">$34,500</div>
						<div class="text-sm preview-muted mt-1">Revenue This Quarter</div>
						<div class="text-xs mt-2" style="color: var(--nce-color-warning)">→ On track</div>
					</div>
					<div class="card">
						<div class="text-3xl font-bold preview-heading">98.2%</div>
						<div class="text-sm preview-muted mt-1">Uptime SLA</div>
						<div class="text-xs mt-2" style="color: var(--nce-color-danger)">↓ 0.3% from target</div>
					</div>
				</div>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
					<div class="card">
						<h3 class="preview-heading font-semibold text-lg mb-2">Recent Activity</h3>
						<ul class="space-y-3">
							<li v-for="a in activities" :key="a.text" class="flex items-start gap-3">
								<span
									class="w-2 h-2 rounded-full mt-1.5 shrink-0"
									:style="{ backgroundColor: `var(--nce-color-${a.color})` }"
								/>
								<div>
									<span class="text-sm preview-text">{{ a.text }}</span>
									<span class="text-xs preview-muted ml-2">{{ a.time }}</span>
								</div>
							</li>
						</ul>
					</div>
					<div class="card">
						<h3 class="preview-heading font-semibold text-lg mb-2">Quick Links</h3>
						<div class="grid grid-cols-2 gap-2">
							<a
								v-for="link in quickLinks"
								:key="link"
								href="#"
								class="block p-3 rounded-lg text-sm text-center transition-colors"
								style="background-color: var(--nce-color-surface); color: var(--nce-color-text)"
								@click.prevent
							>{{ link }}</a>
						</div>
					</div>
				</div>
			</section>

			<!-- ═══════ PROGRESS BARS ═══════ -->
			<section>
				<h2 class="section-label">Progress Bars</h2>
				<div class="preview-surface rounded-xl p-8 space-y-5">
					<div v-for="bar in progressBars" :key="bar.label">
						<div class="flex justify-between text-sm mb-1">
							<span class="preview-text font-medium">{{ bar.label }}</span>
							<span class="preview-muted">{{ bar.pct }}%</span>
						</div>
						<div class="w-full h-2.5 rounded-full" style="background-color: var(--nce-color-border)">
							<div
								class="h-full rounded-full transition-all"
								:style="{
									width: bar.pct + '%',
									backgroundColor: `var(--nce-color-${bar.color})`,
								}"
							/>
						</div>
					</div>
				</div>
			</section>

			<!-- ═══════ NAVIGATION / BREADCRUMBS / TABS ═══════ -->
			<section>
				<h2 class="section-label">Navigation</h2>
				<div class="preview-surface rounded-xl p-8 space-y-6">
					<!-- Breadcrumb -->
					<nav class="flex items-center gap-2 text-sm">
						<a href="#" class="preview-link" @click.prevent>Home</a>
						<span class="preview-muted">/</span>
						<a href="#" class="preview-link" @click.prevent>Settings</a>
						<span class="preview-muted">/</span>
						<span class="preview-text font-medium">Theme</span>
					</nav>
					<!-- Tabs -->
					<div class="flex gap-1" style="border-bottom: 2px solid var(--nce-color-border)">
						<button
							v-for="(t, i) in ['Overview', 'Settings', 'Members', 'Billing']"
							:key="t"
							class="px-4 py-2 text-sm font-medium -mb-px"
							:style="i === 1
								? { borderBottom: '2px solid var(--nce-color-primary)', color: 'var(--nce-color-primary)' }
								: { borderBottom: '2px solid transparent', color: 'var(--nce-color-muted)' }
							"
						>{{ t }}</button>
					</div>
					<!-- Pagination -->
					<div class="flex items-center gap-1">
						<button class="pagination-btn preview-muted">← Prev</button>
						<button
							v-for="p in [1,2,3,4,5]"
							:key="p"
							class="pagination-btn"
							:style="p === 2
								? { backgroundColor: 'var(--nce-color-primary)', color: 'white' }
								: { color: 'var(--nce-color-text)' }
							"
						>{{ p }}</button>
						<button class="pagination-btn preview-muted">Next →</button>
					</div>
				</div>
			</section>

			<!-- ═══════ SHADOW COMPARISON ═══════ -->
			<section>
				<h2 class="section-label">Shadows &amp; Radii</h2>
				<div class="grid grid-cols-4 md:grid-cols-7 gap-4">
					<div
						v-for="s in ['none','sm','md','lg','xl','2xl','3xl']"
						:key="s"
						class="p-6 text-center text-sm preview-text"
						:style="{
							backgroundColor: 'var(--nce-color-surface)',
							borderRadius: 'var(--nce-border-radius)',
							boxShadow: getDynamicShadow(s),
						}"
					>{{ s }}</div>
				</div>
			</section>

		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from "vue"

// ─── Static data ──────────────────────────────────────────────────

const paletteColors = [
	{ label: "Primary", var: "--nce-color-primary" },
	{ label: "Secondary", var: "--nce-color-secondary" },
	{ label: "Accent", var: "--nce-color-accent" },
	{ label: "Success", var: "--nce-color-success" },
	{ label: "Warning", var: "--nce-color-warning" },
	{ label: "Danger", var: "--nce-color-danger" },
	{ label: "Info", var: "--nce-color-info" },
]

const tableRows = [
	{ name: "Sarah Chen", email: "sarah@nce.com", role: "Admin", status: "Active", statusType: "success", score: "94.2" },
	{ name: "Marcus Webb", email: "marcus@nce.com", role: "Coach", status: "Active", statusType: "success", score: "87.5" },
	{ name: "Lisa Patel", email: "lisa@nce.com", role: "Manager", status: "Pending", statusType: "warning", score: "91.0" },
	{ name: "James Kim", email: "james@nce.com", role: "Player", status: "Inactive", statusType: "danger", score: "76.3" },
	{ name: "Emily Ross", email: "emily@nce.com", role: "Coach", status: "Active", statusType: "success", score: "88.9" },
	{ name: "David Okafor", email: "david@nce.com", role: "Scout", status: "Review", statusType: "info", score: "82.1" },
]

const activities = [
	{ text: "New registration: Sarah Chen", color: "success", time: "2m ago" },
	{ text: "Payment received — Invoice #1042", color: "primary", time: "15m ago" },
	{ text: "Session cancelled: U14 Training", color: "warning", time: "1h ago" },
	{ text: "Failed login attempt from 192.168.1.4", color: "danger", time: "3h ago" },
	{ text: "Report generated: Monthly Summary", color: "info", time: "5h ago" },
]

const quickLinks = ["Dashboard", "Players", "Events", "Reports", "Invoices", "Settings"]

const progressBars = [
	{ label: "Registrations", pct: 78, color: "primary" },
	{ label: "Revenue Target", pct: 62, color: "success" },
	{ label: "Capacity Used", pct: 91, color: "warning" },
	{ label: "Support Tickets", pct: 34, color: "danger" },
	{ label: "Tasks Completed", pct: 55, color: "info" },
]

// Shadow definitions using CSS color-mix with dynamic shadow color
const shadowDefs: Record<string, Array<[number, number, number, number, number]>> = {
	none: [],
	sm: [[0, 1, 2, 0, 0.05]],
	md: [[0, 4, 6, -1, 0.1], [0, 2, 4, -2, 0.1]],
	lg: [[0, 10, 15, -3, 0.1], [0, 4, 6, -4, 0.1]],
	xl: [[0, 20, 25, -5, 0.1], [0, 8, 10, -6, 0.1]],
	"2xl": [[0, 25, 50, -12, 0.25]],
	"3xl": [[0, 35, 60, -15, 0.3]],
}

function getDynamicShadow(level: string): string {
	const defs = shadowDefs[level] || shadowDefs.md
	if (!defs.length) return "none"
	return defs
		.map(([x, y, blur, spread, opacity]) => {
			const pct = Math.round(opacity * 100)
			return `${x}px ${y}px ${blur}px ${spread}px color-mix(in srgb, var(--nce-shadow-color, #000000) ${pct}%, transparent)`
		})
		.join(", ")
}

// ─── Page style (applies CSS vars to root for fonts) ──────────────

const pageStyle = computed(() => ({
	fontFamily: "var(--nce-font-family, 'Inter', sans-serif)",
	fontSize: "var(--nce-font-size, 14px)",
	lineHeight: "var(--nce-line-height, 1.5)",
	fontWeight: "var(--nce-font-weight, 400)",
}))

// ─── Listen for live theme updates from the editor ────────────────

function handleMessage(event: MessageEvent) {
	if (event.data?.type !== "nce-theme-update") return
	const root = document.documentElement
	for (const [key, value] of Object.entries(event.data.variables as Record<string, string>)) {
		root.style.setProperty(key, value)
	}
}

onMounted(() => window.addEventListener("message", handleMessage))
onUnmounted(() => window.removeEventListener("message", handleMessage))
</script>

<style scoped>
/* ── Semantic classes mapped to CSS variables ── */

.section-label {
	font-size: 0.6875rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.1em;
	color: var(--nce-color-muted, #6b7280);
	margin-bottom: 0.75rem;
}

.preview-page {
	background-color: var(--nce-color-bg, #ffffff);
	min-height: 100vh;
}

.preview-surface {
	background-color: var(--nce-color-surface, #f9fafb);
	border: 1px solid var(--nce-color-border, #e5e7eb);
	box-shadow: var(--nce-shadow, 0 1px 2px 0 rgb(0 0 0 / 0.05));
}

.preview-heading {
	color: var(--nce-color-heading, #111827);
	font-family: var(--nce-font-heading, var(--nce-font-family, inherit));
}

.preview-text {
	color: var(--nce-color-text, #1f2937);
}

.preview-muted {
	color: var(--nce-color-muted, #6b7280);
}

.preview-link {
	color: var(--nce-color-link, #3b82f6);
	text-decoration: underline;
	text-underline-offset: 2px;
}
.preview-link:hover {
	opacity: 0.8;
}

/* ── Buttons ── */

.btn {
	padding: 0.5rem 1rem;
	font-size: 0.875rem;
	font-weight: 500;
	border-radius: var(--nce-border-radius, 0.375rem);
	transition: all var(--nce-transition-speed, 200ms) ease;
	border: 1px solid transparent;
	cursor: pointer;
	color: white;
}
.btn:hover { opacity: 0.9; }

.btn-primary { background-color: var(--nce-color-primary, #3b82f6); }
.btn-secondary { background-color: var(--nce-color-secondary, #10b981); }
.btn-accent { background-color: var(--nce-color-accent, #8b5cf6); }
.btn-success { background-color: var(--nce-color-success, #10b981); }
.btn-warning { background-color: var(--nce-color-warning, #f59e0b); color: #1f2937; }
.btn-danger { background-color: var(--nce-color-danger, #ef4444); }
.btn-info { background-color: var(--nce-color-info, #3b82f6); }

.btn-outline-primary {
	background: transparent;
	border-color: var(--nce-color-primary, #3b82f6);
	color: var(--nce-color-primary, #3b82f6);
}
.btn-outline-secondary {
	background: transparent;
	border-color: var(--nce-color-secondary, #10b981);
	color: var(--nce-color-secondary, #10b981);
}
.btn-outline-danger {
	background: transparent;
	border-color: var(--nce-color-danger, #ef4444);
	color: var(--nce-color-danger, #ef4444);
}
.btn-ghost {
	background: transparent;
	color: var(--nce-color-text, #1f2937);
}
.btn-ghost:hover {
	background-color: var(--nce-color-surface, #f9fafb);
}

/* ── Form fields ── */

.field-label {
	display: block;
	font-size: 0.8125rem;
	font-weight: 500;
	color: var(--nce-color-text, #1f2937);
	margin-bottom: 0.375rem;
}

.field-input {
	width: 100%;
	border: 1px solid var(--nce-color-border, #e5e7eb);
	border-radius: var(--nce-border-radius, 0.375rem);
	padding: 0.5rem 0.75rem;
	font-size: 0.875rem;
	color: var(--nce-color-text, #1f2937);
	background-color: var(--nce-color-bg, #ffffff);
	transition: border-color var(--nce-transition-speed, 200ms) ease,
				box-shadow var(--nce-transition-speed, 200ms) ease;
}
.field-input:focus {
	outline: none;
	border-color: var(--nce-color-focus, #3b82f6);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--nce-color-focus, #3b82f6) 20%, transparent);
}

.field-checkbox,
.field-radio {
	accent-color: var(--nce-color-primary, #3b82f6);
}

/* ── Badges & Pills ── */

.badge {
	display: inline-block;
	padding: 0.125rem 0.625rem;
	border-radius: var(--nce-border-radius, 0.375rem);
	font-size: 0.75rem;
	font-weight: 500;
}
.badge-primary { background-color: color-mix(in srgb, var(--nce-color-primary) 15%, transparent); color: var(--nce-color-primary); }
.badge-secondary { background-color: color-mix(in srgb, var(--nce-color-secondary) 15%, transparent); color: var(--nce-color-secondary); }
.badge-accent { background-color: color-mix(in srgb, var(--nce-color-accent) 15%, transparent); color: var(--nce-color-accent); }
.badge-success { background-color: color-mix(in srgb, var(--nce-color-success) 15%, transparent); color: var(--nce-color-success); }
.badge-warning { background-color: color-mix(in srgb, var(--nce-color-warning) 15%, transparent); color: var(--nce-color-warning); }
.badge-danger { background-color: color-mix(in srgb, var(--nce-color-danger) 15%, transparent); color: var(--nce-color-danger); }
.badge-info { background-color: color-mix(in srgb, var(--nce-color-info) 15%, transparent); color: var(--nce-color-info); }

.pill {
	display: inline-block;
	padding: 0.125rem 0.75rem;
	border-radius: 9999px;
	font-size: 0.6875rem;
	font-weight: 600;
	color: white;
}
.pill-primary { background-color: var(--nce-color-primary); }
.pill-success { background-color: var(--nce-color-success); }
.pill-warning { background-color: var(--nce-color-warning); color: #1f2937; }
.pill-danger { background-color: var(--nce-color-danger); }
.pill-muted { background-color: var(--nce-color-muted); }

/* ── Alerts ── */

.alert {
	padding: 0.875rem 1rem;
	border-radius: var(--nce-border-radius, 0.375rem);
	font-size: 0.875rem;
	border-left: 4px solid;
}
.alert-success {
	background-color: color-mix(in srgb, var(--nce-color-success) 8%, var(--nce-color-bg, white));
	border-left-color: var(--nce-color-success);
	color: var(--nce-color-text);
}
.alert-info {
	background-color: color-mix(in srgb, var(--nce-color-info) 8%, var(--nce-color-bg, white));
	border-left-color: var(--nce-color-info);
	color: var(--nce-color-text);
}
.alert-warning {
	background-color: color-mix(in srgb, var(--nce-color-warning) 8%, var(--nce-color-bg, white));
	border-left-color: var(--nce-color-warning);
	color: var(--nce-color-text);
}
.alert-danger {
	background-color: color-mix(in srgb, var(--nce-color-danger) 8%, var(--nce-color-bg, white));
	border-left-color: var(--nce-color-danger);
	color: var(--nce-color-text);
}

/* ── Table ── */

.table-header {
	background-color: var(--nce-color-surface, #f9fafb);
	color: var(--nce-color-heading, #111827);
	border-bottom: 2px solid var(--nce-color-border, #e5e7eb);
}
.table-row {
	border-bottom: 1px solid var(--nce-color-border, #e5e7eb);
	transition: background-color var(--nce-transition-speed, 200ms) ease;
}
.table-row:hover {
	background-color: color-mix(in srgb, var(--nce-color-primary) 5%, transparent);
}
.table-row-even {
	background-color: var(--nce-color-bg, #ffffff);
}
.table-row-odd {
	background-color: var(--nce-color-row-alt, #f3f4f6);
}

/* ── Cards ── */

.card {
	background-color: var(--nce-color-surface, #f9fafb);
	border: 1px solid var(--nce-color-border, #e5e7eb);
	border-radius: var(--nce-border-radius, 0.375rem);
	padding: 1.25rem;
	box-shadow: var(--nce-shadow, 0 1px 2px 0 rgb(0 0 0 / 0.05));
	transition: box-shadow var(--nce-transition-speed, 200ms) ease;
}

/* ── Pagination ── */

.pagination-btn {
	width: 2rem;
	height: 2rem;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: var(--nce-border-radius, 0.375rem);
	font-size: 0.8125rem;
	cursor: pointer;
	border: none;
	background: transparent;
	transition: background-color var(--nce-transition-speed, 200ms) ease;
}
.pagination-btn:hover {
	background-color: var(--nce-color-surface, #f9fafb);
}
</style>
