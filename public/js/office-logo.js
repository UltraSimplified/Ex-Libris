/**
 * The Good on Paper mark in the Office sidebar leads back to the magazine.
 *
 * Nova points its logo at the Office dashboard and offers no setting for
 * where it goes. The click is caught on the way down, from the document so
 * it survives Inertia swapping pages, and sent to the public home page as
 * an ordinary page load: the magazine is outside Nova's own app.
 */
;(function () {
    document.addEventListener(
        'click',
        function (event) {
            const link = event.target.closest('a')

            if (!link || !link.querySelector('.gop-logo, .gop-lockup')) {
                return
            }

            event.preventDefault()
            event.stopPropagation()

            // A new-tab click keeps its meaning.
            if (event.metaKey || event.ctrlKey || event.shiftKey) {
                window.open('/', '_blank')
                return
            }

            window.location.assign('/')
        },
        true
    )
})()
