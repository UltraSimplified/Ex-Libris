<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import WidgetController from '@/actions/App/Http/Controllers/Dashboard/WidgetController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { type BrandColours } from '../../../components/dashboard/BrandFields.vue';
import ColourField from '../../../components/dashboard/ColourField.vue';
import FormErrors from '../../../components/dashboard/FormErrors.vue';
import WidgetPreview from '../../../components/dashboard/WidgetPreview.vue';
import Heading from '../../../components/Heading.vue';
import InputError from '../../../components/InputError.vue';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import AppLayout from '../../../layouts/AppLayout.vue';

type Choice = { value: string; label: string; description: string | null };

type Widget = {
    id: number;
    key: string;
    name: string;
    owner: string | null;
    for_promoter: boolean;
    template: string;
    density: string;
    font: string;
    size_mode: string;
    width: number | null;
    height: number | null;
    active: boolean;
    options: {
        count: number;
        show_images: boolean;
        show_price: boolean;
        show_venue: boolean;
        button_text: string;
    };
    use_brand: boolean;
    colours: BrandColours;
};

const props = defineProps<{
    widget: Widget;
    ownerColours: BrandColours | null;
    ownerEditUrl: string | null;
    snippets: { script: string; iframe: string; link: string };
    hostReady: boolean;
    embedUrl: string;
    clicks: number;
    templates: Choice[];
    densities: Choice[];
    fonts: Choice[];
    sizeModes: Choice[];
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Gig widgets', href: WidgetController.index().url },
    {
        title: props.widget.name,
        href: WidgetController.edit(props.widget.id).url,
    },
];

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';

/*
 * What the preview shows, kept in step with the form as it is filled in.
 * The form still posts its own fields; these only drive the preview.
 */
const settings = reactive({
    density: props.widget.density,
    font: props.widget.font,
    sizeMode: props.widget.size_mode,
    width: props.widget.width ?? undefined,
    height: props.widget.height ?? undefined,
    options: { ...props.widget.options },
    useBrand: props.widget.use_brand,
    colours: {
        ...(props.widget.use_brand && props.ownerColours
            ? props.ownerColours
            : props.widget.colours),
    },
});

const colourFields = [
    { name: 'brand_colour', label: 'Brand colour' },
    { name: 'accent_colour1', label: 'Accent colour' },
    { name: 'accent_colour2', label: 'Second accent colour' },
    { name: 'brand_background_colour', label: 'Background colour' },
] as const;

/**
 * The embed with the unsaved settings in its query. Only how it looks can
 * be changed that way; nothing is saved until the form is.
 */
const previewQuery = computed<string>(() => {
    const query = new URLSearchParams({
        preview: '1',
        density: settings.density,
        font: settings.font,
        'options[count]': String(settings.options.count || 1),
        'options[show_images]': settings.options.show_images ? '1' : '0',
        'options[show_price]': settings.options.show_price ? '1' : '0',
        'options[show_venue]': settings.options.show_venue ? '1' : '0',
        'options[button_text]': settings.options.button_text ?? '',
    });

    if (settings.useBrand) {
        query.set('colours', '');
    } else {
        colourFields.forEach(({ name }) =>
            query.set(`colours[${name}]`, settings.colours[name] ?? ''),
        );
    }

    return query.toString();
});

/*
 * Reloading the frame on every keystroke would flicker, so the preview
 * catches up once typing pauses.
 */
const previewSrc = ref(`${props.embedUrl}?${previewQuery.value}`);
let pending: ReturnType<typeof setTimeout> | undefined;

watch(previewQuery, (query) => {
    clearTimeout(pending);
    pending = setTimeout(() => {
        previewSrc.value = `${props.embedUrl}?${query}`;
    }, 350);
});

const snippetTabs = [
    {
        key: 'script',
        label: 'Script',
        hint: 'Best for most sites - WordPress, Squarespace code blocks, Webflow, your own HTML. Grows to fit your events.',
    },
    {
        key: 'iframe',
        label: 'iframe',
        hint: 'For site builders that strip out scripts. Keeps the height set below and scrolls inside if your events need more room.',
    },
    {
        key: 'link',
        label: 'Link',
        hint: 'A page of its own, for a link in bio, an email or a QR code.',
    },
] as const;

const snippetTab = ref<(typeof snippetTabs)[number]['key']>('script');
const copied = ref(false);

