/**
 * Sentinel id used in the category-filter selection to represent "items with no
 * category". Real category ids are positive database ids, so a negative sentinel
 * never collides with one and can live in the same `number[]` selection model.
 */
export const NO_CATEGORY_ID = -1

/**
 * Sentinel id used in the store-filter selection to represent "items with no
 * store attached". Store and category selections live in separate models, so
 * reusing the same -1 value is fine.
 */
export const NO_STORE_ID = -1
