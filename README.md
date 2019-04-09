# Sineflow Elasticsearch Bundle for Symfony

[![License](https://poser.pugx.org/sineflow/elasticsearch-bundle/license)](https://packagist.org/packages/sineflow/elasticsearch-bundle) [![Latest Stable Version](https://poser.pugx.org/sineflow/elasticsearch-bundle/v/stable)](https://packagist.org/packages/sineflow/elasticsearch-bundle) [![Build Status](https://travis-ci.org/sineflow/ElasticsearchBundle.svg?branch=master)](https://travis-ci.org/sineflow/ElasticsearchBundle) [![Coverage Status](https://coveralls.io/repos/sineflow/ElasticsearchBundle/badge.svg?branch=master&service=github)](https://coveralls.io/github/sineflow/ElasticsearchBundle?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/e15da9f2-32b4-4ede-ade6-20f93f8ba076/mini.png)](https://insight.sensiolabs.com/projects/e15da9f2-32b4-4ede-ade6-20f93f8ba076)

This bundle was initially based on the Elasticsearch bundle by ONGR.io, however a different vision about how some things are handled, and some additional core requirements eventually turned it into a separate project.

## Key points

- Uses the official [elasticsearch-php](https://github.com/elastic/elasticsearch-php) client
- Integrates with Symfony 2.7+ and Symfony 3+
- Uses Doctrine-like entity declarations for Elasticsearch documents
- Supports multilanguage documents
- Supports searching in multiple types and indices
- Supports zero-downtime reindexing by utilizing read and write index aliases
- Supports data providers for synchronizing Elasticsearch indices with an external data source such as Doctrine

## Documentation

Installation instructions and documentation of the bundle can be found [here](Resources/doc/index.md).

## Version matrix

| Elasticsearch version | ElasticsearchBundle version |
| --------------------- | --------------------------- |
| >= 6.0                | ~6.x                        |
| >= 5.0, < 6.0         | ~5.x                        |
| >= 2.0, < 5.0         | >= 0.9, < 1.0               |

## Breaking changes in version 5
- Finder::ADAPTER_SCANSCROLL renamed to Finder::ADAPTER_SCROLL
- class ScanScrollAdapter renamed to ScrollAdapter
- Object annotation removed in favour of DocObject

## License

This bundle is licensed under the MIT license. Please, see the complete license in the [LICENSE](LICENSE) file.
