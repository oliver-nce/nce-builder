export interface TabLayout {
	tabs: Array<{
		key: string
		label: string
		fields: string[]
	}>
}

export function mapFieldsToTabs(
	schema: any[],
	tabLayout: TabLayout
): Record<string, any[]> {
	const result: Record<string, any[]> = {}

	for (const tab of tabLayout.tabs) {
		result[tab.key] = []
	}
	result["__unassigned"] = []

	const fieldToTab = new Map<string, string>()
	for (const tab of tabLayout.tabs) {
		for (const fieldName of tab.fields) {
			fieldToTab.set(fieldName, tab.key)
		}
	}

	for (const item of schema) {
		const name = item.name || item.props?.name
		if (name && fieldToTab.has(name)) {
			result[fieldToTab.get(name)!].push(item)
		} else {
			result["__unassigned"].push(item)
		}
	}

	if (result["__unassigned"].length === 0) {
		delete result["__unassigned"]
	}

	return result
}

export function resolveFieldMapping(
	formData: Record<string, any>,
	fieldMapping: Record<string, string> | null
): Record<string, any> {
	if (!fieldMapping) return { ...formData }

	const result: Record<string, any> = {}
	for (const [key, value] of Object.entries(formData)) {
		const mappedKey = fieldMapping[key] || key
		result[mappedKey] = value
	}
	return result
}

export function evaluateCondition(
	expr: string,
	doc: Record<string, any>
): boolean {
	try {
		const safeExpr = expr.replace(/\$doc\./g, "doc.")
		return new Function("doc", `return ${safeExpr}`)(doc)
	} catch {
		return false
	}
}
