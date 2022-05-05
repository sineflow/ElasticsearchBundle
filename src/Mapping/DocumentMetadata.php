<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Holds document metadata.
 */
class DocumentMetadata
{
    public const PROPERTY_ACCESS_PUBLIC = 1;
    public const PROPERTY_ACCESS_PRIVATE = 2;

    /**
     * @var array
     */
    private $metadata;

    /**
     * Resolves metadata.
     *
     * @param array $metadata
     */
    public function __construct(array $metadata)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->metadata = $resolver->resolve($metadata);
    }

    /**
     * Configures options resolver.
     *
     * @param OptionsResolver $optionsResolver
     */
    protected function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setRequired(['properties', 'fields', 'propertiesMetadata', 'repositoryClass', 'providerClass', 'className']);
    }

    /**
     * Retrieves index mapping for the Elasticsearch client
     *
     * @return array
     */
    public function getClientMapping(): array
    {
        $mapping = array_filter(
            array_merge(
                ['properties' => $this->getProperties()],
                $this->getFields()
            ),
            function ($value) {
                // Remove all empty non-boolean values from the mapping array
                return (bool) $value || is_bool($value);
            }
        );

        return $mapping;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->metadata['properties'];
    }

    /**
     * @return array
     */
    public function getPropertiesMetadata(): array
    {
        return $this->metadata['propertiesMetadata'];
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->metadata['fields'];
    }

    /**
     * @return string|null
     */
    public function getRepositoryClass(): ?string
    {
        return $this->metadata['repositoryClass'];
    }

    /**
     * @return string|null
     */
    public function getProviderClass(): ?string
    {
        return $this->metadata['providerClass'];
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->metadata['className'];
    }
}
