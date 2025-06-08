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

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\ObjectType;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements Rule<CollectedDataNode>
 */
final class RoutePipelineRule implements Rule
{
    public function __construct(
        private readonly PipelineAnalyzer $pipelineAnalyzer,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        try {
            // $data = $node->get(RouteCollector::class);

            $errors = [];

            /** @var Router $router */
            $router = resolve(Router::class);

            foreach ($router->getRoutes()->getRoutes() as $route) {
                /** @var Route $route */

                if (self::isOrchestraWorkbenchRoute($route)) {
                    continue;
                }

                $middleware = $router->gatherRouteMiddleware($route);

                $pipelineTypeBuilder = ConstantArrayTypeBuilder::createEmpty();
                $index = 0;

                foreach ($middleware as $item) {
                    $pipelineTypeBuilder->setOffsetValueType(new ConstantIntegerType($index++), new ObjectType($item));
                }

                $pipelineType = $pipelineTypeBuilder->getArray()->getConstantArrays()[0] ?? throw new \DomainException();
                $methodName = 'handle';
                $passableType = new ObjectType(Request::class);
                $returnType = new ObjectType(Response::class);

                $routeErrors = $this->pipelineAnalyzer->analyzePipeline(
                    $pipelineType,
                    $methodName,
                    $passableType,
                    $returnType,
                    $scope,
                );

                $errors = array_merge($errors, $routeErrors);
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }

    private static function isOrchestraWorkbenchRoute(Route $route): bool
    {
        $controller = $route->getAction('controller');

        if (is_array($controller)) {
            $controller = $controller[0];
        }

        return is_string($controller) && str_starts_with($controller, "Orchestra\\Workbench\\");
    }
}
