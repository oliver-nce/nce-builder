import { ref } from "vue"
import { createResource } from "frappe-ui"

const themeLoaded = ref(false)

export function useTheme() {
	const themeSettings = createResource({
		url: "frappe.client.get",
		params: { doctype: "NCE Theme Settings", name: "NCE Theme Settings" },
	})

	async function loadTheme() {
		if (themeLoaded.value) return
		await themeSettings.reload()
		if (themeSettings.data) {
			applyThemeVars(themeSettings.data)
			themeLoaded.value = true
		}
	}

	function applyThemeVars(settings: Record<string, any>) {
		const root = document.documentElement
		if (settings.primary_color) {
			root.style.setProperty("--nce-color-primary", settings.primary_color)
		}
		if (settings.font_family) {
			root.style.setProperty("--nce-font-family", `'${settings.font_family}', sans-serif`)
		}
	}

	return { themeSettings, loadTheme, themeLoaded }
}
