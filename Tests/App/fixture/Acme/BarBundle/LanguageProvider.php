<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle;

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
