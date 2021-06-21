# Sineflow Elasticsearch Bundle for Symfony

![License](https://img.shields.io/github/license/sineflow/elasticsearchbundle.svg) [![Latest Stable Version](https://img.shields.io/github/release/sineflow/elasticsearchbundle.svg)](https://packagist.org/packages/sineflow/elasticsearch-bundle) [![Build Status](https://travis-ci.com/sineflow/ElasticsearchBundle.svg?branch=master)](https://travis-ci.com/sineflow/ElasticsearchBundle) [![Coverage Status](https://coveralls.io/repos/sineflow/ElasticsearchBundle/badge.svg?branch=master&service=github)](https://coveralls.io/github/sineflow/ElasticsearchBundle?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/e15da9f2-32b4-4ede-ade6-20f93f8ba076/mini.png)](https://insight.sensiolabs.com/projects/e15da9f2-32b4-4ede-ade6-20f93f8ba076)

Originally a fork of ongr/elasticsearch-bundle, now a project of its own.

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

## Breaking changes in version 5
- Finder::ADAPTER_SCANSCROLL renamed to Finder::ADAPTER_SCROLL
- class ScanScrollAdapter renamed to ScrollAdapter
- Object annotation removed in favour of DocObject

## License

This bundle is licensed under the MIT license. Please, see the complete license in the [LICENSE](LICENSE) file.
