<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <form class="pantry-barcode-form" autocomplete="off" @submit.prevent="submit">
      <p class="pantry-barcode-form__hint">{{ strings.hint }}</p>
      <!-- eslint-disable-next-line vue/no-v-html -- trusted, static link markup from t() -->
      <p class="pantry-barcode-form__disclaimer" v-html="strings.disclaimer"></p>

      <div v-if="scanning" class="pantry-barcode-form__scanner">
        <video ref="videoRef" class="pantry-barcode-form__video" muted playsinline></video>
        <NcButton class="pantry-barcode-form__scan-stop" @click="stopScan">
          <template #icon>
            <CloseIcon :size="20" />
          </template>
          {{ strings.stopScan }}
        </NcButton>
      </div>

      <div v-else class="pantry-barcode-form__entry">
        <NcTextField
          v-model="value"
          class="pantry-barcode-form__field"
          :label="strings.eanLabel"
          :placeholder="strings.eanPlaceholder"
          inputmode="numeric"
          autocomplete="off"
          :error="!!error"
          :helper-text="error ?? ''"
        />
        <NcButton
          v-if="scanSupported"
          class="pantry-barcode-form__scan-start"
          :aria-label="strings.scan"
          :title="strings.scan"
          @click="startScan"
        >
          <template #icon>
            <BarcodeScanIcon :size="20" />
          </template>
          {{ strings.scan }}
        </NcButton>
      </div>

      <p v-if="scanError" class="pantry-barcode-form__error">{{ scanError }}</p>
    </form>
    <template #actions>
      <NcButton @click="$emit('update:open', false)">{{ strings.cancel }}</NcButton>
      <NcButton variant="primary" :disabled="loading || scanning || !isValid" @click="submit">
        {{ loading ? strings.looking : strings.lookUp }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import BarcodeScanIcon from '@icons/BarcodeScan.vue'
import CloseIcon from '@icons/Close.vue'
import { lookupBarcode, resolveExternally, saveBarcode, type BarcodeResult } from '@/api/barcode'
// The zxing reader wasm, emitted as a local app asset (hashed URL). Used only as
// the ponyfill fallback for browsers without a native BarcodeDetector
// (Firefox/Safari). Serving it from the app origin — rather than the package's
// default jsDelivr CDN — keeps it within Nextcloud's CSP.
import zxingReaderWasmUrl from 'virtual:zxing-reader-wasm-url'

// Minimal structural type covering both the native BarcodeDetector and the
// barcode-detector ponyfill.
type BarcodeDetectorInstance = {
  detect: (source: CanvasImageSource) => Promise<{ rawValue: string }[]>
}
type BarcodeDetectorCtor = new (opts: { formats: string[] }) => BarcodeDetectorInstance

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  /** ean is always the entered code; result is null when the product is unknown. */
  resolved: [ean: string, result: BarcodeResult | null]
}>()

const value = ref('')
const error = ref<string | null>(null)
const loading = ref(false)

// ----- Camera scanning -----
const scanning = ref(false)
const scanError = ref<string | null>(null)
const videoRef = ref<HTMLVideoElement | null>(null)
// Scanning needs a camera API. The decoder is the browser's native
// BarcodeDetector when available (Chrome/Edge/Android — no wasm), otherwise the
// zxing wasm ponyfill served locally (Firefox/Safari). The app relaxes its CSP
// with allowEvalWasm() so the wasm can execute.
const scanSupported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia

// Non-reactive handles for the live scan session.
let stream: MediaStream | null = null
let scanTimer: ReturnType<typeof setInterval> | null = null
let detecting = false

const isValid = computed(() => /^\d{6,14}$/.test(value.value.trim()))

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      value.value = ''
      error.value = null
      scanError.value = null
      loading.value = false
    } else {
      stopScan()
    }
  },
)

onBeforeUnmount(stopScan)

async function startScan() {
  scanError.value = null
  scanning.value = true
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' },
      audio: false,
    })
    // Wait a tick for the <video> to render, then attach the stream.
    await nextFrame()
    const video = videoRef.value
    if (!video) {
      throw new Error('video element unavailable')
    }
    video.srcObject = stream
    await video.play()

    // Prefer the browser's native BarcodeDetector (no wasm). Fall back to the
    // zxing ponyfill, lazy-loaded and pointed at the locally-served wasm.
    const native = (window as unknown as { BarcodeDetector?: BarcodeDetectorCtor }).BarcodeDetector
    let DetectorCtor: BarcodeDetectorCtor
    if (native) {
      DetectorCtor = native
    } else {
      const mod = await import('barcode-detector/ponyfill')
      mod.setZXingModuleOverrides({
        locateFile: (filePath: string, prefix: string) =>
          filePath.endsWith('.wasm') ? zxingReaderWasmUrl : prefix + filePath,
      })
      DetectorCtor = mod.BarcodeDetector as unknown as BarcodeDetectorCtor
    }
    const detector = new DetectorCtor({
      formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e'],
    })

    scanTimer = setInterval(() => void detectFrame(detector, video), 350)
  } catch (err: unknown) {
    scanError.value = errorToMessage(err)
    stopScan()
  }
}

