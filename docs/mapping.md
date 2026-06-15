# Mapping

The Elasticsearch bundle requires document mapping definitions to create the correct index schema and be able to convert data to objects and vice versa - think Doctrine.

## Document class attributes

Elasticsearch index mappings are defined using attributes within document entity classes that implement DocumentInterface:
```php
<?php
namespace App\Document;

use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Attribute as ES;

#[ES\Document]
class Product extends AbstractDocument
{
    #[ES\Property(
        name: 'title',
        type: 'text',
    )]
    public ?string $title = null;
}
```

> Make sure your document classes directly implement `DocumentInterface` or extend `AbstractDocument`.


### Document attribute

The class representing a document must have the `#[ES\Document]` attribute. The following properties are supported inside that attribute:

- `repositoryClass` Allows you to specify a specific repository class for this document. If not specified, the default repository class is used.
```
repositoryClass: App\Document\Repository\ProductRepository
```

- `providerClass` Allows you to specify a specific data provider that will be used as data source when rebuilding the index. If not specified, the default self-provider is used, i.e the index is rebuilt from itself.
```
providerClass: App\Document\Provider\ProductProvider
```

- `options` Allows to specify any type option supported by Elasticsearch, such as [\_all](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-all-field.html), [dynamic_templates](https://www.elastic.co/guide/en/elasticsearch/reference/current/dynamic-templates.html), [dynamic_date_formats](https://www.elastic.co/guide/en/elasticsearch/reference/current/dynamic-field-mapping.html#date-detection), etc.

### Property attribute

Each field within the document is specified using the `#[ES\Property]` attribute. The following properties are supported inside that attribute:

- `name` Specifies the name of the field (required).

- `multilanguage` A flag that specifies whether the field will be multilanguage. For more information, see [declaring multilanguage properties](#mlproperties).
```
multilanguage: true
```

- `objectName` When the field type is `object` or `nested`, this property must be specified, as it specifies which class defines the (nested) object.
```
objectName: App\Document\ObjAlias
```

- `multiple` Relevant only for `object` and `nested` fields. It specifies whether the field contains a single object or multiple ones.
```
multiple: true
```

- `options` An array of literal options, sent to Elasticsearch as they are. The only exception is with multilanguage properties, where further processing is applied.
```
options: [
    'analyzer': 'my_special_analyzer',
    'fields' => [
        'raw'   => ['type' => 'keyword'],
        'title' => ['type' => 'text'],
    ],
],
```

### <a name=mlproperties></a>Multilanguage properties

Sometimes, you may have a field that is available in more than one language. This is declared like this:

```
#[ES\Property(
    name: 'name',
    type: 'text',
    multilanguage: true,
    multilanguageDefaultOptions: [
        'type' => 'text',
        'index' => false,
    ],
    options: [
        'analyzer' => '{lang}_analyzer',
    ],
)]
public ?MLProperty $name = null;
```
> Note the use of `{lang}` placeholder in the options.

When you have a property definition like that, there will not be a field `name` in your index, but instead there will be `name-en`, `name-fr`, `name-de`, etc. where the suffixes are taken from the available languages in your application.
There will also be a field `name-default`, whose default mapping of `type:keyword;ignore_above:256` you can optionally override by specifying alternative `multilanguageDefaultOptions`.

You may also use the special `{lang}` placeholder in the options array, as often you would need to specify different analyzers, depending on the language. For more information on how that works, see [multilanguage support](i18n.md).

### Meta property attributes

#### Id

If you need to have access to the `_id` property of an Elasticsearch document you need to have a class property with this attribute.
This way, you can specify the `_id` when you create or update a document and you will also have that value populated in your object when you retrieve an existing document.

```php
use Sineflow\ElasticsearchBundle\attribute as ES;

#[ES\Document]
class Product
{
    #[ES\Id]
    public ?string $id = null;
}
```
> Such property is already defined in `AbstractDocument`, so you can just extend it.

#### Score

You should have a property with this attribute, if you wish the matching `_score` of the document to be populated in it when searching.

```php
use Sineflow\ElasticsearchBundle\Attribute as ES;

#[ES\Document]
class Product
{
    #[ES\Score]
    public ?float $score = null;
}
```
> Such property is already defined in `AbstractDocument`, so you can just extend it.

## DocObject class attribute

Object classes are almost the same as document classes:

```php
<?php
namespace App\Document;

use Sineflow\ElasticsearchBundle\Document\ObjectInterface;
use Sineflow\ElasticsearchBundle\Attribute as ES;

#[ES\DocObject]
class ObjAlias implements ObjectInterface
{
    #[ES\Property(
        name: 'title',
        type: 'text',
    )]
    public ?string $title = null;
}
```

The difference with document classes is that the class must implement `ObjectInterface` and have a `DocObject` attribute. The mapping of the object properties follows the same rules as the one for the document properties.


More info about mapping is in the [elasticsearch mapping documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping.html)
