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

use jbboehr\PHPStan\Laravel\Pipeline\RouteCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\TestCase;

/**
 * @extends RuleTestCase<Rule<CollectedDataNode>>
 */
final class RouteCollectorTest extends RuleTestCase
{
    private RouteCollector $routeCollector;

    public function setUp(): void
    {
        parent::setUp();

        $this->routeCollector = new RouteCollector();
    }

    public function testRouteCollector(): void
    {
        $this->analyse([
            __DIR__ . '/../workbench/routes/api.php',
            __DIR__ . '/../workbench/routes/console.php',
            __DIR__ . '/../workbench/routes/web.php',
        ], []);
    }

    protected function getRule(): Rule
    {
        return new
        /**
         * @implements Rule<CollectedDataNode>
         */
        class implements Rule {
            public function getNodeType(): string
            {
                return CollectedDataNode::class;
            }

            /**
             * @param CollectedDataNode $node
             */
            public function processNode(Node $node, Scope $scope): array
            {
                $data = $node->get(RouteCollector::class);

                $aggregateData = [];

                foreach ($data as $perFileData) {
                    foreach ($perFileData as $routeData) {
                        $aggregateData[] = $routeData;
                    }
                }

                TestCase::assertSame([
                    [
                        'path' => '/',
                    ],
                ], $aggregateData);

                return [];
            }
        };
    }

    public function getCollectors(): array
    {
        return array_merge(parent::getCollectors(), [
            $this->routeCollector,
        ]);
    }
}
