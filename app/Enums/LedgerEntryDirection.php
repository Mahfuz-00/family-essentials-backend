<?php

namespace App\Enums;

enum LedgerEntryDirection: string
{
    case Expense = 'expense';
    case Income = 'income';
}