const copySnippet = async (): Promise<void> => {
    await navigator.clipboard.writeText(props.snippets[snippetTab.value]);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const confirmDelete = (): boolean =>
    window.confirm(
        'Delete this widget? Anywhere its snippet is pasted will show nothing.',
    );
</script>

<template>
    <Head :title="`Edit ${widget.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="Edit widget"
                :subject="widget.name"
                :description="`Showing upcoming events from ${widget.owner ?? 'nobody'}.`"
            />

            <p
                v-if="status"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-700 dark:text-green-500"
            >
                {{ status }}
            </p>

            <div
                class="grid gap-8 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]"
            >
                <Form
                    v-bind="WidgetController.update.form(widget.id)"
                    class="space-y-8"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                >
                    <FormErrors :errors="errors" subject="widget" />

                    <input
                        type="hidden"
                        name="template"
                        :value="widget.template"
                    />

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="widget.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <fieldset class="grid gap-3">
                        <legend class="text-sm font-medium">Style</legend>
                        <label
                            v-for="density in densities"
                            :key="density.value"
                            class="flex cursor-pointer items-start gap-3 rounded-md border border-input p-3 has-[:checked]:border-foreground"
                        >
                            <input
                                v-model="settings.density"
                                type="radio"
                                name="density"
                                :value="density.value"
                                class="mt-1"
                            />
                            <span>
                                <span class="block text-sm font-medium">{{
                                    density.label
                                }}</span>
                                <span
                                    class="block text-sm text-muted-foreground"
                                    >{{ density.description }}</span
                                >
                            </span>
                        </label>
                        <InputError :message="errors.density" />
                    </fieldset>

                    <fieldset class="grid gap-4">
                        <legend class="text-sm font-medium">
                            What it shows
                        </legend>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="count">Number of events</Label>
                                <Input
                                    id="count"
                                    v-model.number="settings.options.count"
                                    name="options[count]"
                                    type="number"
                                    min="1"
                                    max="50"
                                />
                                <InputError
                                    :message="errors['options.count']"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="button_text">Button wording</Label>
                                <Input
                                    id="button_text"
                                    v-model="settings.options.button_text"
                                    name="options[button_text]"
                                    maxlength="30"
                                    placeholder="Tickets"
                                />
                                <InputError
                                    :message="errors['options.button_text']"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2 text-sm">
                            <label class="flex items-center gap-2">
                                <input
                                    type="hidden"
                                    name="options[show_images]"
                                    value="0"
                                />
                                <input
                                    v-model="settings.options.show_images"
                                    type="checkbox"
                                    name="options[show_images]"
                                    value="1"
                                    class="size-4 rounded border-input"
                                />
                                Show pictures
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    type="hidden"
                                    name="options[show_price]"
                                    value="0"
                                />
                                <input
                                    v-model="settings.options.show_price"
                                    type="checkbox"
                                    name="options[show_price]"
                                    value="1"
                                    class="size-4 rounded border-input"
                                />
                                Show prices
                            </label>
                            <label
                                v-if="widget.for_promoter"
                                class="flex items-center gap-2"
                            >
                                <input
                                    type="hidden"
                                    name="options[show_venue]"
                                    value="0"
                                />
                                <input
                                    v-model="settings.options.show_venue"
                                    type="checkbox"
                                    name="options[show_venue]"
                                    value="1"
                                    class="size-4 rounded border-input"
                                />
                                Show the venue
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4">
                        <legend class="text-sm font-medium">Look</legend>

                        <div class="grid gap-2">
                            <Label for="font">Typeface</Label>
                            <select
                                id="font"
                                v-model="settings.font"
                                name="font"
                                :class="fieldClass"
                            >
                                <option
                                    v-for="font in fonts"
                                    :key="font.value"
                                    :value="font.value"
                                >
                                    {{ font.label }}
                                </option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="use_brand" value="0" />
                            <input
                                v-model="settings.useBrand"
                                type="checkbox"
                                name="use_brand"
                                value="1"
                                class="size-4 rounded border-input"
                            />
                            Use
                            {{
                                widget.for_promoter
                                    ? 'the promoter'
                                    : 'the venue'
                            }}'s brand colours
                        </label>
                        <p
                            v-if="settings.useBrand && ownerEditUrl"
                            class="-mt-2 text-sm text-muted-foreground"
                        >
                            Change them on
                            <Link
                                :href="ownerEditUrl"
                                class="underline underline-offset-4"
                                >{{ widget.owner }}'s page</Link
                            >, and every widget using them follows.
                        </p>

                        <div
                            v-if="!settings.useBrand"
                            class="grid gap-4 sm:grid-cols-2"
                        >
                            <ColourField
                                v-for="field in colourFields"
                                :key="field.name"
                                v-model="settings.colours[field.name]"
                                :name="`colours[${field.name}]`"
                                :label="field.label"
                                :error="errors[`colours.${field.name}`]"
                            />
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4">
                        <legend class="text-sm font-medium">
                            Size on your page
                        </legend>

                        <div class="grid gap-2 text-sm">
                            <label
                                v-for="mode in sizeModes"
                                :key="mode.value"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="settings.sizeMode"
                                    type="radio"
                                    name="size_mode"
                                    :value="mode.value"
                                />
                                {{ mode.label }}
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="width"
                                    >Widest it gets (pixels)</Label
                                >
                                <Input
                                    id="width"
                                    v-model.number="settings.width"
                                    name="width"
                                    type="number"
                                    min="200"
                                    max="2400"
                                    placeholder="As wide as its space"
                                />
                                <InputError :message="errors.width" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="height">Height (pixels)</Label>
                                <Input
                                    id="height"
                                    v-model.number="settings.height"
                                    name="height"
                                    type="number"
                                    min="100"
                                    max="4000"
                                    :placeholder="
                                        settings.sizeMode === 'fixed'
                                            ? 'Required'
                                            : '600'
                                    "
                                />
                                <InputError :message="errors.height" />
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            "Fit the events" grows and shrinks with the script
                            snippet. The height is used by the iframe snippet,
                            and by a fixed-size widget, which scrolls inside.
                        </p>
                    </fieldset>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="active" value="0" />
                        <input
                            type="checkbox"
                            name="active"
                            value="1"
                            :checked="widget.active"
                            class="size-4 rounded border-input"
                        />
                        Switched on - untick to hide it everywhere it is pasted
                    </label>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing" type="submit">
                            {{ processing ? 'Saving…' : 'Save changes' }}
                        </Button>
                        <Link
                            :href="WidgetController.index()"
                            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Back to widgets
                        </Link>
                    </div>
                </Form>

                <div class="space-y-8 xl:sticky xl:top-4 xl:self-start">
                    <section class="space-y-3">
                        <h2 class="text-sm font-medium">Preview</h2>
                        <WidgetPreview :src="previewSrc" />
                        <p class="text-sm text-muted-foreground">
                            Showing your changes before you save them. The
                            events are the real ones.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <h2 class="text-sm font-medium">
                                Put it on your website
                            </h2>
                            <div class="flex gap-1" role="tablist">
                                <button
                                    v-for="tab in snippetTabs"
                                    :key="tab.key"
                                    type="button"
                                    role="tab"
                                    :aria-selected="snippetTab === tab.key"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        snippetTab === tab.key
                                            ? 'bg-foreground text-background'
                                            : 'text-muted-foreground hover:bg-muted'
                                    "
                                    @click="snippetTab = tab.key"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>
                        </div>

                        <p
                            v-if="!hostReady"
                            class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm text-amber-800 dark:text-amber-300"
                        >
                            The widget's permanent web address is still being
                            set up. Try the snippet out, but wait for the
                            address before pasting it into your site for good.
                        </p>

                        <p class="text-sm text-muted-foreground">
                            {{
                                snippetTabs.find(
                                    (tab) => tab.key === snippetTab,
                                )?.hint
                            }}
                        </p>

                        <div class="relative">
                            <pre
                                class="overflow-x-auto rounded-md border border-input bg-muted/50 p-3 pr-12 text-xs leading-relaxed break-all whitespace-pre-wrap"
                            ><code>{{ snippets[snippetTab] }}</code></pre>
                            <button
                                type="button"
                                class="absolute top-2 right-2 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                :aria-label="copied ? 'Copied' : 'Copy snippet'"
                                @click="copySnippet"
                            >
                                <Check v-if="copied" class="size-4" />
                                <Copy v-else class="size-4" />
                            </button>
                        </div>

                        <a
                            :href="snippets.link"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Open it on its own
                            <ExternalLink class="size-3.5" />
                        </a>
                    </section>

                    <section class="space-y-1">
                        <h2 class="text-sm font-medium">How it's doing</h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                clicks === 1
                                    ? '1 ticket click'
                                    : `${clicks} ticket clicks`
                            }}
                            in the last four weeks, sent to your ticket page
                            tagged as coming from Good on Paper.
                        </p>
                    </section>

                    <Link
                        :href="WidgetController.destroy.url(widget.id)"
                        method="delete"
                        as="button"
                        class="text-sm text-destructive underline-offset-4 hover:underline"
                        :on-before="confirmDelete"
                    >
                        Delete this widget
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
