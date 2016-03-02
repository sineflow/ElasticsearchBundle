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

    /**
     * @param array $values
     */
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
     * Gets value based on passed language, if value is missing returns default value
     *
     * @param string $language
     *
     * @return null|string
     */
    public function getValue($language)
    {
        if (isset($this->values[$language])) {
            return $this->values[$language];
        } elseif (isset($this->values[Property::DEFAULT_LANG_SUFFIX])) {
            return $this->values[Property::DEFAULT_LANG_SUFFIX];
        } else {
            return null;
        }
    }

    /**
     * @return string[]
     */
    public function getValues()
    {
        return $this->values;
    }
}
