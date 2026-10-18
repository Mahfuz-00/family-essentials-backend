<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Household ledger
    |--------------------------------------------------------------------------
    |
    | The ledger is money, so every amount in it is an integer count of minor
    | units (poisha, cents, …) and this is the single place the currency is
    | declared. Nothing in the API ever converts to a float.
    |
    */

    'currency' => env('LEDGER_CURRENCY', 'BDT'),

];
