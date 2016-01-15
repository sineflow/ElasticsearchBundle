<?php

namespace Sineflow\ElasticsearchBundle\Document;

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
     * @param string $language
     * @return null|string
     */
    public function getValue($language)
    {
        return isset($this->values[$language]) ? $this->values[$language] : null;
    }

    /**
     * @return string[]
     */
    public function getValues()
    {
        return $this->values;
    }
}
