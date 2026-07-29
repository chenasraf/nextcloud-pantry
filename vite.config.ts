import { createAppConfig } from '@nextcloud/vite-config'
import { existsSync, readFileSync, rmSync } from 'node:fs'
import { createRequire } from 'node:module'
import path from 'path'
import { visualizer } from 'rollup-plugin-visualizer'
import checker from 'vite-plugin-checker'

const require = createRequire(import.meta.url)

// Emit the zxing barcode-reader wasm as a local app asset and expose its
// hashed URL via a virtual module. This lets the scanner ponyfill (used on
// browsers without a native BarcodeDetector) load the wasm from the app origin
// instead of the package's default jsDelivr CDN, which Nextcloud's CSP blocks.
function zxingWasmAsset() {
  const virtualId = 'virtual:zxing-reader-wasm-url'
  const resolvedId = '\0' + virtualId
  return {
    name: 'pantry-zxing-wasm-asset',
    resolveId(id: string) {
      if (id === virtualId) return resolvedId
    },
    load(this: { emitFile: (f: object) => string }, id: string) {
      if (id === resolvedId) {
        const wasmPath = require.resolve('zxing-wasm/reader/zxing_reader.wasm')
        const ref = this.emitFile({
          type: 'asset',
          name: 'zxing_reader.wasm',
          source: readFileSync(wasmPath),
        })
        return `export default import.meta.ROLLUP_FILE_URL_${ref}`
      }
    },
  }
}

const manualChunksList = [
  'emoji-mart-vue-fast',
  'date-fns',
  'lodash',
  'floating-vue',
  'vue-material-design-icons',
]

const manualChunksGroups = {
  vue: ['vue-router', 'vue'],
}

const nextcloudSharedList = [
  'auth',
  'axios',
  'browser-storage',
  'capabilities',
  'event-bus',
  'files',
  'initial-state',
  'l10n',
  'logger',
  'paths',
  'router',
  'sharing',
]

// https://vite.dev/config/
export default createAppConfig(
  {
    app: path.resolve(path.join('src', 'app.ts')),
  },
  {
    emptyOutputDirectory: false,
    config: {
      root: 'src',
      resolve: {
        alias: {
          '@icons': path.resolve(__dirname, 'node_modules/vue-material-design-icons'),
          '@': path.resolve(__dirname, 'src'),
        },
      },
      plugins: [
        zxingWasmAsset(),
        {
          name: 'clean-dist-js',
          generateBundle() {
            for (const dir of ['dist/js', 'dist/css']) {
              const p = path.resolve(__dirname, dir)
              if (existsSync(p)) {
                rmSync(p, { recursive: true })
              }
            }
          },
        },
        checker({
          vueTsc: true,
        }),
        visualizer({
          open: process.env.VITE_BUILD_ANALYZE === 'true',
          filename: 'stats.html',
          template: 'treemap',
        }),
      ],
      build: {
        outDir: '../dist',
        manifest: true,
        cssCodeSplit: false,
        rollupOptions: {
          output: {
            entryFileNames: 'js/[name]-[hash].mjs',
            chunkFileNames: 'js/[name]-[hash].mjs',
            assetFileNames: '[ext]/[name]-[hash].[ext]',
            manualChunks(id) {
              if (!id.includes('node_modules')) {
                return
              }

              // Parse package path
              const parts = id.split('node_modules/')
              const pkgPath = parts[parts.length - 1]

              // Check for @nextcloud/xxx or nextcloud-xxx
              const ncMatch = pkgPath.match(/^@?nextcloud[/-]([^/]+)/)

              // Get the package name (e.g., 'auth', 'vue', 'axios')
              const ncPkgName = ncMatch?.[1]

              if (ncPkgName) {
                if (nextcloudSharedList.includes(ncPkgName)) {
                  return 'nextcloud-common'
                }
                return `nextcloud-${ncPkgName}`
              }

              for (const chunk of manualChunksList) {
                if (pkgPath.includes(chunk)) {
                  return chunk
                }
              }

              for (const [groupName, groupPackages] of Object.entries(manualChunksGroups)) {
                if (groupPackages.some((pkg) => pkgPath.includes(pkg))) {
                  return groupName
                }
              }

              // Fallback
              return 'vendor'
            },
          },
        },
      },
    },
  },
)
