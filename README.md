# Sineflow Elasticsearch Bundle for Symfony

[![License](https://poser.pugx.org/sineflow/elasticsearch-bundle/license)](https://packagist.org/packages/sineflow/elasticsearch-bundle) [![Latest Stable Version](https://poser.pugx.org/sineflow/elasticsearch-bundle/v/stable)](https://packagist.org/packages/sineflow/elasticsearch-bundle) [![Build Status](https://travis-ci.org/sineflow/ElasticsearchBundle.svg?branch=master)](https://travis-ci.org/sineflow/ElasticsearchBundle) [![Coverage Status](https://coveralls.io/repos/sineflow/ElasticsearchBundle/badge.svg?branch=master&service=github)](https://coveralls.io/github/sineflow/ElasticsearchBundle?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/6b260852-6b41-43a3-8fa4-39f0313b8326/mini.png)](https://insight.sensiolabs.com/projects/6b260852-6b41-43a3-8fa4-39f0313b8326)

This bundle was initially based on the Elasticsearch bundle by ONGR.io, however a different vision about how some things are handled, and some additional core requirements eventually turned it into a separate project.

## Key points

- Uses the official [elasticsearch-php](https://github.com/elastic/elasticsearch-php) client
- Integrates with Symfony 2.7+ and Symfony 3+
- Uses Doctrine-like entity declarations for Elasticsearch documents
- Supports multilanguage documents
- Supports searching in multiple types and indices
- Supports zero-time reindexing by utilizing read and write index aliases
- Supports data providers for synchronizing Elasticsearch indices with an external data source such as Doctrine

## Documentation

Installation instructions and documentation of the bundle can be found [here](Resources/doc/index.md).
 
## License

This bundle is licensed under the MIT license. Please, see the complete license in the [LICENSE](LICENSE) file.
