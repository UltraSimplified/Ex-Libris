<template>
    <DefaultField
        :field="field"
        :errors="errors"
        :show-help-text="showHelpText"
        :full-width-content="fullWidthContent"
    >
        <template #field>
            <div class="specific-days" :dusk="`${field.attribute}-specific-days`">
                <p v-if="!runDays.length" class="specific-days__hint">
                    Give the listing an end date after its start to choose the
                    days it is on.
                </p>

                <template v-else>
                    <label class="specific-days__toggle">
                        <input
                            v-model="enabled"
                            type="checkbox"
                            class="checkbox"
                            dusk="set-specific-days"
                        />
                        Set specific days
                    </label>

                    <div
                        v-if="enabled"
                        role="group"
                        :aria-labelledby="`${field.uniqueKey}-help`"
                        class="specific-days__panel"
                    >
                        <p
                            :id="`${field.uniqueKey}-help`"
                            class="specific-days__help"
                        >
                            Tick the days the event is on, or tap Mon, Tue and
                            so on to tick every one of that weekday. When you
                            save, the start and end dates move to the first and
                            last day you tick.
                        </p>

                        <div class="specific-days__controls">
                            <button
                                type="button"
                                class="specific-days__chip"
                                @click="selectAll"
                            >
                                Select all
                            </button>
                            <button
                                type="button"
                                class="specific-days__chip"
                                @click="clearAll"
                            >
                                Clear all
                            </button>
                        </div>

                        <div class="specific-days__week">
                            <button
                                v-for="(weekday, column) in WEEKDAYS"
                                :key="weekday"
                                type="button"
                                class="specific-days__chip"
                                :class="{ 'is-on': weekdayTicked(column) }"
                                :aria-label="`Every ${weekday}`"
                                :aria-pressed="weekdayTicked(column)"
                                :disabled="runDaysOn(column).length === 0"
                                @click="toggleWeekday(column)"
                            >
                                {{ weekday.slice(0, 3) }}
                            </button>
                        </div>

                        <div
                            v-for="month in months"
                            :key="month.key"
                            class="specific-days__month"
                        >
                            <p class="specific-days__month-title">
                                {{ month.title }}
                            </p>
                            <div
                                v-for="(week, row) in month.weeks"
                                :key="row"
                                class="specific-days__week"
                            >
                                <template
                                    v-for="(day, column) in week"
                                    :key="day?.key ?? column"
                                >
                                    <button
                                        v-if="day?.inRun"
                                        type="button"
                                        class="specific-days__day"
                                        :class="{
                                            'is-on': chosen.has(day.key),
                                            'is-today': day.isToday,
                                        }"
                                        :data-day="day.key"
                                        :aria-label="longDate(day.date)"
                                        :aria-pressed="chosen.has(day.key)"
                                        :aria-current="
                                            day.isToday ? 'date' : undefined
                                        "
                                        @click="toggleDay(day.key)"
                                    >
                                        {{ day.date.getDate() }}
                                    </button>
                                    <span
                                        v-else-if="day"
                                        aria-hidden="true"
                                        class="specific-days__day is-outside"
                                        :class="{ 'is-today': day.isToday }"
                                    >
                                        {{ day.date.getDate() }}
                                    </span>
                                    <span v-else />
                                </template>
                            </div>
                        </div>

                        <div class="specific-days__status" aria-live="polite">
                            <p data-chosen-count>
                                {{ chosenInRun.length }} of
                                {{ runDays.length }} days chosen
                            </p>
                            <p v-if="leftOut" class="specific-days__left-out">
                                {{
                                    leftOut === 1
                                        ? '1 chosen day is'
                                        : `${leftOut} chosen days are`
                                }}
                                outside the new dates, so will not be saved.
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova';
import { ref } from 'vue';
import { useSpecificDays, WEEKDAYS } from '@/composables/useSpecificDays';

/*
 * The Office's calendar of specific days: the same behaviour as the
 * Dashboard's (useSpecificDays), drawn in the Office's own colours.
 *
 * It follows the start and end date fields through the events Nova's form
 * fields send as they change, rather than dependsOn, whose round trip to
 * the server would put back the stored days over whatever had been ticked.
 */
export default {
    mixins: [FormField, HandlesValidationErrors],

    setup(props) {
        const start = ref(props.field.start || '');
        const end = ref(props.field.end || '');
        const value = props.field.value || {};

        return {
            WEEKDAYS,
            start,
            end,
            ...useSpecificDays(
                start,
                end,
                value.enabled ? value.days || [] : [],
            ),
        };
    },

    data() {
        return { dateListeners: [] };
    },

    mounted() {
        const toDay = (date) => String(date ?? '').slice(0, 10);

        this.dateListeners = [
            [
                this.getFieldAttributeChangeEventName(this.field.startAttribute),
                (date) => (this.start = toDay(date)),
            ],
            [
                this.getFieldAttributeChangeEventName(this.field.endAttribute),
                (date) => (this.end = toDay(date)),
            ],
        ];

        this.dateListeners.forEach(([event, listener]) =>
            Nova.$on(event, listener),
        );
    },

    beforeUnmount() {
        this.dateListeners.forEach(([event, listener]) =>
            Nova.$off(event, listener),
        );
    },

    methods: {
        /*
         * Only the ticked days inside the run go, and only while the box is
         * ticked on a run of more than a day.
         */
        fill(formData) {
            this.fillIfVisible(
                formData,
                this.fieldAttribute,
                JSON.stringify({
                    enabled: this.enabled && this.runDays.length > 0,
                    days: this.chosenInRun,
                }),
            );
        },

        longDate(date) {
            return date.toLocaleDateString('en-GB', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
    },
};
</script>
