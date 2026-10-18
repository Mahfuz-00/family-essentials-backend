<?php

namespace App\Enums;

enum LedgerEntrySource: string
{
    /** Typed into the ledger. */
    case Manual = 'manual';

    /** Written by the completion bridge when a request carries a ledger. */
    case Request = 'request';
}
