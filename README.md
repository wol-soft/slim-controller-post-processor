# slim-controller-post-processor

Creates Slim controllers from JSON Schema files based on the JSON Schema Model Generator (https://github.com/wol-soft/php-json-schema-model-generator)

> :warning: This is currently a **prototype** to show the possibilities of post processors.

## Installation

The recommended way to install slim-controller-post-processor is through [Composer](http://getcomposer.org):

```
$ composer require --dev wol-soft/slim-controller-post-processor
```

To avoid adding all dependencies of the php-json-schema-model-generator to your production dependencies it's recommended to add the library as a dev-dependency

## Usage

To use the slim controller post processor simply add the post processor to your generator. For working code you must additionally add the builtin `PopulationPostProcessor`, disable immutability and enable the serialization option. A full example could look like:

```php
<?php

use PHPModelGenerator\ModelGenerator;
use PHPModelGenerator\Model\GeneratorConfiguration;
use PHPModelGenerator\SchemaProcessor\PostProcessor\PopulatePostProcessor;
use PHPModelGenerator\SchemaProvider\RecursiveDirectoryProvider;
use SlimControllerPostProcessor\SlimControllerPostProcessor;

require_once __DIR__ . '/vendor/autoload.php';

$generator = new ModelGenerator((new GeneratorConfiguration())
    ->setNamespacePrefix('\\App\\Model\\Generated')
    ->setImmutable(false)
    ->setSerialization(true)
);

$generator
    ->addPostProcessor(new PopulatePostProcessor())
    ->addPostProcessor(new SlimControllerPostProcessor(__DIR__ . '/src/Controller/Generated', '\\App\\Controller\\Generated'))
    ->generateModelDirectory(__DIR__ . '/src/Model/Generated')
    ->generateModelDirectory(__DIR__ . '/src/Controller/Generated')
    ->generateModels(new RecursiveDirectoryProvider(__DIR__ . '/src/schema'), __DIR__ . '/src/Model/Generated');
```

The first argument is the directory which shall contain the generated code and the second argument is the namespace prefix for the generated controllers.

On a run of the model generator the post processor will now create a controller for each base model which was generated. The controller will contain basic CRUD actions and a getAll method. To achieve the functionality you must implement the persistence layer of your application yourself. To interact with your persistence layer the post processor will set up a repository interface for each base model. You must implement the interfaces and make sure the DI container of your Slim application passes the repositories to your controller constructors.

The controllers use the generated models. Consequently if your JSON Schema file contains validation rules your repository implementation will only be called if the create/update routes are called with valid data.

The routing is already set up. After all controllers have been generated the post processor will set up a single `BootstrapGeneratedControllers.php` file which you have to include into your bootstrap process:

```php
(new \App\Controller\Generated\BootstrapGeneratedControllers())->bootstrap($app);
```

This method call will wire all generated controllers with the routing engine. As an optional second argument you can pass a route prefix which will be added to each route (eg. `/API`). After everything is set up you will get the following routes (let's assume you have a single JSON Schema file `Album.json`):

Method | Route | Description
--- | --- | ---
`GET` | `/albums` | Returns a JSON response including all albums returned by the repository implementation of the `getAll` method.
`GET` | `/albums/{id}` | Returns JSON response including a single album returned by the repository implementation of the `get($id)` method.
`PUT` | `/albums` | Creates a new album entry. The content of the request body will be validated with the generated `Album` model. Returns the new created entity.
`POST` | `/albums/{id}` | Updates an existing album entry via the repository implementation of the `getAll` method.
`DELETE` | `/albums/{id}` | Deletes an existing album entry via the repository implementation of the `delete` method.
