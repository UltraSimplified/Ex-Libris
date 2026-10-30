<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gig widget host
    |--------------------------------------------------------------------------
    |
    | The host the gig widgets are served from: the loader script, the
    | embedded pages and the click-throughs. Snippets pasted into venues'
    | sites carry this host for good, so it is set once and not changed.
    | Left empty, the widget routes answer on whatever host the site is
    | reached by, which is right for local work and for previews before
    | the host is set up.
    |
    */

    'domain' => env('WIDGETS_DOMAIN'),

];
