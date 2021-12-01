<?php

namespace Butopea\Composer\Plugin\Filescopier;

use Composer\IO\IOInterface;
use Composer\Composer;
use Composer\Script\Event;

/**
 *
 * @author sasedev <seif.salah@gmail.com>
 */
class Processor
{

	/**
	 *
	 * @var IOInterface
	 */
	private $io;

	/**
	 *
	 * @var Composer $composer
	 */
	private $composer;

	public function __construct(Event $ev)
	{

		$this->io = $ev->getIO();

		$this->composer = $ev->getComposer();
	}

	public function processCopy(array $config)
	{

		$config = $this->processConfig($config);

		$project_path = \realpath($this->composer->getConfig()->get('vendor-dir').'/../').'/';

		$debug = $config['debug'];

		if ($debug) {
			$this->io->write('[butopea/composer-plugin-filecopier] basepath : '.$project_path);
		}

		$destination = $config['destination'];

		if (\strlen($destination) == 0 || (\strlen($destination) != 0 && !$this->startsWith($destination, '/'))) {
			$destination = $project_path.$destination;
		}

		if (false === \realpath($destination)) {
			mkdir($destination, 0755, true);
		}
		$destination = \realpath($destination);

		$source = $config['source'];

		if ($debug) {
			$this->io->write('[butopea/composer-plugin-filecopier] init source : '.$source);
			$this->io->write('[butopea/composer-plugin-filecopier] init destination : '.$destination);
		}

		$sources = \glob($source, GLOB_MARK);
		if (!empty($sources)) {
			foreach ($sources as $newsource) {
				$this->copyr($newsource, $destination, $project_path, $debug);
			}
		}

	}

	private function processConfig(array $config)
	{

		if (empty($config['source'])) {
			throw new \InvalidArgumentException('The extra.filescopier.source setting is required to use this script handler.');
		}

		if (empty($config['destination'])) {
			throw new \InvalidArgumentException('The extra.filescopier.destination setting is required to use this script handler.');
		}

		if (empty($config['debug']) || $config['debug'] != 'true') {
			$config['debug'] = false;
		} else {
			$config['debug'] = true;
		}

		return $config;

	}

	private function copyr($source, $destination, $project_path, $debug = false)
	{

		if (\strlen($source) == 0 || (\strlen($source) != 0 && !$this->startsWith($source, '/'))) {
			$source = $project_path.$source;
		}

		if (false === \realpath($source)) {
			if ($debug) {
				$this->io->write('[butopea/composer-plugin-filecopier] No copy : source ('.$source.') does not exist');
			}
		}

		$source = \realpath($source);

		if ($source === $destination && \is_dir($source)) {
			if ($debug) {
				$this->io->write('[butopea/composer-plugin-filecopier] No copy : source ('.$source.') and destination ('.$destination.') are identicals');
			}
			return true;
		}


		// Check for symlinks
		if (\is_link($source)) {
			if ($debug) {
				$this->io->write('[butopea/composer-plugin-filecopier] Copying Symlink '.source.' to '.$destination);
			}
			$source_entry = \basename($source);
			return \symlink(\readlink($source), $destination.'/'.$source_entry);
		}

		if (\is_dir($source)) {
			// Loop through the folder
			$source_entry = \basename($source);
			if ($project_path.$source_entry == $source) {
				$destination = $destination.'/'.$source_entry;
			}
			// Make destination directory
			if (!\is_dir($destination)) {
				if ($debug) {
					$this->io->write('[butopea/composer-plugin-filecopier] New Folder '.$destination);
				}
				\mkdir($destination);
			}

			if ($debug) {
				$this->io->write('[butopea/composer-plugin-filecopier] Scanning Folder '.$source);
			}

			$dir = \dir($source);
			while (false !== $entry = $dir->read()) {
				// Skip pointers
				if ($entry == '.' || $entry == '..') {
					continue;
				}

				// Deep copy directories
				$this->copyr($source.'/'.$entry, $destination.'/'.$entry, $project_path, $debug);
			}

			// Clean up
			$dir->close();
			return true;
		}

		// Simple copy for a file
		if (\is_file($source)) {
			$source_entry = \basename($source);
			if ($project_path.$source_entry == $source || \is_dir($destination)) {
				$destination = $destination.'/'.$source_entry;
			}
			if ($debug) {
				$this->io->write('[butopea/composer-plugin-filecopier] Copying File '.$source.' to '.$destination);
			}

			return \copy($source, $destination);
		}


		return true;

	}

	/**
	 * Check if a string starts with a prefix
	 *
	 * @param string $string
	 * @param string $prefix
	 *
	 * @return boolean
	 */
	private function startsWith($string, $prefix) {
		return $prefix === "" || strrpos($string, $prefix, -strlen($string)) !== FALSE;
	}

	/**
	 * Check if a string ends with a suffix
	 *
	 * @param string $string
	 * @param string $suffix
	 *
	 * @return boolean
	 */
	private function endswith($string, $suffix)
	{
		$strlen = strlen($string);
		$testlen = strlen($suffix);
		if ($testlen > $strlen) {
			return false;
		}

		return substr_compare($string, $suffix, -$testlen) === 0;
	}

}