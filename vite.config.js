import { defineConfig } from "vite";

const HTTP_PORT = 3004;

export default defineConfig(({ command }) => ({
	base: command === "serve" ? "" : "/dist/",
	build: {
		outDir: "./src/web/assets/dist",
		manifest: true,
		emptyOutDir: true,
		rollupOptions: {
			input: ["src/web/assets/admin/admin.js"],
		},
	},
	server: {
		host: "0.0.0.0",
		port: HTTP_PORT,
		strictPort: true,
		cors: true,
		allowedHosts: ["hoevebakkerij.ddev.site"],
	},
}));
