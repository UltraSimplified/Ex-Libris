<?php

/**
 * Advert settings.
 *
 * listings_every: on the listings pages, an advert from the live line-up
 *                 goes in after every this many events. 0 leaves them out.
 */
return [

    'listings_every' => (int) env('LISTINGS_ADVERT_EVERY', 5),

];
