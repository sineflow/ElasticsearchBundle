<?php

namespace Sineflow\ElasticsearchBundle\LanguageProvider;

/**
 * Defines the interface that language providers must implement
 * A language provider supplies all available languages in the application
 */
interface LanguageProviderInterface
{
    /**
     * Returns array of available language codes
     *
     * @return array
     */
    public function getLanguages();
}
