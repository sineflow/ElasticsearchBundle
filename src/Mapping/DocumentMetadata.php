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

    private array $metadata;

    /**
     * Resolves metadata.
     */
    public function __construct(array $metadata)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->metadata = $resolver->resolve($metadata);
    }

    /**
     * Configures options resolver.
     */
    protected function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['properties', 'fields', 'propertiesMetadata', 'repositoryClass', 'providerClass', 'className']);
    }

    /**
     * Retrieves index mapping for the Elasticsearch client
     */
    public function getClientMapping(): array
    {
        $mapping = \array_filter(
            \array_merge(
                ['properties' => $this->getProperties()],
                $this->getFields()
            ),
            static fn ($value): bool => // Remove all empty non-boolean values from the mapping array
                (bool) $value || \is_bool($value)
        );

        return $mapping;
    }

    public function getProperties(): array
    {
        return $this->metadata['properties'];
    }

    public function getPropertiesMetadata(): array
    {
        return $this->metadata['propertiesMetadata'];
    }

    public function getFields(): array
    {
        return $this->metadata['fields'];
    }

    public function getRepositoryClass(): ?string
    {
        return $this->metadata['repositoryClass'];
    }

    public function getProviderClass(): ?string
    {
        return $this->metadata['providerClass'];
    }

    public function getClassName(): string
    {
        return $this->metadata['className'];
    }
}
