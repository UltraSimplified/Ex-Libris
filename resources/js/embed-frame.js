/*
 * Runs inside a gig widget's frame, inlined by
 * resources/views/embed/layout.blade.php. Tells the loader on the host page
 * (public/widget.js) how big the frame should be: as tall as the events
 * when the widget fits its events, or the size the owner declared. Without
 * the loader - a plain iframe snippet - nobody is listening and the frame
 * keeps the height written into it.
 */
(function () {
    var config = window.gopWidget || {};

    if (window.parent === window) {
        return;
    }

    var fixed = config.mode === 'fixed';
    var last = null;

    function send() {
        var height = fixed && config.height
            ? config.height
            : Math.ceil(document.body.getBoundingClientRect().height);

        if (height === last) {
            return;
        }

        last = height;

        window.parent.postMessage(
            {
                type: 'gop:size',
                key: config.key,
                height: height,
                width: config.width || null,
                scroll: fixed,
            },
            '*',
        );
    }

    if ('ResizeObserver' in window) {
        new ResizeObserver(send).observe(document.body);
    }

    window.addEventListener('load', send);
    send();
})();
