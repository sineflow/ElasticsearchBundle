# Mapping

The Elasticsearch bundle requires document mapping definitions to create the correct index schema and be able to convert data to objects and vice versa - think Doctrine. 

## Document class annotations

Elasticsearch type mappings are defined using annotations within document entity classes that implement DocumentInterface:
```php
<?php
namespace AppBundle\Document;

use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * @ES\Document(type="product")
 */
class Product extends AbstractDocument
{
    /**
     * @var string
     *
     * @ES\Property(name="title", type="string")
     */
    public $title;
}
```

> Make sure your document classes directly implement DocumentInterface or extend AbstractDocument.


#### Document annotation

The class representing a document must be annotated as `@ES\Document`. The following properties are supported inside that annotation:

- `type` Specifies the name of the Elasticsearch type this class represents. The parameter is optional and, if not supplied, the bundle will use the lowercased class name as such. 

- `repositoryClass` Allows you to specify a specific repository class for this document. If not specified, the default repository class is used.
```
repositoryClass="AppBundle\Document\Repository\ProductRepository"
```

- `parent` Allows you to specify a parent type ([more info here](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-parent-field.html))
```
parent="AppBundle:ParentDoc"
```
This should be used in conjunction with the <a href="#parentid">@ES\ParentId</a> meta annotation

- `options` Allows to specify any type option supported by Elasticsearch, such as [\_all](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-all-field.html), [dynamic_templates](https://www.elastic.co/guide/en/elasticsearch/reference/current/dynamic-templates.html), [dynamic_date_formats](https://www.elastic.co/guide/en/elasticsearch/reference/current/dynamic-field-mapping.html#date-detection), etc. 

### Property annotation

Each field within the document is specified using the `@ES\Property` annotation. The following properties are supported inside that annotation:

- `name` Specifies the name of the field (required).

- `type` Specifies the type of the field in Elasticsearch (required).

- `multilanguage` A flag that specifies whether the field will be multilanguage. For more information, see [declaring multilanguage properties](#mlproperties).
```
multilanguage=true
```

- `objectName` When the field type is `object` or `nested`, this property must be specified, as it specifies which class defines the (nested) object.
```
objectName="AppBundle:ObjAlias"
```

- `multiple` Relevant only for `object` and `nested` fields. It specifies whether the field contains a single object or multiple ones.
```
multiple=true
```

- `options` An array of literal options, sent to Elasticsearch as they are. The only exception is with multilanguage properties, where further processing is applied. 
```
options={
    "analyzer":"my_special_analyzer", 
    "null_value":0
}
```

### <a name=mlproperties></a>Multilanguage properties

Sometimes, you may have a field that is available in more than one language. This is declared like this:

```
    /**
     * @ES\Property(
     *  name="name",
     *  type="string",
     *  multilanguage=true,
     *  options={
     *      "analyzer":"{lang}_analyzer",
     *  }
     * )
     */
    public $name;
```
> Note the use of `{lang}` placeholder in the options.

When you have a property definition like that, there will not be a field `name` in your index, but instead there will be `name-en`, `name-fr`, `name-de`, etc. where the suffixes are taken from the available languages in your application. You may also use the special `{lang}` placeholder in the options array, as often you would need to specify different analyzers, depending on the language. For more information on how that works, see [multilanguage support](i18n.md).

### Meta property annotations

#### @ES\Id

If you need to have access to the `_id` property of an Elasticsearch document you need to have a class property with this annotation.
This way, you can specify the `_id` when you create or update a document and you will also have that value populated in your object when you retrieve an existing document.

```php
use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * @ES\Document(type="product")
 */
class Product
{
    /**
     * @var string
     *
     * @ES\Id
     */
    public $id;
}
```
> Such property is already defined in `AbstractDocument`, so you can just extend it.

#### @ES\Score

You should have a property with this annotation, if you wish the matching `_score` of the document to be populated in it when searching.

```php
use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * @ES\Document(type="product")
 */
class Product
{
    /**
     * @var float
     *
     * @ES\Score
     */
    public $score;
}
```
> Such property is already defined in `AbstractDocument`, so you can just extend it.

#### <a name=parentid></a>@ES\ParentId

When you need to have a parent-child relation between two types in your index, you'd need access to the `_parent` meta field of the document.
To do that, you will need a class property with that annotation that can be used for both setting and getting the `_parent` value.

```php
use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * @ES\Document(
 *     type="answers",
 *     parent="AppBundle:Question"
 * );
 */
class Answer
{
    /**
     * @var string
     *
     * @ES\ParentId
     */
    public $parent;
}
```
> Do not forget to also set the `parent` property of the `@ES\Document` annotation to specify the parent entity for that type. 

**WARNING**: If using Elasticsearch 1.x, the parent property value will NOT be populated when retrieving documents, as Elasticsearch does not return it by default! 

## DocObject class annotation

Object classes are almost the same as document classes:

```php
<?php
namespace AppBundle\Document;

use Sineflow\ElasticsearchBundle\Document\ObjectInterface;
use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * @ES\DocObject
 */
class ObjAlias implements ObjectInterface
{
    /**
     * @var string
     *
     * @ES\Property(name="title", type="string")
     */
    public $title;
}
```

The difference with document classes is that the class must implement `ObjectInterface` and be annotated as `@ES\DocObject`. The mapping of the object properties follows the same rules as the one for the document properties.


More info about mapping is in the [elasticsearch mapping documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping.html)
