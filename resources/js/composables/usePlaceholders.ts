import type { Listing } from '@/types/global';

/**
 * Resolves a consistent placeholder image path for a listing based on its title and category.
 *
 * Uses new URL() with import.meta.url to ensure Vite correctly resolves the asset path
 * at both dev server and build time. The category folder name is derived from the first
 * word of the category string (lowercased).
 *
 * @param {string} title    - The listing title used to determine the placeholder index.
 * @param {string} category - The category name string (e.g. 'Arts + Crafts', 'Music').
 * @returns {string}        - The fully resolved URL to the placeholder image.
 */
export function placeholder(title: string, category: string): string {
    const categoryFolder = category.trim().split(/\s+/)[0].toLowerCase();
    const charSum = [...title].reduce(
        (sum, char) => sum + char.charCodeAt(0),
        0,
    );
    const imageIndex = (charSum % 4) + 1;

    return new URL(
        `../../placeholders/${categoryFolder}/${categoryFolder}${imageIndex}.webp`,
        import.meta.url,
    ).href;
}

/**
 * Iterates through a collection of listing objects, resolving each listing's image path.
 *
 * If a listing has a thumbnail, its path is prefixed with the provided CDN base URL.
 * If a listing has no thumbnail, a deterministic local placeholder is assigned based
 * on the listing's title and category name.
 *
 * @param {Listing[]} listings - Array of listing objects.
 * @param {string}    cdnUrl   - CDN base URL.
 * @returns {Listing[]}        - New array of listings with a resolved `image` property.
 */
export function resolvePlaceholders(
    listings: Listing[],
    cdnUrl: string,
): Listing[] {
    return listings.map((listing): Listing => {
        const categoryName =
            typeof listing.category === 'object'
                ? listing.category.name
                : listing.category;

        return {
            ...listing,
            image: listing.thumbnail
                ? `${cdnUrl.replace(/\/$/, '')}/${listing.thumbnail}`
                : placeholder(listing.title, categoryName),
        };
    });
}
