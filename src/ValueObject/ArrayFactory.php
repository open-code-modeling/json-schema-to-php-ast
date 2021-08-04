<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassMethodBuilder;
use OpenCodeModeling\CodeAst\Code\BodyGenerator;
use OpenCodeModeling\CodeAst\Code\MethodGenerator;
use OpenCodeModeling\CodeAst\Code\ParameterGenerator;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassMethod;
use OpenCodeModeling\JsonSchemaToPhp\Type\ArrayType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ReferenceType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ScalarType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeSet;
use OpenCodeModeling\JsonSchemaToPhpAst\Common\IteratorFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type string.
 *
 * The following code will be generated:
 *
 *  private int $position = 0;
 *
 *  private array $items;
 *
 *  public function rewind() : void
 *  {
 *      $this->position = 0;
 *  }
 *
 *  public function current() : ReasonType
 *  {
 *      return $this->items[$this->position];
 *  }
 *
 *  public function key() : int
 *  {
 *      return $this->position;
 *  }
 *
 *  public function next() : void
 *  {
 *      ++$this->position;
 *  }
 *
 *  public function valid() : bool
 *  {
 *      return isset($this->items[$this->position]);
 *  }
 *
 *  public function count() : int
 *  {
 *      return count($this->items);
 *  }
 *
 *  public static function fromArray(array $items) : self
 *  {
 *      return new self(...array_map(static function (string $item) {
 *          return ReasonType::fromString($item);
 *      }, $items));
 *  }
 *
 *  public static function fromItems(ReasonType ...$items) : self
 *  {
 *      return new self(...$items);
 *  }
 *
 *  public static function emptyList() : self
 *  {
 *      return new self();
 *  }
 *
 *  private function __construct(ReasonType ...$items)
 *  {
 *      $this->items = $items;
 *  }
 *
 *  public function add(ReasonType $reasonType) : self
 *  {
 *      $copy = clone $this;
 *      $copy->items[] = $reasonType;
 *      return $copy;
 *  }
 *
 *  public function remove(ReasonType $reasonType) : self
 *  {
 *      $copy = clone $this;
 *      $copy->items = array_values(array_filter($copy->items, static function ($v) {
 *          return !$v->equals($reasonType);
 *      }));
 *      return $copy;
 *  }
 *
 *  public function first() : ?ReasonType
 *  {
 *      return $this->items[0] ?? null;
 *  }
 *
 *  public function last() : ?ReasonType
 *  {
 *      if (count($this->items) === 0) {
 *          return null;
 *      }
 *      return $this->items[count($this->items) - 1];
 *  }
 *
 *  public function contains(ReasonType $reasonType) : bool
 *  {
 *      foreach ($this->items as $existingItem) {
 *          if ($existingItem->equals($reasonType)) {
 *           *  return true;
 *          }
 *      }
 *      return false;
 *  }
 *
 *  public function filter(callable $filter) : self
 *  {
 *      return new self(...array_values(array_filter($this->items, static function ($v) {
 *          return $filter($v);
 *      })));
 *  }
 *
 *  public function items() : array
 *  {
 *      return $this->items;
 *  }
 *
 *  public function toArray() : array
 *  {
 *      return \array_map(static function (ReasonType $reasonType) {
 *          return $reasonType->toString();
 *      }, $this->items);
 *  }
 *
 *  public function equals($other) : bool
 *  {
 *      if (!$other instanceof self) {
 *          return false;
 *      }
 *      return $this->toArray() === $other->toArray();
 *  }
 */
final class ArrayFactory
{
    private Parser $parser;
    private IteratorFactory $iteratorFactory;
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
     * ArrayFactory constructor.
     * @param Parser $parser
     * @param bool $typed
     * @param callable $classNameFilter
     * @param callable $propertyNameFilter
     */
    public function __construct(Parser $parser, bool $typed, callable $classNameFilter, callable $propertyNameFilter)
    {
        $this->parser = $parser;
        $this->typed = $typed;
        $this->classNameFilter = $classNameFilter;
        $this->propertyNameFilter = $propertyNameFilter;
        $this->iteratorFactory = new IteratorFactory($parser, $typed, $propertyNameFilter);
    }

