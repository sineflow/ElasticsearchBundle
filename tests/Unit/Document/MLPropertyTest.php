<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Document\MLProperty;

/**
 * Class MLPropertyTest
 */
class MLPropertyTest extends TestCase
{
    /**
     * Tests if value is set and returned correctly
     */
    public function testGetSetValue(): void
    {
        $mlProperty = new MLProperty();
        $mlProperty->setValue('test en', 'en');

        $this->assertNull(
            $mlProperty->getValue('bg'),
            'MLProperty does not return null if required and default language is missing.'
        );

        $mlProperty->setValue('test default', 'default');

        $this->assertSame(
            'test en',
            $mlProperty->getValue('en'),
            'MLProperty does not return required language correctly.'
        );

        $this->assertSame(
            'test default',
            $mlProperty->getValue('default'),
            'MLProperty does not return default language correctly.'
        );

        $this->assertSame(
            'test default',
            $mlProperty->getValue('bg'),
            'MLProperty does not return default language if required language is missing.'
        );
    }

    /**
     * Tests if returns all values
     */
    public function testGetValues(): void
    {
        $mlProperty = new MLProperty();
        $mlProperty->setValue('test default', 'default');
        $mlProperty->setValue('test en', 'en');
        $mlProperty->setValue('test bg', 'bg');

        $this->assertSame(
            [
                'default' => 'test default',
                'en'      => 'test en',
                'bg'      => 'test bg',
            ],
            $mlProperty->getValues(),
            'MLProperty does not return all values correctly.'
        );
    }

    /**
     * Tests if construct set all values properly
     */
    public function testConstruct(): void
    {
        $mlProperty = new MLProperty([
            'default' => 'test default',
            'en'      => 'test en',
            'bg'      => 'test bg',
        ]);

        $this->assertSame(
            [
                'default' => 'test default',
                'en'      => 'test en',
                'bg'      => 'test bg',
            ],
            $mlProperty->getValues(),
            'MLProperty construct does not set all values correctly.'
        );
    }
}
