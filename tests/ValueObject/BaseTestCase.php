<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\Filter\FilterFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected Parser $parser;
    protected PrettyPrinterAbstract $printer;
    protected ValueObjectFactory $voFactory;

    /**
     * @var callable
     */
    protected $classNameFilter;

    /**
     * @var callable
     */
    protected $propertyNameFilter;

    /**
     * @var callable
     */
    protected $methodNameFilter;

    /**
     * @var callable
     */
    protected $filterConstName;

    /**
     * @var callable
     */
    protected $filterConstValue;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);

        $this->classNameFilter = FilterFactory::classNameFilter();
        $this->filterConstName = FilterFactory::constantNameFilter();
        $this->filterConstValue = FilterFactory::constantValueFilter();
        $this->propertyNameFilter = FilterFactory::propertyNameFilter();
        $this->methodNameFilter = FilterFactory::methodNameFilter();

        $this->voFactory = new ValueObjectFactory(
            $this->parser,
            true,
            $this->classNameFilter,
            $this->propertyNameFilter,
            $this->methodNameFilter,
            $this->filterConstName,
            $this->filterConstValue,
        );
    }
}
