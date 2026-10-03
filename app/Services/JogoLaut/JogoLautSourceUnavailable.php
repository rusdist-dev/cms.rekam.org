<?php

namespace App\Services\JogoLaut;

use RuntimeException;
use Throwable;

/**
 * An upstream read that failed — now, or recently enough that it is not being
 * retried yet. Reported once, where the read actually failed; everything that
 * merely depends on the read rethrows this quietly.
 */
class JogoLautSourceUnavailable extends RuntimeException
{
    public function __construct(public readonly string $source, ?Throwable $previous = null)
    {
        parent::__construct("Sumber JOGO LAUT '{$source}' tidak tersedia.", 0, $previous);
    }
}
