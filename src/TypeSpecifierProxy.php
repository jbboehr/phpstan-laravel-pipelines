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

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Type\Type;

final class TypeSpecifierProxy
{
    private int $createParameterCount;

    public function __construct(
        public readonly TypeSpecifier $typeSpecifier,
    ) {
        $this->createParameterCount = (new \ReflectionMethod($this->typeSpecifier, 'create'))->getNumberOfParameters();
    }

    public function create(
        Expr $expr,
        Type $type,
        TypeSpecifierContext $context,
        bool $overwrite = false,
        ?Scope $scope = null,
        ?Expr $rootExpr = null,
    ): SpecifiedTypes {
        if ($this->createParameterCount === 4) {
            $specifiedTypes = $this->typeSpecifier->create($expr, $type, $context, $scope);
            if ($overwrite) {
                $specifiedTypes = $specifiedTypes->setAlwaysOverwriteTypes();
            }
            if ($rootExpr !== null) {
                $specifiedTypes = $specifiedTypes->setRootExpr($rootExpr);
            }
            return $specifiedTypes;
        }

        if ($this->createParameterCount === 6) {
            return $this->typeSpecifier->create($expr, $type, $context, $overwrite, $scope, $rootExpr);
        }

        throw new ShouldNotHappenException();
    }
}
