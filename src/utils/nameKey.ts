/**
 * The key item memory files a name under, so "Milk", "milk " and "Milk (2%)"
 * share what the household picked for them. Mirrors the server's
 * `NameKey::of()`; tests/fixtures/name-key-cases.json holds the cases both run.
 */
export function nameKey(name: string): string {
  let key = name.normalize('NFD').replace(/\p{Mn}/gu, '')
  // Innermost parentheses first, so nested ones go too.
  let before: string
  do {
    before = key
    key = key.replace(/\s*\([^()]*\)/gu, '')
  } while (key !== before)
  const comma = key.indexOf(',')
  if (comma >= 0) key = key.slice(0, comma)
  return key.toLowerCase().replace(/\s+/gu, ' ').trim()
}
