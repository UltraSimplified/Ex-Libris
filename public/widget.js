/*
 * Good on Paper gig widget loader.
 *
 *   <div data-gop-widget="KEY"></div>
 *   <script async src="https://events.goodonpaper.info/widget.js"></script>
 *
 * Pasted into venues' and promoters' own sites, where it stays for years, so
 * this file keeps its name and its behaviour: anything that would break an
 * embed already out there ships as /widget/v2.js instead.
 *
 * It finds each placeholder, puts the widget's frame in it, and sizes the
 * frame as the widget asks (resources/js/embed-frame.js), listening only to
 * frames it made, from the host it was itself loaded from.
 */
(function () {
    var script = document.currentScript;

    if (!script || !script.src) {
        return;
    }

    var origin = new URL(script.src).origin;
    var frames = [];

    function mount(placeholder) {
        if (placeholder.getAttribute('data-gop-mounted')) {
            return;
        }

        var key = placeholder.getAttribute('data-gop-widget');

        if (!key) {
            return;
        }

        placeholder.setAttribute('data-gop-mounted', '1');

        var frame = document.createElement('iframe');
        frame.src = origin + '/embed/' + encodeURIComponent(key);
        frame.title = placeholder.getAttribute('data-title') || 'Upcoming events';
        frame.loading = 'lazy';
        frame.setAttribute('scrolling', 'no');
        frame.style.cssText =
            'display:block;width:100%;height:' +
            (parseInt(placeholder.getAttribute('data-height'), 10) || 480) +
            'px;border:0;overflow:hidden;background:transparent;';

        placeholder.appendChild(frame);
        frames.push(frame);
    }

    function mountAll() {
        var placeholders = document.querySelectorAll('[data-gop-widget]');

        for (var i = 0; i < placeholders.length; i++) {
            mount(placeholders[i]);
        }
    }

    window.addEventListener('message', function (event) {
        var data = event.data;

        if (event.origin !== origin || !data || data.type !== 'gop:size') {
            return;
        }

        for (var i = 0; i < frames.length; i++) {
            var frame = frames[i];

            if (frame.contentWindow !== event.source) {
                continue;
            }

            frame.style.height = Math.max(0, Math.ceil(data.height)) + 'px';
            frame.style.maxWidth = data.width ? data.width + 'px' : '';
            frame.setAttribute('scrolling', data.scroll ? 'auto' : 'no');
            frame.style.overflow = data.scroll ? 'auto' : 'hidden';
        }
    });

    mountAll();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountAll);
    }
})();
