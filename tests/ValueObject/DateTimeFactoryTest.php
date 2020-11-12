<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\DateTimeFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

final class DateTimeFactoryTest extends TestCase
{
    private Parser $parser;
    private PrettyPrinterAbstract $printer;
    private DateTimeFactory $dateTimeFactory;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);
        $this->dateTimeFactory = new DateTimeFactory($this->parser, true);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->dateTimeFactory->nodeVisitorsFromNative('dateTime'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $voFactory = new ValueObjectFactory($this->parser, true);
        $this->assertCode(
            $voFactory->nodeVisitors(
                StringType::fromDefinition(['type' => 'string', 'format' => TypeDefinition::FORMAT_DATETIME])
            )
        );
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $voFactory = new ValueObjectFactory($this->parser, true);

        $classBuilder = $voFactory->classBuilder(
            StringType::fromDefinition(['type' => 'string', 'format' => TypeDefinition::FORMAT_DATETIME])
        );
        $classBuilder->setName('DateTimeVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class DateTimeVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class DateTimeVO
{
    private const OUTPUT_FORMAT = 'Y-m-d\TH:i:sP';
    private DateTimeImmutable $dateTime;
    public static function fromDateTime(DateTimeImmutable $dateTime) : self
    {
        return new self(self::ensureUtc($dateTime));
    }
    public static function fromString(string $dateTime) : self
    {
        try {
            $dateTimeImmutable = new DateTimeImmutable($dateTime);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('String "%s" is not supported. Use a date time format which is compatible with ISO 8601.', $dateTime));
        }
        $dateTimeImmutable = self::ensureUtc($dateTimeImmutable);
        return new self($dateTimeImmutable);
    }
    private function __construct(DateTimeImmutable $dateTime)
    {
        $this->dateTime = $dateTime;
    }
    public function toString() : string
    {
        return $this->dateTime->format(self::OUTPUT_FORMAT);
    }
    public function dateTime() : DateTimeImmutable
    {
        return $this->dateTime;
    }
    public function __toString() : string
    {
        return $this->toString();
    }
    private static function ensureUtc(DateTimeImmutable $dateTime) : DateTimeImmutable
    {
        if ($dateTime->getTimezone()->getName() !== 'UTC') {
            $dateTime = $dateTime->setTimezone(new \DateTimeZone('UTC'));
        }
        return $dateTime;
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
