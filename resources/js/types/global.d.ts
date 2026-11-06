import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            cdnUrl: string;
            name: string;
            auth: Auth;
            /** Ids of the listings saved, on the account or in the browser. */
            bookmarks: number[];
            sidebarOpen: boolean;
            /** Every in-list advert live now, oldest first; never a sponsorship. */
            liveAdverts: Advert[];
            /**
             * The in-list advert the slots on this page start after, so they
             * share out evenly from one view to the next. Null on pages
             * without slots.
             */
            listingAdvert: Advert | null;
            /** The listings break for an advert after this many events; 0 for none. */
            advertEvery: number;
            /** How many things are in the basket; null until the shop is open for business. */
            basket: { count: number } | null;
            /** The shop's shelves with something on sale, for the sub-nav under Shop. */
            shopCategories: { name: string; slug: string }[];
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

interface Tag {
    id: number;
    tag: string;
    slug: string;
}

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface Advert {
    id: number;
    title: string;
    desktop_image: string | null;
    mobile_image: string | null;
    alt_text: string | null;
    owner: string | null;
}

interface Venue {
    id: number;
    name: string;
    address: string;
    town: string;
    postcode: string;
    cover_photo: string;
    /** Written in the Office's editor, as HTML. */
    description: string | null;
    /** Where the venue is, for its map; null until its postcode is found. */
    latitude: number | null;
    longitude: number | null;
    url: string;
    /**
     * The website tagged as Good on Paper's referral. Sent only to the
     * venue's own page.
     */
    website_link?: string | null;
    phone: string | null;
    email: string | null;
    stockist: boolean;
}

interface Promoter {
    id: number;
    name: string;
    /** The rest are sent only to the promoter's own page. */
    banner?: string | null;
    /** Written as plain text. */
    about?: string | null;
    website?: string | null;
    /** The website tagged as Good on Paper's referral. */
    website_link?: string | null;
    phone?: string | null;
    email?: string | null;
}

interface Writer {
    id: number;
    writer: string;
    writer_bio: string;
    writer_photo: string;
    photo_alt_text: string;
}

/**
 * Who wrote an article, as a public page is allowed to see them: a name,
 * a picture and a note. The account behind it holds an email address and
 * a telephone number, and Feature::BYLINE keeps those off the front end.
 */
interface Byline {
    id: number;
    name: string;
    avatar: string | null;
    avatar_alt_text: string | null;
    bio: string | null;
    /** Their page on Our Writers, or null for an account that has none. */
    slug: string | null;
}

/**
 * A writer as a link to their page shows them: a name and a picture, and
 * the slug their page is reached by.
 */
interface WriterLink {
    id: number;
    name: string;
    slug: string;
    avatar: string | null;
    avatar_alt_text: string | null;
}

/**
 * How large a writer's previous or next lozenge is drawn. The page picks
 * the largest at which the pair fit side by side; the smallest lets a long
 * name run on to a second line, so the pair always fits.
 */
type LozengeSize = 'lg' | 'md' | 'sm';

/** One artist on a listing's bill, as the page shows them. */
interface LineUpArtist {
    name: string;
    playlist: string | null;
    video: string | null;
}

/** A line-up artist as stored, in Nova Flexible Content's shape. */
interface LineUpGroup {
    layout: string;
    key: string;
    attributes: Partial<LineUpArtist>;
}

interface GalleryImage {
    key: string;
    layout: string;
    attributes: {
        image: string;
        caption: string | null;
    };
}

interface CopyLayout {
    key: string;
    layout: 'copy';
    attributes: {
        text: string;
    };
}

interface ColophonLayout {
    key: string;
    layout: 'colophon';
    attributes: {
        text: string;
    };
}

interface PullQuoteLayout {
    key: string;
    layout: 'pullquote';
    attributes: {
        quote: string;
        cite: string;
    };
}

interface TwoColumnLayout {
    key: string;
    layout: 'twocolumns';
    attributes: {
        col1_text: string;
        col2_text: string;
    };
}

interface ImgLeftLayout {
    key: string;
    layout: 'imgleft';
    attributes: {
        image: string;
        alt: string;
        caption: string;
        text: string;
        link?: string | null;
    };
}

interface ImgRightLayout {
    key: string;
    layout: 'imgright';
    attributes: {
        image: string;
        alt: string;
        caption: string;
        text: string;
        link?: string | null;
    };
}

interface ImgFullLayout {
    key: string;
    layout: 'imgfull';
    attributes: {
        image: string;
        alt: string;
        text: string;
        caption: string;
        link?: string | null;
    };
}

interface PlaylistLayout {
    key: string;
    layout: 'playlist';
    attributes: {
        playlist: string | null;
    };
}

interface VideoLayout {
    key: string;
    layout: 'video';
    attributes: {
        video: string | null;
    };
}

/**
 * Block of code, eg Donorbox subscriptions widget
 */
