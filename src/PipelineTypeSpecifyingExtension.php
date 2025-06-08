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

use Illuminate\Pipeline\Pipeline;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class PipelineTypeSpecifyingExtension implements TypeSpecifierAwareExtension, MethodTypeSpecifyingExtension
{
    private TypeSpecifier $typeSpecifier;

    private Type $emptyListType;

    public function __construct()
    {
        $this->emptyListType = TypeCombinator::intersect(
            new ArrayType(IntegerRangeType::fromInterval(0, null), new ObjectWithoutClassType()),
            /** @phpstan-ignore-next-line phpstanApi.constructor */
            new AccessoryArrayListType(),
        );
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function getClass(): string
    {
        return Pipeline::class;
    }

    public function isMethodSupported(
        MethodReflection $methodReflection,
        MethodCall $node,
        TypeSpecifierContext $context,
    ): bool {
        return (
            count($node->getArgs()) === 1 &&
            match ($methodReflection->getName()) {
                "via",
                "through",
                "send",
                "pipe" => true,
                default => false,
            }
        );
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context,
    ): SpecifiedTypes {
        $args = $node->getArgs();

        if (count($args) !== 1) {
            return new SpecifiedTypes();
        }

        $varType = $scope->getType($node->var);

        /** @phpstan-ignore-next-line phpstanApi.instanceofType */
        if (!($varType instanceof GenericObjectType)) {
            return new SpecifiedTypes();
        }

        if (count($varType->getTypes()) < 1) {
            return new SpecifiedTypes();
        }

        $argType = $scope->getType($node->getArgs()[0]->value);

        return match ($methodReflection->getName()) {
            "through" => $this->specifyTypesForThrough($node, $scope, $varType, $argType, false),
            "pipe" => $this->specifyTypesForThrough($node, $scope, $varType, $argType, true),
            "via" => $this->specifyTypesForVia($node, $scope, $varType, $argType),
            "send" => $this->specifyTypesForSend($node, $scope, $varType, $argType),
            default => new SpecifiedTypes(),
        };
    }

    public function specifyTypesForThrough(
        MethodCall $node,
        Scope $scope,
        GenericObjectType $varType,
        Type $argType,
        bool $replace,
    ): SpecifiedTypes {
        $genericType = $varType->getTypes()[0];

        if ($replace || $genericType->accepts($this->emptyListType, true)->yes()) {
            $newType = new GenericObjectType(
                $varType->getClassName(),
                [$argType, ...array_slice($varType->getTypes(), 1)],
                null,
                $varType->getClassReflection(),
                $varType->getVariances(),
            );

            return $this->typeSpecifier->create($node->var, $newType, TypeSpecifierContext::createTruthy(), true, $scope);
        }

        if (!$genericType->isConstantArray()->yes() || count($genericType->getConstantArrays()) !== 1) {
            return new SpecifiedTypes();
        }

        if (!$argType->isConstantArray()->yes() || count($argType->getConstantArrays()) !== 1) {
            return new SpecifiedTypes();
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();
        $index = 0;

        foreach ($genericType->getConstantArrays()[0]->getValueTypes() as $type) {
            $builder->setOffsetValueType(new ConstantIntegerType($index++), $type);
        }

        foreach ($argType->getConstantArrays()[0]->getValueTypes() as $type) {
            $builder->setOffsetValueType(new ConstantIntegerType($index++), $type);
        }

        $newType = new GenericObjectType(
            $varType->getClassName(),
            [$builder->getArray(), ...array_slice($varType->getTypes(), 1)],
            null,
            $varType->getClassReflection(),
            $varType->getVariances(),
        );

        return $this->typeSpecifier->create($node->var, $newType, TypeSpecifierContext::createTruthy(), true, $scope);
    }

    public function specifyTypesForVia(
        MethodCall $node,
        Scope $scope,
        GenericObjectType $varType,
        Type $argType,
    ): SpecifiedTypes {
        $newType = new GenericObjectType(
            $varType->getClassName(),
            [$varType->getTypes()[0], $argType, ...array_slice($varType->getTypes(), 2)],
            null,
            $varType->getClassReflection(),
            $varType->getVariances(),
        );

        return $this->typeSpecifier->create($node->var, $newType, TypeSpecifierContext::createTruthy(), true, $scope);
    }

    public function specifyTypesForSend(
        MethodCall $node,
        Scope $scope,
        GenericObjectType $varType,
        Type $argType,
    ): SpecifiedTypes {
        $newType = new GenericObjectType(
            $varType->getClassName(),
            [
                ...array_slice($varType->getTypes(), 0, 2),
                $argType,
                ...array_slice($varType->getTypes(), 3),
            ],
            null,
            $varType->getClassReflection(),
            $varType->getVariances(),
        );

        return $this->typeSpecifier->create($node->var, $newType, TypeSpecifierContext::createTruthy(), true, $scope);
    }
}
