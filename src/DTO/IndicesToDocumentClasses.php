<?php

namespace Sineflow\ElasticsearchBundle\DTO;

/**
 * Class to be used as a data transport structure of mappings between physical indices to document class names
 */
class IndicesToDocumentClasses
{
    /**
     * <physical_index_name|*> => <document_class>
     */
    private array $documentClasses = [];

    /**
     * Set the document class for the physical index name
     *
     * @param string|null $index         The name of the physical index in Elasticsearch.
     *                                   `null` to be passed if there's only one index and we don't need the actual index name
     * @param string      $documentClass The document class in short notation
     */
    public function set(?string $index, string $documentClass): void
    {
        if (!$index) {
            if (!empty($this->documentClasses)) {
                throw new \InvalidArgumentException(\sprintf('Cannot set document class without index, as there are already classes set for concrete indices: [%s]', \implode(',', \array_keys($this->documentClasses))));
            }
            $this->documentClasses['*'] = $documentClass;
        } else {
            if (isset($this->documentClasses['*'])) {
                throw new \InvalidArgumentException(\sprintf('Cannot set document class for index [%s], as there is already a class set for any index: [%s]', $documentClass, $this->documentClasses['*']));
            }
            $this->documentClasses[$index] = $documentClass;
        }
    }

    /**
     * Get the document class for the physical index name
     *
     * @param string $index The name of the physical index in Elasticsearch
     */
    public function get(string $index): string
    {
        if (isset($this->documentClasses[$index])) {
            return $this->documentClasses[$index];
        }

        if (isset($this->documentClasses['*'])) {
            return $this->documentClasses['*'];
        }

        throw new \InvalidArgumentException(\sprintf('Document class for index [%s] is not set', $index));
    }
}
