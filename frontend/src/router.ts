import { createRouter, createWebHistory } from "vue-router"

const routes = [
	{
		path: "/nce/theme-settings",
		name: "ThemeSettings",
		component: () => import("@/pages/ThemeSettingsPage.vue"),
	},
	{
		path: "/nce/preview",
		name: "ThemePreview",
		component: () => import("@/pages/ThemePreviewPage.vue"),
		meta: { standalone: true },
	},
	{
		path: "/nce/builder",
		redirect: "/nce/theme-settings",
	},
	{
		path: "/nce/builder/:formName",
		name: "FormBuilder",
		component: () => import("@/pages/FormBuilderPage.vue"),
		meta: { standalone: true },
	},
	{
		path: "/nce/form/:formName",
		name: "FormNew",
		component: () => import("@/pages/FormPage.vue"),
	},
	{
		path: "/nce/form/:formName/:docName",
		name: "FormEdit",
		component: () => import("@/pages/FormPage.vue"),
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
