# Sineflow Elasticsearch Bundle for Symfony

![License](https://img.shields.io/github/license/sineflow/elasticsearchbundle.svg)
[![Latest Stable Version](https://img.shields.io/github/release/sineflow/elasticsearchbundle.svg)](https://packagist.org/packages/sineflow/elasticsearch-bundle)
[![Build Status](https://travis-ci.com/sineflow/ElasticsearchBundle.svg?branch=master)](https://travis-ci.com/sineflow/ElasticsearchBundle)
[![Coverage Status](https://coveralls.io/repos/sineflow/ElasticsearchBundle/badge.svg?branch=master&service=github)](https://coveralls.io/github/sineflow/ElasticsearchBundle?branch=master)
[![SensioLabsInsight](https://insight.symfony.com/projects/4a865639-e552-4aef-8237-1aeb38aaaecd/mini.svg)](https://insight.symfony.com/account/widget?project=4a865639-e552-4aef-8237-1aeb38aaaecd)

## Key points

- Uses the official [elasticsearch-php](https://github.com/elastic/elasticsearch-php) client
- Uses Doctrine-like entity declarations for Elasticsearch documents
- Supports multilanguage documents
- Supports searching in multiple indices
- Supports zero-downtime reindexing by utilizing read and write index aliases
- Supports data providers for synchronizing Elasticsearch indices with an external data source such as Doctrine

## Documentation

Installation instructions and documentation of the bundle can be found [here](Resources/doc/index.md).

## Version matrix

| ElasticsearchBundle | Elasticsearch  | Symfony     | PHP         |
| ------------------- | -------------- | ----------- | ----------- |
| ~7.0                | >= 7.0         | 4.4+ / 5.0+ | 7.3+ / 8.0+ |
| ~6.2                | >= 6.2, < 7.0  | 3.4+ / 4.0+ | 7.3+        |
| ~6.1.0              | >= 6.0, < 6.2  |             |             |
| ~5.0                | >= 5.0, < 6.0  |             |             |
| >= 0.9, < 1.0       | >= 2.0, < 5.0  |             |             |

## License

This bundle is licensed under the MIT license. Please, see the complete license in the [LICENSE](LICENSE) file.
