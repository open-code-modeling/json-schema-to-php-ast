<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Builder\FileCollection;
use OpenCodeModeling\CodeAst\FileCodeGenerator;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\CodeAst\Package\Psr4Info;
use OpenCodeModeling\Filter\FilterFactory;
use OpenCodeModeling\JsonSchemaToPhp\Shorthand\Shorthand;
use OpenCodeModeling\JsonSchemaToPhp\Type\Type;
use OpenCodeModeling\JsonSchemaToPhpAst\ExceptionFactory;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

final class ExceptionFactoryTest extends TestCase
{
    private Parser $parser;
    private PrettyPrinterAbstract $printer;
    private ExceptionFactory $exceptionFactory;
    private ClassInfoList $classInfoList;
    private FileCodeGenerator $fileCodeGenerator;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);

        $this->classInfoList = new ClassInfoList(
            new Psr4Info(
                'tmp/',
                'Acme',
                FilterFactory::directoryToNamespaceFilter(),
                FilterFactory::namespaceToDirectoryFilter(),
            )
        );

        $classNameFilter = FilterFactory::classNameFilter();
        $propertyNameFilter = FilterFactory::propertyNameFilter();
        $methodNameFilter = FilterFactory::methodNameFilter();

        $this->fileCodeGenerator = new FileCodeGenerator(
            $this->parser,
            $this->printer,
            $this->classInfoList
        );

        $this->exceptionFactory = new ExceptionFactory(
            $this->classInfoList,
            true,
            $classNameFilter,
            $propertyNameFilter,
            $methodNameFilter
        );
    }

    /**
     * @test
     */
    public function it_generates_exception_class_for_enums(): void
    {
        $typeSet = Type::fromDefinition(Shorthand::convertToJsonSchema('enum:not_interested,invalid|ns:Contact'), 'reason_type');

        $fileCollection = FileCollection::emptyList();

        $classBuilder = $this->exceptionFactory->classBuilder($typeSet->first(), 'Acme\Contact\ReasonType');

        $this->assertEnumException($classBuilder);
        $fileCollection->add($classBuilder);

        $files = $this->fileCodeGenerator->generateFiles($fileCollection);

        $this->assertEnumExceptionFile($files['tmp/Contact/Exception/InvalidReasonType.php']);
    }

    private function assertEnumException(ClassBuilder $classBuilder): void
    {
        $this->assertSame('InvalidReasonType', $classBuilder->getName());
    }

    private function assertEnumExceptionFile(string $code): void
    {
        $expected = <<<'PHP'
        <?php
        
        declare (strict_types=1);
        namespace Acme\Contact\Exception;
        
        use Fig\Http\Message\StatusCodeInterface;
        use InvalidArgumentException;
        use Acme\Contact\ReasonType;
        final class InvalidReasonType extends InvalidArgumentException
        {
            public static function forReasonType(string $reasonType) : self
            {
                return new self(sprintf('Invalid value for "ReasonType" given. Got "%s", but allowed values are ' . implode(', ', ReasonType::CHOICES), $reasonType, StatusCodeInterface::STATUS_BAD_REQUEST));
            }
        }
        PHP;
        $this->assertSame($expected, $code);
    }
}
