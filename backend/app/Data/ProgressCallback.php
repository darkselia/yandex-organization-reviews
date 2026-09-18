<?php

namespace App\Data;

use Closure;

final readonly class ProgressCallback
{
    private Closure $callback;

    public function __construct(?callable $callback = null)
    {
        $this->callback = $callback === null
            ? static fn (int $fetched, ?int $expected): null => null
            : Closure::fromCallable($callback);
    }

    public function __invoke(int $fetched, ?int $expected): void
    {
        ($this->callback)($fetched, $expected);
    }
}
