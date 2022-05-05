<?php

namespace Sineflow\ElasticsearchBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation\Property;

/**
 * Class MLProperty
 *
 * Represents a multi-language property within a document entity
 */
class MLProperty
{
    /**
     * @var string[]
     */
    private $values = [];

    public function __construct(array $values = [])
    {
        foreach ($values as $language => $value) {
            $this->setValue($value, $language);
        }
    }

    /**
     * Set value of property in given language
     *
     * @param string $value
     * @param string $language
     */
    public function setValue($value, $language)
    {
        $this->values[$language] = $value;
    }

    /**
     * Gets value based on passed language, falling back on the default language, by default
     *
     * @param string $language
     * @param bool   $fallbackToDefault If set and value for the requested language is missing, return default language value
     *
     * @return string|null
     */
    public function getValue($language, $fallbackToDefault = true)
    {
        if (isset($this->values[$language])) {
            return $this->values[$language];
        } elseif ($fallbackToDefault && isset($this->values[Property::DEFAULT_LANG_SUFFIX])) {
            return $this->values[Property::DEFAULT_LANG_SUFFIX];
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getValues()
    {
        return $this->values;
    }
}
