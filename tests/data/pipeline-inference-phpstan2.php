<?php

namespace jbboehr\PHPStan\Laravel\Pipeline\Tests\Data;

use Illuminate\Pipeline\Pipeline;
use jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe;
use function PHPStan\Testing\assertType;

$pipeline = new Pipeline();

assertType('Illuminate\Pipeline\Pipeline<list<(callable(): mixed)|object>, string, mixed>', $pipeline);

$pipeline->via('handle');

assertType("Illuminate\Pipeline\Pipeline<list<(callable(): mixed)|object>, 'handle', mixed>", $pipeline);

$pipeline->send(1);

assertType("Illuminate\Pipeline\Pipeline<list<(callable(): mixed)|object>, 'handle', 1>", $pipeline);

$pipeline->through(new SamplePipe());

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1>", $pipeline);

$pipeline->through(new SamplePipe());

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe, jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1>", $pipeline);

$pipeline->pipe(new SamplePipe());

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1>", $pipeline);

if (random_int(0, 10) > 5) {
    $pipeline->pipe([new SamplePipe(), new SamplePipe()]);
} else {
    $pipeline->pipe(new SamplePipe());
}

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1>|Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe, jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1>", $pipeline);
