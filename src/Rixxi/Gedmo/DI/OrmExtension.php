<?php

namespace Rixxi\Gedmo\DI;

use Kdyby;
use Nette\DI\CompilerExtension;
use Nette\Utils\Validators;
use Nette;


class OrmExtension extends CompilerExtension implements Kdyby\Doctrine\DI\IEntityProvider
{

	private $defaults = array(
		'translatableLocale' => 'cs_CZ',
		'defaultLocale'	=> 'cs_CZ',
		// enable all
		'all' => FALSE,
		// enable per annotation
		'loggable' => FALSE,
		'sluggable' => FALSE,
		'softDeleteable' => FALSE,
		'sortable' => FALSE,
		'timestampable' => FALSE,
		'translatable' => FALSE,
		'treeable' => FALSE,
		'uploadable' => FALSE,
	);

	private $annotations = array(
		'loggable',
		'sluggable',
		'softDeleteable',
		'sortable',
		'timestampable',
		'translatable',
		'treeable',
		'uploadable',
	);


	public function getEntityMappings()
	{
		$config = $this->getValidatedConfig();

		$annotations = array(
			'loggable' => 'Loggable',
			'translatable' => 'Translatable',
			'treeable' => 'Tree',
		);

		$path = realpath(__DIR__ . '/../../../../../../gedmo/doctrine-extensions/src');

		$mappings = array();
		foreach ($annotations as $annotation => $namespace) {
			if ($config['all'] || $config[$annotation]) {
				$mappings["Gedmo\\$namespace\\Entity"] = "$path/$namespace/Entity";
			}
		}

		return $mappings;
	}

	public function loadConfiguration()
	{
		$config = $this->getValidatedConfig();

		$this->loadConfig('gedmo');

		$builder = $this->getContainerBuilder();
		
		foreach ($this->annotations as $annotation) {
			if ($config['all'] || $config[$annotation]) {
				continue;
			}

			$builder->removeDefinition($this->prefix($annotation));
		}
	}

	public function beforeCompile()
	{
		$eventsExt = NULL;
		foreach ($this->compiler->getExtensions() as $extension) {
			if ($extension instanceof Kdyby\Doctrine\DI\OrmExtension) {
				$eventsExt = $extension;
				break;
			}
		}

		if ($eventsExt === NULL) {
			throw new Nette\Utils\AssertionException('Please register the required Kdyby\Doctrine\DI\OrmExtension to Compiler.');
		}

		$config = $this->getValidatedConfig();
		$builder = $this->getContainerBuilder();
		if ($builder->hasDefinition($this->prefix('translatable'))) {
			$translatable = $builder->getDefinition($this->prefix('translatable')))
			$translatable->addSetup('setTranslatableLocale', array($config['translatableLocale']));
			$translatable->addSetup('setDefaultLocale', array($config['defaultLocale']));
		}
	}


	/**
	 * Default values are added to the extension config values retrieved using parent's getConfig
	 * @param array|object $defaults Array or object with default values
	 * @return array|object Config with default values applied if needed
	 */
	public function getConfig($defaults = NULL)
	{
		$config = parent::getConfig($defaults);

		if (is_array($defaults) || is_object($defaults)) {
			if (is_array($config)) {
				foreach ($defaults as $key => $defaultValue) {
					if (!array_key_exists($key, $config)) {
						$config[$key] = $defaultValue;
					}
				}
			}
			else if (is_object($config)) {
				foreach ($defaults as $key => $defaultValue) {
					if (!isset($config->$key)) {
						$config->$key = $defaultValue;
					}
				}
			}
		}

		return $config;
	}


	/**
	 * @return array
	 */
	private function getValidatedConfig()
	{
		$config = $this->getConfig($this->defaults);

		Validators::assertField($config, 'translatableLocale', 'string');
		Validators::assertField($config, 'defaultLocale', 'string');
		Validators::assertField($config, 'all', 'bool');

		$atLeastOneEnabled = $config['all'];
		foreach ($this->annotations as $annotation) {
			Validators::assertField($config, $annotation, 'bool');
			$atLeastOneEnabled |= $config[$annotation];
		}

		if (!$atLeastOneEnabled) {
			throw new Nette\Utils\AssertionException('Please enable one or more annotations in configuration.');
		}

		return $config;
	}

	/**
	 * @param string $name
	 */
	private function loadConfig($name)
	{
		if (method_exists($this->compiler, 'loadDefinitionsFromConfig')) {
			$config = $this->loadFromFile(__DIR__ . '/config/' . $name . '.neon');
			Validators::assertField($config, 'services');
			$services = [];
			foreach ($config['services'] as $key => $value) {
				$services[$this->prefix($key)] = $value;
			}
			$this->compiler->loadDefinitionsFromConfig(
				$services
			);
		}
		else {
			$this->compiler->parseServices(
				$this->getContainerBuilder(),
				$this->loadFromFile(__DIR__ . '/config/' . $name . '.neon'),
				$this->prefix($name)
			);
		}
	}

}
