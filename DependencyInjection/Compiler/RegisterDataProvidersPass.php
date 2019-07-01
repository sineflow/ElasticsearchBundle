<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Registers data providers for type entities
 */
class RegisterDataProvidersPass implements CompilerPassInterface
{
    /**
     * Mapping of class names to booleans indicating whether the class
     * implements ProviderInterface.
     *
     * @var array
     */
    private $implementations = array();

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('sfes.provider_registry')) {
            return;
        }

        $registry = $container->getDefinition('sfes.provider_registry');
        $providers = $container->findTaggedServiceIds('sfes.provider');

        foreach ($providers as $providerId => $tags) {
            $documentClass = null;

            $class = $container->getDefinition($providerId)->getClass();
            if (!$class || !$this->isProviderImplementation($class)) {
                throw new \InvalidArgumentException(sprintf('Data provider [%s] with class [%s] must implement ProviderInterface.', $providerId, $class));
            }

            foreach ($tags as $attributes) {
                if (!isset($attributes['type'])) {
                    throw new \InvalidArgumentException(sprintf('Data provider [%s] must specify the "type" attribute.', $providerId));
                }
                $documentClass = $attributes['type'];

                $registry->addMethodCall('addProvider', array($documentClass, $providerId));
            }
        }
    }

    /**
     * Returns whether the class implements ProviderInterface.
     *
     * @param string $class
     *
     * @return boolean
     *
     * @throws \ReflectionException
     */
    private function isProviderImplementation($class)
    {
        if (!isset($this->implementations[$class])) {
            $reflectionClass = new \ReflectionClass($class);
            $this->implementations[$class] = $reflectionClass->implementsInterface('Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface');
        }

        return $this->implementations[$class];
    }
}
