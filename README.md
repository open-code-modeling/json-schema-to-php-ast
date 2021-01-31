# JSON Schema to PHP AST

Compiles a JSON schema to PHP classes / value objects via PHP AST.

## Installation

```bash
$ composer require open-code-modeling/json-schema-to-php-ast --dev
```

## Usage

> See unit tests in `tests` folder for comprehensive examples.

You can use each value object factory to compose your value object with PHP AST node visitors or high level builder API.
The easiest way to use this library is the `ValueObjectFactory`.

Assume you have the following JSON schema
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "definitions": {
        "address": {
            "type": "object",
            "properties": {
                "street_address": {
                    "type": "string"
                },
                "city": {
                    "type": ["string", "null"]
                },
                "federal_state": {
                    "$ref": "#/definitions/state"
                }
            },
            "required": [
                "street_address",
                "city",
                "federal_state"
            ]
        },
        "state": {
            "type": "string",
            "enum": ["NY", "DC"]
        }
    },
    "type": "object",
    "properties": {
        "billing_address": {
            "$ref": "#/definitions/address"
        },
        "shipping_addresses": {
            "type": "array",
            "items": {
                "$ref": "#/definitions/address"
            }
        }
    },
    "required": [
        "billing_address"
    ]
}
```

Then you can use the `ValueObjectFactory` to generate PHP code for the following classes:
- `Order`
- `BillingAddress`
- `ShippingAddresses`
- `Address`
- `StreetAddress`
- `City`
- `State`

```php
<?php

use OpenCodeModeling\CodeAst\Builder\FileCollection;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\CodeAst\Package\Psr4Info;
use OpenCodeModeling\Filter\FilterFactory;
use OpenCodeModeling\JsonSchemaToPhp\Type\Type;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;

$parser = (new PhpParser\ParserFactory())->create(PhpParser\ParserFactory::ONLY_PHP7);
$printer = new PhpParser\PrettyPrinter\Standard(['shortArraySyntax' => true]);

// configure your Composer info
// FilterFactory of library open-code-modeling/php-filter is used for sake of brevity
$classInfoList = new ClassInfoList(
    ...Psr4Info::fromComposer(
        'src/',
        file_get_contents('composer.json'),
        FilterFactory::directoryToNamespaceFilter(),
        FilterFactory::namespaceToDirectoryFilter(),
    )
);

$valueObjectFactory = new ValueObjectFactory(
    $classInfoList,
    $parser,
    $printer,
    true,
    FilterFactory::classNameFilter(),
    FilterFactory::propertyNameFilter(),
    FilterFactory::methodNameFilter(),
    FilterFactory::constantNameFilter(),
    FilterFactory::constantValueFilter()
);

// $json contains the json string from above
$decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

$typeSet = Type::fromDefinition($decodedJson);
$srcFolder = 'tmp/';

$fileCollection = FileCollection::emptyList();
$classBuilder = ClassBuilder::fromScratch('Order', 'YourNamespaceFromComposer')->setFinal(true);

$valueObjectFactory->generateClasses($classBuilder, $fileCollection, $typeSet, $srcFolder);

// $fileCollection contains 7 classes

// now let's add constants and getter methods of properties for non value objects
$valueObjectFactory->addGetterMethodsForProperties($fileCollection, true);
$valueObjectFactory->addClassConstantsForProperties($fileCollection);

// generate PHP code
$files = $valueObjectFactory->generateFiles($fileCollection);

foreach ($files as $filename => $code) {
    // store PHP code to filesystem
}
```
