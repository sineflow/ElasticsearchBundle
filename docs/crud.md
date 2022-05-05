# CRUD Actions

> To proceed with steps bellow it is necessary to read the [mapping](mapping.md) topic and have defined documents in the bundle.

For all steps below we assume that there is an `App` entity location with the `Product` document and that you have an index manager defined to manage that document.

```php

<?php
//App:Product
use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document
 */
class Product extends AbstractDocument
{
    /**
     * @ES\Property(type="text", name="title")
     */
    public $title;
}

```

```
# config.yml
sineflow_elasticsearch:
    indices:
        ...
        products:
            extends: _base
            name: acme_products
            class: App:Product
```

## Index manager

In order to work with an index, you will need the respective index manager service.
Once you define a manager in your `config.yml` file, you will have a service id `sfes.index.<name>` available, which you can inject in your Controller.

If you'd rather have your controller/service autowired, you have the following 2 options:

```php
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;

class MyController
{
    public function myAction(IndexManagerRegistry $imRegistry)
    {
        $productsIndexManager = $imRegistry->get('products');
    }

    public function myOtherAction(IndexManager $productsIndexManager)
    {
        // An alias is automatically registered for each index, so any IndexManager
        // argument named like '<index>IndexManager' will be autowired to the respective manager.
    }
}
```

## Create a document

```php
$product = new Product();
$product->id = 5; // If not set, elasticsearch will set a random unique id.
$product->title = 'Acme title';
$im->persist($product);
$im->getConnection()->commit();
```

If you want to bypass the entity objects for some reason, you can persist a raw array into the index like this:

```php
$product = [
    '_id' => 5,
    'title' => 'Acme title'
];
$im->persistRaw($product);
$im->getConnection()->commit();
```

> **id** is a special field that comes from `AbstractDocument` and translates to **\_id** in Elasticsearch.

## Update a document

```php
$repo = $im->getRepository();
$product = $repo->getById(5);
$product->title = 'changed Acme title';
$im->persist($product);
$im->getConnection()->commit();
```

## Delete a document

```php
$im->delete(5);
$im->getConnection()->commit();
```

## Reindex a document

You can refresh the content of a document from its registered data provider.
```php
$im->reindex(5);
$im->getConnection()->commit();
```
For more information about that, see [data providers](dataproviders.md).

## Bulk operations

It is important to note that you have to explicitly call `commit()` of the connection, after create, update, delete or reindex operations. This allows you to do multiple operations as a single bulk request, which in certain situation greatly increases performance by reducing network round trips.
This behaviour can be changed though, by turning **on** the autocommit mode of the connection.

```php
$im->getConnection()->setAutocommit(true);
```
When you do that, all of the above operations will not need to be explicitly committed and will be executed right away.

> Note that turning on the autocommit mode of the connection when it was off, will commit any pending operations.
