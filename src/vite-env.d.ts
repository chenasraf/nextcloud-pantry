/// <reference types="vite/client" />

declare module '@icons/*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<object, object, unknown>
  export default component
}

// Local zxing barcode-reader wasm URL, emitted by the zxingWasmAsset() Vite plugin.
declare module 'virtual:zxing-reader-wasm-url' {
  const url: string
  export default url
}
