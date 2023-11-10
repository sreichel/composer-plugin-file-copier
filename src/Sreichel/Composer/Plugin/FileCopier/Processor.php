<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopier;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use InvalidArgumentException;
use function basename;
use function copy;
use function dir;
use function glob;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function readlink;
use function realpath;
use function strlen;
use function symlink;

class Processor extends AbstractCopier
{
    public function __construct(Event $event)
    {
        $this->io       = $event->getIO();
        $this->composer = $event->getComposer();
        $this->vendor   = $event->getComposer()->getConfig()->get('vendor-dir');;
    }

    /**
     * @param array $config
     * @return void
     */
    public function processCopy(array $config)
    {
        $config = $this->processConfig($config);

        $projectPath = $this->getProjectPath();

        $debug = $config[static::CONFIG_DEBUG];
        if ($debug) {
            $this->io->write('Base path : ' . $projectPath);
        }

        $target = $config[static::CONFIG_TARGET];

        if (strlen($target) == 0 || !str_starts_with($target, '/')) {
            $target = $projectPath . $target;
        }

        if (false === realpath($target)) {
            mkdir($target, 0755, true);
        }
        $target = realpath($target);

        $configSource = $config[static::CONFIG_SOURCE];

        /**
         * @todo handle different links ...
         */
        if (!str_starts_with($configSource, $this->vendor . '/')) {
            $configSource = $this->vendor . '/' . $configSource;
        }

        if ($debug) {
            $this->io->write('Source: ' . $configSource);
            $this->io->write('Target: ' . $target);
        }

        $sources = glob($configSource, GLOB_MARK);
        if (!empty($sources)) {
            foreach ($sources as $source) {
                $this->copyr($source, $target, $projectPath, $debug);
            }
        }
    }

    /**
     * @param array $config
     * @return array
     */
    private function processConfig(array $config): array
    {
        if (empty($config[static::CONFIG_SOURCE])) {
            throw new InvalidArgumentException('The extra.file-copier.source setting is required to use this script handler.');
        }

        if (empty($config[static::CONFIG_TARGET])) {
            throw new InvalidArgumentException('The extra.file-copier.target setting is required to use this script handler.');
        }

        if (empty($config[static::CONFIG_DEBUG]) || $config[static::CONFIG_DEBUG] != 'true') {
            $config[static::CONFIG_DEBUG] = false;
        } else {
            $config[static::CONFIG_DEBUG] = true;
        }

        return $config;
    }

    private function getTargetFromConfig(array $config)
    {
        $target = $config[static::CONFIG_TARGET];

        if (strlen($target) == 0 || !str_starts_with($target, '/')) {
            $target = $this->getProjectPath() . $target;
        }

        if (false === realpath($target)) {
            mkdir($target, 0755, true);
        }

        return realpath($target);
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $projectPath
     * @param bool $debug
     * @return bool
     */
    private function copyr(string $source, string $target, string $projectPath, bool $debug = false): bool
    {
        if (strlen($source) == 0 || !str_starts_with($source, '/')) {
            $source = $projectPath . $source;
        }

        if (false === realpath($source)) {
            if ($debug) {
                $this->io->write('No copy : source ('.$source.') does not exist');
            }
        }

        $source = realpath($source);

        if ($source === $target && is_dir($source)) {
            if ($debug) {
                $this->io->write('No copy : source ('.$source.') and target ('.$target.') are identical');
            }
            return true;
        }


        // Check for symlinks
        if (is_link($source)) {
            if ($debug) {
                $this->io->write('Copying Symlink ' . $source . ' to ' . $target);
            }
            $source_entry = basename($source);
            return symlink(readlink($source), $target . '/ '. $source_entry);
        }

        if (is_dir($source)) {
            // Loop through the folder
            $source_entry = basename($source);
            if ($projectPath.$source_entry == $source) {
                $target = $target . '/' . $source_entry;
            }
            // Make destination directory
            if (!is_dir($target)) {
                if ($debug) {
                    $this->io->write('New Folder '.$target);
                }
                mkdir($target);
            }

            if ($debug) {
                $this->io->write('Scanning Folder '.$source);
            }

            $dir = dir($source);
            while (false !== $entry = $dir->read()) {
                // Skip pointers
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                // Deep copy directories
                $this->copyr($source . '/' . $entry, $target . '/' . $entry, $projectPath, $debug);
            }

            // Clean up
            $dir->close();
            return true;
        }

        // Simple copy for a file
        if (is_file($source)) {
            $source_entry = basename($source);
            if ($projectPath.$source_entry == $source || is_dir($target)) {
                $target = $target . '/' . $source_entry;
            }
            if ($debug) {
                $this->io->write('Copying File ' . $source . ' to ' . $target);
            }

            return copy($source, $target);
        }

        return true;
    }
}
