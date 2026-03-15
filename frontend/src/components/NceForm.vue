<template>
	<FormKit
		type="form"
		:value="value"
		:actions="false"
		@submit="$emit('submit', $event)"
	>
		<div v-if="tabLayout && tabLayout.tabs.length">
			<div class="flex border-b mb-4">
				<button
					v-for="tab in tabLayout.tabs"
					:key="tab.key"
					type="button"
					class="px-4 py-2 text-sm font-medium border-b-2"
					:class="activeTab === tab.key
						? 'border-blue-500 text-blue-600'
						: 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
					@click="activeTab = tab.key"
				>
					{{ tab.label }}
				</button>
			</div>

			<div
				v-for="tab in tabLayout.tabs"
				:key="tab.key"
				v-show="activeTab === tab.key"
			>
				<FormKitSchema
					:schema="filterSchemaForTab(tab.fields)"
					:data="schemaData"
				/>
			</div>
		</div>

		<div v-else>
			<FormKitSchema :schema="schema" :data="schemaData" />
		</div>

		<div class="mt-4">
			<slot name="actions">
				<Button variant="solid" type="submit" :loading="loading">
					{{ submitLabel }}
				</Button>
			</slot>
		</div>
	</FormKit>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect } from "vue"
import { FormKitSchema } from "@formkit/vue"

interface Tab {
	key: string
	label: string
	fields: string[]
}

interface TabLayoutDef {
	tabs: Tab[]
}

const props = withDefaults(
	defineProps<{
		schema: any[]
		tabLayout?: TabLayoutDef | null
		value?: Record<string, any>
		loading?: boolean
		submitLabel?: string
	}>(),
	{
		tabLayout: null,
		value: () => ({}),
		loading: false,
		submitLabel: "Save",
	}
)

defineEmits<{
	(e: "submit", formData: Record<string, any>): void
}>()

const activeTab = ref("")

watchEffect(() => {
	if (props.tabLayout?.tabs?.length && !activeTab.value) {
		activeTab.value = props.tabLayout.tabs[0].key
	}
})

const schemaData = computed(() => ({}))

function getFieldName(item: any): string | null {
	if (item.$formkit) return item.name || null
	if (item.$cmp && item.props) return item.props.name || null
	return null
}

function filterSchemaForTab(fields: string[]): any[] {
	const fieldSet = new Set(fields)
	return props.schema.filter((item) => {
		const name = getFieldName(item)
		return name != null && fieldSet.has(name)
	})
}
</script>
