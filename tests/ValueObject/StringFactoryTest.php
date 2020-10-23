<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\StringFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

final class StringFactoryTest extends TestCase
{
    private Parser $parser;
    private PrettyPrinterAbstract $printer;
    private StringFactory $stringFactory;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);
        $this->stringFactory = new StringFactory($this->parser, true);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->stringFactory->nodeVisitorsFromNative('name'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $voFactory = new ValueObjectFactory($this->parser, true);
        $this->assertCode($voFactory->nodeVisitors(StringType::fromDefinition(['type' => 'string', 'name' => 'name'])));
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class StringVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class StringVO
{
    private string $name;
    public static function fromString(string $name) : self
    {
        return new self($name);
    }
    private function __construct(string $name)
    {
        $this->name = $name;
    }
    public function toString() : string
    {
        return $this->name;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->name === $other->name;
    }
    public function __toString() : string
    {
        return $this->name;
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
