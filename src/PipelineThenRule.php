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

use Illuminate\Contracts\Pipeline\Pipeline;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final class PipelineThenRule implements Rule
{
    private ObjectType $pipelineObjectType;

    public function __construct()
    {
        $this->pipelineObjectType = new ObjectType(Pipeline::class);
    }

    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            if (!($node->name instanceof Node\Identifier) || $node->name->name !== 'then') {
                return [];
            }

            $varType = $scope->getType($node->var);

            if (
                /** @phpstan-ignore-next-line phpstanApi.instanceofType */
                !$varType instanceof GenericObjectType ||
                !$this->pipelineObjectType->accepts($varType, true)->yes()
            ) {
                return [];
            }

            $genericTypes = $varType->getTypes();

            if (count($genericTypes) < 3) {
                return [];
            }

            $errors = [];
            $pipelineType = $genericTypes[0];
            $methodType = $genericTypes[1];
            $passableType = $genericTypes[2];

            if (
                !$pipelineType->isConstantArray()->yes() ||
                count($pipelineType->getConstantArrays()) !== 1 ||
                !$methodType->isConstantScalarValue()->yes() ||
                count($methodType->getConstantStrings()) !== 1 ||
                !$pipelineType->getConstantArrays()[0]->isList()->yes()
            ) {
                $errors[] = RuleErrorBuilder::message('Unconfigured pipeline: ' . $varType->describe(VerbosityLevel::precise()))
                    ->identifier('laravelPipelines.unconfigured')
                    ->build();

                return $errors;
            }

            $pipelineType = $pipelineType->getConstantArrays()[0];
            $methodType = $methodType->getConstantStrings()[0];
            $methodName = $methodType->getValue();

            foreach ($pipelineType->getValueTypes() as $valueType) {
                if ($valueType->isCallable()->yes()) {
                    $selector = ParametersAcceptorSelector::selectFromTypes(
                        [$passableType],
                        $valueType->getCallableParametersAcceptors($scope),
                        true,
                    );

                    $callableName = $valueType->describe(VerbosityLevel::precise());
                } elseif ($valueType->isObject()->yes()) {
                    if (!$valueType->hasMethod($methodName)->yes()) {
                        $errors[] = RuleErrorBuilder::message(sprintf(
                            'Pipeline item %s does not have method %s',
                            $valueType->describe(VerbosityLevel::typeOnly()),
                            $methodName,
                        ))
                            ->identifier('laravelPipelines.missingMethod')
                            ->build();
                        continue;
                    }

                    $methodReflection = $valueType->getMethod($methodName, $scope);
                    $variants = $methodReflection->getVariants();

                    $selector = ParametersAcceptorSelector::selectFromTypes(
                        [
                            $passableType,
                        ],
                        $variants,
                        true,
                    );
                    $callableName = $methodReflection->getDeclaringClass()->getName() . '::' . $methodReflection->getName() . '()';
                } else {
                    continue;
                }

                if (
                    count($selector->getParameters()) !== 1 ||
                    !$selector->getParameters()[0]->getType()->accepts($passableType, true)->yes()
                ) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        "%s does not accept as passable: %s",
                        $callableName,
                        $passableType->describe(VerbosityLevel::typeOnly()),
                    ))
                        ->identifier('laravelPipelines.invalidParameter')
                        ->build();
                }
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
