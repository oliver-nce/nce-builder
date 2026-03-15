/**
 * Minimal Tailwind-based FormKit theme.
 * Replace this file with a download from https://themes.formkit.com/editor
 * for a fully customized theme.
 */
export function rootClasses(
	sectionKey: string,
	_node: any
): Record<string, boolean> {
	const classes: Record<string, Record<string, boolean>> = {
		outer: { "mb-4 formkit-disabled:opacity-50": true },
		label: {
			"block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300": true,
		},
		inner: { "": true },
		input: {
			"w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100": true,
		},
		help: { "text-xs text-gray-500 mt-1": true },
		messages: { "list-none p-0 mt-1": true },
		message: { "text-red-600 text-xs mt-1": true },
		wrapper: { "": true },
		legend: { "block mb-1 text-sm font-medium text-gray-700": true },
		fieldset: { "border border-gray-300 rounded-md p-3": true },
		options: { "list-none p-0": true },
		option: { "mb-1": true },
	}
	return classes[sectionKey] ?? {}
}
