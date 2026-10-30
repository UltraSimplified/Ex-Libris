<?php

namespace App\Services\Adverts;

/**
 * The two sets of adverts that take turns, each among themselves.
 *
 * Sponsorships fill the panel at the foot of every page; every other advert
 * fills the slots among the listings, stockists and features. Neither is ever
 * shown in the other's place.
 */
enum AdvertPool: string
{
    case Sponsorship = 'sponsorship';
    case InList = 'in-list';
}
