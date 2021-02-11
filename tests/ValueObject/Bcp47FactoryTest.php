<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\Bcp47Factory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class Bcp47FactoryTest extends BaseTestCase
{
    private Bcp47Factory $stringFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->stringFactory = new Bcp47Factory($this->parser, true, $this->propertyNameFilter);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->stringFactory->nodeVisitorsFromNative('bcp47'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $this->assertCode(
            $this->voFactory->nodeVisitors(
                StringType::fromDefinition(['type' => 'string', 'name' => 'bcp47', 'format' => 'BCP 47'])
            )
        );
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            StringType::fromDefinition(['type' => 'string', 'name' => 'bcp47', 'format' => 'BCP 47'])
        );
        $classBuilder->setName('LocaleVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class LocaleVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class LocaleVO
{
    private string $bcp47;
    private ?string $language;
    private ?string $region;
    public static function fromString(string $bcp47) : self
    {
        return new self($bcp47);
    }
    private function __construct(string $bcp47)
    {
        $this->bcp47 = $bcp47;
        $parsedLocale = locale_parse($bcp47);
        $this->language = $parsedLocale['language'] ?? null;
        $this->region = $parsedLocale['region'] ?? null;
    }
    public function toString() : string
    {
        return $this->bcp47;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->bcp47 === $other->bcp47;
    }
    public function __toString() : string
    {
        return $this->bcp47;
    }
    public function language() : ?string
    {
        return $this->language;
    }
    public function region() : ?string
    {
        return $this->region;
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
