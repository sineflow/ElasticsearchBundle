# Using data providers

Simply put, data providers are the sources of data for your Elasticsearch indices.

By default, each index is assigned a default *self* data provider (`ElasticsearchProvider`), which retrieves the data from the Elasticsearch index itself. This is useful when you want to rebuild the index (like if you changed the mapping and want to update it).

In order to define your own custom data provider for an index, you have to create a service that implements `ProviderInterface` and tag it as `sfes.provider`, specifying the entity it is for in the `type` argument:

```
services:
    app.es.data_provider.product:
        class: App\ElasticSearch\Document\Provider\ProductProvider
        arguments:
            - App:Product
            - @doctrine.orm.entity_manager
        tags:
            - { name: sfes.provider, type: "App:Product" }
```

If your data is coming from Doctrine, you may extend the `AbstractDoctrineProvider` class, which provides you with the basic framework and you only need to extend a couple of methods in it, to make it work for your case:

```
    /**
     * Gets the query that will return all records from the DB
     *
     * @return Query
     */
    abstract public function getQuery();

    /**
     * Converts a Doctrine entity to Elasticsearch entity
     *
     * @param mixed $entity A doctrine entity object or data array
     * @return mixed An ES document entity object or document array
     */
    abstract protected function getAsDocument($entity);
```
