import type { Article } from '@/types/global';

/** Where a feature is read. */
export function articleUrl(article: Article): string {
    return '/magazine/features/' + article.id + '/' + article.slug + '/';
}

/** The page of features filed under the same tag. */
export function tagUrl(article: Article): string {
    return '/magazine/features/' + article.tag.slug;
}
