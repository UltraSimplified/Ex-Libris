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

    /** A Nova card shell: white panel, rounded. */
    const shell = (children) =>
        h('div', { class: 'bg-white dark:bg-gray-800 rounded-lg shadow h-full flex flex-col p-4' }, children)

    /** A card's heading, or the heading of one section of it. */
    const cardHeading = (heading) =>
        h('h3', { class: 'text-sm font-bold gop-heading', style: { marginBottom: '1rem' } }, heading)

    /** A Nova card shell with a heading. */
    const panel = (heading, children) => shell([cardHeading(heading), ...children])

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

        return h('div', { style: { textAlign: 'left' } }, [
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

    /** A rounded top for a chart's axis and the gap between its marks. */
    const axisScale = (highest) => {
        const rough = Math.max(1, highest) / 7
        const power = 10 ** Math.floor(Math.log10(rough))
        const step = Math.max(1, [1, 2, 5, 10].map((multiple) => multiple * power).find((gap) => gap >= rough))

        return { step, top: Math.ceil(Math.max(1, highest) / step) * step }
    }

    /** 1,770 as 1.77k, the way the figure sits beside the chart. */
    const compactNumber = (value) =>
        new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 2 }).format(value).toLowerCase()

    /*
     * The visitors chart: two lines over dashed gridlines. Unique visitors
     * are filled in blue from the axis up, and page views add a pink band
     * on top of that fill rather than a second one from the axis, so the
     * two shadings never sit over each other and the pink is always and
     * only the pages read beyond one apiece. Both lines are drawn against
     * one axis, so the gap between them is the gap it looks like.
     * The svg stretches to fit, so the labels are HTML placed by percentage
     * rather than svg text, which would stretch with it. It sits out of the
     * flow, as an svg in it holds the page open at its viewBox width on a
     * phone.
     */
    const visitorsChart = (points, hovered, onHover) => {
        const highest = Math.max(...points.map((point) => Math.max(point.visits, point.requests)))
        const { step, top } = axisScale(highest)
        const xOf = (position) => (position / (points.length - 1)) * 100
        const yOf = (value) => 100 - (value / top) * 100
        const marks = Array.from({ length: top / step + 1 }, (_, index) => index * step)
        const lineOf = (reading) => points.map((point, position) => `${xOf(position)},${yOf(reading(point))}`).join(' ')
        const visitsLine = lineOf((point) => point.visits)
        const requestsLine = lineOf((point) => point.requests)
        const hoveredPoint = hovered === null ? null : points[hovered]

        const nearestPoint = (event) => {
            const box = event.currentTarget.getBoundingClientRect()

            onHover(Math.round(((event.clientX - box.left) / box.width) * (points.length - 1)))
        }

        /* A line's points right to left, to close a band over the line below it. */
        const backAlong = (line) => line.split(' ').reverse().join(' ')

        const reading = (count, one, many) => `${count.toLocaleString()} ${count === 1 ? one : many}`

        const axisLabel = points
            .map((point) => `${point.label}: ${reading(point.visits, 'visitor', 'visitors')}, ${reading(point.requests, 'page view', 'page views')}`)
            .join(', ')

        return h('div', { class: 'gop-visitors-chart' }, [
            h('div', {
                class: 'gop-visitors-plot',
                onPointermove: nearestPoint,
                onPointerdown: nearestPoint,
                onPointerleave: () => onHover(null),
            }, [
                h('svg', {
                    viewBox: '0 0 100 100',
                    preserveAspectRatio: 'none',
                    role: 'img',
                    'aria-label': axisLabel,
                }, [
                    ...marks.map((mark) =>
                        h('line', {
                            class: 'gop-visitors-grid',
                            x1: 0,
                            x2: 100,
                            y1: yOf(mark),
                            y2: yOf(mark),
                            'vector-effect': 'non-scaling-stroke',
                        })
                    ),
                    h('polygon', { class: 'gop-visitors-area', points: `0,100 ${visitsLine} 100,100` }),
                    h('polygon', { class: 'gop-visitors-band', points: `${requestsLine} ${backAlong(visitsLine)}` }),
                    h('polyline', { class: 'gop-visitors-line', points: visitsLine, 'vector-effect': 'non-scaling-stroke' }),
                    h('polyline', {
                        class: 'gop-visitors-line gop-visitors-requests',
                        points: requestsLine,
                        'vector-effect': 'non-scaling-stroke',
                    }),
                ]),
                ...marks.map((mark) =>
                    h('span', { class: 'gop-visitors-y gop-muted', style: { top: `${yOf(mark)}%` } }, compactNumber(mark))
                ),
                ...points.flatMap((point, position) =>
                    point.tick
                        ? [h('span', { class: 'gop-visitors-x gop-muted', style: { left: `${xOf(position)}%` } }, point.tick)]
                        : []
                ),
                hoveredPoint && h('span', { class: 'gop-visitors-crosshair', style: { left: `${xOf(hovered)}%` } }),
                hoveredPoint && h('span', {
                    class: 'gop-visitors-dot',
                    style: { left: `${xOf(hovered)}%`, top: `${yOf(hoveredPoint.visits)}%` },
                }),
                hoveredPoint && h('span', {
                    class: 'gop-visitors-dot gop-visitors-requests',
                    style: { left: `${xOf(hovered)}%`, top: `${yOf(hoveredPoint.requests)}%` },
                }),
                hoveredPoint && h('div', {
                    class: 'gop-visitors-tooltip',
                    style: xOf(hovered) > 50
                        ? { right: `calc(${100 - xOf(hovered)}% + 0.6rem)` }
                        : { left: `calc(${xOf(hovered)}% + 0.6rem)` },
                }, [
                    h('span', { class: 'gop-muted' }, hoveredPoint.label),
                    h('strong', { class: 'gop-strong' }, reading(hoveredPoint.visits, 'visitor', 'visitors')),
                    h('strong', { class: 'gop-strong gop-visitors-requests-text' },
                        reading(hoveredPoint.requests, 'page view', 'page views')
                    ),
                ]),
            ]),
        ])
    }

    /**
     * A headline reading under its heading, carrying the swatch of the line
     * it reads where it has one. The average visit has no line of its own.
     */
    const chartFigure = (heading, value, name, swatchClass) =>
        h('div', { class: `gop-visitors-reading gop-visitors-${name}` }, [
            h('span', { class: 'text-sm gop-muted' }, [
                swatchClass ? h('span', { class: swatchClass }) : null,
                heading,
            ]),
            h('span', { class: 'text-2xl font-bold gop-strong' }, value),
        ])

    /** 252 seconds as 4m 12s, 300 as 5m, and nothing measurable as a dash. */
    const duration = (seconds) => {
        if (seconds === null || seconds === undefined) {
            return '—'
        }

        const minutes = Math.floor(seconds / 60)

        if (minutes >= 60) {
            return `${Math.floor(minutes / 60)}h ${minutes % 60}m`
        }

        if (minutes === 0) {
            return `${seconds}s`
        }

        return seconds % 60 === 0 ? `${minutes}m` : `${minutes}m ${seconds % 60}s`
    }

    app.component('gop-site-visits', {
        props: ['card'],

        data() {
            return { selected: 0, hovered: null }
        },

        render() {
            const range = this.card.ranges[this.selected]

            return h('div', { class: 'gop-visitors' }, [
                h('div', { class: 'gop-visitors-bar' }, [
                    h('div', { class: 'gop-visitors-tabs', role: 'group', 'aria-label': 'Period' },
                        this.card.ranges.map((option, position) =>
                            h('button', {
                                type: 'button',
                                'aria-pressed': position === this.selected ? 'true' : 'false',
                                onClick: () => {
                                    this.selected = position
                                    this.hovered = null
                                },
                            }, option.label)
                        )
                    ),
                    h('span', { class: 'gop-visitors-period gop-muted' }, range.period),
                ]),
                h('div', { class: 'gop-visitors-panel bg-white dark:bg-gray-800 rounded-lg shadow' }, [
                    h('div', { class: 'gop-visitors-figure' }, [
                        chartFigure(this.card.averageVisitHeading, duration(range.averageVisit), 'visit', null),
                        chartFigure(this.card.heading, compactNumber(range.total), 'unique', 'gop-visitors-swatch'),
                        chartFigure(this.card.requestsHeading, compactNumber(range.requests), 'views', 'gop-visitors-swatch gop-visitors-requests'),
                    ]),
                    visitorsChart(range.points, this.hovered, (position) => {
                        this.hovered = position
                    }),
                ]),
            ])
        },
    })

    /** A feature in the most-read list: its readers, then its time. */
    const readingRow = (row) =>
        h('div', { style: { padding: '0.35rem 0' } }, [
            h('div', { style: { display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: '1rem' } }, [
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
                h('span', { class: 'text-sm font-bold gop-strong', style: { whiteSpace: 'nowrap' } }, `${row.reads} ${row.reads === 1 ? 'read' : 'reads'}`),
            ]),
            h('div', { class: 'text-xs gop-muted' }, [
                `${duration(row.seconds)} in all`,
                row.averageRead === null ? null : ` · ${duration(row.averageRead)} each`,
                row.reachedEnd === null ? null : ` · ${row.reachedEnd}% reached the end`,
            ].filter(Boolean).join('')),
        ])

    /** The line a reading card shows when nothing was read in its period. */
    const nothingRead = (card) => h('p', { class: 'gop-muted text-sm', style: { flex: '1' } }, card.emptyText)

    /**
     * A legend beside a donut, one arc per slice. `describe` words a slice
     * for the legend, given its share of the whole as a percentage.
     */
    const donut = (slices, describe) => {
        const total = slices.reduce((sum, slice) => sum + slice.value, 0)

        // Each circle carries a dash the length of its share, rotated past
        // everything before it.
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
                h('span', { class: 'text-xs gop-muted' }, describe(slice, Math.round((slice.value / total) * 100))),
            ])
        )

        return h('div', { style: { display: 'flex', alignItems: 'center', gap: '1rem' } }, [
            h('div', { style: { flex: '1', minWidth: '0' } }, legend),
            h('svg', { width: 120, height: 120, viewBox: '0 0 120 120', style: { flexShrink: '0' } }, arcs),
        ])
    }

    /**
     * Reading time over the last month: the total, then a legend of the
     * categories beside a donut of the time each took.
     */
    const categoryDonut = (card) => [
        h('div', { style: { marginBottom: '1rem' } }, [
            h('span', { class: 'text-2xl font-bold gop-strong' }, duration(card.total || 0)),
            h('span', { class: 'text-sm gop-muted' }, ` ${card.period}`),
        ]),
        donut(card.slices || [], (slice, percent) => `${slice.label} · ${duration(slice.value)} (${percent}%)`),
    ]

    /*
     * The dashboard's reading card: the donut, and the features the most
     * people read beneath it.
     */
    app.component('gop-features-by-category', {
        props: ['card'],

        render() {
            const footer = h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)])

            if (!this.card.total) {
                return panel(this.card.heading, [nothingRead(this.card), footer])
            }

            return panel(this.card.heading, [
                ...categoryDonut(this.card),
                h('h4', {
                    class: 'text-xs uppercase tracking-wide font-bold gop-muted',
                    style: { margin: '1.25rem 0 0.25rem' },
                }, this.card.listHeading),
                h('div', { class: 'gop-reading-list', style: { flex: '1' } }, (this.card.leaders || []).map(readingRow)),
                footer,
            ])
        },
    })

    /* The donut on its own, over the features index. */
    app.component('gop-reading-by-category', {
        props: ['card'],

        render() {
            return panel(this.card.heading, this.card.total ? categoryDonut(this.card) : [nothingRead(this.card)])
        },
    })

    /* The features the most people read over the last month. */
    app.component('gop-most-read', {
        props: ['card'],

        render() {
            const leaders = this.card.leaders || []

            return panel(this.card.heading, [
                h('p', { class: 'text-xs gop-muted', style: { marginBottom: '0.5rem' } }, this.card.period),
                leaders.length === 0
                    ? nothingRead(this.card)
                    : h('div', { class: 'gop-reading-list', style: { flex: '1' } }, leaders.map(readingRow)),
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
     * One slot's live adverts, one at a time: its creative with its own
     * figures for the last thirty days beside it, and under them arrows and a
     * dot apiece to turn through them when more than one is running, with
     * the report of the one on show to their right. The sponsorship panel's
     * section is sent only its one sponsorship, so it never turns. With
     * nothing live in the slot, the figures for every advert in it together
     * stand in.
     */
    const advertSlot = {
        props: ['section'],

        data() {
            return { index: 0 }
        },

        computed: {
            adverts() {
                return this.section.adverts || []
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
            const section = this.section
            const advert = this.shown
            const count = this.adverts.length

            const creative = advert && advert.image
                ? h('a', {
                      href: Nova.url(advert.link),
                      class: 'gop-advert-creative',
                      onClick: (event) => {
                          event.preventDefault()
                          Nova.visit(advert.link)
                      },
                  }, [h('img', { src: advert.image, alt: advert.title, class: 'rounded' })])
                : null

            const figures = advert || section

            const slide = h('div', { key: advert ? advert.link : 'none', class: 'gop-advert-slide' }, [
                creative,
                h('div', { class: 'gop-advert-figures' }, [
                    h('p', { class: 'text-xs gop-muted' }, advert ? advert.title : section.emptyText),
                    h('p', { class: 'text-xl font-bold gop-strong' },
                        Number(figures.impressions).toLocaleString() + ' impressions'
                    ),
                    h('p', { class: 'text-xs gop-muted' }, figures.summary),
                    section.notice ? h('p', { class: 'text-xs gop-strong', style: { marginTop: '0.25rem' } }, section.notice) : null,
                ]),
            ])

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

            return h('section', { class: 'gop-advert-slot', 'aria-label': section.heading }, [
                h('h4', { class: 'text-xs uppercase tracking-wide font-bold gop-muted' }, section.heading),
                h(Transition, { name: 'gop-fade', mode: 'out-in' }, { default: () => slide }),
                controls,
            ])
        },
    }

    /* The adverts card: the in-list slots, then the sponsorship panel. */
    app.component('gop-advert-reach', {
        props: ['card'],

        render() {
            return panel(this.card.heading, [
                ...(this.card.sections || []).map((section) => h(advertSlot, { key: section.heading, section })),
                h('div', { style: { flex: '1' } }),
                h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
            ])
        },
    })

    /* A top-five list over a resource's own index: who has the most on. */
    app.component('gop-leaderboard', {
        props: ['card'],

        render() {
            const rows = this.card.rows || []

            const list = rows.length === 0
                ? [h('p', { class: 'gop-muted text-sm' }, this.card.emptyText)]
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

            return panel(this.card.heading, [h('div', { style: { flex: '1' } }, list)])
        },
    })

    /*
     * The headline counts: venues, events and writers, ruled apart, their
     * figures set in the same columns all the way down.
     */
    app.component('gop-headline-counts', {
        props: ['card'],

        render() {
            const sections = this.card.sections || []
            const columns = Math.max(1, ...sections.map((section) => section.counts.length))

            return panel(this.card.heading, sections.map((section) =>
                h('section', { key: section.heading, class: 'gop-count-section' }, [
                    h('h4', { class: 'text-xs uppercase tracking-wide font-bold gop-muted', style: { marginBottom: '0.5rem' } }, section.heading),
                    h('div', { class: 'gop-count-row', style: { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` } },
                        section.counts.map((count) => bigNumber(count.value, count.label))
                    ),
                ])
            ))
        },
    })

    /*
     * The listings card: what is coming up, by category and by month, over
     * the download of next month's listings for the issue being laid out,
     * their number reversed out of a disk.
     */
    app.component('gop-listings-export', {
        props: ['card'],

        render() {
            const charts = this.card.charts || []
            const subheading = (text) =>
                h('h4', { class: 'text-xs uppercase tracking-wide font-bold gop-muted', style: { marginBottom: '0.5rem' } }, text)

            const upcoming = charts.some((chart) => chart.slices.length > 0)
                ? charts.map((chart) =>
                    h('section', { key: chart.heading, class: 'gop-listings-section' }, [
                        subheading(chart.heading),
                        donut(chart.slices, (slice, percent) => `${slice.label} · ${Number(slice.value).toLocaleString()} (${percent}%)`),
                    ])
                )
                : [h('section', { class: 'gop-listings-section' }, [h('p', { class: 'gop-muted text-sm' }, this.card.emptyText)])]

            return panel(this.card.heading, [
                ...upcoming,
                h('section', { class: 'gop-listings-section' }, [
                    cardHeading(this.card.exportHeading),
                    h('div', { style: { display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: '1rem 1.5rem' } }, [
                        h('div', { class: 'gop-disk' }, [
                            h('span', { class: 'text-3xl font-bold' }, Number(this.card.count).toLocaleString()),
                            h('span', { class: 'text-xs uppercase tracking-wide' }, this.card.countLabel),
                        ]),
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
                ]),
                h('div', { style: { flex: '1' } }),
                h('div', { style: { marginTop: '0.75rem' } }, [novaLink(app, this.card.linkText, this.card.link)]),
            ])
        },
    })
})
