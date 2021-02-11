<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Code\BodyGenerator;
use OpenCodeModeling\CodeAst\Code\MethodGenerator;
use OpenCodeModeling\CodeAst\Code\ParameterGenerator;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassMethod;
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type string with format BCP 47.
 */
final class Bcp47Factory
{
    private Parser $parser;
    private PropertyFactory $propertyFactory;
    private bool $typed;

    /**
     * @var callable
     */
    private $propertyNameFilter;

    public function __construct(Parser $parser, bool $typed, callable $propertyNameFilter)
    {
        $this->parser = $parser;
        $this->typed = $typed;
        $this->propertyNameFilter = $propertyNameFilter;
        $this->propertyFactory = new PropertyFactory($typed, $propertyNameFilter);
    }

    /**
     * @param StringType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(StringType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'bcp47';

        return $this->nodeVisitorsFromNative($name);
    }

    public function classBuilder(StringType $typeDefinition): ClassBuilder
    {
        $name = $typeDefinition->name() ?: 'bcp47';

        return $this->classBuilderFromNative($name)->setTyped($this->typed);
    }

    /**
     * @param string $name
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'string');
        $nodeVisitors = \array_merge($nodeVisitors, $this->propertyFactory->nodeVisitorFromNative('language', '?string'));
        $nodeVisitors = \array_merge($nodeVisitors, $this->propertyFactory->nodeVisitorFromNative('region', '?string'));
        $nodeVisitors[] = new ClassMethod($this->methodFromString($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicConstruct($name));
        $nodeVisitors[] = new ClassMethod($this->methodToString($name));
        $nodeVisitors[] = new ClassMethod($this->methodEquals($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicToString($name));
        $nodeVisitors[] = new ClassMethod($this->methodLanguage());
        $nodeVisitors[] = new ClassMethod($this->methodRegion());

        return $nodeVisitors;
    }

    public function classBuilderFromNative(string $name): ClassBuilder
    {
        return ClassBuilder::fromNodes(
            $this->propertyFactory->propertyGenerator($name, 'string')->generate(),
            $this->propertyFactory->propertyGenerator('language', '?string')->generate(),
            $this->propertyFactory->propertyGenerator('region', '?string')->generate(),
            $this->methodFromString($name)->generate(),
            $this->methodMagicConstruct($name)->generate(),
            $this->methodToString($name)->generate(),
            $this->methodEquals($name)->generate(),
            $this->methodMagicToString($name)->generate(),
            $this->methodLanguage()->generate(),
            $this->methodRegion()->generate(),
        )->setTyped($this->typed);
    }

    public function methodFromString(string $argumentName): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $method = new MethodGenerator(
            'fromString',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self($' . $argumentName . ');')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodMagicConstruct(string $argumentName): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $body = \sprintf('$this->%s = $%s;', $argumentName, $argumentName);
        $body .= \sprintf('$parsedLocale = locale_parse($%s);', $argumentName);
        $body .= <<<'PHP'
        $this->language = $parsedLocale['language'] ?? null;
        $this->region = $parsedLocale['region'] ?? null;
        PHP;

        $method = new MethodGenerator(
            '__construct',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, $body)
        );
        $method->setTyped($this->typed);

        return $method;
    }

    public function methodToString(string $argumentName): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $method = new MethodGenerator(
            'toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }

    public function methodEquals(string $propertyName, string $argumentName = 'other'): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);
        $propertyName = ($this->propertyNameFilter)($propertyName);

        $body = <<<PHP
    if(!\$$argumentName instanceof self) {
       return false;
    }

    return \$this->$propertyName === \$$argumentName->$propertyName;
PHP;

        $method = new MethodGenerator(
            'equals',
            [
                new ParameterGenerator($argumentName),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, $body)
        );
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }

    public function methodMagicToString(string $argumentName): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }

    public function methodLanguage(): MethodGenerator
    {
        $method = new MethodGenerator(
            'language',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->language;')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('?string');

        return $method;
    }

    public function methodRegion(): MethodGenerator
    {
        $method = new MethodGenerator(
            'region',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->region;')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('?string');

        return $method;
    }
}
