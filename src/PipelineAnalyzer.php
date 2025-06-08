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

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

final class PipelineAnalyzer
{
    /**
     * @return list<IdentifierRuleError>
     */
    public function analyzePipeline(
        ConstantArrayType $pipelineType,
        string $methodName,
        Type $passableType,
        Scope $scope,
    ): array {
        $errors = [];

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
    }
}
