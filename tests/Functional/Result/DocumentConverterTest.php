<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Result;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\Attributes\Depends;
use Sineflow\ElasticsearchBundle\Document\MLProperty;
use Sineflow\ElasticsearchBundle\Exception\DocumentConversionException;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\ObjCategory;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;

class DocumentConverterTest extends AbstractContainerAwareTestCase
{
    use ArraySubsetAsserts;

    private array $fullDocArray = [
        '_id'      => 'doc1',
        'title'    => 'Foo Product',
        'category' => [
            'title' => 'Bar',
            'tags'  => [],
        ],
        'related_categories' => [
            [
                'title' => 'Acme',
                'tags'  => [],
            ],
        ],
        'ml_info-en'  => 'info in English',
        'ml_info-fr'  => 'info in French',
        'ml_info'     => 'should be skipped',
        'nonexisting' => 'should be skipped',
    ];

    public function testAssignArrayToObjectWithNestedSingleValueInsteadOfArray(): void
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);
        $metadataCollector = $this->getContainer()->get(DocumentMetadataCollector::class);

        // Raw doc with a single object value where an array of objects is expected according to metadata def
        $rawDoc = [
            '_id'                => 'rawDocWithSingleObjValueInsteadOfArray',
            'related_categories' => [
                'id'    => '123',
                'title' => 'Acme',
            ],
        ];

        $product = new Product();
        $result = $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );
        $category = $result->relatedCategories->current();

        $this->assertSame(123, $category->id);
    }

    public function testAssignArrayToObjectWithNestedSingleValueArrayInsteadOfSingleValue(): void
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);
        $metadataCollector = $this->getContainer()->get(DocumentMetadataCollector::class);

        // Raw doc with array of single object where a single object value is expected according to metadata def
        $rawDoc = [
            '_id'      => 'rawDocWithArrayValueInsteadOfSingleObject',
            'category' => [
                [
                    'id'    => '123',
                    'title' => 'Acme',
                ],
            ],
        ];

        $product = new Product();
        $result = $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );

        $this->assertSame(123, $result->category->id);
    }

    public function testAssignArrayToObjectWithNestedMultiValueArrayInsteadOfSingleValue(): void
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);
        $metadataCollector = $this->getContainer()->get(DocumentMetadataCollector::class);

        // Raw doc with array of many objects where a single object value is expected according to metadata def
        $rawDoc = [
            '_id'      => 'rawDocWithArrayValueInsteadOfSingleObject',
            'category' => [
                [
                    'id'    => '123',
                    'title' => 'Acme',
                ],
                [
                    'id'    => '234',
                    'title' => 'Ucme',
                ],
            ],
        ];

        $product = new Product();

        $this->expectException(DocumentConversionException::class);
        $this->expectExceptionMessage('Multiple objects found for a single object field `category`');

        $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );
    }

    public function testAssignArrayToObjectWithAllFieldsCorrectlySet()
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);
        $metadataCollector = $this->getContainer()->get(DocumentMetadataCollector::class);

        $rawDoc = $this->fullDocArray;

        $product = new Product();
        $result = $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );

        $this->assertSame($product, $result);

        $this->assertSame('Foo Product', $product->title);
        $this->assertSame('doc1', $product->id);
        $this->assertInstanceOf(ObjCategory::class, $product->category);
        $this->assertContainsOnlyInstancesOf(ObjCategory::class, $product->relatedCategories);
        $this->assertInstanceOf(MLProperty::class, $product->mlInfo);
        $this->assertSame('info in English', $product->mlInfo->getValue('en'));
        $this->assertSame('info in French', $product->mlInfo->getValue('fr'));

        return $product;
    }

    public function testAssignArrayToObjectWithEmptyFields(): void
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);
        $metadataCollector = $this->getContainer()->get(DocumentMetadataCollector::class);

        $rawDoc = [];

        $product = new Product();
        $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata(Product::class)->getPropertiesMetadata()
        );
        $this->assertNull($product->title);
        $this->assertNull($product->category);
        $this->assertSame([], $product->relatedCategories);
        $this->assertNull($product->mlInfo);
    }

    public function testAssignArrayToObjectWithEmptyMultipleNestedField(): void
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);
        $metadataCollector = $this->getContainer()->get(DocumentMetadataCollector::class);

        $rawDoc = [
            'related_categories' => [],
        ];

        $product = new Product();
        $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata(Product::class)->getPropertiesMetadata()
        );
        $this->assertInstanceOf(ObjectIterator::class, $product->relatedCategories);
        $this->assertSame(0, $product->relatedCategories->count());
    }

    #[Depends('testAssignArrayToObjectWithAllFieldsCorrectlySet')]
    public function testConvertToArray(Product $product): void
    {
        $converter = $this->getContainer()->get(DocumentConverter::class);

        $arr = $converter->convertToArray($product);

        $this->assertGreaterThanOrEqual(6, \count($arr));
        $this->assertArraySubset($arr, $this->fullDocArray);
    }

    public function testConvertToDocumentWithSource(): void
    {
        $rawFromEs = [
            '_index'   => 'sineflow-esb-test-bar',
            '_id'      => 'doc1',
            '_version' => 1,
            'found'    => true,
            '_source'  => [
                'title'    => 'Foo Product',
                'category' => [
                    'title' => 'Bar',
                ],
                'related_categories' => [
                    0 => [
                        'title' => 'Acme',
                    ],
                ],
                'ml_info-en' => 'info in English',
                'ml_info-fr' => 'info in French',
            ],
        ];

        $converter = $this->getContainer()->get(DocumentConverter::class);

        /** @var Product $product */
        $product = $converter->convertToDocument($rawFromEs, 'AcmeBarBundle:Product');

        $this->assertSame('Foo Product', $product->title);
        $this->assertSame('doc1', $product->id);
        $this->assertInstanceOf(ObjCategory::class, $product->category);
        $this->assertContainsOnlyInstancesOf(ObjCategory::class, $product->relatedCategories);
        $this->assertInstanceOf(MLProperty::class, $product->mlInfo);
        $this->assertSame('info in English', $product->mlInfo->getValue('en'));
        $this->assertSame('info in French', $product->mlInfo->getValue('fr'));
    }

    public function testConvertToDocumentWithFields(): void
    {
        $rawFromEs = [
            '_index' => 'sineflow-esb-test-bar',
            '_id'    => 'doc1',
            '_score' => 1,
            'fields' => [
                'title' => [
                    0 => 'Foo Product',
                ],
                'related_categories.title' => [
                    0 => 'Acme',
                    1 => 'Bar',
                ],
                'category.title' => [
                    0 => 'Bar',
                ],
                'ml_info-en' => [
                    0 => 'info in English',
                ],
            ],
        ];

        /** @var DocumentConverter $converter */
        $converter = $this->getContainer()->get(DocumentConverter::class);

        /** @var Product $product */
        $product = $converter->convertToDocument($rawFromEs, 'AcmeBarBundle:Product');

        $this->assertSame('Foo Product', $product->title);
        $this->assertSame('doc1', $product->id);
        $this->assertInstanceOf(MLProperty::class, $product->mlInfo);
        $this->assertSame('info in English', $product->mlInfo->getValue('en'));
    }
}
