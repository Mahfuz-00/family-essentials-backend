<?php

namespace App\Plans;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A plan limit was reached.
 *
 * Extends HttpException so it renders as a 422 with its message, exactly like
 * `abort(422, ...)` — the caller does not have to catch it.
 */
class QuotaExceededException extends HttpException
{
    public function __construct(string $message, public readonly string $meter)
    {
        parent::__construct(422, $message);
    }
}
