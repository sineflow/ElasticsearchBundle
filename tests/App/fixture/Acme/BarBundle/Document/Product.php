<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Document\MLProperty;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Repository\ProductRepository;

/**
 * Product document for testing.
 */
#[SFES\Document(
    repositoryClass: ProductRepository::class,
    options: [
        'dynamic' => 'strict',
    ],
)]
class Product extends AbstractDocument
{
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

    #[SFES\Property(
        name: 'description',
        type: 'text',
    )]
    public ?string $description = null;

    #[SFES\Property(
        name: 'category',
        type: 'object',
        objectName: ObjCategory::class,
    )]
    public ?ObjCategory $category = null;

    /**
     * @var ObjCategory[]|ObjectIterator<ObjCategory>
     */
    #[SFES\Property(
        name: 'related_categories',
        type: 'object',
        objectName: ObjCategory::class,
        multiple: true,
    )]
    public array|ObjectIterator $relatedCategories = [];

    #[SFES\Property(
        name: 'price',
        type: 'float',
    )]
    public ?int $price = null;

    #[SFES\Property(
        name: 'location',
        type: 'geo_point',
    )]
    public ?string $location = null;

    #[SFES\Property(
        name: 'limited',
        type: 'boolean',
    )]
    public ?string $limited = null;

    #[SFES\Property(
        name: 'released',
        type: 'date',
    )]
    public ?string $released = null;

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
