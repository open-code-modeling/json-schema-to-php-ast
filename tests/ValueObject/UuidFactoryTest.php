<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\UuidFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class UuidFactoryTest extends BaseTestCase
{
    private UuidFactory $uuidFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->uuidFactory = new UuidFactory($this->parser, true, $this->propertyNameFilter);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->uuidFactory->nodeVisitorsFromNative('uuid'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $this->assertCode(
            $this->voFactory->nodeVisitors(
                StringType::fromDefinition(
                    [
                        'type' => 'string',
                        'name' => 'uuid',
                        'format' => 'uuid',
                    ]
                )
            )
        );
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            StringType::fromDefinition(
                [
                    'type' => 'string',
                    'name' => 'uuid',
                    'format' => 'uuid',
                ]
            )
        );
        $classBuilder->setName('UuidVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class UuidVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class UuidVO
{
    private UuidInterface $uuid;
    public static function fromString(string $uuid) : self
    {
        return new self(Uuid::fromString($uuid));
    }
    private function __construct(UuidInterface $uuid)
    {
        $this->uuid = $uuid;
    }
    public function toString() : string
    {
        return $this->uuid->toString();
    }
    /**
     * @param mixed $other
     */
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->uuid === $other->uuid;
    }
    public function __toString() : string
    {
        return $this->uuid->toString();
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
