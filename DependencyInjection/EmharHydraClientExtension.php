<?php

namespace Emhar\HydraClientBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class EmharHydraClientExtension extends Extension
{
    /**
     * {@inheritDoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->getDefinition('emhar_hydra_client.client.hydra')
            ->replaceArgument(0, new Reference($config['guzzle_client']));
        $container->getDefinition('emhar_hydra_client.serializer.encoder.hydra_decode')
            ->replaceArgument(0, new Reference($config['guzzle_client']));
        $objectNormalizerDefinition = $container->getDefinition('emhar_hydra_client.serializer.normalizer.hydra_object_denormalize');
        $objectNormalizerDefinition->replaceArgument(1, new Reference($config['guzzle_client']));
        $objectNormalizerDefinition->replaceArgument(2, new Reference('emhar_hydra_client.serializer.cache'));
    }
}