<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

/**
 * Finds ES document classes in bundles.
 */
class DocumentLocator
{
    /**
     * @var array All locations of Elasticsearch entities available in the application
     */
    private $entityLocations = [];

    /**
     * @param array $entityLocations Parameter sfes.entity_locations
     */
    public function __construct(array $entityLocations)
    {
        $this->entityLocations = $entityLocations;
    }

    /**
     * Returns list of existing directories within all application bundles that are possible locations for ES documents
     */
    public function getAllDocumentDirs(): array
    {
        return \array_column($this->entityLocations, 'directory');
    }

    /**
     * Returns the document class name from short syntax
     * or the class name as it is, if it is already fully qualified
     *
     * @param string $fullClassName FQN of an entity class or a short syntax (e.g App:Product)
     *
     * @return string Fully qualified class name
     *
     * @throws \UnexpectedValueException
     */
    public function resolveClassName(string $fullClassName): string
    {
        if (\str_contains($fullClassName, ':')) {
            // Resolve short syntax into an FQN
            [$locationAlias, $className] = \explode(':', $fullClassName);
            if (!\array_key_exists($locationAlias, $this->entityLocations)) {
                throw new \UnexpectedValueException(\sprintf('Location "%s" does not exist.', $locationAlias));
            }
            $fullClassName = \rtrim($this->entityLocations[$locationAlias]['namespace'], '\\').'\\'.$className;
        }

        return $fullClassName;
    }
}
