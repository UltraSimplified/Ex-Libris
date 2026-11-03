<?php

use App\Events\Shop\OrderPaid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Lettr\Contracts\TransporterContract;
use Lettr\Exceptions\ValidationException;
use Lettr\Lettr;

/**
 * Stands in for Lettr's API, so these exercise the real mailer and transport
 * without anything leaving the machine. Returns the requests as they arrive.
 */
function fakeLettrApi(): ArrayObject
{
    $requests = new ArrayObject;

    $api = Mockery::mock(TransporterContract::class);
    $api->allows('lastResponseHeaders')->andReturn([]);
    $api->allows('post')->andReturnUsing(function (string $uri, array $data) use ($requests) {
        $requests[] = ['uri' => $uri, 'data' => $data];

        return ['request_id' => 'test-request', 'accepted' => 1, 'rejected' => 0];
    });

    app()->instance(Lettr::class, new Lettr($api));

    return $requests;
}

/**
 * Sending goes through Lettr by default, as it does on live and staging.
 */
function useLettrMailer(): void
{
    config([
        'mail.default' => 'lettr',
        'mail.from.address' => 'donotreply@telecaster.goodonpaper.info',
        'mail.from.name' => 'Good on Paper',
    ]);
}

/**
 * Production names Lettr as the default mailer, so the mailer has to exist
 * in config/mail.php or every email the site sends fails before it starts.
 */
test('the lettr mailer is defined and resolves to the lettr transport', function () {
    config(['lettr.api_key' => 'test-key']);

    $transport = Mail::mailer('lettr')->getSymfonyTransport();

    expect((string) $transport)->toBe('lettr');
});

test('a lost password email goes to Lettr from the configured sender with a working link', function () {
    // An Office request earlier in this process leaves Nova's own reset email set.
    ResetPassword::toMailUsing(null);

    useLettrMailer();
    $requests = fakeLettrApi();
    $user = User::factory()->create(['email' => 'reader@example.com']);

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

    expect($requests)->toHaveCount(1);

    $email = $requests[0]['data'];

    expect($requests[0]['uri'])->toBe('emails')
        ->and($email['from'])->toBe('donotreply@telecaster.goodonpaper.info')
        ->and($email['from_name'])->toBe('Good on Paper')
        ->and($email['to'])->toBe(['reader@example.com'])
        ->and($email['subject'])->toBe('Reset your password')
        ->and($email['options']['click_tracking'])->toBeFalse()
        ->and($email['options']['open_tracking'])->toBeFalse();

    preg_match('#href="([^"]+/reset-password/[^"]+)"#', $email['html'], $link);

    $this->get(html_entity_decode($link[1]))->assertOk();
});

test('an email verification message goes to Lettr with a signed link', function () {
    useLettrMailer();
    $requests = fakeLettrApi();
    $user = User::factory()->unverified()->create(['email' => 'reader@example.com']);

    $this->actingAs($user)->post(route('verification.send'));

    expect($requests)->toHaveCount(1);

    $email = $requests[0]['data'];

    expect($email['to'])->toBe(['reader@example.com'])
        ->and($email['subject'])->toBe('Verify your email address')
        ->and($email['html'])->toContain('/email/verify/'.$user->id)
        ->and($email['html'])->toContain('signature=');
});

test('the shop emails for a paid order reach Lettr with their reply-to and copy addresses', function () {
    useLettrMailer();
    config(['shop.notify.to' => 'orders@goodonpaper.info', 'shop.notify.cc' => 'accounts@goodonpaper.info']);
    $requests = fakeLettrApi();
    $order = Order::factory()->paid()->create(['email' => 'reader@example.com']);

    OrderPaid::dispatch($order);

    $emails = collect($requests)->pluck('data')->keyBy(fn (array $email) => $email['to'][0]);

    expect($emails)->toHaveCount(2)
        ->and($emails['reader@example.com']['reply_to'])->toBe('orders@goodonpaper.info')
        ->and($emails['reader@example.com']['from'])->toBe('donotreply@telecaster.goodonpaper.info')
        ->and($emails['orders@goodonpaper.info']['cc'])->toBe(['accounts@goodonpaper.info']);
});

test('a Lettr template goes out without tracking too', function () {
    useLettrMailer();
    $requests = fakeLettrApi();

    Mail::lettr()->to('reader@example.com')->sendTemplate('a-template', subject: 'A subject');

    expect($requests[0]['data']['template_slug'])->toBe('a-template')
        ->and($requests[0]['data']['options']['click_tracking'])->toBeFalse()
        ->and($requests[0]['data']['options']['open_tracking'])->toBeFalse();
});

test('a send Lettr refuses fails loudly instead of telling the reader it went', function () {
    useLettrMailer();

    $api = Mockery::mock(TransporterContract::class);
    $api->allows('post')->andThrow(new ValidationException('The from domain is not verified.'));
    app()->instance(Lettr::class, new Lettr($api));

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertServerError();
});