async function detectFrame(detector: BarcodeDetectorInstance, video: HTMLVideoElement) {
  if (detecting || !scanning.value) return
  detecting = true
  try {
    const codes = await detector.detect(video)
    const raw = codes.find((c) => /^\d{6,14}$/.test(c.rawValue))?.rawValue
    if (raw) {
      value.value = raw
      stopScan()
      void submit()
    }
  } catch {
    // Transient decode errors are expected between frames — ignore.
  } finally {
    detecting = false
  }
}

function stopScan() {
  scanning.value = false
  detecting = false
  if (scanTimer !== null) {
    clearInterval(scanTimer)
    scanTimer = null
  }
  if (stream) {
    stream.getTracks().forEach((track) => track.stop())
    stream = null
  }
  if (videoRef.value) {
    videoRef.value.srcObject = null
  }
}

function nextFrame(): Promise<void> {
  return new Promise((resolve) => requestAnimationFrame(() => resolve()))
}

function errorToMessage(err: unknown): string {
  const name = typeof err === 'object' && err !== null && 'name' in err ? String(err.name) : ''
  if (name === 'NotAllowedError' || name === 'SecurityError') {
    return strings.cameraDenied
  }
  if (name === 'NotFoundError' || name === 'OverconstrainedError') {
    return strings.cameraMissing
  }
  return strings.cameraError
}

async function submit() {
  const ean = value.value.trim()
  if (!/^\d{6,14}$/.test(ean)) {
    error.value = strings.invalid
    return
  }
  error.value = null
  loading.value = true
  try {
    // 1. Shared cache — instant, no external call in the common case.
    let result = await lookupBarcode(ean)
    if (!result) {
      // 2. Cache miss — resolve from the user's own IP, then write back so it
      //    becomes a permanent free hit for the whole house.
      result = await resolveExternally(ean)
      if (result) {
        try {
          await saveBarcode(result)
        } catch {
          // Write-back is best-effort; a failure just means the next scan
          // re-resolves. Never block the user on it.
        }
      }
    }
    emit('resolved', ean, result)
    emit('update:open', false)
  } finally {
    loading.value = false
  }
}

const strings = {
  // TRANSLATORS: Title of the dialog for looking up a product by its barcode.
  title: t('pantry', 'Look up by barcode'),
  hint: t('pantry', 'Enter or scan a product barcode (EAN/UPC) to fill in the item details.'),
  disclaimer: t(
    'pantry',
    'Products provided by {linkStart}Open Food Facts{linkEnd}, an external community database that Pantry does not own or control.',
    {
      linkStart:
        '<a href="https://world.openfoodfacts.org" target="_blank" rel="noreferrer noopener">',
      linkEnd: '</a>',
    },
    { escape: false },
  ),
  // TRANSLATORS: Label of the text field where the barcode number is typed.
  eanLabel: t('pantry', 'Barcode'),
  eanPlaceholder: t('pantry', 'e.g. 4001724819103'),
  invalid: t('pantry', 'Enter a valid barcode (6 to 14 digits).'),
  cancel: t('pantry', 'Cancel'),
  // TRANSLATORS: Verb, button that looks up the entered barcode.
  lookUp: t('pantry', 'Look up'),
  looking: t('pantry', 'Looking up …'),
  // TRANSLATORS: Verb, button that opens the camera to scan a barcode.
  scan: t('pantry', 'Scan'),
  // TRANSLATORS: Verb, button that stops the camera barcode scanner.
  stopScan: t('pantry', 'Stop scanning'),
  cameraDenied: t(
    'pantry',
    'Camera access was denied. Allow it to scan, or enter the barcode manually.',
  ),
  cameraMissing: t('pantry', 'No camera was found. Enter the barcode manually.'),
  cameraError: t('pantry', 'Could not start the camera. Enter the barcode manually.'),
}
</script>

<style scoped>
.pantry-barcode-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 8px 4px;
}

.pantry-barcode-form__hint {
  color: var(--color-text-maxcontrast);
  font-size: 0.9em;
}

.pantry-barcode-form__disclaimer {
  color: var(--color-text-maxcontrast);
  font-size: 0.8em;
  font-style: italic;
}

.pantry-barcode-form__disclaimer :deep(a) {
  text-decoration: underline;
}

.pantry-barcode-form__entry {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.pantry-barcode-form__field {
  flex: 1 1 auto;
  min-width: 0;
}

.pantry-barcode-form__scan-start {
  flex: 0 0 auto;
  white-space: nowrap;
}

.pantry-barcode-form__scanner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.pantry-barcode-form__video {
  width: 100%;
  max-height: 320px;
  background: var(--color-background-dark);
  border-radius: var(--border-radius-large);
  object-fit: cover;
}

.pantry-barcode-form__error {
  color: var(--color-error);
  font-size: 0.9em;
}
</style>
