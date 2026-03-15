import { defaultConfig } from "@formkit/vue"
import { createProPlugin, inputs } from "@formkit/pro"
import { rootClasses } from "./formkit.theme"

const pro = createProPlugin("fk-4cdfdcd400", inputs)

export default defaultConfig({
	config: { rootClasses },
	plugins: [pro],
})