interface EmbedLayout {
    key: string;
    layout: 'embed';
    attributes: {
        code: string | null;
    };
}

/**
 * One photo in a Photojournal, as the Nova Gallery field stores it.
 * Paths are on the Spaces disk; width and height are the stored photo's.
 */
interface PhotojournalImage {
    image: string;
    thumb: string;
    width: number | null;
    height: number | null;
    alt: string | null;
    caption: string | null;
}

interface GalleryLayout {
    key: string;
    layout: 'gallery';
    attributes: {
        images: PhotojournalImage[] | null;
    };
}

interface DividerLayout {
    key: string;
    layout: 'divider';
    colourway: Colourway;
}
interface AdvertLayout {
    key: string;
    layout: 'advert';
}
interface ListingLayout {
    key: string;
    layout: 'listing';
    attributes: {
        listing_id: number | string | null;
        /** The listing chosen, or null once it has finished. */
        listing: Listing | null;
    };
}
interface SpacerLayout {
    key: string;
    layout: 'spacer';
    attributes: {
        padding: string;
    };
}
/**
 * Colourways as stored in the database.
 */
export type Colourway = 'white' | 'hi-vis' | 'slate' | 'key-lime';

type Layout =
    | CopyLayout
    | ColophonLayout
    | TwoColumnLayout
    | PullQuoteLayout
    | ImgLeftLayout
    | ImgRightLayout
    | ImgFullLayout
    | PlaylistLayout
    | DividerLayout
    | EmbedLayout
    | SpacerLayout
    | AdvertLayout
    | ListingLayout
    | VideoLayout
    | GalleryLayout;

interface Article {
    id: number;
    issue_number: number;
    title: string;
    subject: string | null;
    published: string | null;
    /** A draft is only sent to the Office, and marked as one. */
    status: 'draft' | 'published' | 'private';
    /** Picked for the top of the home page. */
    featured: boolean;
    slug: string;
    colourway: string;
    tag: Tag;
    cover_photo: string;
    cover_alt_text: string;
    feature_image: string;
    feature_alt_text: string;
    body: Layout[];
    excerpt: string;
    listing_id: number | null;
    /** The event the feature is about, shown at its foot. */
    listing: Listing | null;
    user: Byline;
}

interface Page {
    id: number;
    issue_number: number;
    title: string;
    subject: string;
    published: string;
    slug: string;
    colourway: string;
    tag: Tag;
    cover_photo: string;
    cover_alt_text: string;
    feature_image: string;
    feature_alt_text: string;
    body: Layout[];
    excerpt: string;
    listing_id: number | null;
    /** The event the feature is about, shown at its foot. */
    listing: Listing | null;
    user: Byline;
}

interface Magazine {
    id: number;
    issue_number: number;
    month_year: string;
    tagline: string;
    cover_photo: string;
    hero_image: string;
    alt_text: string;
    cover_artist: string;
    playlist: string;
    playlist_creator: string;
    pdf: string | null;
    /** Whether there is a file of the issue to download; sent by the archive. */
    downloadable?: boolean;
}

interface Listing {
    id: number;
    title: string;
    category: Category;
    published: string;
    slug: string;
    colourway: string;
    tag: Tag;
    cover_photo: string;
    thumbnail: string | null;
    image?: string; // resolved at runtime by resolvePlaceholders
    body: string;
    venue: Venue;
    /**
     * Only loaded where a list is long enough to be searched, so a listing
     * arriving from anywhere else has no promoter on it at all - which is
     * not the same as having no promoter.
     */
    promoter?: Promoter | null;
    event_link: string;
    /**
     * The event link, or the venue's website, tagged as Good on Paper's
     * referral. Sent only to the listing's own page.
     */
    ticket_link?: string | null;
    /**
     * The artists on the bill, in Nova Flexible Content's shape like the
     * gallery: each group's attributes hold a name and a playlist and/or a
     * video embed, already rebuilt by the server's EmbedSanitiser.
     */
    line_up: string | LineUpGroup[] | null;
    /**
     * Nova Flexible Content's own shape, which the dashboard writes too.
     * Arrives decoded from some endpoints and as the raw column from
     * others, and ListItem parses whichever it is given.
     */
    gallery: string | GalleryImage[] | null;
    start_date: string;
    end_date: string;
    additional_date_information: string | null;
    ticket_price: string;
    /** Said across the event's image rather than beside it. */
    sold_out: boolean;
    event_time_description: string;
    /** Blank where there is nothing to book, and then no button is drawn. */
    button_text: string | null;
    today: boolean;
    thisweek: boolean;
}

/**
 * A listing as the Dashboard's bookmarks panel is given it, with the tick
 * from the bookmark itself: 0 or 1 from the server, as pivot columns are not
 * cast, and true or false once it has been changed on the page.
 */
interface BookmarkedListing extends Listing {
    is_free: boolean;
    pivot: { attending: boolean | number };
}
