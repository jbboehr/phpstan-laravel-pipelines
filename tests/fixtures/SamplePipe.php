<?php

namespace jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture;

class SamplePipe
{
    public function handle(int $value): int
    {
        return 14 + $value * 88;
    }
}
