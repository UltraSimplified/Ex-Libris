/**
 * What to show above a video embed code: the player's address, or a word
 * on why there is nothing to show.
 *
 * The code is read with DOMParser, which builds a document without running
 * its scripts or fetching anything it names, and only the iframe's address
 * is taken from it. That address must be a YouTube or Vimeo player, the
 * same providers the site accepts for a listing's video.
 *
 * @param {string|null} code
 * @returns {{ src: string|null, message: string|null }}
 */
export default function embedPreview(code) {
    const text = (code || '').trim();

    if (text === '') {
        return { src: null, message: null };
    }

    const iframe = new DOMParser()
        .parseFromString(text, 'text/html')
        .querySelector('iframe[src]');

    if (iframe) {
        const src = playerAddress(iframe.getAttribute('src'));

        return src
            ? { src, message: null }
            : {
                  src: null,
                  message:
                      'This embed is not a YouTube or Vimeo player, so there is nothing to preview.',
              };
    }

    if (/^https:\/\/\S+$/i.test(text) && isVideoHost(text)) {
        return {
            src: null,
            message:
                'That is a link to the video, not its embed code. On YouTube or Vimeo choose Share, then Embed, and paste the code it gives.',
        };
    }

    return {
        src: null,
        message:
            'No YouTube or Vimeo embed code found. Paste the whole code, from <iframe to </iframe>.',
    };
}

const VIDEO_HOSTS = [
    'youtube.com',
    'youtube-nocookie.com',
    'youtu.be',
    'vimeo.com',
];

function parse(address) {
    try {
        return new URL(address);
    } catch {
        return null;
    }
}

function hostMatches(host, allowed) {
    return host === allowed || host.endsWith('.' + allowed);
}

function isVideoHost(address) {
    const url = parse(address);

    return Boolean(
        url &&
        url.protocol === 'https:' &&
        VIDEO_HOSTS.some((allowed) => hostMatches(url.hostname, allowed)),
    );
}

/**
 * The address, if it is a YouTube or Vimeo player that can be framed.
 *
 * @param {string|null} address
 * @returns {string|null}
 */
function playerAddress(address) {
    const url = parse(address);

    if (!url || url.protocol !== 'https:') {
        return null;
    }

    const host = url.hostname.toLowerCase();
    const isYouTubePlayer =
        (hostMatches(host, 'youtube.com') ||
            hostMatches(host, 'youtube-nocookie.com')) &&
        url.pathname.startsWith('/embed/');
    const isVimeoPlayer =
        host === 'player.vimeo.com' && url.pathname.startsWith('/video/');

    return isYouTubePlayer || isVimeoPlayer ? url.toString() : null;
}
