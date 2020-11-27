<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst\Common;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Code\BodyGenerator;
use OpenCodeModeling\CodeAst\Code\MethodGenerator;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassImplements;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassMethod;
use OpenCodeModeling\CodeAst\NodeVisitor\Property;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for an iterable value object.
 *
 * The following code will be generated:
 */
final class IteratorFactory
{
    private Parser $parser;
    private PropertyFactory $propertyFactory;
    private bool $typed;

    public function __construct(Parser $parser, bool $typed)
    {
        $this->parser = $parser;
        $this->typed = $typed;
        $this->propertyFactory = new PropertyFactory($typed);
    }

    /**
     * @param  string $name
     * @param  string $itemType
     * @param  string $positionPropertyName
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(
        string $name,
        string $itemType,
        string $positionPropertyName = 'position'
    ): array {
        $nodeVisitors = [];

        $nodeVisitors[] = new Property(
            $this->propertyFactory->propertyGenerator($positionPropertyName, 'int')->setDefaultValue(0)
        );
        $nodeVisitors[] = new Property(
            $this->propertyFactory->propertyGenerator($name, 'array')->setTypeDocBlockHint($itemType . '[]')
        );

        $nodeVisitors[] = new ClassMethod($this->methodRewind($positionPropertyName));
        $nodeVisitors[] = new ClassMethod($this->methodCurrent($name, $itemType, $positionPropertyName));
        $nodeVisitors[] = new ClassMethod($this->methodKey($positionPropertyName));
        $nodeVisitors[] = new ClassMethod($this->methodNext($positionPropertyName));
        $nodeVisitors[] = new ClassMethod($this->methodValid($name, $positionPropertyName));
        $nodeVisitors[] = new ClassMethod($this->methodCount($name));

        $nodeVisitors[] = new ClassImplements('\\Iterator', '\\Countable');

        return $nodeVisitors;
    }

    public function classBuilderFromNative(
        string $name,
        string $itemType,
        string $positionPropertyName = 'position'
    ): ClassBuilder {
        return ClassBuilder::fromNodes(
            $this->propertyFactory->propertyGenerator($positionPropertyName, 'int')->setDefaultValue(0)->generate(),
            $this->propertyFactory->propertyGenerator($name, 'array')->setTypeDocBlockHint($itemType . '[]')->generate(),
            $this->methodRewind($positionPropertyName)->generate(),
            $this->methodCurrent($name, $itemType, $positionPropertyName)->generate(),
            $this->methodKey($positionPropertyName)->generate(),
            $this->methodNext($positionPropertyName)->generate(),
            $this->methodValid($name, $positionPropertyName)->generate(),
            $this->methodCount($name)->generate(),
        )->setImplements('\\Iterator', '\\Countable')
            ->setTyped($this->typed);
    }

    public function methodRewind(string $positionPropertyName): MethodGenerator
    {
        $method = new MethodGenerator(
            'rewind',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, '$this->' . $positionPropertyName . ' = 0;')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('void');

        return $method;
    }

    public function methodCurrent(string $name, string $itemType, string $positionPropertyName): MethodGenerator
    {
        $method = new MethodGenerator(
            'current',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf('return $this->%s[$this->%s];', $name, $positionPropertyName))
        );
        $method->setTyped($this->typed);
        $method->setReturnType($itemType);

        return $method;
    }

    public function methodKey(string $positionPropertyName): MethodGenerator
    {
        $method = new MethodGenerator(
            'key',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $positionPropertyName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('int');

        return $method;
    }

    public function methodNext(string $positionPropertyName): MethodGenerator
    {
        $method = new MethodGenerator(
            'next',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, '++$this->' . $positionPropertyName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('void');

        return $method;
    }

    public function methodValid(string $name, string $positionPropertyName): MethodGenerator
    {
        $method = new MethodGenerator(
            'valid',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator(
                $this->parser,
                \sprintf('return isset($this->%s[$this->%s]);', $name, $positionPropertyName)
            )
        );
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }

    public function methodCount(string $name): MethodGenerator
    {
        $method = new MethodGenerator(
            'count',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return count($this->' . $name . ');')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('int');

        return $method;
    }
}
