import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import type { FileAttachMode } from '@/api/types'

/** An image picked from the account's Nextcloud files, not yet attached. */
export interface FilesImage {
  fileId: number
  name: string
  mode: FileAttachMode
}

/** An image waiting to be attached: a file from the device, or one from Nextcloud files. */
export type PendingImage = File | FilesImage

export function isFilesImage(image: PendingImage | null | undefined): image is FilesImage {
  return !!image && !(image instanceof File)
}

/** Preview URL for a picked Nextcloud file, served by core since the file is the viewer's own. */
export function filesImagePreviewUrl(image: FilesImage, size = 96): string {
  return generateUrl('/core/preview?fileId={fileId}&x={size}&y={size}&a=1', {
    fileId: image.fileId,
    size,
  })
}

/**
 * Open the Nextcloud file picker filtered to images. Its two buttons decide
 * whether the picked file is linked in place or imported as a copy.
 * Resolves to null when the picker is closed without choosing.
 */
export async function pickImageFromFiles(): Promise<FilesImage | null> {
  const { getFilePickerBuilder } = await import('@nextcloud/dialogs')
  let picked: FilesImage | null = null
  const choose = (mode: FileAttachMode) => (nodes: { fileid?: number; basename: string }[]) => {
    const node = nodes[0]
    if (node?.fileid) picked = { fileId: node.fileid, name: node.basename, mode }
  }
  const picker = getFilePickerBuilder(t('pantry', 'Choose an image'))
    .setMultiSelect(false)
    .setMimeTypeFilter(['image/*'])
    .allowDirectories(false)
    .setButtonFactory((selected) => [
      {
        // TRANSLATORS: Button in the file picker that adds a copy of the picked image to Pantry
        label: t('pantry', 'Import a copy'),
        variant: 'secondary',
        disabled: selected.length === 0,
        callback: choose('import'),
      },
      {
        // TRANSLATORS: Button in the file picker that shows the picked image in place, without copying it
        label: t('pantry', 'Link'),
        variant: 'primary',
        disabled: selected.length === 0,
        callback: choose('link'),
      },
    ])
    .build()
  try {
    await picker.pick()
  } catch {
    return null
  }
  return picked
}
