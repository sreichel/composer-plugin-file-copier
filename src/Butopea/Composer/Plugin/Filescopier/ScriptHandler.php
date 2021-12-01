<?php

namespace Butopea\Composer\Plugin\Filescopier;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

/**
 *
 * @author sasedev <seif.salah@gmail.com>
 */
class ScriptHandler implements PluginInterface, EventSubscriberInterface
{

	/**
	 *
	 * @var IOInterface $io
	 */
	protected $io;

	/**
	 *
	 * @var Composer $composer
	 */
	protected $composer;

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \Composer\Plugin\PluginInterface::activate()
	 */
	public function activate(Composer $composer, IOInterface $io)
	{

		$this->composer = $composer;
		$this->io = $io;

	}

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \Composer\EventDispatcher\EventSubscriberInterface::getSubscribedEvents()
	 */
	public static function getSubscribedEvents()
	{

		return array(
			"post-install-cmd" => array(
				array(
					'onPostCmd',
					0
				)
			),
			"post-update-cmd" => array(
				array(
					'onPostCmd',
					0
				)
			)
		);

	}

	public function onPostCmd(Event $event)
	{

		self::buildParameters($event);

	}

	public static function buildParameters(Event $event)
	{

		$IO = $event->getIO();
		$composer = $event->getComposer();

		$extras = $composer->getPackage()->getExtra();
		if (!isset($extras['filescopier'])) {
			$IO->write('The parameter handler needs to be configured through the extra.filescopier setting.');
		} else {
			$configs = $extras['filescopier'];
			if (!is_array($configs)) {
				throw new \InvalidArgumentException('The extra.filescopier setting must be an array or a configuration object.');
			}

			if (array_keys($configs) !== range(0, count($configs) - 1)) {
				$configs = array(
					$configs
				);
			}

			$processor = new Processor($event);

			foreach ($configs as $config) {
				if (!is_array($config)) {
					throw new \InvalidArgumentException('The extra.filescopier setting must be an array of configuration objects.');
				}

				$processor->processCopy($config);
			}
		}

	}

}