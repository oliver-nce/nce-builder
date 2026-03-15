import path from "node:path"
import { fileURLToPath } from "node:url"
import forms from "@tailwindcss/forms"
import typography from "@tailwindcss/typography"

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default {
	darkMode: ["selector", "[data-theme=\"dark\"]"],
	content: [
		"./index.html",
		"./src/**/*.{vue,js,ts,jsx,tsx}",
		path.resolve(__dirname, "node_modules/frappe-ui/src/components/**/*.{vue,js,ts,jsx,tsx}"),
	],
	theme: {
		extend: {},
	},
	plugins: [forms, typography],
}
