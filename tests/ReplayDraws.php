<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use Riddler\Uxid\DrawSource;

/**
 * A draw source that replays known values in order, as the conformance
 * corpus's monotonic cases give them: each seed an unsigned integer written
 * as the field's big-endian bytes, each step as given. Running out of either
 * is an error, so a test sees a draw it did not expect.
 */
final class ReplayDraws implements DrawSource
{
    /** @var list<string> the draws made, in order: `seed:<width>` or `step:<width>` */
    public array $calls = [];

    /**
     * @param list<int> $seeds the seeds, in the order they are drawn
     * @param list<int> $steps the steps, in the order they are drawn
     */
    public function __construct(
        private array $seeds,
        private array $steps,
    ) {}

    public function seed(int $width): string
    {
        $this->calls[] = 'seed:' . $width;
        $seed = array_shift($this->seeds);
        if ($seed === null) {
            throw new \LogicException('No seed is left to replay.');
        }

        $bytes = '';
        for ($index = 0; $index < $width; $index++) {
            $bytes = \chr($seed & 0xFF) . $bytes;
            $seed >>= 8;
        }

        return $bytes;
    }

    public function step(int $width): int
    {
        $this->calls[] = 'step:' . $width;
        $step = array_shift($this->steps);
        if ($step === null) {
            throw new \LogicException('No step is left to replay.');
        }

        return $step;
    }
}
