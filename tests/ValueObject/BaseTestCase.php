<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use Laminas\Filter;
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
    protected $filterConstName;

    /**
     * @var callable
     */
    protected $filterConstValue;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);

        $filterConstName = new Filter\FilterChain();
        $filterConstName->attach(new Filter\Word\SeparatorToSeparator(' ', ''));
        $filterConstName->attach(new Filter\Word\CamelCaseToUnderscore());
        $filterConstName->attach(new Filter\Word\DashToUnderscore());
        $filterConstName->attach(new Filter\StringToUpper());

        $this->filterConstName = $filterConstName;

        $filterConstValue = new Filter\FilterChain();
        $filterConstValue->attach(new Filter\Word\SeparatorToSeparator(' ', '-'));
        $filterConstValue->attach(new Filter\Word\UnderscoreToCamelCase());
        $filterConstValue->attach(new Filter\Word\DashToCamelCase());
        $filterConstValue->attach(function(string $value) { return \lcfirst($value); });

        $this->filterConstValue = $filterConstValue;

        $this->voFactory = new ValueObjectFactory(
            $this->parser,
            true,
            $this->filterConstName,
            $this->filterConstValue,
        );
    }
}
