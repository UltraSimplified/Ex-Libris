<?php

namespace Ultrasimplified\VideoEmbed;

use Laravel\Nova\Fields\Textarea;

/**
 * A textarea for the embed code YouTube or Vimeo gives out, showing the
 * video above it as the code is pasted.
 *
 * It is stored exactly as a textarea is. The preview is drawn in the
 * browser from the address in the pasted iframe, and only for a YouTube
 * or Vimeo player, so pasted markup is never itself put into the page.
 */
class VideoEmbed extends Textarea
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'video-embed';
}
