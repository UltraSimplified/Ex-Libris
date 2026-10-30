<?php

/**
 * Visitor geolocation settings.
 *
 * driver:  which GeoResolver implementation to bind.
 * buckets: editorial locality groupings for advert stats. City names
 *          must match what the geolocation provider emits — check the
 *          raw `city` column periodically for spellings falling through
 *          to "Elsewhere UK" and fold them in here.
 */
return [

    'driver' => env('GEO_DRIVER', 'null'), // 'cloudflare' | 'null'

    'buckets' => [

        'Stroud district' => [
            'Stroud', 'Nailsworth', 'Stonehouse', 'Dursley', 'Cam',
            'Painswick', 'Minchinhampton', 'Chalford', 'Bussage',
            'Brimscombe', 'Thrupp', 'Rodborough', 'Woodchester',
            'Amberley', 'Kings Stanley', 'Leonard Stanley', 'Eastington',
            'Whiteshill', 'Randwick', 'Slad', 'Bisley', 'Oakridge',
            'Uley', 'Horsley', 'Wotton-under-Edge', 'Berkeley',
        ],

        'Gloucester' => [
            'Gloucester', 'Quedgeley', 'Hucclecote', 'Brockworth',
            'Churchdown', 'Longlevens', 'Barnwood',
        ],

        'Cheltenham' => [
            'Cheltenham', 'Charlton Kings', 'Leckhampton', 'Prestbury',
            'Bishops Cleeve', 'Shurdington',
        ],

        'Bristol' => [
            'Bristol', 'Filton', 'Kingswood', 'Bradley Stoke',
            'Portishead', 'Keynsham', 'Yate',
        ],

        'Cirencester & the Cotswolds' => [
            'Cirencester', 'Tetbury', 'Malmesbury', 'Northleach',
        ],

    ],
];
