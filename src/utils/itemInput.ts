/**
 * Splits what someone types into an item name and a quantity: "2 L milk",
 * "milk 2L", "3x eggs" and "- [ ] 500 g flour" all come apart, while "7up" and
 * "3M tape" stay whole. The mobile app parses the same way, and
 * tests/fixtures/item-input-cases.json holds the cases both clients run.
 */

export interface ParsedItemInput {
  name: string
  /** The amount found in the text, or null when there is none. */
  quantity: string | null
}

interface UnitPack {
  units: string[]
  /** Whether "1,5" is one and a half. */
  decimalComma?: boolean
  /** Words joining a unit to the name, as in "2 cups of flour". */
  connectors?: string[]
}

// Metric symbols and English units are understood in every language, since
// people type them regardless of the interface language.
const SHARED: UnitPack = {
  units: [
    'g',
    'kg',
    'mg',
    'l',
    'ml',
    'cl',
    'dl',
    'oz',
    'lb',
    'lbs',
    'pt',
    'qt',
    'gal',
    'cup',
    'cups',
    'tbsp',
    'tsp',
    'pc',
    'pcs',
    'piece',
    'pieces',
    'pk',
    'pack',
    'packs',
    'packet',
    'packets',
    'bottle',
    'bottles',
    'can',
    'cans',
    'jar',
    'jars',
    'box',
    'boxes',
    'bag',
    'bags',
    'carton',
    'cartons',
    'tin',
    'tins',
    'roll',
    'rolls',
    'bunch',
    'bunches',
    'loaf',
    'loaves',
    'slice',
    'slices',
    'dozen',
    'head',
    'heads',
  ],
  connectors: ['of'],
}

const PACKS: Record<string, UnitPack> = {
  de: {
    units: [
      'stück',
      'stk',
      'pck',
      'pkg',
      'packung',
      'packungen',
      'flasche',
      'flaschen',
      'dose',
      'dosen',
      'becher',
      'glas',
      'gläser',
      'tüte',
      'tüten',
      'bund',
      'beutel',
    ],
    decimalComma: true,
  },
  es: {
    units: [
      'paquete',
      'paquetes',
      'botella',
      'botellas',
      'lata',
      'latas',
      'bolsa',
      'bolsas',
      'caja',
      'cajas',
      'unidad',
      'unidades',
      'docena',
      'docenas',
      'tarro',
      'tarros',
    ],
    decimalComma: true,
    connectors: ['de'],
  },
  fr: {
    units: [
      'paquet',
      'paquets',
      'bouteille',
      'bouteilles',
      'boîte',
      'boîtes',
      'sachet',
      'sachets',
      'pot',
      'pots',
      'tranche',
      'tranches',
      'botte',
      'bottes',
      'douzaine',
      'pièce',
      'pièces',
    ],
    decimalComma: true,
    connectors: ['de', "d'"],
  },
  he: {
    units: [
      'ק"ג',
      'ק״ג',
      'קג',
      'קילו',
      'גרם',
      "ג'",
      'ג׳',
      'ליטר',
      "ל'",
      'ל׳',
      'מ"ל',
      'מ״ל',
      'מל',
      "יח'",
      'יח׳',
      'יחידה',
      'יחידות',
      'חבילה',
      'חבילות',
      'בקבוק',
      'בקבוקים',
      'קופסה',
      'קופסאות',
      'שקית',
      'שקיות',
      'צנצנת',
      'צנצנות',
      'תריסר',
    ],
  },
  it: {
    units: [
      'confezione',
      'confezioni',
      'bottiglia',
      'bottiglie',
      'lattina',
      'lattine',
      'barattolo',
      'barattoli',
      'busta',
      'buste',
      'scatola',
      'scatole',
      'pezzo',
      'pezzi',
    ],
    decimalComma: true,
    connectors: ['di'],
  },
  nl: {
    units: [
      'stuk',
      'stuks',
      'pak',
      'pakken',
      'fles',
      'flessen',
      'blik',
      'blikken',
      'zak',
      'zakken',
      'doos',
      'dozen',
    ],
    decimalComma: true,
  },
  pl: {
    units: [
      'szt',
      'opak',
      'butelka',
      'butelki',
      'puszka',
      'puszki',
      'paczka',
      'paczki',
      'słoik',
      'słoiki',
    ],
    decimalComma: true,
  },
  pt: {
    units: [
      'pacote',
      'pacotes',
      'garrafa',
      'garrafas',
      'lata',
      'latas',
      'caixa',
      'caixas',
      'saco',
      'sacos',
      'unidade',
      'unidades',
      'dúzia',
      'dúzias',
    ],
    decimalComma: true,
    connectors: ['de'],
  },
  ru: {
    units: [
      'г',
      'гр',
      'кг',
      'л',
      'мл',
      'шт',
      'уп',
      'пачка',
      'пачки',
      'бутылка',
      'бутылки',
      'банка',
      'банки',
      'пакет',
      'пакета',
      'пакетов',
    ],
    decimalComma: true,
  },
}

