<?php

use Illuminate\Support\Facades\Mail;

/**
 * Production names Lettr as the default mailer, so the mailer has to exist
 * in config/mail.php or every email the site sends fails before it starts.
 */
test('the lettr mailer is defined and resolves to the lettr transport', function () {
    config(['lettr.api_key' => 'test-key']);

    $transport = Mail::mailer('lettr')->getSymfonyTransport();

    expect((string) $transport)->toBe('lettr');
});
