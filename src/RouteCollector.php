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

namespace jbboehr\PHPStan\Laravel\Pipeline;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<Node\Expr\StaticCall, array{path: string}>
 */
final class RouteCollector implements Collector
{
    public function getNodeType(): string
    {
        return Node\Expr\StaticCall::class;
    }

    /**
     * @param Node\Expr\StaticCall $node
     */
    public function processNode(Node $node, Scope $scope)
    {
        if (!($node->class instanceof Node\Name\FullyQualified) || $node->class->toString() !== \Illuminate\Support\Facades\Route::class) {
            return null;
        }

        if (count($node->args) < 1) {
            return null;
        }

        $routeArg = $node->getArgs()[0];
        $routeType = $scope->getType($routeArg->value);

        if (!$routeType->isConstantScalarValue()->yes() || count($routeType->getConstantStrings()) <= 0) {
            return null;
        }

        return [
            'path' => $routeType->getConstantStrings()[0]->getValue(),
        ];
    }
}
