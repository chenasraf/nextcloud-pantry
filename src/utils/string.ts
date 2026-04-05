/**
 * Truncates a string to a specified length, adding an ellipsis if truncated.
 *
 * @param str - The string to truncate
 * @param maxLength - Maximum length of the resulting string (including ellipsis)
 * @param ellipsis - The ellipsis string to append (default: '...')
 * @returns The truncated string
 */
export function truncate(
  str: string | null | undefined,
  maxLength: number,
  ellipsis: string = '...',
): string {
  if (str == null) {
    return ''
  }

  if (maxLength < 0) {
    return ''
  }

  if (str.length <= maxLength) {
    return str
  }

  const truncatedLength = maxLength - ellipsis.length
  if (truncatedLength <= 0) {
    return ellipsis.slice(0, maxLength)
  }

  return str.slice(0, truncatedLength) + ellipsis
}

/**
 * Capitalizes the first letter of a string.
 *
 * @param str - The string to capitalize
 * @returns The string with its first letter capitalized
 */
export function capitalize(str: string | null | undefined): string {
  if (str == null || str.length === 0) {
    return ''
  }

  return str.charAt(0).toUpperCase() + str.slice(1)
}

/**
 * Formats a number as a human-readable file size.
 *
 * @param bytes - The number of bytes
 * @param decimals - Number of decimal places (default: 2)
 * @returns Formatted string like "1.5 KB" or "2.3 MB"
 */
export function formatFileSize(bytes: number | null | undefined, decimals: number = 2): string {
  if (bytes == null || bytes < 0 || !Number.isFinite(bytes)) {
    return '0 B'
  }

  if (bytes === 0) {
    return '0 B'
  }

  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  const index = Math.min(i, sizes.length - 1)

  return `${parseFloat((bytes / Math.pow(k, index)).toFixed(decimals))} ${sizes[index]}`
}
