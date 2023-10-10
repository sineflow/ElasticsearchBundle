<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Document\MLProperty;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;

/**
 * Product document for testing.
 *
 * @ES\Document(
 *  repositoryClass="Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Repository\ProductRepository",
 *  options={
 *      "dynamic":"strict",
 *  }
 * )
 */
class Product extends AbstractDocument
{
    /**
     * @var string
     *
     * @ES\Property(
     *  type="text",
     *  name="title",
     *  options={
     *    "fields"={
     *        "raw"={"type"="keyword"},
     *        "title"={"type"="text"}
     *    }
     *  }
     * )
     */
    public $title;

    /**
     * @var string
     *
     * @ES\Property(type="text", name="description")
     */
    public $description;

    /**
     * @var ObjCategory
     *
     * @ES\Property(type="object", name="category", objectName="AcmeBarBundle:ObjCategory")
     */
    public $category;

    /**
     * @var ObjCategory[]|ObjectIterator<ObjCategory>
     *
     * @ES\Property(type="object", name="related_categories", multiple=true, objectName="AcmeBarBundle:ObjCategory")
     */
    public $relatedCategories;

    /**
     * @var int
     *
     * @ES\Property(type="float", name="price")
     */
    public $price;

    /**
     * @var string
     *
     * @ES\Property(type="geo_point", name="location")
     */
    public $location;

    /**
     * @var string
     *
     * @ES\Property(type="boolean", name="limited")
     */
    public $limited;

    /**
     * @var string
     *
     * @ES\Property(type="date", name="released")
     */
    public $released;

    /**
     * @var MLProperty
     *
     * @ES\Property(
     *  name="ml_info",
     *  type="text",
     *  multilanguage=true,
     *  options={
     *      "analyzer":"{lang}_analyzer",
     *      "fields": {
     *          "ngram": {
     *              "type": "text",
     *              "analyzer":"{lang}_analyzer"
     *          }
     *      }
     *  }
     * )
     */
    public $mlInfo;

    /**
     * @var MLProperty
     *
     * @ES\Property(
     *  name="ml_more_info",
     *  type="text",
     *  multilanguage=true,
     *  multilanguageDefaultOptions={
     *     "type":"text",
     *     "index":false,
     *  }
     * )
     */
    public $mlMoreInfo;

    /**
     * @var int
     *
     * @ES\Property(
     *     type="text",
     *     name="pieces_count",
     *     options={
     *        "fields"={
     *          "count"={"type"="token_count", "analyzer"="whitespace"}
     *        }
     *     }
     * )
     */
    public $tokenPiecesCount;
}
