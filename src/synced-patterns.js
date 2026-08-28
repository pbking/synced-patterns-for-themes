/**
 * Which patterns the theme wants kept linked.
 *
 * The list is printed before this script by `Editor_Support`, from the `Synced`
 * header of the theme's pattern files.
 */

/**
 * Determines whether a pattern is inserted as a live reference.
 *
 * @param {string} slug Pattern slug, including namespace.
 * @return {boolean} Whether the pattern is synced.
 */
export function isSyncedPattern( slug ) {
	const slugs = window.syncedPatternsForThemes?.syncedPatterns;

	return Array.isArray( slugs ) && slugs.includes( slug );
}
