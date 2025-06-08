<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace jbboehr\PHPStan\Laravel\Pipeline\Tests;

use jbboehr\PHPStan\Laravel\Pipeline\PipelineAnalyzer;
use jbboehr\PHPStan\Laravel\Pipeline\RouteCollector;
use jbboehr\PHPStan\Laravel\Pipeline\RoutePipelineRule;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<Rule<CollectedDataNode>>
 */
final class RoutePipelineRuleTest extends RuleTestCase
{
    private RouteCollector $routeCollector;

    public function setUp(): void
    {
        parent::setUp();

        $this->routeCollector = new RouteCollector();
    }

    public function testRoutePipelineRule(): void
    {
        $this->analyse([
            __DIR__ . '/../workbench/routes/api.php',
            __DIR__ . '/../workbench/routes/console.php',
            __DIR__ . '/../workbench/routes/web.php',
        ], []);
    }

    protected function getRule(): Rule
    {
        return new RoutePipelineRule(new PipelineAnalyzer());
    }

    public function getCollectors(): array
    {
        return array_merge(parent::getCollectors(), [
            $this->routeCollector,
        ]);
    }
}
