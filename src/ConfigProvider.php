<?php

namespace Johndodev\Component\Config;

use Johndodev\Component\Config\Entity\Config;
use Pimple\ServiceProviderInterface;
use Pimple\Container;
use Silex\Api\BootableProviderInterface;
use Silex\Application;

class ConfigProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * Config files to load
     * @var array
     */
    private $files;

    /**
     * The %paramaters% to replace in config files
     * @var array
     */
    private $parameters = [];

    /**
     * @param array $files array of paths
     * @param string $parametersFile
     */
    public function __construct($files, $parametersFile = null)
    {
        $this->files = $files;

        if ($parametersFile) {
            $parameters = require $parametersFile;

            foreach ($parameters as $k => $v) {
                $this->parameters['%'.$k.'%'] = $v;
            }
        }
    }

    public function register(Container $container)
    {
        // register les clés de config des fichiers dans le container
        foreach ($this->files as $file) {
            $confif = require $file;

            foreach ($confif as $key => $conf) {
                $container[$key] = $this->getReplacedValue($conf);
            }
        }

        // bdd
        $container['jdd.config.repository'] = function(Container $container) {
            return $container['orm.manager_registry']->getManagerForClass('Johndodev\Component\Config\Entity\Config')->getRepository('Johndodev\Component\Config\Entity\Config');
        };
    }

    /**
     * recursive replace on $values with parameters.php values
     * @param string|array $value
     * @return string|array
     */
    private function getReplacedValue($value)
    {
        if (is_array($value)) {
            $output = array();

            foreach ($value as $k => $v) {
                $output[$k] = $this->getReplacedValue($v);
            }

            return $output;
        }

        // par ex si value est une fonction, on replace rien..
        if (!is_scalar($value)) {
            return $value;
        }

        return str_replace(array_keys($this->parameters), array_values($this->parameters), $value);
    }

    /**
     * @inheritdoc
     */
    public function boot(Application $app)
    {
        // load config from database ?
        if (isset($app['jdd.config.db_config_enabled']) && $app['jdd.config.db_config_enabled']) {
            /** @var Config[] $configs */
            $configs = $app['jdd.config.repository']->findAll();

            foreach ($configs as $config) {
                $app[$config->getKey()] = $this->getReplacedValue($config->getValue());
            }
        }
    }
}
