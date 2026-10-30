/**
 * The eye on a listings or features row opens it as the public will see it.
 *
 * Nova's eye is a plain link to the read-only detail page - a second
 * rendering of the same fields the edit form already shows. This frames
 * the real page instead, so what is read is what is published - for
 * listings, and for features in the magazine.
 *
 * Nova's row template cannot be extended without forking its build, so
 * the click is caught on the way up instead. Delegated from the document
 * so it survives Inertia moving between pages, and scoped to these two so
 * every other resource's eye still goes where Nova sends it.
 */
;(function () {
    /*
     * Each resource the eye previews: the row's address, and the preview
     * route to frame for it. Those routes are listings.preview and
     * features.preview, and their shapes are pinned by tests, so a change
     * to either fails there rather than quietly showing an empty frame.
     */
    const PREVIEWS = [
        {
            row: /\/resources\/listings\/(\d+)$/,
            src: (id) => '/listings/' + id + '/preview',
            title: (id) => 'Preview of listing ' + id,
        },
        {
            row: /\/resources\/features\/(\d+)$/,
            src: (id) => '/magazine/features/' + id + '/preview',
            title: (id) => 'Preview of feature ' + id,
        },
    ]

    let overlay = null

    function close() {
        if (overlay) {
            overlay.remove()
            overlay = null
            document.removeEventListener('keydown', onKeydown)
        }
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            close()
        }
    }

    function open(preview, id) {
        close()

        overlay = document.createElement('div')
        overlay.className = 'gop-preview-overlay'
        overlay.addEventListener('click', close)

        const panel = document.createElement('div')
        panel.className = 'gop-preview-panel bg-white dark:bg-gray-800 rounded-lg shadow'
        panel.addEventListener('click', (event) => event.stopPropagation())

        const bar = document.createElement('div')
        bar.className = 'gop-preview-bar'

        const heading = document.createElement('h3')
        heading.className = 'text-sm font-bold gop-heading'
        heading.textContent = 'Preview'

        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'gop-muted hover:text-primary-500 cursor-pointer text-sm font-bold'
        button.textContent = 'Close'
        button.addEventListener('click', close)

        const frame = document.createElement('iframe')
        frame.src = preview.src(id)
        frame.title = preview.title(id)
        frame.className = 'gop-preview-frame'

        bar.append(heading, button)
        panel.append(bar, frame)
        overlay.append(panel)
        document.body.append(overlay)

        document.addEventListener('keydown', onKeydown)
    }

    document.addEventListener(
        'click',
        (event) => {
            const link = event.target.closest?.('a[dusk$="-view-button"]')

            if (!link) {
                return
            }

            const href = link.getAttribute('href') || ''
            const preview = PREVIEWS.find((candidate) => candidate.row.test(href))

            if (!preview) {
                return
            }

            // Caught while going down, so Inertia's own handler on the
            // link never runs and the page does not change underneath.
            event.preventDefault()
            event.stopPropagation()

            open(preview, preview.row.exec(href)[1])
        },
        true
    )
})()
