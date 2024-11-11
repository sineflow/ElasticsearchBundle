<?php

namespace Sineflow\ElasticsearchBundle\Annotation;

use Sineflow\ElasticsearchBundle\Mapping\DumperInterface;

/**
 * Annotation used for all properties of the document, apart from the special ones (like _id and _score)
 *
 * @Annotation
 *
 * @Target("PROPERTY")
 */
final class Property implements PropertyAnnotationInterface, DumperInterface
{
    public const LANGUAGE_PLACEHOLDER = '{lang}';

    public const DEFAULT_LANG_SUFFIX = 'default';

    /**
     * @Required
     */
    public string $name;

    /**
     * @Required
     */
    public string $type;

    public ?string $enumType = null;

    public bool $multilanguage = false;

    /**
     * Override mapping for the 'default' language field of multilanguage properties
     */
    public array $multilanguageDefaultOptions = [];

    /**
     * The object name must be defined, if type is 'object' or 'nested'
     */
    public ?string $objectName = null;

    /**
     * Defines if related object will have one or multiple values.
     * If this value is set to true, ObjectIterator will be provided in the result, as opposed to an ObjectInterface object
     */
    public bool $multiple = false;

    /**
     * Settings directly passed to Elasticsearch client as-is
     */
    public array $options = [];

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Dumps property fields as array for index mapping
     */
    public function dump(array $settings = []): array
    {
        $result = $this->options;

        // Although it is completely valid syntax to explicitly define objects as such in the mapping definition, ES does not do that by default.
        // So, in order to ensure that the mapping for index creation would exactly match the mapping returned from the ES _mapping endpoint, we don't explicitly set 'object' data types
        if ('object' !== $this->type) {
            $result = \array_merge($result, ['type' => $this->type]);
        }

        if (isset($settings['language'])) {
            if (!isset($settings['indexAnalyzers'])) {
                throw new \InvalidArgumentException('Available index analyzers missing');
            }

            // Recursively replace {lang} in any string option with the respective language
            \array_walk_recursive($result, static function (&$value, $key, $settings): void {
                if (\is_string($value) && \str_contains($value, self::LANGUAGE_PLACEHOLDER)) {
                    if (\in_array($key, ['analyzer', 'index_analyzer', 'search_analyzer'])) {
                        // Replace {lang} in any analyzers with the respective language
                        // If no analyzer is defined for a certain language, replace {lang} with 'default'

                        // Get the names of all available analyzers in the index
                        $indexAnalyzers = \array_keys($settings['indexAnalyzers']);

                        // Make sure a default analyzer is defined, even if we don't need it right now
                        // because, if a new language is added and we don't have an analyzer for it, ES mapping would fail
                        $defaultAnalyzer = \str_replace(self::LANGUAGE_PLACEHOLDER, self::DEFAULT_LANG_SUFFIX, $value);
                        if (!\in_array($defaultAnalyzer, $indexAnalyzers)) {
                            throw new \LogicException(\sprintf('There must be a default language analyzer "%s" defined for index', $defaultAnalyzer));
                        }

                        $value = \str_replace(self::LANGUAGE_PLACEHOLDER, $settings['language'], $value);
                        if (!\in_array($value, $indexAnalyzers)) {
                            $value = $defaultAnalyzer;
                        }
                    } else {
                        // If it's any other option, just replace with the respective language
                        $value = \str_replace(self::LANGUAGE_PLACEHOLDER, $settings['language'], $value);
                    }
                }
            }, $settings);
        }

        return $result;
    }
}
