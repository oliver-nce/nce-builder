<template>
	<div id="nce-builder-app" class="h-screen flex">
		<aside class="w-56 border-r bg-gray-50 dark:bg-gray-900 flex flex-col">
			<div class="px-4 py-3 font-semibold text-sm text-gray-700 dark:text-gray-200 border-b">
				NCE Builder
			</div>
			<nav class="flex-1 px-2 py-3 space-y-1">
				<router-link
					v-for="link in navLinks"
					:key="link.to"
					:to="link.to"
					class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
					active-class="bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-200"
				>
					{{ link.label }}
				</router-link>
			</nav>
		</aside>
		<main class="flex-1 overflow-y-auto">
			<router-view />
		</main>
	</div>
</template>

<script setup lang="ts">
const navLinks = [
	{ label: "Theme Settings", to: "/nce/theme-settings" },
]
</script>
