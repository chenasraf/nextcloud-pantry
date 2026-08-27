// @ts-check
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'astro/config'
import starlight from '@astrojs/starlight'

// Custom domain deploy. Update `site` to the final domain; a matching CNAME
// lives in public/CNAME. Served at the domain root, so `base` stays '/'.
export default defineConfig({
	site: 'https://pantry.casraf.dev',
	trailingSlash: 'ignore',
	// Define the `@` alias here rather than via tsconfig `paths`. website/ is a
	// self-contained project nested in a repo whose root tsconfig extends a
	// package that isn't installed in this isolated env; keeping tsconfig free of
	// `paths` stops Vite from scanning ancestor tsconfigs and choking on it.
	vite: {
		resolve: {
			alias: {
				'@': fileURLToPath(new URL('./src', import.meta.url)),
			},
		},
	},
	integrations: [
		starlight({
			title: 'Pantry',
			description:
				'The household list app that lives in your Nextcloud. Checklists, a photo board and a notes wall — shared with the people you live with, synced to a server you own.',
			logo: {
				src: './src/assets/pantry-icon.png',
				alt: 'Pantry',
			},
			favicon: '/brand/pantry-icon.png',
			customCss: ['./src/styles/custom.css'],
			social: [
				{
					icon: 'github',
					label: 'GitHub',
					href: 'https://github.com/chenasraf/nextcloud-pantry',
				},
			],
			// The landing page is a bespoke .astro route; docs live under /docs.
			pagefind: true,
			sidebar: [
				{
					label: 'Getting started',
					items: [
						{ label: 'Overview', slug: 'docs' },
						{ slug: 'docs/getting-started/install' },
						{ slug: 'docs/getting-started/pairing' },
					],
				},
				{
					label: 'Using Pantry',
					items: [{ autogenerate: { directory: 'docs/using' } }],
				},
				{
					label: 'Administration',
					items: [{ autogenerate: { directory: 'docs/administration' } }],
				},
			],
			components: {
				// Bespoke landing lives in src/pages/index.astro; keep Starlight's
				// chrome for everything under /docs.
			},
		}),
	],
})
