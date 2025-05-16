<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Document\MLProperty;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Repository\ProductRepository;

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
#[SFES\Document(
    repositoryClass: ProductRepository::class,
    options: [
        'dynamic' => 'strict',
    ],
)]
class Product extends AbstractDocument
{
    /**
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
    #[SFES\Property(
        name: 'title',
        type: 'text',
        options: [
            'fields' => [
                'raw'   => ['type' => 'keyword'],
                'title' => ['type' => 'text'],
            ],
        ],
    )]
    public ?string $title = null;

    /**
     * @ES\Property(type="text", name="description")
     */
    #[SFES\Property(
        name: 'description',
        type: 'text',
    )]
    public ?string $description = null;

    /**
     * @ES\Property(type="object", name="category", objectName="AcmeBarBundle:ObjCategory")
     */
    #[SFES\Property(
        name: 'category',
        type: 'object',
        objectName: ObjCategory::class,
    )]
    public ?ObjCategory $category = null;

    /**
     * @var ObjCategory[]|ObjectIterator<ObjCategory>
     *
     * @ES\Property(type="object", name="related_categories", multiple=true, objectName="AcmeBarBundle:ObjCategory")
     */
    #[SFES\Property(
        name: 'related_categories',
        type: 'object',
        objectName: ObjCategory::class,
        multiple: true,
    )]
    public array|ObjectIterator $relatedCategories = [];

    /**
     * @ES\Property(type="float", name="price")
     */
    #[SFES\Property(
        name: 'price',
        type: 'float',
    )]
    public ?int $price = null;

    /**
     * @ES\Property(type="geo_point", name="location")
     */
    #[SFES\Property(
        name: 'location',
        type: 'geo_point',
    )]
    public ?string $location = null;

    /**
     * @ES\Property(type="boolean", name="limited")
     */
    #[SFES\Property(
        name: 'limited',
        type: 'boolean',
    )]
    public ?string $limited = null;

    /**
     * @ES\Property(type="date", name="released")
     */
    #[SFES\Property(
        name: 'released',
        type: 'date',
    )]
    public ?string $released = null;

    /**
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
    #[SFES\Property(
        name: 'ml_info',
        type: 'text',
        multilanguage: true,
        options: [
            'analyzer' => '{lang}_analyzer',
            'fields'   => [
                'ngram' => [
                    'type'     => 'text',
                    'analyzer' => '{lang}_analyzer',
                ],
            ],
        ],
    )]
    public ?MLProperty $mlInfo = null;

    /**
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
    #[SFES\Property(
        name: 'ml_more_info',
        type: 'text',
        multilanguage: true,
        multilanguageDefaultOptions: [
            'type'  => 'text',
            'index' => false,
        ],
    )]
    public ?MLProperty $mlMoreInfo = null;

    /**
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
    #[SFES\Property(
        name: 'pieces_count',
        type: 'text',
        options: [
            'fields' => [
                'count' => [
                    'type'     => 'token_count',
                    'analyzer' => 'whitespace',
                ],
            ],
        ],
    )]
    public ?int $tokenPiecesCount = null;
}
