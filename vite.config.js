import { defineConfig } from "vite";

const HTTP_PORT = 3004;
const HTTPS_PORT = 3005;

export default defineConfig(({ command }) => {
	// Set directly by DDEV inside the web container (.ddev/.env.web) — not read from
	// a project .env file, since this config's cwd has none.
	const primarySiteUrl = process.env.PRIMARY_SITE_URL ?? "https://craft-seo-test.ddev.site";

	return {
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
			origin: `${primarySiteUrl}:${HTTPS_PORT}`,
			allowedHosts: [new URL(primarySiteUrl).hostname],
		},
	};
});
