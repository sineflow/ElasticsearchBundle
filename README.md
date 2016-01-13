# Sineflow Elasticsearch Bundle for Symfony

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