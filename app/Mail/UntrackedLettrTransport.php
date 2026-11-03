<?php

namespace App\Mail;

use Lettr\Builders\EmailBuilder;
use Lettr\Laravel\Transport\LettrTransportFactory;
use Symfony\Component\Mime\Email;

/**
 * Lettr's transport with its open and click tracking off. Left on, the SDK
 * rewrites every link, reset and verification links among them, and adds a
 * tracking pixel, and the mail transport has no setting of its own for this.
 */
class UntrackedLettrTransport extends LettrTransportFactory
{
    protected function configureContent(EmailBuilder $builder, Email $email): void
    {
        parent::configureContent($builder, $email);

        $this->withoutTracking($builder);
    }

    protected function configureTemplate(EmailBuilder $builder, Email $email, string $templateSlug): void
    {
        parent::configureTemplate($builder, $email, $templateSlug);

        $this->withoutTracking($builder);
    }

    private function withoutTracking(EmailBuilder $builder): void
    {
        $builder->withClickTracking(false)->withOpenTracking(false);
    }
}
