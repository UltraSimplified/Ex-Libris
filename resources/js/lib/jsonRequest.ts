/**
 * Laravel encrypts the CSRF token into a cookie and accepts it back as a
 * header. Nearly everything in this app posts through Inertia, so the few
 * small requests that do not read it here rather than pulling in a client.
 */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** The headers for a request made outside Inertia that answers in JSON. */
export function jsonRequestHeaders(): Record<string, string> {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}
