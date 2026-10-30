/**
 * The Active writer tickbox on a writer's page in the Office, and the tick
 * in the Active writer column of the Writers list. Either one saves when
 * clicked.
 *
 * Written as render functions rather than single-file components so they
 * need no build step, the same way office-cards.js is written. Only on
 * the detail page and the list, so only registered as detail- and
 * index-<component>: the edit form has an ordinary checkbox for the same
 * column.
 */
Nova.booting((app) => {
    const { h, resolveComponent } = Vue;

    /*
     * What both share: the writer's standing as it is now, and saving a
     * change to it.
     */
    const savesActive = {
        data() {
            return {
                // The page is not reloaded after a change, so the field's
                // own value would go on saying what it said when it loaded.
                active: this.field.value,
                working: false,
            };
        },

        methods: {
            async save(active) {
                if (this.working) {
                    return;
                }

                this.working = true;

                try {
                    await Nova.request().put(this.field.endpoint, { active });

                    this.active = active;
                    Nova.success(
                        active
                            ? 'Listed on Our Writers.'
                            : 'Moved to the alumni.',
                    );
                } catch (error) {
                    Nova.error('That writer could not be changed just now.');
                } finally {
                    this.working = false;
                }
            },
        },
    };

    const detailActiveWriterToggle = {
        mixins: [savesActive],

        props: ['index', 'field'],

        methods: {
            async toggle(event) {
                const checkbox = event.target;

                await this.save(checkbox.checked);

                // Put the box back to what was saved, if the save failed or
                // another was still under way.
                checkbox.checked = this.active;
            },
        },

        /*
         * Laid out in Nova's own detail row, the one every other field on
         * the page sits in, with the tickbox as its value.
         */
        render() {
            return h(
                resolveComponent('PanelItem'),
                { index: this.index, field: this.field },
                {
                    value: () =>
                        h(
                            'label',
                            {
                                class: 'inline-flex items-center gap-2',
                                style: {
                                    cursor: this.working ? 'wait' : 'pointer',
                                },
                            },
                            [
                                h('input', {
                                    type: 'checkbox',
                                    class: 'checkbox',
                                    checked: this.active,
                                    disabled: this.working,
                                    onChange: this.toggle,
                                }),
                                h(
                                    'span',
                                    this.active
                                        ? 'Listed on Our Writers'
                                        : 'Alumni',
                                ),
                            ],
                        ),
                },
            );
        },
    };

    /*
     * The same tick or cross the list shows for any yes-or-no column, made
     * a button. A click on a row opens the writer, so the button keeps its
     * click to itself.
     */
    const indexActiveWriterToggle = {
        mixins: [savesActive],

        props: ['field'],

        render() {
            return h('div', { class: `text-${this.field.textAlign}` }, [
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'inline-block',
                        disabled: this.working,
                        title: this.active
                            ? 'Move to the alumni'
                            : 'List on Our Writers',
                        'aria-label': this.active
                            ? 'Active writer: click to move to the alumni'
                            : 'Alumni: click to list on Our Writers',
                        style: {
                            cursor: this.working ? 'wait' : 'pointer',
                            opacity: this.working ? '0.5' : '1',
                        },
                        onClick: (event) => {
                            event.stopPropagation();
                            this.save(!this.active);
                        },
                    },
                    [
                        h(resolveComponent('IconBoolean'), {
                            value: this.active,
                        }),
                    ],
                ),
            ]);
        },
    };

    app.component('detail-active-writer-toggle', detailActiveWriterToggle);
    app.component('index-active-writer-toggle', indexActiveWriterToggle);
});
