<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Result;

use Sineflow\ElasticsearchBundle\Document\MLProperty;
use Sineflow\ElasticsearchBundle\Exception\DocumentConversionException;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\ObjCategory;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;

class DocumentConverterTest extends AbstractContainerAwareTestCase
{
    private $fullDocArray = [
        '_id' => 'doc1',
        'title' => 'Foo Product',
        'category' => [
            'title' => 'Bar',
        ],
        'related_categories' => [
            [
                'title' => 'Acme',
            ],
        ],
        'ml_info-en' => 'info in English',
        'ml_info-fr' => 'info in French',
        'ml_info' => 'should be skipped',
        'nonexisting' => 'should be skipped',
    ];

    public function testAssignArrayToObjectWithNestedSingleValueInsteadOfArray()
    {
        $converter = $this->getContainer()->get('sfes.document_converter');
        $metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');

        // Raw doc with a single object value where an array of objects is expected according to metadata def
        $rawDoc = [
            '_id' => 'rawDocWithSingleObjValueInsteadOfArray',
            'related_categories' => [
                'id' => '123',
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

        $this->assertEquals($category->id, 123);
    }

    public function testAssignArrayToObjectWithNestedSingleValueArrayInsteadOfSingleValue()
    {
        $converter = $this->getContainer()->get('sfes.document_converter');
        $metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');

        // Raw doc with array of single object where a single object value is expected according to metadata def
        $rawDoc = [
            '_id' => 'rawDocWithArrayValueInsteadOfSingleObject',
            'category' => [
                [
                    'id' => '123',
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

        $this->assertEquals($result->category->id, 123);
    }

    public function testAssignArrayToObjectWithNestedMultiValueArrayInsteadOfSingleValue()
    {
        $converter = $this->getContainer()->get('sfes.document_converter');
        $metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');

        // Raw doc with array of many objects where a single object value is expected according to metadata def
        $rawDoc = [
            '_id' => 'rawDocWithArrayValueInsteadOfSingleObject',
            'category' => [
                [
                    'id' => '123',
                    'title' => 'Acme',
                ],
                [
                    'id' => '234',
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
        $converter = $this->getContainer()->get('sfes.document_converter');
        $metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');

        $rawDoc = $this->fullDocArray;

        $product = new Product();
        $result = $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );

        $this->assertSame($product, $result);

        $this->assertEquals('Foo Product', $product->title);
        $this->assertEquals('doc1', $product->id);
        $this->assertInstanceOf(ObjCategory::class, $product->category);
        $this->assertContainsOnlyInstancesOf(ObjCategory::class, $product->relatedCategories);
        $this->assertInstanceOf(MLProperty::class, $product->mlInfo);
        $this->assertEquals('info in English', $product->mlInfo->getValue('en'));
        $this->assertEquals('info in French', $product->mlInfo->getValue('fr'));

        return $product;
    }

    public function testAssignArrayToObjectWithEmptyFields()
    {
        $converter = $this->getContainer()->get('sfes.document_converter');
        $metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');

        $rawDoc = [];

        $product = new Product();
        $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );
        $this->assertNull($product->title);
        $this->assertNull($product->category);
        $this->assertNull($product->relatedCategories);
        $this->assertNull($product->mlInfo);
    }

    public function testAssignArrayToObjectWithEmptyMultipleNestedField()
    {
        $converter = $this->getContainer()->get('sfes.document_converter');
        $metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');

        $rawDoc = [
            'related_categories' => [],
        ];

        $product = new Product();
        $converter->assignArrayToObject(
            $rawDoc,
            $product,
            $metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')->getPropertiesMetadata()
        );
        $this->assertInstanceOf(ObjectIterator::class, $product->relatedCategories);
        $this->assertSame(0, $product->relatedCategories->count());
    }

    /**
     * @depends testAssignArrayToObjectWithAllFieldsCorrectlySet
     */
    public function testConvertToArray(Product $product)
    {
        $converter = $this->getContainer()->get('sfes.document_converter');

        $arr = $converter->convertToArray($product);

        $this->assertGreaterThanOrEqual(6, count($arr));
        $this->assertArraySubset($arr, $this->fullDocArray);
    }

    public function testConvertToDocumentWithSource()
    {
        $rawFromEs = array (
            '_index' => 'sineflow-esb-test-bar',
            '_type' => 'product',
            '_id' => 'doc1',
            '_version' => 1,
            'found' => true,
            '_source' =>
                [
                    'title' => 'Foo Product',
                    'category' =>
                        [
                            'title' => 'Bar',
                        ],
                        'related_categories' =>
                        [
                        0 =>
                            [
                                'title' => 'Acme',
                            ],
                        ],
                        'ml_info-en' => 'info in English',
                        'ml_info-fr' => 'info in French',
                ],
        );

        $converter = $this->getContainer()->get('sfes.document_converter');

        /** @var Product $product */
        $product = $converter->convertToDocument($rawFromEs, 'AcmeBarBundle:Product');

        $this->assertEquals('Foo Product', $product->title);
        $this->assertEquals('doc1', $product->id);
        $this->assertInstanceOf(ObjCategory::class, $product->category);
        $this->assertContainsOnlyInstancesOf(ObjCategory::class, $product->relatedCategories);
        $this->assertInstanceOf(MLProperty::class, $product->mlInfo);
        $this->assertEquals('info in English', $product->mlInfo->getValue('en'));
        $this->assertEquals('info in French', $product->mlInfo->getValue('fr'));
    }

    public function testConvertToDocumentWithFields()
    {
        $rawFromEs = [
            '_index' => 'sineflow-esb-test-bar',
            '_type' => 'product',
            '_id' => 'doc1',
            '_score' => 1,
            'fields' =>
                [
                    'title' =>
                        [
                            0 => 'Foo Product',
                        ],
                        'related_categories.title' =>
                        [
                            0 => 'Acme',
                            1 => 'Bar',
                        ],
                        'category.title' =>
                        [
                            0 => 'Bar',
                        ],
                        'ml_info-en' =>
                        [
                            0 => 'info in English',
                        ],
                ],
        ];

        $converter = $this->getContainer()->get('sfes.document_converter');

        /** @var Product $product */
        $product = $converter->convertToDocument($rawFromEs, 'AcmeBarBundle:Product');

        $this->assertEquals('Foo Product', $product->title);
        $this->assertEquals('doc1', $product->id);
        $this->assertInstanceOf(MLProperty::class, $product->mlInfo);
        $this->assertEquals('info in English', $product->mlInfo->getValue('en'));
    }
}
