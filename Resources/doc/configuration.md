# Configuration

Here is an example configuration, covering all available options:

```
sineflow_elasticsearch:

    languages: ['en', 'fr']

    metadata_cache_pool: sfes.metadata_cache_pool

    entity_locations:
        # Specify locations of Elasticsearch entities
        # looks for entity classes in 'directory' directory and gives them an 'App' alias (so you can say things like App:Post):
        App:
            directory: '%kernel.project_dir%/src/ElasticSearch/Document'
            namespace: 'App\ElasticSearch\Document'

    connections:
        default:
            hosts:
                - user:pass@127.0.0.1:9200
            profiling: true
            logging: true
            bulk_batch_size: 1000

    indices:
        _base:
            connection: default
            use_aliases: true
            settings:
                refresh_interval: -1
                number_of_replicas: 1
                analysis:
                    filter:
                        unigram_filter:
                            type: ngram
                            min_gram: 1
                            max_gram: 20

                    tokenizer:
                        email_tokenizer:
                            type: ngram
                            min_gram: 3
                            max_gram: 60

                    analyzer:
                        default_ngram_analyzer:
                            type: custom
                            tokenizer: standard
                            filter: [lowercase, unigram_filter]
                        fr_ngram_analyzer:
                            type: custom
                            tokenizer: standard
                            filter: [lowercase, unigram_filter]
                        email_analyzer:
                            type: custom
                            tokenizer: email_tokenizer
                            filter: [lowercase]

        products:
            extends: _base
            name: dev_products
            class: App:Product

```

And here is the breakdown:

* `languages`: Specifies all languages that will be available for multilanguage properties

* `metadata_cache_pool`: Optional cache pool to use for metadata caching. If not provided, the `cache.system` pool will be used by default

* `entity_locations` *(required)*: Specifies all directories where the Elasticsearch entities are located

* `connections` *(required)*: Here you define your Elasticsearch connections. In the above example we have only one connection, named **default**, which will be accessible from the service container like **$container->get('sfes.connection.default')** or just **$container->get('sfes.connection')** in case of the default connection.
    * `hosts`: This is a list of each Elasticsearch instance within the connection. Note that if you have basic authentication for an instance, you have to specify the user and password here.
    * `profiling` *(default: true)*: Enable or disable profiling. The default setup makes use of Elasticsearch client's profiling to gather information for the Symfony profiler toolbar, which is extremely useful in development.
    * `logging` *(default: true)*: When enabled, the bundle uses Symfony's 'logger' service to log Elasticsearch events in the 'sfes' channel. Using symfony/monolog-bundle, the logging can be easily controlled. For example the 'sfes' channel can be redirected to a rotating file log.
    * `bulk_batch_size` *(default: 1000)*: This is currently used only when using the **rebuildIndex()** method of the index manager.

* `indices`: Here you define the Elasticsearch indexes you have. The key here is the name of the index manager, which determines how it will be accessible in the application. In the example above, we have an index manager named **products**, which would be accessible as **$container->get('sfes.index.products')**.
It is important to note here the use of **'_'** in front of the index manager name. When defined like that, this will be an abstract definition, i.e. no manager will actually be created from that definition. This is very useful when you have common setting for several indices, as you can define a template for them all and not have to duplicate stuff.
    * `extends`: You can specify the name of another index manager here in order to inherit its definition.
    * `connection`: The connection under which that index will live.
    * `use_aliases` *(default: true)*: Whether to setup read and write alias for working with the physical index. Very useful for being able to reindex with no downtime.
    * `settings`: Here you can specify any index settings supported by Elasticsearch. [See here for more info on that](https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-update-settings.html)
    * `types`: This is where you specify the types, which will be managed by the index. This is done by listing the document entities that manage the respective types, in short notation.

## Configuring custom cache pool:

### Example (cache metadata in Redis):

```yaml
services:
    app.redis.client.cache:
        class: Predis\Client
        factory: ['Symfony\Component\Cache\Adapter\RedisAdapter', 'createConnection']
        arguments:
            - '%env(REDIS_DB1_DSN)%'

    app.cache.sfes:
        parent: 'cache.adapter.redis'
        tags:
            - name: cache.pool
              namespace: '%deploy_env_prefix%es_cache' # Optional, if you want to control the namespace, otherwise it is a unique hash string
              clearer: cache.system_clearer            # Optional, if you want your cache to be cleared with the system cache (i.e. with cache:clear)
              provider: app.redis.client.cache         # Use the Predis client declared above, created by Symfony's built-in redis adapter
              #provider: snc_redis.cache               # Use a Predis client created by the snc_redis bundle

sineflow_elasticsearch:
    metadata_cache_pool: sfes.metadata_cache_pool
```
