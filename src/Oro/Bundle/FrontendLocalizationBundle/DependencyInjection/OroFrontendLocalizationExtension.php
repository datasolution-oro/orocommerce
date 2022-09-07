<?php

namespace Oro\Bundle\FrontendLocalizationBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages FrontendLocalizationBundle service configuration
 */
class OroFrontendLocalizationExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('block_types.yml');
        $loader->load('services.yml');
        $loader->load('services_api.yml');
        $loader->load('layout.yml');
        $loader->load('controllers.yml');

        $container->prependExtensionConfig($this->getAlias(), $config);
    }
}
