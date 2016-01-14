<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

/**
 * Finds ES document classes in bundles.
 */
class DocumentLocator
{
    /**
     * @var array All bundles available in the application
     */
    private $bundles = [];

    /**
     * @var array All bundle names indexed by their namespaces
     */
    private $bundleNamesByNamespace = [];

    /**
     * @var string Directory in bundle to load documents from.
     */
    private $documentDir = 'Document';

    /**
     * @param array $bundles Parameter kernel.bundles from service container.
     */
    public function __construct(array $bundles)
    {
        $this->bundles = $bundles;

        foreach ($bundles as $bundleName => $bundleClass) {
            $this->bundleNamesByNamespace[substr($bundleClass, 0, strrpos($bundleClass, '\\'))] = $bundleName;
        }
    }

    /**
     * Document directory in bundle to load documents from.
     *
     * @param string $documentDir
     */
    public function setDocumentDir($documentDir)
    {
        $this->documentDir = $documentDir;
    }

    /**
     * Returns directory name in which documents should be put.
     *
     * @return string
     */
    public function getDocumentDir()
    {
        return $this->documentDir;
    }

    /**
     * Returns list of existing directories within all application bundles that are possible locations for ES documents
     *
     * @return array
     */
    public function getAllDocumentDirs()
    {
        $dirs = [];
        foreach ($this->bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            $filename = $reflection->getFileName();
            $dir = dirname($filename).'/'.$this->getDocumentDir();
            if (file_exists($dir)) {
                $dirs[] = $dir;
            }
        }

        return $dirs;
    }

    /**
     * Returns the document class name from short syntax
     * or the class name as it is, if it is already fully qualified
     *
     * @param string $className Short syntax for class name (e.g AppBundle:Product)
     * @return string Fully qualified class name
     */
    public function resolveClassName($className)
    {
        if (strpos($className, ':') !== false) {
            list($bundleName, $document) = explode(':', $className);

            if (array_key_exists($bundleName, $this->bundles)) {
                $bundleClass = $this->bundles[$bundleName];
            } else {
                throw new \UnexpectedValueException(sprintf('Bundle "%s" does not exist.', $bundleName));
            }

            $className = substr($bundleClass, 0, strrpos($bundleClass, '\\') + 1)
                . str_replace('/', '\\', $this->getDocumentDir()) . '\\' . $document;
        }

        return $className;
    }

    /**
     * Return the short name for a fully qualified document class name
     * or the name as it is, if it is already a short name
     *
     * @param string $className Fully qualified class name
     * @return string Class name in short notation (e.g. AppBundle:Product)
     */
    public function getShortClassName($className)
    {
        if (strpos($className, ':') === false) {
            if (!preg_match('/^([a-z0-9\\\\]+)\\\\' . preg_quote(str_replace('/', '\\', $this->getDocumentDir()), '/') . '\\\\([a-z0-9]+)$/i', $className, $matches)
                || !isset($this->bundleNamesByNamespace[$matches[1]])) {
                throw new \UnexpectedValueException(sprintf('Class "%s" is not a valid document entity', $className));
            }

            $className = $this->bundleNamesByNamespace[$matches[1]] . ':' . $matches[2];
        }

        return $className;
    }
}
