<?php

namespace Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle;

use Sineflow\ElasticsearchBundle\LanguageProvider\LanguageProviderInterface;

/**
 * Class LanguageProvider
 */
class LanguageProvider implements LanguageProviderInterface
{
    /**
     * @return array
     */
    public function getLanguages()
    {
        return ['en', 'fr'];
    }
}