    /**
     * @param  ArrayType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(ArrayType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'items';

        return $this->nodeVisitorsFromNative($name, ...$typeDefinition->items());
    }

    public function classBuilder(ArrayType $typeDefinition): ClassBuilder
    {
        $name = $typeDefinition->name() ?: 'items';

        return $this->classBuilderFromNative($name, ...$typeDefinition->items());
    }

    private function determineTypeName(string $name, TypeSet ...$typeSets): ?string
    {
        if (\count($typeSets) !== 1) {
            throw new \RuntimeException('Can only handle one JSON type');
        }
        $typeSet = \array_shift($typeSets);

        if ($typeSet === null || $typeSet->count() !== 1) {
            throw new \RuntimeException('Can only handle one JSON type');
        }

        $type = $typeSet->first();

        switch (true) {
            case $type instanceof ReferenceType:
                $resolvedTypeSet = $type->resolvedType();

                if ($resolvedTypeSet === null) {
                    return $type->extractNameFromReference();
                }
                if (\count($resolvedTypeSet) !== 1) {
                    throw new \RuntimeException('Can only handle one JSON type');
                }
                $type = $resolvedTypeSet->first();
                // no break
            case $type instanceof ScalarType:
                break;
            default:
                throw new \RuntimeException(
                    \sprintf('Only scalar and reference types are supported. Got "%s" for "%s"', \get_class($type), $name)
                );
        }

        return $type->name();
    }

    /**
     * @param  string  $name
     * @param  TypeSet ...$typeSets
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name, TypeSet ...$typeSets): array
    {
        $typeName = $this->determineTypeName($name, ...$typeSets);

        $nodeVisitors = $this->iteratorFactory->nodeVisitorsFromNative(
            ($this->propertyNameFilter)($name),
            ($this->classNameFilter)($typeName)
        );

        $nodeVisitors[] = new ClassMethod($this->methodFromArray($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodFromItems($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodEmptyList());
        $nodeVisitors[] = new ClassMethod($this->methodMagicConstruct($name, $name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodAdd($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodRemove($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodFirst($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodLast($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodContains($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodFilter($name));
        $nodeVisitors[] = new ClassMethod($this->methodItems($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodToArray($name, $typeName));
        $nodeVisitors[] = new ClassMethod($this->methodEquals());

        return $nodeVisitors;
    }

    public function classBuilderFromNative(string $name, TypeSet ...$typeSets): ClassBuilder
    {
        $typeName = $this->determineTypeName($name, ...$typeSets);

        $classBuilder = $this->iteratorFactory->classBuilderFromNative(
            ($this->propertyNameFilter)($name),
            ($this->classNameFilter)($typeName)
        );
        $classBuilder->addMethod(
            ClassMethodBuilder::fromNode($this->methodFromArray($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodFromArray($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodFromItems($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodEmptyList()->generate()),
            ClassMethodBuilder::fromNode($this->methodMagicConstruct($name, $name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodAdd($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodRemove($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodFirst($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodLast($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodContains($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodFilter($name)->generate()),
            ClassMethodBuilder::fromNode($this->methodItems($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodToArray($name, $typeName)->generate()),
            ClassMethodBuilder::fromNode($this->methodEquals()->generate()),
        );

        return $classBuilder;
    }

    public function methodEmptyList(): MethodGenerator
    {
        $method = new MethodGenerator(
            'emptyList',
            [],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self();')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodMagicConstruct(
        string $propertyName,
        string $argumentName,
        string $argumentType
    ): MethodGenerator {
        $method = new MethodGenerator(
            '__construct',
            [
                (new ParameterGenerator(($this->propertyNameFilter)($argumentName), ($this->classNameFilter)($argumentType)))->setVariadic(true),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', ($this->propertyNameFilter)($propertyName), ($this->propertyNameFilter)($argumentName)))
        );
        $method->setTyped($this->typed);

        return $method;
    }

    public function methodAdd(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $body = <<<'PHP'
        $copy = clone $this;
        $copy->%s[] = $%s;
    
        return $copy;
PHP;

        $method = new MethodGenerator(
            'add',
            [
                (new ParameterGenerator(($this->propertyNameFilter)($argumentType), ($this->classNameFilter)($argumentType))),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator(
                $this->parser,
                \sprintf(
                    $body,
                    ($this->propertyNameFilter)($propertyName),
                    ($this->propertyNameFilter)($argumentType)
                )
            )
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodRemove(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $body = <<<'PHP'
        $copy = clone $this;
        $copy->%s = array_values(
            array_filter(
                $copy->%s,
                static function($v) use ($%s) { return !$v->equals($%s); }
            )
        );
        return $copy;
PHP;

        $method = new MethodGenerator(
            'remove',
            [
                (new ParameterGenerator(($this->propertyNameFilter)($argumentType), ($this->classNameFilter)($argumentType))),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator(
                $this->parser,
                \sprintf(
                    $body,
                    ($this->propertyNameFilter)($propertyName),
                    ($this->propertyNameFilter)($propertyName),
                    ($this->propertyNameFilter)($argumentType),
                    ($this->propertyNameFilter)($argumentType)
                )
            )
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodFirst(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $method = new MethodGenerator(
            'first',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf('return $this->%s[0] ?? null;', ($this->propertyNameFilter)($propertyName)))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('?' . ($this->classNameFilter)($argumentType));

        return $method;
    }

    public function methodLast(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $body = <<<'PHP'
        if (count($this->%s) === 0) {
            return null;
        }

        return $this->%s[count($this->%s) - 1];
PHP;

        $propertyName = ($this->propertyNameFilter)($propertyName);
        $argumentType = ($this->classNameFilter)($argumentType);

        $method = new MethodGenerator(
            'last',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf($body, $propertyName, $propertyName, $propertyName))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('?' . $argumentType);

        return $method;
    }

    public function methodContains(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $body = <<<'PHP'
        foreach ($this->%s as $existingItem) {
            if ($existingItem->equals($%s)) {
                return true;
            }
        }
        return false;
PHP;

        $propertyName = ($this->propertyNameFilter)($propertyName);
        $argumentType = ($this->classNameFilter)($argumentType);

        $method = new MethodGenerator(
            'contains',
            [
                (new ParameterGenerator(($this->propertyNameFilter)($argumentType), ($this->classNameFilter)($argumentType))),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf($body, $propertyName, ($this->propertyNameFilter)($argumentType)))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }

    public function methodFilter(
        string $propertyName
    ): MethodGenerator {
        $body = <<<'PHP'
        return new self(
            ...array_values(
                array_filter(
                    $this->%s,
                    static function($%s) use ($filter) { return $filter($%s); }
                )
            )
        );
PHP;

        $method = new MethodGenerator(
            'filter',
            [
                new ParameterGenerator('filter', 'callable'),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf($body, ($this->propertyNameFilter)($propertyName), 'v', 'v'))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodItems(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $propertyName = ($this->propertyNameFilter)($propertyName);
        $argumentType = ($this->classNameFilter)($argumentType);

        $method = new MethodGenerator(
            'items',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf('return $this->%s;', $propertyName))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('array');
        $method->setReturnTypeDocBlockHint($argumentType . '[]');

        return $method;
    }

    public function methodToArray(
        string $propertyName,
        string $argumentType
    ): MethodGenerator {
        $body = <<<'PHP'
        return \array_map(static function (%s $%s) {
            return $%s->toString();
        }, $this->%s);
PHP;

        $propertyName = ($this->propertyNameFilter)($propertyName);
        $argumentType = ($this->classNameFilter)($argumentType);
        $argumentTypeVarName = ($this->propertyNameFilter)($argumentType);

        $method = new MethodGenerator(
            'toArray',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf($body, $argumentType, $argumentTypeVarName, $argumentTypeVarName, $propertyName))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('array');

        return $method;
    }

    public function methodFromArray(
        string $argumentName,
        string $typeName
    ): MethodGenerator {
        $body = <<<'PHP'
        return new self(...array_map(static function (string $item) {
            return %s::fromString($item);
        }, $%s));
PHP;
        $argumentName = ($this->propertyNameFilter)($argumentName);
        $typeName = ($this->classNameFilter)($typeName);

        $method = new MethodGenerator(
            'fromArray',
            [
                new ParameterGenerator($argumentName, 'array'),
            ],
            MethodGenerator::FLAG_PUBLIC | MethodGenerator::FLAG_STATIC,
            new BodyGenerator($this->parser, \sprintf($body, $typeName, $argumentName))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodFromItems(
        string $argumentName,
        string $argumentType
    ): MethodGenerator {
        $method = new MethodGenerator(
            'fromItems',
            [
                (new ParameterGenerator(($this->propertyNameFilter)($argumentName), ($this->classNameFilter)($argumentType)))->setVariadic(true),
            ],
            MethodGenerator::FLAG_PUBLIC | MethodGenerator::FLAG_STATIC,
            new BodyGenerator($this->parser, \sprintf('return new self(...$%s);', ($this->propertyNameFilter)($argumentName)))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodEquals(string $argumentName = 'other'): MethodGenerator
    {
        $body = <<<'PHP'
    if(!$%s instanceof self) {
       return false;
    }

    return $this->toArray() === $%s->toArray();
PHP;

        $method = new MethodGenerator(
            'equals',
            [
                (new ParameterGenerator($argumentName))->setTypeDocBlockHint('mixed'),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, \sprintf($body, $argumentName, $argumentName))
        );
        $method->setDocBlockComment('');
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }
}
