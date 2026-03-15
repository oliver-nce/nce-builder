import { createRouter, createWebHistory } from "vue-router"

const routes = [
	{
		path: "/nce/theme-settings",
		name: "ThemeSettings",
		component: () => import("@/pages/ThemeSettingsPage.vue"),
	},
	{
		path: "/nce",
		redirect: "/nce/theme-settings",
	},
]

const router = createRouter({
	history: createWebHistory(),
	routes,
})

export default router
