# CRUD Actions

> To proceed with steps bellow it is necessary to read the [mapping](mapping.md) topic and have defined documents in the bundle.

For all steps below we assume that there is an `AppBundle` with the `Product` document and that you have an index manager defined to manage that document.

```php

<?php
//AppBundle:Product
use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document(type="product")
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
            class: AppBundle:Product
```

## Index manager

In order to work with an index, you will need the respective index manager service.
Once you define managers in your `config.yml` file, you can use them in controllers and grab them from DI container via `sfes.index.<name>`. With the above example:

```php
$im = $this->get('sfes.index.product');
```

## Repositories

When you need to work with a specific type, you can do so through the Repository class of an entity. 
The default Repository is mostly a convenience class, as its methods are also available either through the IndexManager or the Finder services. However it may be useful to have type-specific methods in your own custom repositories.
You can get a type's repository through the index manager that manages it:

```php
$repo = $im->getRepository('AppBundle:Product');
```

## Create a document

```php
$product = new Product();
$product->id = 5; // If not set, elasticsearch will set a random unique id.
$product->title = 'Acme title';
$repo->persist($product);
$repo->getIndexManager()->getConnection()->commit();
```

Alternatively, you can directly persist the document through the index manager as well:
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
$repo->persistRaw($product);
$im->getConnection()->commit();
```

> **id** is a special field that comes from `AbstractDocument` and translates to **\_id** in Elasticsearch.

## Update a document

```php
$product = $repo->getById(5);
$product->title = 'changed Acme title';
$repo->persist($product);
$im->getConnection()->commit();
```

## Delete a document

```php
$repo->delete(5);
$im->getConnection()->commit();
```

## Reindex a document

You can refresh the content of a document from its registered data provider.
```php
$repo->reindex(5);
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
