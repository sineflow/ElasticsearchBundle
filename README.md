# Sineflow Elasticsearch Bundle for Symfony

![License](https://img.shields.io/github/license/sineflow/elasticsearchbundle.svg)
[![Latest Stable Version](https://img.shields.io/github/release/sineflow/elasticsearchbundle.svg)](https://packagist.org/packages/sineflow/elasticsearch-bundle)
[![Tests Workflow](https://github.com/sineflow/ElasticsearchBundle/actions/workflows/phpunit-tests.yml/badge.svg)](https://github.com/sineflow/ElasticsearchBundle/actions/workflows/phpunit-tests.yml)
[![Coverage Status](https://coveralls.io/repos/github/sineflow/ElasticsearchBundle/badge.svg?branch=main)](https://coveralls.io/github/sineflow/ElasticsearchBundle?branch=main)

## Key points

- Uses the official [elasticsearch-php](https://github.com/elastic/elasticsearch-php) client
- Uses Doctrine-like entity declarations for Elasticsearch documents
- Supports multilanguage documents
- Supports searching in multiple indices
- Supports zero-downtime reindexing by utilizing read and write index aliases
- Supports data providers for synchronizing Elasticsearch indices with an external data source such as Doctrine

## Documentation

Installation instructions and documentation of the bundle can be found [here](docs/index.md).

## Version matrix

| ElasticsearchBundle | Elasticsearch | Symfony     | PHP         |
|---------------------|---------------|-------------|-------------|
| ^8.0                | >= 8.0        | 5.0+        | 8.1+        |
| ^7.2                | >= 7.0        | 5.0+        | 8.1+        |
| ^7.0                | >= 7.0        | 4.4+ / 5.0+ | 7.3+ / 8.0+ |
| ^6.2                | >= 6.2, < 7.0 | 3.4+ / 4.0+ | 7.3+        |
| ^6.1.0              | >= 6.0, < 6.2 |             |             |
| ^5.0                | >= 5.0, < 6.0 |             |             |
| >= 0.9, < 1.0       | >= 2.0, < 5.0 |             |             |

## License

This bundle is licensed under the MIT license. Please, see the complete license in the [LICENSE](LICENSE) file.

## Running tests

```
composer install
docker compose up --detach --wait
vendor/bin/phpunit
docker compose down --remove-orphans
```

## Checking and fixing code quality

NOTE: Tests must be run first, so a Symfony container is generated
```
composer check-code
composer fix-code
```
