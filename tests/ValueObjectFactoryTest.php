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
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\CodeAst\Package\Psr4Info;
use OpenCodeModeling\Filter\FilterFactory;
use OpenCodeModeling\JsonSchemaToPhp\Type\Type;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

final class ValueObjectFactoryTest extends TestCase
{
    private Parser $parser;
    private PrettyPrinterAbstract $printer;
    private ValueObjectFactory $valueObjectFactory;
    private ClassInfoList $classInfoList;

    /**
     * @var \Closure
     */
    private $fileFilter;

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
        $constantNameFilter = FilterFactory::constantNameFilter();
        $constantValueFilter = FilterFactory::constantValueFilter();
        $methodNameFilter = FilterFactory::methodNameFilter();

        $this->valueObjectFactory = new ValueObjectFactory(
            $this->classInfoList,
            $this->parser,
            $this->printer,
            true,
            $classNameFilter,
            $propertyNameFilter,
            $methodNameFilter,
            $constantNameFilter,
            $constantValueFilter
        );

        /**
         * @param string $className
         * @return \Closure
         *
         * @psalm-return \Closure(\OpenCodeModeling\CodeAst\Builder\ClassBuilder):bool
         */
        $this->fileFilter = static function (string $className): \Closure {
            return static function (ClassBuilder $classBuilder) use ($className) {
                return $classBuilder->getName() === $className;
            };
        };
    }

    /**
     * @test
     */
    public function it_generates_classes_of_objects(): void
    {
        $json = \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_objects.json');
        $decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $typeSet = Type::fromDefinition($decodedJson);
        $srcFolder = 'tmp/';

        $fileCollection = FileCollection::emptyList();
        $classBuilder = ClassBuilder::fromScratch('Order', 'Acme')->setFinal(true);

        $this->valueObjectFactory->generateClasses($classBuilder, $fileCollection, $typeSet, $srcFolder);

        $this->assertCount(6, $fileCollection);

        $this->assertOrder($fileCollection->filter(($this->fileFilter)('Order'))->current());
        $this->assertShippingAddresses($fileCollection->filter(($this->fileFilter)('ShippingAddresses'))->current());
        $this->assertAddress($fileCollection->filter(($this->fileFilter)('Address'))->current());
        $this->assertStreetAddress($fileCollection->filter(($this->fileFilter)('StreetAddress'))->current());
        $this->assertCity($fileCollection->filter(($this->fileFilter)('City'))->current());
        $this->assertState($fileCollection->filter(($this->fileFilter)('State'))->current());
    }

    /**
     * @test
     */
    public function it_generates_files(): void
    {
        $json = \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_objects.json');
        $decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $typeSet = Type::fromDefinition($decodedJson);
        $srcFolder = 'tmp/';

        $fileCollection = FileCollection::emptyList();
        $classBuilder = ClassBuilder::fromScratch('Order', 'Acme')->setFinal(true);

        $this->valueObjectFactory->generateClasses($classBuilder, $fileCollection, $typeSet, $srcFolder);

        $this->assertCount(6, $fileCollection);

        $this->valueObjectFactory->addGetterMethodsForProperties($fileCollection, true);
        $this->valueObjectFactory->addClassConstantsForProperties($fileCollection);

        $files = $this->valueObjectFactory->generateFiles($fileCollection);

        $this->assertCount(6, $files);

        $this->assertArrayHasKey('tmp/City.php', $files);
        $this->assertArrayHasKey('tmp/Order.php', $files);
        $this->assertArrayHasKey('tmp/ShippingAddresses.php', $files);
        $this->assertArrayHasKey('tmp/Address.php', $files);
        $this->assertArrayHasKey('tmp/State.php', $files);
        $this->assertArrayHasKey('tmp/StreetAddress.php', $files);

        $this->assertOrderFile($files['tmp/Order.php']);
        $this->assertShippingAddressesFile($files['tmp/ShippingAddresses.php']);
        $this->assertAddressFile($files['tmp/Address.php']);
        $this->assertStreetAddressFile($files['tmp/StreetAddress.php']);
        $this->assertCityFile($files['tmp/City.php']);
        $this->assertStateFile($files['tmp/State.php']);
    }

    /**
     * @test
     */
    public function it_generates_classes_of_objects_with_namespaced_properties(): void
    {
        $json = \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_objects_namespace.json');
        $decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $typeSet = Type::fromDefinition($decodedJson);
        $srcFolder = 'tmp/';

        $fileCollection = FileCollection::emptyList();
        $classBuilder = ClassBuilder::fromScratch('Order', 'Acme')->setFinal(true);

        $this->valueObjectFactory->generateClasses($classBuilder, $fileCollection, $typeSet, $srcFolder);

        $this->assertCount(6, $fileCollection);

        /** @var ClassBuilder $classBuilder */
        foreach ($fileCollection as $classBuilder) {
            switch (true) {
                case $classBuilder->getName() === 'Address':
                    $this->assertSame('Acme', $classBuilder->getNamespace());
                    $this->assertArrayHasKey('Acme\Address\City', $classBuilder->getNamespaceImports());
                    $this->assertArrayHasKey('Acme\Address\State', $classBuilder->getNamespaceImports());
                    $this->assertArrayHasKey('Acme\Address\StreetAddress', $classBuilder->getNamespaceImports());
                    break;
                case $classBuilder->getName() === 'City':
                case $classBuilder->getName() === 'StreetAddress':
                case $classBuilder->getName() === 'State':
                    $this->assertSame('Acme\\Address', $classBuilder->getNamespace());
                    break;
                case $classBuilder->getName() === 'ShippingAddresses':
                    $this->assertSame('Acme\\Order', $classBuilder->getNamespace());

                    $properties = $classBuilder->getProperties();
                    $this->assertArrayHasKey('shippingAddresses', $properties);
                    $this->assertSame('array', $properties['shippingAddresses']->getType());
                    $this->assertSame('Address[]', $properties['shippingAddresses']->getTypeDocBlockHint());
                    break;
                case $classBuilder->getName() === 'Order':
                    $this->assertSame('Acme', $classBuilder->getNamespace());
                    $this->assertArrayHasKey('Acme\Order\Address', $classBuilder->getNamespaceImports());
                    $this->assertArrayHasKey('Acme\Order\ShippingAddresses', $classBuilder->getNamespaceImports());

                    $properties = $classBuilder->getProperties();
                    $this->assertArrayHasKey('billingAddress', $properties);
                    $this->assertArrayHasKey('shippingAddresses', $properties);

                    $this->assertSame('Address', $properties['billingAddress']->getType());
                    $this->assertSame('?ShippingAddresses', $properties['shippingAddresses']->getType());
                    break;
                default:
                    $this->assertTrue(false, \sprintf('Unexpected class "%s"', $classBuilder->getName()));
            }
        }
    }

    /**
     * @test
     */
    public function it_generates_classes_of_objects_with_namespaced_properties_shorthand(): void
    {
        $json = \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_shorthand_namespace.json');
        $decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $typeSet = Type::fromShorthand($decodedJson);
        $srcFolder = 'tmp/';

        $fileCollection = FileCollection::emptyList();
        $classBuilder = ClassBuilder::fromScratch('Order', 'Acme')->setFinal(true);

        $this->valueObjectFactory->generateClasses($classBuilder, $fileCollection, $typeSet, $srcFolder);

        $this->assertCount(1, $fileCollection);

        /** @var ClassBuilder $classBuilder */
        foreach ($fileCollection as $classBuilder) {
            switch (true) {
                case $classBuilder->getName() === 'Order':
                    $this->assertSame('Acme', $classBuilder->getNamespace());
                    $this->assertArrayHasKey('Acme\Billing\Address', $classBuilder->getNamespaceImports());
                    $this->assertArrayHasKey('Acme\Payment\ShippingAddresses', $classBuilder->getNamespaceImports());

                    $properties = $classBuilder->getProperties();
                    $this->assertArrayHasKey('billingAddress', $properties);
                    $this->assertArrayHasKey('address', $properties);

                    $this->assertSame('Address', $properties['billingAddress']->getType());
                    $this->assertSame('?ShippingAddresses', $properties['address']->getType());
                    break;
                default:
                    $this->assertTrue(false, \sprintf('Unexpected class "%s"', $classBuilder->getName()));
            }
        }
    }

    private function assertOrder(ClassBuilder $classBuilder): void
    {
        $this->assertSame('Order', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(2, $properties);
        $this->assertArrayHasKey('billingAddress', $properties);
        $this->assertArrayHasKey('shippingAddresses', $properties);

        $this->assertSame('billingAddress', $properties['billingAddress']->getName());
        $this->assertSame('Address', $properties['billingAddress']->getType());
        $this->assertTrue($properties['billingAddress']->isTyped());

        $this->assertSame('shippingAddresses', $properties['shippingAddresses']->getName());
        $this->assertSame('?ShippingAddresses', $properties['shippingAddresses']->getType());
        $this->assertTrue($properties['shippingAddresses']->isTyped());

        $this->assertCount(0, $classBuilder->getNamespaceImports());
    }

    private function assertBillingAddress(ClassBuilder $classBuilder): void
    {
        $this->assertSame('BillingAddress', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('address', $properties);

        $this->assertSame('Address', $properties['address']->getType());

        $this->assertCount(0, $classBuilder->getNamespaceImports());
    }

    private function assertShippingAddresses(ClassBuilder $classBuilder): void
    {
        $this->assertSame('ShippingAddresses', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(2, $properties);
        $this->assertArrayHasKey('position', $properties);
        $this->assertArrayHasKey('shippingAddresses', $properties);

        $this->assertSame('shippingAddresses', $properties['shippingAddresses']->getName());
        $this->assertSame('array', $properties['shippingAddresses']->getType());
        $this->assertSame('Address[]', $properties['shippingAddresses']->getTypeDocBlockHint());

        $this->assertCount(0, $classBuilder->getNamespaceImports());
    }

    private function assertAddress(ClassBuilder $classBuilder): void
    {
        $this->assertSame('Address', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(3, $properties);
        $this->assertArrayHasKey('city', $properties);
        $this->assertArrayHasKey('federalState', $properties);
        $this->assertArrayHasKey('streetAddress', $properties);

        $this->assertSame('?City', $properties['city']->getType());
        $this->assertSame('State', $properties['federalState']->getType());
        $this->assertSame('StreetAddress', $properties['streetAddress']->getType());

        $this->assertCount(0, $classBuilder->getNamespaceImports());
    }

    private function assertStreetAddress(ClassBuilder $classBuilder): void
    {
        $this->assertSame('StreetAddress', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $this->assertCount(5, $classBuilder->getMethods());

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('streetAddress', $properties);

        $this->assertSame('streetAddress', $properties['streetAddress']->getName());
        $this->assertSame('string', $properties['streetAddress']->getType());
    }

    private function assertState(ClassBuilder $classBuilder): void
    {
        $this->assertSame('State', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $constants = $classBuilder->getConstants();
        $this->assertCount(3, $constants);
        $this->assertArrayHasKey('CHOICES', $constants);
        $this->assertArrayHasKey('DC', $constants);
        $this->assertArrayHasKey('NY', $constants);

        $methods = $classBuilder->getMethods();

        $this->assertCount(8, $methods);
        $this->assertArrayHasKey('dc', $methods);
        $this->assertArrayHasKey('ny', $methods);
        $this->assertArrayHasKey('isOneOf', $methods);

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('state', $properties);

        $this->assertSame('state', $properties['state']->getName());
        $this->assertSame('string', $properties['state']->getType());
    }

    private function assertCity(ClassBuilder $classBuilder): void
    {
        $this->assertSame('City', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $this->assertCount(5, $classBuilder->getMethods());

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('city', $properties);

        $this->assertSame('city', $properties['city']->getName());
        $this->assertSame('string', $properties['city']->getType());
    }

    private function assertOrderFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class Order
{
    public const BILLING_ADDRESS = 'billing_address';
    public const SHIPPING_ADDRESSES = 'shipping_addresses';
    private Address $billingAddress;
    private ?ShippingAddresses $shippingAddresses;
    public function billingAddress() : Address
    {
        return $this->billingAddress;
    }
    public function shippingAddresses() : ?ShippingAddresses
    {
        return $this->shippingAddresses;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertShippingAddressesFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class ShippingAddresses implements \Iterator, \Countable
{
    private int $position = 0;
    /**
     * @var Address[]
     */
    private array $shippingAddresses;
    public function rewind() : void
    {
        $this->position = 0;
    }
    public function current() : Address
    {
        return $this->shippingAddresses[$this->position];
    }
    public function key() : int
    {
        return $this->position;
    }
    public function next() : void
    {
        ++$this->position;
    }
    public function valid() : bool
    {
        return isset($this->shippingAddresses[$this->position]);
    }
    public function count() : int
    {
        return count($this->shippingAddresses);
    }
    public static function fromArray(array $shippingAddresses) : self
    {
        return new self(...array_map(static function (string $item) {
            return Address::fromString($item);
        }, $shippingAddresses));
    }
    public static function fromItems(Address ...$shippingAddresses) : self
    {
        return new self(...$shippingAddresses);
    }
    public static function emptyList() : self
    {
        return new self();
    }
    private function __construct(Address ...$shippingAddresses)
    {
        $this->shippingAddresses = $shippingAddresses;
    }
    public function add(Address $address) : self
    {
        $copy = clone $this;
        $copy->shippingAddresses[] = $address;
        return $copy;
    }
    public function remove(Address $address) : self
    {
        $copy = clone $this;
        $copy->shippingAddresses = array_values(array_filter($copy->shippingAddresses, static function ($v) use($address) {
            return !$v->equals($address);
        }));
        return $copy;
    }
    public function first() : ?Address
    {
        return $this->shippingAddresses[0] ?? null;
    }
    public function last() : ?Address
    {
        if (count($this->shippingAddresses) === 0) {
            return null;
        }
        return $this->shippingAddresses[count($this->shippingAddresses) - 1];
    }
    public function contains(Address $address) : bool
    {
        foreach ($this->shippingAddresses as $existingItem) {
            if ($existingItem->equals($address)) {
                return true;
            }
        }
        return false;
    }
    public function filter(callable $filter) : self
    {
        return new self(...array_values(array_filter($this->shippingAddresses, static function ($v) use($filter) {
            return $filter($v);
        })));
    }
    /**
     * @return Address[]
     */
    public function items() : array
    {
        return $this->shippingAddresses;
    }
    public function toArray() : array
    {
        return \array_map(static function (Address $address) {
            return $address->toArray();
        }, $this->shippingAddresses);
    }
    /**
     * @param mixed $other
     * @return bool
     */
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->toArray() === $other->toArray();
    }
}
PHP;

        $this->assertSame($expected, $code);
    }

    private function assertAddressFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class Address
{
    public const STREET_ADDRESS = 'street_address';
    public const CITY = 'city';
    public const FEDERAL_STATE = 'federal_state';
    private StreetAddress $streetAddress;
    private ?City $city;
    private State $federalState;
    public function streetAddress() : StreetAddress
    {
        return $this->streetAddress;
    }
    public function city() : ?City
    {
        return $this->city;
    }
    public function federalState() : State
    {
        return $this->federalState;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertBillingAddressFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class BillingAddress
{
    public const ADDRESS = 'address';
    private Address $address;
    public function address() : Address
    {
        return $this->address;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertCityFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class City
{
    private string $city;
    public static function fromString(string $city) : self
    {
        return new self($city);
    }
    private function __construct(string $city)
    {
        $this->city = $city;
    }
    public function toString() : string
    {
        return $this->city;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->city === $other->city;
    }
    public function __toString() : string
    {
        return $this->city;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertStreetAddressFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class StreetAddress
{
    private string $streetAddress;
    public static function fromString(string $streetAddress) : self
    {
        return new self($streetAddress);
    }
    private function __construct(string $streetAddress)
    {
        $this->streetAddress = $streetAddress;
    }
    public function toString() : string
    {
        return $this->streetAddress;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->streetAddress === $other->streetAddress;
    }
    public function __toString() : string
    {
        return $this->streetAddress;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertStateFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class State
{
    private const NY = 'NY';
    private const DC = 'DC';
    public const CHOICES = [self::NY, self::DC];
    private string $state;
    public static function fromString(string $state) : self
    {
        return new self($state);
    }
    public static function ny() : self
    {
        return new self(self::NY);
    }
    public static function dc() : self
    {
        return new self(self::DC);
    }
    private function __construct(string $state)
    {
        if (false === in_array($state, self::CHOICES, true)) {
            throw InvalidState::forState($state);
        }
        $this->state = $state;
    }
    public function toString() : string
    {
        return $this->state;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->state === $other->state;
    }
    public function isOneOf(...$state) : bool
    {
        foreach ($state as $otherState) {
            if ($this->equals($otherState)) {
                return true;
            }
        }
        return false;
    }
    public function __toString() : string
    {
        return $this->state;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }
}
