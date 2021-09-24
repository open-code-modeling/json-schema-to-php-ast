<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassMethodBuilder;
use OpenCodeModeling\CodeAst\Builder\ParameterBuilder;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;

final class ExceptionFactory
{
    private ClassInfoList $classInfoList;

    private bool $typed;

    /**
     * @var callable
     */
    private $classNameFilter;

    /**
     * @var callable
     */
    private $propertyNameFilter;

    /**
     * @var callable
     */
    private $methodNameFilter;

    /**
     * @param bool $typed
     * @param callable $classNameFilter Converts the name of the type to a proper class name
     * @param callable $propertyNameFilter Converts the name to a proper class property name
     * @param callable $methodNameFilter Converts the name to a proper class method name
     */
    public function __construct(
        ClassInfoList $classInfoList,
        bool $typed,
        callable $classNameFilter,
        callable $propertyNameFilter,
        callable $methodNameFilter
    ) {
        $this->classInfoList = $classInfoList;
        $this->typed = $typed;
        $this->classNameFilter = $classNameFilter;
        $this->propertyNameFilter = $propertyNameFilter;
        $this->methodNameFilter = $methodNameFilter;
    }

    /**
     * @param TypeDefinition $typeDefinition
     * @param string $valueObjectFqcn FQCN of the value object (needed for determine exception class namespace and namespace imports)
     * @return ClassBuilder
     */
    public function classBuilder(
        TypeDefinition $typeDefinition,
        string $valueObjectFqcn
    ): ClassBuilder {
        $classInfo = $this->classInfoList->classInfoForNamespace($valueObjectFqcn);
        $namespace = $classInfo->getClassNamespace($valueObjectFqcn) . '\\Exception';

        switch (true) {
            case $typeDefinition instanceof StringType:
                if ($typeDefinition->enum() !== null) {
                    return $this->exceptionClassForEnum($typeDefinition, $namespace)
                        ->addNamespaceImport($valueObjectFqcn);
                }
                // no break
            default:
                throw new \RuntimeException(\sprintf('Type "%s" not supported', \get_class($typeDefinition)));
        }
    }

    private function exceptionClassForEnum(
        TypeDefinition $typeDefinition,
        string $namespace
    ): ClassBuilder {
        $name = $typeDefinition->name() ?: 'text';

        $argumentName = ($this->propertyNameFilter)($name);
        $className = ($this->classNameFilter)($name);

        $body = <<<EOF
        return new self(sprintf('Invalid value for "$className" given. Got "%s", but allowed values are ' . implode(', ', $className::CHOICES), \$$argumentName, StatusCodeInterface::STATUS_BAD_REQUEST));
        EOF;

        $invalidMethodFor = ClassMethodBuilder::fromScratch(($this->methodNameFilter)('for_' . $name), $this->typed)
            ->setStatic(true)
            ->setParameters(ParameterBuilder::fromScratch($argumentName, 'string'))
            ->setReturnType('self')
            ->setBody($body);

        $classBuilder = ClassBuilder::fromScratch('Invalid' . ($this->classNameFilter)($name), $namespace)
            ->setFinal(true)
            ->setExtends('InvalidArgumentException');
        $classBuilder->addNamespaceImport(
            'Fig\Http\Message\StatusCodeInterface',
            'InvalidArgumentException'
        );

        $classBuilder->addMethod($invalidMethodFor);

        return $classBuilder;
    }
}