const MULTIPLIER = '[x×]'
const FRACTION = '\\d+/\\d+|[½⅓⅔¼¾]'

// A bullet ("-", "*", "+", "1.", "1)") and a task box ("[ ]", "[x]"), each optional.
const LIST_MARKUP_RE = /^\s*(?:(?:[-*+]|\d+[.)])\s+)?(?:\[[ xX]\]\s+)?/u

interface Grammar {
  /** A whole text that is only a unit, which is no name to take an amount from. */
  bareUnit: RegExp
  leadingMultiplier: RegExp
  leadingMultiplierSuffix: RegExp
  leadingAmount: RegExp
  trailingMultiplier: RegExp
  trailingMultiplierSuffix: RegExp
  trailingAmount: RegExp
}

const grammars = new Map<string, Grammar>()

function escape(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function grammarFor(locale: string): Grammar {
  const language = locale.toLowerCase().split(/[-_@]/)[0] ?? ''
  const cached = grammars.get(language)
  if (cached) return cached
  const pack = PACKS[language]
  const units = [...new Set([...SHARED.units, ...(pack?.units ?? [])])].sort(
    (a, b) => b.length - a.length,
  )
  const connectors = [...(SHARED.connectors ?? []), ...(pack?.connectors ?? [])]
  const number = pack?.decimalComma ? `\\d+(?:[.,]\\d+)?` : `\\d+(?:\\.\\d+)?`
  const amount = `(?:${FRACTION}|${number})`
  const unit = `(?:${units.map(escape).join('|')})`
  // A connector either stands alone ("of flour") or, like "d'", runs into the name.
  const connector = connectors.length
    ? `(?:(?:${connectors.map(escape).join('|')})(?:\\s+|(?<='))\\s*)?`
    : ''
  const grammar: Grammar = {
    bareUnit: new RegExp(`^${unit}$`, 'iu'),
    leadingMultiplier: new RegExp(`^(\\d+)\\s*${MULTIPLIER}\\s+(.+)$`, 'iu'),
    leadingMultiplierSuffix: new RegExp(`^${MULTIPLIER}\\s*(\\d+)\\s+(.+)$`, 'iu'),
    // The unit is optional, but when there is none the number must stand
    // apart from the name, so "7up" stays one word.
    leadingAmount: new RegExp(
      `^(${amount})(?:\\s*(${unit})(?=\\s)\\s+${connector}|\\s+)(.+)$`,
      'iu',
    ),
    trailingMultiplier: new RegExp(`^(.+?)\\s+${MULTIPLIER}\\s*(\\d+)$`, 'iu'),
    trailingMultiplierSuffix: new RegExp(`^(.+?)\\s+(\\d+)\\s*${MULTIPLIER}$`, 'iu'),
    // Trailing amounts need a unit: "Pixel 8" is a name, not eight Pixels.
    trailingAmount: new RegExp(`^(.+?)\\s+(${amount})\\s*(${unit})$`, 'iu'),
  }
  grammars.set(language, grammar)
  return grammar
}

/** The text without a leading bullet or task box. */
export function stripListMarkup(text: string): string {
  return text.replace(LIST_MARKUP_RE, '').trim()
}

export function parseItemInput(text: string, locale: string): ParsedItemInput {
  const original = stripListMarkup(text)
  const grammar = grammarFor(locale)
  const result = (name: string, quantity: string): ParsedItemInput => {
    const trimmed = name.trim()
    return trimmed && !grammar.bareUnit.test(trimmed)
      ? { name: trimmed, quantity }
      : { name: original, quantity: null }
  }
  let m: RegExpMatchArray | null

  if ((m = original.match(grammar.leadingMultiplier))) return result(m[2]!, m[1]!)
  if ((m = original.match(grammar.leadingMultiplierSuffix))) return result(m[2]!, m[1]!)
  if ((m = original.match(grammar.leadingAmount))) {
    return result(m[3]!, m[2] ? `${m[1]} ${m[2]}` : m[1]!)
  }
  if ((m = original.match(grammar.trailingMultiplier))) return result(m[1]!, m[2]!)
  if ((m = original.match(grammar.trailingMultiplierSuffix))) return result(m[1]!, m[2]!)
  if ((m = original.match(grammar.trailingAmount))) return result(m[1]!, `${m[2]} ${m[3]}`)
  return { name: original, quantity: null }
}
