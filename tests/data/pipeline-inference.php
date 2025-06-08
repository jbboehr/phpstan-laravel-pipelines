<?php

namespace jbboehr\PHPStan\Laravel\Pipeline\Tests\Data;

use Illuminate\Pipeline\Pipeline;
use jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe;
use function PHPStan\Testing\assertType;

$pipeline = new Pipeline();

assertType('Illuminate\Pipeline\Pipeline<array<int, (callable(): mixed)|object>, string, mixed, mixed>', $pipeline);

$pipeline->via('handle');

assertType("Illuminate\Pipeline\Pipeline<array<int, (callable(): mixed)|object>, 'handle', mixed, mixed>", $pipeline);

$pipeline->send(1);

assertType("Illuminate\Pipeline\Pipeline<array<int, (callable(): mixed)|object>, 'handle', 1, mixed>", $pipeline);

$pipeline->through(new SamplePipe());

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1, mixed>", $pipeline);

$pipeline->through(new SamplePipe());

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe, jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1, mixed>", $pipeline);

$pipeline->pipe(new SamplePipe());

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1, mixed>", $pipeline);

$pipeline->then(function () {
    return 42;
});

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1, 42>", $pipeline);

if (random_int(0, 10) > 5) {
    $pipeline->pipe([new SamplePipe(), new SamplePipe()]);
} else {
    $pipeline->pipe(new SamplePipe());
}

assertType("Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1, 42>|Illuminate\Pipeline\Pipeline<array{jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe, jbboehr\PHPStan\Laravel\Pipeline\Tests\Fixture\SamplePipe}, 'handle', 1, 42>", $pipeline);
