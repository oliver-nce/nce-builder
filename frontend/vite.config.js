import path from "node:path"
import vue from "@vitejs/plugin-vue"
import { defineConfig } from "vite"

const STUB_ICON = path.resolve(__dirname, "src/stub-icon.vue")

function stubIconsPlugin() {
	return {
		name: "stub-lucide-icons",
		resolveId(id) {
			if (id.startsWith("~icons/")) return STUB_ICON
		},
	}
}

export default defineConfig({
	plugins: [stubIconsPlugin(), vue()],
	resolve: {
		alias: {
			"@": path.resolve(__dirname, "src"),
			"tailwind.config.js": path.resolve(__dirname, "tailwind.config.js"),
		},
	},
	build: {
		chunkSizeWarningLimit: 1500,
		outDir: "../nce_builder/public/frontend",
		emptyOutDir: true,
		target: "es2015",
		sourcemap: true,
		rollupOptions: {
			output: {
				entryFileNames: "assets/nce-builder.js",
				chunkFileNames: "assets/nce-builder-[name].js",
				assetFileNames: "assets/nce-builder.[ext]",
			},
		},
	},
	optimizeDeps: {
		include: ["feather-icons", "showdown"],
	},
	server: {
		port: 8080,
		allowedHosts: true,
	},
})
