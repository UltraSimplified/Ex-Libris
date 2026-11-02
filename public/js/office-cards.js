/**
 * The bespoke tiles on the Good on Paper Office dashboard.
 *
 * Written as render functions rather than single-file components so the
 * cards need no build step: Nova loads this file as-is, the same way it
 * loads the admin CSS overrides. Each component reads its data from the
 * `card` prop that its PHP class serialises.
 */
Nova.booting((app) => {
    const { h, Transition } = Vue

    /** A Nova card shell: white panel, rounded, with a heading. */
    const panel = (heading, children) =>
        h('div', { class: 'bg-white dark:bg-gray-800 rounded-lg shadow h-full flex flex-col p-4' }, [
            h('h3', { class: 'text-sm font-bold gop-heading', style: { marginBottom: '1rem' } }, heading),
            ...children,
        ])

    /** Heroicons' arrow-down-tray, at the size Nova sets its own button icons. */
    const downloadIcon = () =>
        h('svg', {
            width: 16,
            height: 16,
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': 2,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'aria-hidden': 'true',
            style: { flexShrink: '0' },
        }, [
            h('path', { d: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5' }),
            h('path', { d: 'M16.5 12 12 16.5 7.5 12' }),
            h('path', { d: 'M12 16.5V3' }),
        ])

    /** A headline count with its label underneath. */
    const bigNumber = (value, label, link) => {
        const number = h(
            link ? 'a' : 'span',
            Object.assign(
                {
                    class:
                        'block text-4xl font-bold gop-strong no-underline' +
                        (link ? ' hover:text-primary-500 cursor-pointer' : ''),
                },
                link
                    ? {
                          href: Nova.url(link),
                          onClick: (event) => {
                              event.preventDefault()
                              Nova.visit(link)
                          },
                      }
                    : {}
            ),
            Number(value).toLocaleString()
        )

        return h('div', { style: { textAlign: 'center' } }, [
            number,
            h('span', { class: 'text-xs uppercase tracking-wide gop-muted' }, label),
        ])
    }

    /** A Nova-styled internal link, routed rather than reloading the page. */
    const novaLink = (app, text, path) =>
        h(
            'a',
            {
                href: Nova.url(path),
                class: 'text-primary-500 font-bold text-sm no-underline hover:underline cursor-pointer',
                onClick: (event) => {
                    event.preventDefault()
                    Nova.visit(path)
                },
            },
            text
        )

    /** A line through the day's figures, drawn to fill whatever width it's given. */
    const sparkline = (days, description) => {
        const width = 700
        const height = 64
        const inset = 4
        const highest = Math.max(1, ...days.map((day) => day.visits))
        const points = days.map((day, position) => [
            (position / (days.length - 1)) * width,
            height - inset - (day.visits / highest) * (height - inset * 2),
        ])
        const line = points.map(([x, y]) => `${x},${y}`).join(' ')

        // Positioned out of the flow, as an svg in it holds the page open at its viewBox width on a phone.
        return h('div', { style: { flex: '1', minWidth: '0', height: `${height}px`, position: 'relative' } }, [
            h('svg', {
                viewBox: `0 0 ${width} ${height}`,
                preserveAspectRatio: 'none',
                role: 'img',
                'aria-label': description,
                style: {
                    position: 'absolute',
                    inset: '0',
                    width: '100%',
                    height: '100%',
                    color: 'rgb(var(--colors-primary-500))',
                },
            }, [
                h('polygon', { points: `0,${height} ${line} ${width},${height}`, fill: 'currentColor', opacity: '0.12' }),
                h('polyline', {
                    points: line,
                    fill: 'none',
                    stroke: 'currentColor',
                    'stroke-width': 2,
                    'stroke-linejoin': 'round',
                    'stroke-linecap': 'round',
                    'vector-effect': 'non-scaling-stroke',
                }),
            ]),
        ])
    }

    app.component('gop-site-visits', {
        props: ['card'],
        render() {
            const { heading, label, days, total } = this.card
            const description = days.map((day) => `${day.day}: ${day.visits}`).join(', ')

            return panel(heading, [
                h('div', { style: { display: 'flex', alignItems: 'center', gap: '1.5rem' } }, [
                    sparkline(days, description),
                    h('div', { style: { flexShrink: '0' } }, [bigNumber(total, label)]),
                ]),
            ])
        },
    })

    app.component('gop-features-by-category', {
        props: ['card'],

        render() {
            const slices = this.card.slices || []
            const total = this.card.total || 0

            if (total === 0) {
                return panel(this.card.heading, [
                    h('p', { class: 'gop-muted text-sm' }, this.card.emptyText),
                    h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
                ])
            }

            // A donut drawn as one arc per slice: each circle carries a dash
            // the length of its share, rotated past everything before it.
            const R = 42
            const CIRCUMFERENCE = 2 * Math.PI * R
            let travelled = 0

            const arcs = slices.map((slice) => {
                const share = slice.value / total
                const arc = h('circle', {
                    cx: 60,
                    cy: 60,
                    r: R,
                    fill: 'none',
                    stroke: slice.colour,
                    'stroke-width': 18,
                    'stroke-dasharray': `${share * CIRCUMFERENCE} ${CIRCUMFERENCE}`,
                    transform: `rotate(${travelled * 360 - 90} 60 60)`,
                })
                travelled += share

                return arc
            })

            const legend = slices.map((slice) =>
                h('div', { style: { display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.15rem 0' } }, [
                    h('span', { style: { width: '8px', height: '8px', borderRadius: '50%', background: slice.colour, flexShrink: '0' } }),
                    h('span', { class: 'text-xs gop-muted' },
                        `${slice.label} (${slice.value} - ${Math.round((slice.value / total) * 100)}%)`
                    ),
                ])
            )

            return panel(this.card.heading, [
                h('div', { style: { flex: '1', display: 'flex', alignItems: 'center', gap: '1rem' } }, [
                    h('div', { style: { flex: '1' } }, legend),
                    h('svg', { width: 120, height: 120, viewBox: '0 0 120 120', style: { flexShrink: '0' } }, arcs),
                ]),
                h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
            ])
        },
    })

    /** A small round button for turning a carousel. */
    const turnButton = (label, symbol, onClick) =>
        h('button', {
            type: 'button',
            'aria-label': label,
            class: 'gop-carousel-turn',
            onClick,
        }, symbol)

    /*
     * Every live advert, one at a time: its creative, its own figures for
     * the last thirty days, and arrows and a dot apiece to turn through them
     * when more than one is running. With nothing live, the figures for
     * every advert together stand in.
     */
    app.component('gop-advert-reach', {
        props: ['card'],

        data() {
            return { index: 0 }
        },

        computed: {
            adverts() {
                return this.card.adverts || []
            },

            shown() {
                return this.adverts[Math.min(this.index, this.adverts.length - 1)] || null
            },
        },

        methods: {
            turnTo(index) {
                const count = this.adverts.length

                this.index = ((index % count) + count) % count
            },
        },

        render() {
            const advert = this.shown
            const count = this.adverts.length

            const creative = advert === null
                ? [h('p', { class: 'gop-muted text-sm' }, 'Nothing is running at the moment.')]
                : [
                    advert.image
                        ? h('a', {
                              href: Nova.url(advert.link),
                              style: { display: 'block', flex: '1', minHeight: '0' },
                              onClick: (event) => {
                                  event.preventDefault()
                                  Nova.visit(advert.link)
                              },
                          }, [
                              h('img', {
                                  src: advert.image,
                                  alt: advert.title,
                                  class: 'rounded',
                                  // Scaled to fit rather than cropped: these are
                                  // whole creatives, and a cropped one misleads.
                                  style: { display: 'block', width: 'auto', height: '100%', maxWidth: '100%', objectFit: 'contain', objectPosition: 'left' },
                              }),
                          ])
                        : null,
                    h('p', { class: 'text-xs gop-muted', style: { marginTop: '0.4rem' } },
                        advert.title + ' · ' + advert.slot
                    ),
                ]

            const figures = advert === null
                ? { impressions: this.card.impressions, summary: this.card.summary }
                : advert

            const turning = count > 1
                ? [
                      turnButton('Previous advert', '‹', () => this.turnTo(this.index - 1)),
                      h('span', { class: 'gop-carousel-dots' }, this.adverts.map((item, position) =>
                          h('button', {
                              type: 'button',
                              class: 'gop-carousel-dot',
                              'aria-label': 'Show ' + item.title,
                              'aria-current': position === this.index ? 'true' : null,
                              onClick: () => this.turnTo(position),
                          })
                      )),
                      turnButton('Next advert', '›', () => this.turnTo(this.index + 1)),
                      h('span', { class: 'text-xs gop-muted', 'aria-live': 'polite' },
                          (this.index + 1) + ' of ' + count + ' live'
                      ),
                  ]
                : []

            // The PDF of the advert on show, across its whole run, kept to
            // the right of the carousel's own controls.
            const report = advert
                ? h('a', {
                      href: advert.report,
                      download: advert.reportFilename,
                      class: 'gop-report-button',
                      title: 'A PDF of ' + advert.title + ' across its whole run',
                  }, [downloadIcon(), h('span', {}, 'Download report')])
                : null

            const controls = advert
                ? h('div', {
                      class: 'gop-carousel-controls',
                      role: count > 1 ? 'group' : null,
                      'aria-label': count > 1 ? 'Live adverts' : null,
                  }, [...turning, report])
                : null

            return panel(this.card.heading, [
                h(Transition, { name: 'gop-fade', mode: 'out-in' }, {
                    default: () => h('div', {
                        key: advert ? advert.link : 'none',
                        style: {
                            // Two parts creative to one part figures, so the
                            // advert is two thirds of what is left under the
                            // heading however tall the row happens to be.
                            display: 'flex',
                            flexDirection: 'column',
                            flex: '3',
                            minHeight: '0',
                        },
                    }, [
                        h('div', {
                            style: { display: 'flex', flexDirection: 'column', flex: '2', minHeight: '0', marginBottom: '0.9rem' },
                        }, creative),
                        h('div', { style: { flex: '1', minHeight: '0' } }, [
                            h('p', { class: 'text-2xl font-bold gop-strong' },
                                Number(figures.impressions).toLocaleString() + ' impressions'
                            ),
                            h('p', { class: 'text-xs gop-muted' }, figures.summary),
                        ]),
                    ]),
                }),
                controls,
                h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
            ])
        },
    })

    app.component('gop-leaderboard', {
        props: ['card'],

        render() {
            const rows = this.card.rows || []

            const list = rows.length === 0
                ? [h('p', { class: 'gop-muted text-sm', style: { textAlign: 'center' } }, this.card.emptyText)]
                : rows.map((row) =>
                    h('div', { style: { display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: '1rem', padding: '0.3rem 0' } }, [
                        h(
                            'a',
                            {
                                href: Nova.url(row.link),
                                class: 'gop-strong text-sm no-underline hover:text-primary-500 cursor-pointer',
                                onClick: (event) => {
                                    event.preventDefault()
                                    Nova.visit(row.link)
                                },
                            },
                            row.name
                        ),
                        h('span', { class: 'text-xs gop-muted', style: { whiteSpace: 'nowrap' } },
                            row.count + ' ' + this.card.unit + (row.count === 1 ? '' : 's')
                        ),
                    ])
                )

            return panel(this.card.heading, [
                h('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-around', gap: '1rem', marginBottom: '1.25rem' } },
                    (this.card.counts || []).map((count) => bigNumber(count.value, count.label))
                ),
                this.card.listHeading
                    ? h('h4', {
                          class: 'text-xs uppercase tracking-wide font-bold gop-muted',
                          style: { marginBottom: '0.5rem' },
                      }, this.card.listHeading)
                    : null,
                h('div', { style: { flex: '1' } }, list),
                h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
            ])
        },
    })

    app.component('gop-listings-export', {
        props: ['card'],

        render() {
            return panel(this.card.heading, [
                h('div', { style: { flex: '1', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '1rem', minHeight: '110px' } }, [
                    bigNumber(this.card.count, this.card.countLabel),
                    h(
                        'a',
                        {
                            href: this.card.url,
                            download: this.card.filename,
                            class: 'inline-flex items-center justify-center h-9 px-3 rounded text-sm font-bold text-white bg-primary-500 hover:bg-primary-400 no-underline cursor-pointer',
                            style: { gap: '0.45rem' },
                        },
                        [downloadIcon(), h('span', {}, this.card.buttonText)]
                    ),
                ]),
                h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
            ])
        },
    })
})
