/**
 * Keeps work in progress in the Office from being lost.
 *
 * Features half-written in the Office were lost to switching to an account
 * in another tab: a switch moves the whole browser into that account, every
 * Office tab with it, and Nova met the next refusal from any of its calls by
 * leaving the form for its 403 page. Its notifications are fetched every
 * seven seconds, so that took moments. Three things here, each about a form
 * somebody is part-way through:
 *
 * - A held tab. When the Office refuses a tab with a form open, or the tab
 *   comes back into view, this asks who the browser is signed in as now:
 *   switched to an account, signed in as somebody else, or signed out. It
 *   says so over the page, with the way back that leaves the form where it
 *   is, and keeps Nova from leaving the form for its 403 or sign-in page.
 *   HoldOfficeWhileSwitched answers a switched browser's calls Locked.
 * - Unsaved changes. The line at the head of a form (UnsavedChanges) keeps
 *   what is typed in this browser until it is saved, and offers it back on
 *   the next visit when it never was: a closed tab, a sign-out, or a form
 *   left for another page.
 * - A warning before switching to an account while another tab has a form
 *   with unsaved changes in it.
 *
 * Written as render functions and plain DOM rather than single-file
 * components so it needs no build step, as office-cards.js is.
 */
;(function () {
    /** How often an open form is looked over for changes to keep. */
    const KEEP_EVERY = 3000

    /**
     * How long a tab's word that it has unsaved changes is believed without
     * being renewed. Long, since a browser may wake a tab in the background
     * to renew it only once a minute or less, and a tab that closes, saves
     * or leaves its form says so itself; this only clears out after a tab
     * that never got the chance.
     */
    const OPEN_FORM_LIFETIME = 12 * 60 * 60 * 1000

    /** Where tabs with unsaved changes say so, for the switch warning. */
    const OPEN_FORMS_KEY = 'gop-open-forms'

    /** Sent with every save, and nothing to do with what the form holds. */
    const NOT_KEPT = ['_method', '_retrieved_at']

    /** Nova's save of a whole record: create, or update by id. */
    const SAVE_URL = /^\/nova-api\/([^/]+)(?:\/(\d+))?$/

    /** This tab, among the others with forms open. */
    const tabId = Math.random().toString(36).slice(2)

    function csrfToken() {
        return document.head.querySelector('meta[name="csrf-token"]')?.content ?? ''
    }

    /**
     * A JSON post that Nova's own request handlers stay out of: they leave
     * the page on a refusal or an expired session, which is what is being
     * guarded against.
     */
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        })
    }

    /** A time as the office says it: 14:32, or 14:32 on Thu 27 Nov. */
    function when(iso) {
        const date = new Date(iso)
        const time = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })

        if (date.toDateString() === new Date().toDateString()) {
            return time
        }

        return time + ' on ' + date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
    }

    function element(tag, className, text) {
        const node = document.createElement(tag)
        node.className = className

        if (text) {
            node.textContent = text
        }

        return node
    }

    function button(label, className) {
        const node = element('button', className, label)
        node.type = 'button'

        return node
    }

    /*
     * A held tab
     * ----------
     *
     * Any tab can switch the browser to an account, sign it out, or sign it
     * in as somebody else, and every tab is then that account. An Office tab
     * finds out when the Office refuses it, or when it comes back into view,
     * asks the site who the browser is now, and says so over the page until
     * the browser is the Office's again. A form open in it is never left for
     * Nova's 403 or sign-in page meanwhile.
     */

    /** Nova's refusals that would take it off a form, and the Office's Locked. */
    const HELD_STATUSES = [401, 403, 419, 423]

    /** An Office create or edit form, by its address. */
    const FORM_PATH = /\/resources\/[^/]+\/(?:new|\d+\/edit)$/

    /** Who the browser is signed in as: the account.signed-in route. */
    const SIGNED_IN_URL = '/account/signed-in'

    let notice = null

    /**
     * What the browser was when Not now was pressed, so the notifications
     * Nova fetches every few seconds do not put the notice straight back.
     * Anything else puts it back: another account, a sign-out, the Office
     * letting the tab in again in between, or a save tried while held.
     */
    let dismissedFor = null

    function isFormOpen() {
        return FORM_PATH.test(window.location.pathname)
    }

    /**
     * What the browser is, to tell one hold from another, and null for the
     * Office. Each sign-in has its own mark, so switching back and then to
     * the same account again is another hold.
     */
    function situationOf(account) {
        if (account.office) {
            return null
        }

        if (!account.name) {
            return 'signed-out'
        }

        return (account.switched ? 'switched:' : 'other:') + account.name + ':' + account.signIn
    }

    /**
     * Who the browser is signed in as now. The session's token comes with
     * the answer and goes onto the page, where Nova reads it for every call:
     * signing in again gives the session a new one, which the page would
     * otherwise not have.
     */
    async function signedIn() {
        const response = await fetch(SIGNED_IN_URL, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })

        if (!response.ok) {
            throw new Error('Asking who is signed in was answered ' + response.status + '.')
        }

        const account = await response.json()

        document.head.querySelector('meta[name="csrf-token"]')?.setAttribute('content', account.csrfToken)

        return account
    }

    function closeNotice() {
        notice?.remove()
        notice = null
    }

    /**
     * The browser is the Office's again: lift the hold, and say so if there
     * was one. Returns whether there was.
     */
    function release(account) {
        const wasHeld = notice !== null || dismissedFor !== null

        closeNotice()
        dismissedFor = null

        if (wasHeld) {
            Nova.success(
                account?.name
                    ? 'You are back in your own account, ' + account.name + '. Carry on where you left off.'
                    : 'You are back in your own account. Carry on where you left off.',
            )
        }

        return wasHeld
    }

    /**
     * Find out who the browser is after a refusal, or on coming back to the
     * tab, and hold the tab or let it go accordingly. A refusal of somebody
     * the Office does let in was a real one: an expired token, now renewed,
     * or a thing they may not do.
     *
     * @param {{ status?: number|null, isSave?: boolean }} [trigger]
     */
    async function checkAccount({ status = null, isSave = false } = {}) {
        let account

        try {
            account = await signedIn()
        } catch (error) {
            console.error('Could not tell who this browser is signed in as.', error)

            return
        }

        const situation = situationOf(account)

        if (situation !== null) {
            if (isSave || situation !== dismissedFor) {
                showHeldNotice(account, situation)
            }

            return
        }

        if (release(account)) {
            return
        }

        if (status === 419) {
            Nova.warning('This page’s sign-in had run out and has been renewed. Try that again.')
        } else if (status === 403) {
            Nova.error('The Office would not let you do that.')
        }
    }

    /**
     * Ask for the Office account back, leaving this page as it is. The site
     * answers with who the browser is signed in as afterwards.
     */
    async function switchBack(account, control, problem) {
        control.disabled = true
        problem.textContent = ''

        try {
            const response = await post(account.switchBackUrl)

            if (!response.ok) {
                throw new Error('Switching back was answered ' + response.status + '.')
            }

            const after = await response.json()
            const situation = situationOf(after)

            if (situation === null) {
                release(after)
            } else {
                showHeldNotice(after, situation)
            }
        } catch (error) {
            console.error('Could not switch back to the Office account.', error)
            problem.textContent = 'That did not work just now. Try again, or use Switch back in the bar at the top of the site in your other tab, then come back here.'
        } finally {
            control.disabled = false
        }
    }

    /** Look again, once the browser should be the Office's. */
    async function carryOn(control, problem) {
        control.disabled = true
        problem.textContent = ''

        try {
            const account = await signedIn()
            const situation = situationOf(account)

            if (situation === null) {
                release(account)
            } else if (situation !== notice?.dataset.situation) {
                showHeldNotice(account, situation)
            } else {
                problem.textContent = account.name
                    ? 'This browser is still signed in as ' + account.name + '.'
                    : 'This browser is still signed out.'
            }
        } catch (error) {
            console.error('Could not tell who this browser is signed in as.', error)
            problem.textContent = 'That did not work just now. Try again in a moment.'
        } finally {
            control.disabled = false
        }
    }

    /** What the notice says, for each way a browser stops being the Office's. */
    function wordsFor(account) {
        if (account.switched) {
            return {
                heading: 'You are in ' + account.name + '’s account',
                text: 'This browser was switched to ' + account.name + '’s account in another tab, so the Office cannot save anything from this page as you. Nothing here has been lost: switch back to your own account and carry on where you left off.',
            }
        }

        if (account.name) {
            return {
                heading: 'This browser is signed in as ' + account.name,
                text: 'It was signed in as ' + account.name + ' in another tab, so the Office cannot save anything from this page as you. Nothing here has been lost: sign out of that account and back in as yourself in another tab, then come back and press Carry on.',
            }
        }

        return {
            heading: 'This browser has been signed out',
            text: 'It was signed out in another tab, or its sign-in ran out, so the Office cannot save anything from this page. Nothing here has been lost: sign in again in another tab, then come back and press Carry on.',
        }
    }

    /**
     * The notice over a held tab: who the browser is now, that the page is
     * safe as it is, and the way back. Left as it is when it already says
     * the same, so the notifications Nova keeps fetching do not redraw it.
     */
    function showHeldNotice(account, situation) {
        if (notice?.dataset.situation === situation) {
            return
        }

        closeNotice()

        const words = wordsFor(account)

        notice = element('div', 'gop-notice-overlay')
        notice.dataset.situation = situation

        const panel = element('div', 'gop-notice-panel bg-white dark:bg-gray-800 rounded-lg shadow')
        panel.setAttribute('role', 'alertdialog')
        panel.setAttribute('aria-modal', 'true')
        panel.setAttribute('aria-labelledby', 'gop-held-heading')
        panel.setAttribute('aria-describedby', 'gop-held-text')

        const heading = element('h3', 'gop-heading gop-notice-heading', words.heading)
        heading.id = 'gop-held-heading'

        const text = element('p', 'gop-strong', words.text)
        text.id = 'gop-held-text'

        const problem = element('p', 'gop-notice-problem')
        problem.setAttribute('aria-live', 'polite')

        const actions = element('div', 'gop-notice-actions')

        if (account.switched) {
            const back = button('Switch back to my account', 'gop-button')
            back.addEventListener('click', () => switchBack(account, back, problem))
            actions.append(back)
        } else {
            if (!account.name) {
                const signIn = element('a', 'gop-button', 'Sign in in a new tab')
                signIn.href = Nova.url('/login')
                signIn.target = '_blank'
                signIn.rel = 'noopener'
                actions.append(signIn)
            }

            const again = button('Carry on', account.name ? 'gop-button' : 'gop-button-quiet')
            again.addEventListener('click', () => carryOn(again, problem))
            actions.append(again)
        }

        const later = button('Not now', 'gop-button-quiet')
        later.addEventListener('click', () => {
            dismissedFor = situation
            closeNotice()
        })
        actions.append(later)

        panel.append(heading, text, problem, actions)
        notice.append(panel)
        document.body.append(notice)

        actions.firstElementChild.focus()
    }

    /**
     * Keep a refusal from taking Nova off the page, and find out why it
     * came. Nova lets a cancelled call go without a word, so the refusal is
     * marked as one; whatever made the call still hears that it failed.
     */
    function holdBack(error) {
        const status = error?.response?.status

        if (!HELD_STATUSES.includes(status) || (status !== 423 && !isFormOpen())) {
            return
        }

        error.__CANCEL__ = true
        checkAccount({ status, isSave: (error.config?.method ?? 'get') !== 'get' })
    }

    /**
     * Tell this tab's form that Nova has saved it, so the copy kept of its
     * changes can go: by the save itself rather than by Nova's events,
     * which Create & Add Another does not send.
     */
    function noteSave(response) {
        const config = response?.config ?? {}
        const editMode = config.params?.editMode
        const saved = SAVE_URL.exec(config.url ?? '')

        if (config.method !== 'post' || !saved || (editMode !== 'create' && editMode !== 'update')) {
            return
        }

        window.dispatchEvent(
            new CustomEvent('gop:saved', {
                detail: { resourceName: saved[1], resourceId: saved[2] ?? null },
            }),
        )
    }

    function noteAnswer(response) {
        noteSave(response)

        // The Office answered: the browser is the Office's again.
        if ((response?.config?.url ?? '').startsWith('/nova-api/')) {
            release(null)
        }

        return response
    }

    /*
     * Nova makes a fresh request client for every call and keeps no shared
     * one to listen on, so each is handed out with these listeners on it,
     * ahead of Nova's own: Nova's would otherwise leave the page for its 403
     * or sign-in page before these heard of the refusal.
     */
    const novaRequest = Nova.request.bind(Nova)

    Nova.request = function (options = null) {
        const client = novaRequest()
        const responses = client.interceptors.response
        const novaOwn = responses.handlers.filter(Boolean)

        responses.clear()
        responses.use(noteAnswer, (error) => {
            holdBack(error)

            return Promise.reject(error)
        })
        novaOwn.forEach((handler) =>
            responses.use(handler.fulfilled, handler.rejected, { synchronous: handler.synchronous, runWhen: handler.runWhen }),
        )

        return options != null ? client(options) : client
    }

    /*
     * Coming back to a form after time in another tab: find out straight
     * away whether the browser is still the Office's, rather than at the
     * next call the Office refuses.
     */
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && isFormOpen()) {
            checkAccount()
        }
    })

    /*
     * Unsaved changes
     * ---------------
     */

    function readJson(key, fallback) {
        try {
            return JSON.parse(localStorage.getItem(key)) ?? fallback
        } catch (error) {
            console.warn('Could not read ' + key + ' from this browser’s storage.', error)

            return fallback
        }
    }

    /** The tabs that have said they hold unsaved changes, still current. */
    function openForms() {
        const now = Date.now()

        return Object.fromEntries(
            Object.entries(readJson(OPEN_FORMS_KEY, {})).filter(([, form]) => now - form.at < OPEN_FORM_LIFETIME),
        )
    }

    /** Say whether this tab has unsaved changes, and in what. */
    function announce(label) {
        const forms = openForms()

        if (label) {
            forms[tabId] = { label, at: Date.now() }
        } else {
            delete forms[tabId]
        }

        try {
            localStorage.setItem(OPEN_FORMS_KEY, JSON.stringify(forms))
        } catch (error) {
            console.warn('Could not tell other tabs about this form.', error)
        }
    }

    /** The form a field sits in: Nova's update view, or its create form. */
    function formAround(component) {
        for (let parent = component.$parent; parent; parent = parent.$parent) {
            if (typeof parent.updateResourceFormData === 'function') {
                return { component: parent, formData: () => parent.updateResourceFormData() }
            }

            if (typeof parent.createResourceFormData === 'function') {
                return { component: parent, formData: () => parent.createResourceFormData() }
            }
        }

        return null
    }

    /**
     * What the form would send if it were saved now: its fields as name and
     * value, and the names of the pictures it would upload, which a browser
     * has nowhere to keep.
     */
    function contentsOf(form) {
        const fields = []
        const pictures = []

        for (const [name, value] of form.formData().entries()) {
            if (NOT_KEPT.includes(name)) {
                continue
            }

            if (typeof value === 'string') {
                fields.push([name, value])
            } else {
                pictures.push(name)
            }
        }

        return { fields, pictures }
    }

    Nova.booting((app) => {
        const { h } = Vue

        const paragraph = (className, text) => h('p', { class: className }, text)

        app.component('form-gop-unsaved-changes', {
            inheritAttrs: false,

            /*
             * Everything Nova hands a form field, declared so none of it
             * lands on the line as an attribute.
             */
            props: [
                'field',
                'index',
                'resourceName',
                'resourceId',
                'relatedResourceName',
                'relatedResourceId',
                'viaResource',
                'viaResourceId',
                'viaRelationship',
                'shownViaNewRelationModal',
                'formUniqueId',
                'mode',
                'showHelpText',
                'validationErrors',
            ],

            data() {
                return {
                    // Changes kept from an earlier visit, waiting on Restore or Discard.
                    offer: null,
                    // Changes put back into the form on this visit, until it is saved.
                    restored: null,
                    // Whether changes are being kept yet: not until the form has settled.
                    started: false,
                    keptAt: null,
                    problem: null,
                    working: false,
                }
            },

            created() {
                // Nova saves a form by calling fill() on every field it drew,
                // and this one has nothing to send.
                this.field.fill = () => {}

                this.key = ['gop-unsaved', Nova.config('userId'), this.resourceName, this.resourceId || 'new'].join(':')
                this.restoredKey = 'gop-restored:' + this.key
            },

            mounted() {
                this.form = formAround(this)

                if (!this.form) {
                    return
                }

                // Once every other field is drawn and its editor has settled,
                // what the form holds is what it was opened with.
                this.starting = setTimeout(() => this.start(), 1500)

                this.onSaved = (event) => this.saved(event.detail)
                this.onHidden = () => document.visibilityState === 'hidden' && this.keep()
                this.onPageHide = () => {
                    this.keep()
                    announce(null)
                }

                window.addEventListener('gop:saved', this.onSaved)
                document.addEventListener('visibilitychange', this.onHidden)
                window.addEventListener('pagehide', this.onPageHide)
            },

            beforeUnmount() {
                clearTimeout(this.starting)
                clearInterval(this.looking)

                window.removeEventListener('gop:saved', this.onSaved)
                document.removeEventListener('visibilitychange', this.onHidden)
                window.removeEventListener('pagehide', this.onPageHide)

                // Left for another page: keep what was typed since the last look.
                this.keep()
                announce(null)
            },

            computed: {
                singular() {
                    return this.form?.component.resourceInformation?.singularLabel?.toLowerCase() ?? 'record'
                },

                saveButton() {
                    return this.resourceId ? 'Update' : 'Create'
                },
            },

            methods: {
                start() {
                    const opened = this.contents()

                    if (!opened) {
                        return
                    }

                    this.opened = JSON.stringify(opened.fields)
                    this.lastSeen = this.opened

                    const kept = readJson(this.key, null)

                    if (sessionStorage.getItem(this.restoredKey)) {
                        sessionStorage.removeItem(this.restoredKey)
                        this.restored = kept ?? { keptAt: new Date().toISOString(), pictures: [] }
                    } else if (kept && JSON.stringify(kept.fields) !== this.opened) {
                        this.offer = kept
                    } else if (kept) {
                        localStorage.removeItem(this.key)
                    }

                    this.looking = setInterval(() => this.keep(), KEEP_EVERY)
                    this.started = true
                },

                contents() {
                    try {
                        return contentsOf(this.form)
                    } catch (error) {
                        console.error('Could not read the form to keep its changes.', error)
                        this.problem = 'Your changes could not be kept in this browser just now, so save as you go.'

                        return null
                    }
                },

                /** Whether the form holds anything its record does not. */
                isChanged(fields) {
                    return this.restored !== null || fields !== this.opened
                },

                /**
                 * What the form is about, for the switch warning in another
                 * tab: its record's title, or the title typed so far.
                 */
                label(fields) {
                    const title = this.form.component.title
                        || fields.find(([name]) => name === 'title')?.[1]

                    return title ? '“' + title + '”' : 'a new ' + this.singular
                },

                /**
                 * Keep what the form holds, when it has changed. Changes from
                 * an earlier visit still on offer are not written over until
                 * they are restored or discarded.
                 */
                keep() {
                    if (this.isSaved || this.opened === undefined) {
                        return
                    }

                    const now = this.contents()

                    if (!now) {
                        return
                    }

                    const fields = JSON.stringify(now.fields)
                    const changed = this.isChanged(fields)

                    announce(changed ? this.label(now.fields) : null)

                    if (fields === this.lastSeen || this.offer) {
                        return
                    }

                    this.lastSeen = fields

                    if (!changed) {
                        localStorage.removeItem(this.key)
                        this.keptAt = null

                        return
                    }

                    const kept = { keptAt: new Date().toISOString(), fields: now.fields, pictures: now.pictures }

                    try {
                        localStorage.setItem(this.key, JSON.stringify(kept))
                        this.keptAt = kept.keptAt
                        this.problem = null
                    } catch (error) {
                        console.error('Could not keep this form’s changes.', error)
                        this.problem = 'This browser could not keep a copy of your changes, so save as you go.'
                    }
                },

                saved({ resourceName, resourceId }) {
                    if (resourceName !== this.resourceName || String(resourceId ?? '') !== String(this.resourceId ?? '')) {
                        return
                    }

                    this.isSaved = true
                    localStorage.removeItem(this.key)
                    announce(null)
                },

                /**
                 * Hand the kept changes to the server for the form's next
                 * load, then load it again: Nova draws the form from the
                 * record, filled with them but not saved.
                 */
                async restore() {
                    this.working = true
                    this.problem = null

                    try {
                        const response = await post(this.field.restoreUrl, { fields: this.offer.fields })

                        if (HELD_STATUSES.includes(response.status)) {
                            checkAccount({ status: response.status, isSave: true })

                            return
                        }

                        if (!response.ok) {
                            throw new Error('Restoring was answered ' + response.status + '.')
                        }

                        sessionStorage.setItem(this.restoredKey, '1')
                        this.form.component.getFields()
                    } catch (error) {
                        console.error('Could not put the kept changes back.', error)
                        this.problem = 'Those changes could not be put back just now. They are still kept: try again in a moment.'
                    } finally {
                        this.working = false
                    }
                },

                discard() {
                    if (!window.confirm('Throw away the changes kept at ' + when(this.offer.keptAt) + '? They cannot be got back.')) {
                        return
                    }

                    localStorage.removeItem(this.key)
                    this.offer = null
                },

                picturesNote(kept) {
                    return kept.pictures?.length
                        ? paragraph('gop-muted', 'Pictures chosen then could not be kept, so add those again.')
                        : null
                },

                offerLines() {
                    const savedSince = this.field.lastSavedAt && new Date(this.field.lastSavedAt) > new Date(this.offer.keptAt)

                    return [
                        paragraph('gop-strong', 'You left changes here unsaved at ' + when(this.offer.keptAt) + '.'),
                        savedSince
                            ? paragraph('gop-muted', 'This ' + this.singular + ' has been saved since, at ' + when(this.field.lastSavedAt) + ', and restoring will replace what that save changed.')
                            : null,
                        this.picturesNote(this.offer),
                        h('div', { class: 'gop-notice-actions' }, [
                            h('button', { type: 'button', class: 'gop-button', disabled: this.working, onClick: () => this.restore() }, 'Restore them'),
                            h('button', { type: 'button', class: 'gop-button-quiet', disabled: this.working, onClick: () => this.discard() }, 'Discard them'),
                        ]),
                        paragraph('gop-muted', 'Nothing typed now is kept until you choose.'),
                    ]
                },

                lines() {
                    if (this.offer) {
                        return this.offerLines()
                    }

                    if (this.restored) {
                        return [
                            paragraph('gop-strong', 'Your changes from ' + when(this.restored.keptAt) + ' are back. Check them over, then press ' + this.saveButton + ' to save them.'),
                            this.picturesNote(this.restored),
                        ]
                    }

                    if (!this.started) {
                        return []
                    }

                    return [
                        paragraph(
                            'gop-muted',
                            'Your changes are kept in this browser until you save.' + (this.keptAt ? ' Last kept at ' + when(this.keptAt) + '.' : ''),
                        ),
                    ]
                },
            },

            render() {
                return h('div', { class: 'gop-unsaved', 'data-unsaved-changes': '' }, [
                    ...this.lines(),
                    this.problem ? h('p', { class: 'gop-notice-problem', 'aria-live': 'polite' }, this.problem) : null,
                ])
            },
        })
    })

    /*
     * The switch warning
     * ------------------
     *
     * Caught on the way down, before Nova's own handler on the menu item,
     * and only when another tab has unsaved changes.
     */
    document.addEventListener(
        'click',
        (event) => {
            if (!event.target.closest?.('[dusk$="-impersonate-button"]')) {
                return
            }

            const others = Object.entries(openForms())
                .filter(([id]) => id !== tabId)
                .map(([, form]) => form.label)

            if (others.length === 0) {
                return
            }

            const goAhead = window.confirm(
                'Switch to this account?\n\n'
                    + 'You have unsaved changes to ' + others.join(' and ') + ' in another tab. Save them first if you can. '
                    + 'While this browser is in another account that tab cannot save, though it will keep your changes and offer to switch you back.',
            )

            if (!goAhead) {
                event.preventDefault()
                event.stopPropagation()
            }
        },
        true,
    )
})()
