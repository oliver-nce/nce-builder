import { createApp } from "vue"
import { plugin as formkitPlugin } from "@formkit/vue"
import {
	Button,
	frappeRequest,
	pageMetaPlugin,
	resourcesPlugin,
	setConfig,
} from "frappe-ui"

import App from "./App.vue"
import router from "./router"
import formkitConfig from "./formkit.config"
import "./index.css"

const app = createApp(App)

setConfig("resourceFetcher", frappeRequest)

app.use(router)
app.use(resourcesPlugin)
app.use(pageMetaPlugin)
app.use(formkitPlugin, formkitConfig)

app.component("Button", Button)

app.mount("#app")
