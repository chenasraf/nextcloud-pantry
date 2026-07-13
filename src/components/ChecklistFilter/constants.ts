/**
 * Sentinel id used in the category-filter selection to represent "items with no
 * category". Real category ids are positive database ids, so a negative sentinel
 * never collides with one and can live in the same `number[]` selection model.
 */
export const NO_CATEGORY_ID = -1
