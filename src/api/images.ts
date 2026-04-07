import { generateOcsUrl } from '@nextcloud/router'

/**
 * Build a proxied preview URL for a photo board image.
 * Served through the Pantry API so any house member can view it.
 */
export function photoPreviewUrl(houseId: number, photoId: number, size = 300): string {
  return generateOcsUrl('/apps/pantry/api/houses/{houseId}/photos/{photoId}/preview?size={size}', {
    houseId,
    photoId,
    size,
  })
}

/**
 * Build a proxied preview URL for a checklist item image.
 * Served through the Pantry API so any house member can view it.
 */
export function itemImagePreviewUrl(
  houseId: number,
  fileId: number,
  owner: string,
  size = 300,
): string {
  return generateOcsUrl(
    '/apps/pantry/api/houses/{houseId}/image-preview?fileId={fileId}&owner={owner}&size={size}',
    { houseId, fileId, owner, size },
  )
}
