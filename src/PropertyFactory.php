<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Code\PropertyGenerator;
use OpenCodeModeling\JsonSchemaToPhp\Type\ArrayType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ObjectType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ReferenceType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeSet;
use PhpParser\NodeVisitor;

final class PropertyFactory
{
    /**
     * @var bool
     **/
    private $typed;

    /**
     * @var callable
     */
    private $propertyNameFilter;

    public function __construct(bool $typed, callable $propertyNameFilter)
    {
        $this->typed = $typed;
        $this->propertyNameFilter = $propertyNameFilter;
    }

    /**
     * @param  TypeSet $typeSet
     * @return array<NodeVisitor>
     */
    public function nodeVisitorFromTypeSet(TypeSet $typeSet): array
    {
        if (\count($typeSet) !== 1) {
            throw new \RuntimeException('Can only handle one type');
        }

        return $this->nodeVisitorFromTypeDefinition($typeSet->first());
    }

    /**
     * @param  ObjectType $type
     * @return array<NodeVisitor>
     */
    public function nodeVisitorFromObjectType(ObjectType $type): array
    {
        $nodeVisitors = [];
        $properties = $type->properties();

        /**
         * @var TypeSet $typeSet
         */
        foreach ($properties as $typeName => $typeSet) {
            if (\count($typeSet) !== 1) {
                throw new \RuntimeException(\sprintf('Can only handle one type for property "%s"', $typeName));
            }

            $nodeVisitors = \array_merge($nodeVisitors, $this->nodeVisitorFromTypeDefinition($typeSet->first()));
        }

        return $nodeVisitors;
    }

    /**
     * @param  ArrayType $type
     * @return array<NodeVisitor>
     */
    public function nodeVisitorFromArrayType(ArrayType $type): array
    {
        return [];
    }

    /**
     * @param  TypeDefinition $type
     * @return array<NodeVisitor>
     */
    public function nodeVisitorFromTypeDefinition(TypeDefinition $type): array
    {
        return $this->nodeVisitorFromNative($type->name(), $type->type());
    }

    /**
     * @param string $name
     * @param string $type
     * @return array<NodeVisitor>
     */
    public function nodeVisitorFromNative(string $name, string $type): array
    {
        return [
            new \OpenCodeModeling\CodeAst\NodeVisitor\Property(
                $this->propertyGenerator($name, $type)
            ),
        ];
    }

    public function propertyGenerator(string $name, string $type): PropertyGenerator
    {
        return new PropertyGenerator(($this->propertyNameFilter)($name), $type, null, $this->typed);
    }

    /**
     * @param  ReferenceType $type
     * @return array<NodeVisitor>
     */
    public function nodeVisitorFromReferenceType(ReferenceType $type): array
    {
        $resolvedTypeSet = $type->resolvedType();

        if (null === $resolvedTypeSet) {
            throw new \RuntimeException(\sprintf('No resolved type available for reference "%s"', $type->ref()));
        }

        if (\count($resolvedTypeSet) !== 1) {
            throw new \RuntimeException(\sprintf('Can only handle handle one type for reference "%s"', $type->ref()));
        }
        $resolvedType = $resolvedTypeSet->first();

        switch (true) {
            case $resolvedType instanceof ObjectType:
                return $this->nodeVisitorFromObjectType($resolvedType);
            case $resolvedType instanceof ArrayType:
                return $this->nodeVisitorFromArrayType($resolvedType);
            default:
                return $this->nodeVisitorFromTypeDefinition($resolvedType);
        }
    }
}
