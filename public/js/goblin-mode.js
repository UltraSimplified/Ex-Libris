/**
 * Goblin mode: a fourth entry in Nova's Light / Dark / System menu.
 *
 * Nova's theme menu is compiled into its bundle and has no hook for a
 * further choice, so the entry is added to the menu as it opens. Goblin
 * is dark mode plus a `goblin` class on <html>, which public/css/
 * nova-overrides.css turns into a green palette. Choosing it clicks Nova's
 * own Dark entry first, so Nova's state, icon and stored preference all
 * say dark; choosing anything else drops the class again.
 */
;(function () {
    const KEY = 'gopGoblin'
    const root = document.documentElement

    function read() {
        try {
            return localStorage.getItem(KEY) === '1'
        } catch (e) {
            return false
        }
    }

    function remember(on) {
        try {
            on ? localStorage.setItem(KEY, '1') : localStorage.removeItem(KEY)
        } catch (e) {}
    }

    function setGoblin(on) {
        root.classList.toggle('goblin', on)
        remember(on)
    }

    // Applied straight away so the page does not flash dark before it
    // turns green. Nova's own boot script has already run by now, so a
    // stored goblin only counts while Nova is still in dark.
    if (read() && root.classList.contains('dark')) {
        root.classList.add('goblin')
    } else {
        setGoblin(false)
    }

    const LEAF =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4" aria-hidden="true">' +
        '<path d="M13.5 2.5c-5.5 0-9 2.2-9 6.2 0 .8.2 1.5.5 2.1L2.5 13.3l.9.9 2.5-2.5c.6.3 1.3.5 2.1.5 4 0 6.2-3.5 6.2-9.2l-.7-.5z"/></svg>'

    function findItem(nav, label) {
        return Array.from(nav.querySelectorAll('button')).find(
            (button) => button.textContent.trim() === label
        )
    }

    function addEntry(nav) {
        if (nav.querySelector('[data-goblin]')) {
            return
        }

        const dark = findItem(nav, 'Dark')

        if (!dark || !findItem(nav, 'Light') || !findItem(nav, 'System')) {
            return
        }

        const entry = dark.cloneNode(true)
        entry.setAttribute('data-goblin', '')
        entry.querySelector('svg')?.replaceWith(
            Object.assign(document.createElement('span'), { innerHTML: LEAF }).firstChild
        )
        const label = Array.from(entry.querySelectorAll('span')).find(
            (span) => span.textContent.trim() === 'Dark'
        )
        label.textContent = 'Goblin'

        nav.insertBefore(entry, findItem(nav, 'System'))
    }

    // The menu's button is a bare icon that Nova swaps between sun, moon
    // and desktop, so it is recognised by those icons and tagged. The tag
    // survives the swaps because only the icon inside is replaced.
    const THEME_ICONS = ['M12 3v2.25', 'M21.752 15.002', 'M9 17.25v1.007']

    function tagTrigger() {
        document.querySelectorAll('header button[aria-haspopup]').forEach((button) => {
            const path = button.querySelector('svg path')

            if (path && THEME_ICONS.some((d) => path.getAttribute('d').startsWith(d))) {
                button.setAttribute('data-goblin-trigger', '')
            }
        })
    }

    tagTrigger()

    new MutationObserver(function () {
        tagTrigger()
        document
            .querySelectorAll('nav')
            .forEach((nav) => nav.querySelector('button') && addEntry(nav))
    }).observe(document.body, { childList: true, subtree: true })

    // Capture phase, so Nova's own Light / Dark / System handlers still run.
    document.addEventListener(
        'click',
        function (event) {
            const button = event.target.closest('nav button')

            if (!button) {
                return
            }

            const nav = button.closest('nav')

            if (!nav.querySelector('[data-goblin]')) {
                return
            }

            if (button.hasAttribute('data-goblin')) {
                event.stopPropagation()
                findItem(nav, 'Dark').click()
                setGoblin(true)
            } else {
                setGoblin(false)
            }
        },
        true
    )
})()
