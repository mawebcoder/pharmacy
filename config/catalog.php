<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Product types that cannot be activated for sale
    |--------------------------------------------------------------------------
    |
    | Prescription medicines (rx) are out of MVP scope (roadmap D0 rule 9). OTC medicines are sellable by the
    | owner's decision of 2026-11-07 (S10); the legal check before selling OTC is still open (roadmap D10 #8).
    | Add 'otc' here to stop OTC sales again.
    |
    */

    'unsellable_product_types' => ['rx'],

    /*
    |--------------------------------------------------------------------------
    | Near expiry
    |--------------------------------------------------------------------------
    |
    | A variant whose expiry date is within this many days is marked near-expiry ("زودمصرف", roadmap D9).
    |
    */

    'near_expiry_days' => 90,

];
