<script setup lang="ts"></script>
<!-- SUBSCRIBE -->
<template>
    <div
        class="bg-[url('@/../img/rectangle.png')] bg-cover bg-center px-5 py-10 md:w-full md:border-l md:border-silver lg:pr-0"
    >
        <div class="flex justify-start">
            <!--
                Side by side the cover and the form come to a fixed 800px -
                480 of artwork and 320 of form, neither of which gives. The
                page holds its content off a 192px left margin, so there is
                not room for that until about 1000px wide, and asking for
                it at md put 800px into a 576px column: centred, it spilled
                over both edges at once and took the form off the right.

                So the turn to a row waits for lg, and below that the cover
                sits above the form, which is what the phone layout has
                always done.

                Stacked, the two are still 320px of fixed width apiece and
                gave nothing back, so anything narrower than that - a small
                phone, a split screen, a zoomed-in page - was pushed wider
                than the window. The hamburger is positioned against the
                window, so it went with it. They cap at the width they are
                given now.

                The block sits against the page's 20px left gutter. At lg
                it keeps that gutter but none on the right: 20 and 800 fit
                the 830px column, where 20 either side would not.
            -->
            <div class="flex max-w-full flex-col lg:flex-row">
                <img src="@/../img/egg.png" class="w-80 max-w-full lg:hidden" />
                <img
                    src="@/../img/egg-wide.png"
                    class="hidden w-140 max-w-120 lg:block"
                />
                <div class="w-80 max-w-full bg-white p-6 dark:bg-surface">
                    <div
                        class="font-haas text-xl/4 font-bold text-black hover:text-gray-600 md:text-2xl/5 lg:pr-15 dark:text-white"
                    >
                        GOOD<br />ON<br />PAPER
                    </div>
                    <div id="mc_embed_shell">
                        <div id="mc_embed_signup">
                            <form
                                action="https://goodonpaper.us1.list-manage.com/subscribe/post?u=07b4227f365952d45c4b4882c&amp;id=9b19beef3a&amp;f_id=001fc7e4f0"
                                method="post"
                                id="mc-embedded-subscribe-form"
                                name="mc-embedded-subscribe-form"
                                class="validate"
                                target="_self"
                                novalidate="true"
                            >
                                <div id="mc_embed_signup_scroll">
                                    <h2 class="mt-5 text-xl">
                                        Subscribe to our mailing list
                                    </h2>

                                    <div class="mc-field-group">
                                        <input
                                            type="email"
                                            name="EMAIL"
                                            class="required email mt-6 w-full rounded-xl border border-silver bg-transparent p-3"
                                            id="mce-EMAIL"
                                            required="true"
                                            placeholder="you@domain.com"
                                            value=""
                                        />
                                    </div>
                                    <div id="mce-responses" class="clear foot">
                                        <div
                                            class="response"
                                            id="mce-error-response"
                                            style="display: none"
                                        ></div>
                                        <div
                                            class="response"
                                            id="mce-success-response"
                                            style="display: none"
                                        ></div>
                                    </div>
                                    <div
                                        style="
                                            position: absolute;
                                            left: -5000px;
                                        "
                                        aria-hidden="true"
                                    >
                                        /* real people should not fill this in
                                        and expect good things - do not remove
                                        this or risk form bot signups */
                                        <input
                                            type="text"
                                            name="b_07b4227f365952d45c4b4882c_9b19beef3a"
                                            tabindex="-1"
                                            value=""
                                        />
                                    </div>
                                    <div class="optionalParent">
                                        <div class="clear foot">
                                            <button
                                                id="mc-embedded-subscribe"
                                                type="submit"
                                                name="submit"
                                                value="subscribe"
                                                class="clear-both mt-8 rounded-md bg-tarmac px-8 py-3 text-sm font-bold text-white dark:bg-silver dark:hover:bg-hivis dark:hover:text-black"
                                            >
                                                Subscribe
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
