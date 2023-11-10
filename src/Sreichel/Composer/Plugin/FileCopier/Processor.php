<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopier;

use function basename;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use function copy;
use function dir;
use function glob;
use InvalidArgumentException;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function readlink;
use function realpath;
use function strlen;
use function symlink;

class Processor
{
    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Composer $composer
     */
    private $composer;

    public function __construct(Event $event)
    {
        $this->io = $event->getIO();
        $this->composer = $event->getComposer();
    }

    /**
     * @param array $config
     * @return void
     */
    public function processCopy(array $config)
    {
        $config = $this->processConfig($config);

        $project_path = realpath($this->composer->getConfig()->get('vendor-dir').'/../').'/';

        $debug = $config['debug'];

        if ($debug) {
            $this->io->write('Base path : '.$project_path);
        }

        $destination = $config['destination'];

        if (strlen($destination) == 0 || !$this->startsWith($destination, '/')) {
            $destination = $project_path.$destination;
        }

        if (false === realpath($destination)) {
            mkdir($destination, 0755, true);
        }

        $destination = realpath($destination);
        $configSource = $config['source'];

        if ($debug) {
            $this->io->write('Init source : '.$configSource);
            $this->io->write('Init destination : '.$destination);
        }

        $sources = glob($configSource, GLOB_MARK);
        if (!empty($sources)) {
            foreach ($sources as $source) {
                $this->copyr($source, $destination, $project_path, $debug);
            }
        }
    }

    /**
     * @param array $config
     * @return array
     */
    private function processConfig(array $config): array
    {
        if (empty($config['source'])) {
            throw new InvalidArgumentException('The extra.file-copier.source setting is required to use this script handler.');
        }

        if (empty($config['destination'])) {
            throw new InvalidArgumentException('The extra.file-copier.destination setting is required to use this script handler.');
        }

        if (empty($config['debug']) || $config['debug'] != 'true') {
            $config['debug'] = false;
        } else {
            $config['debug'] = true;
        }

        return $config;
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
        if (strlen($source) == 0 || !$this->startsWith($source, '/')) {
            $source = $projectPath.$source;
        }

        if (false === realpath($source)) {
            if ($debug) {
                $this->io->write('No copy : source ('.$source.') does not exist');
            }
        }

        $source = realpath($source);

        if ($source === $target && is_dir($source)) {
            if ($debug) {
                $this->io->write('No copy : source ('.$source.') and destination ('.$target.') are identical');
            }
            return true;
        }


        // Check for symlinks
        if (is_link($source)) {
            if ($debug) {
                $this->io->write('Copying Symlink '.$source.' to '.$target);
            }
            $source_entry = basename($source);
            return symlink(readlink($source), $target.'/'.$source_entry);
        }

        if (is_dir($source)) {
            // Loop through the folder
            $source_entry = basename($source);
            if ($projectPath.$source_entry == $source) {
                $target = $target.'/'.$source_entry;
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
                $this->copyr($source.'/'.$entry, $target.'/'.$entry, $projectPath, $debug);
            }

            // Clean up
            $dir->close();
            return true;
        }

        // Simple copy for a file
        if (is_file($source)) {
            $source_entry = basename($source);
            if ($projectPath.$source_entry == $source || is_dir($target)) {
                $target = $target.'/'.$source_entry;
            }
            if ($debug) {
                $this->io->write('Copying File '.$source.' to '.$target);
            }

            return copy($source, $target);
        }


        return true;
    }

    /**
     * Check if a string starts with a prefix
     *
     * @param string $string
     * @param string $prefix
     * @return boolean
     */
    private function startsWith($string, $prefix)
    {
        return $prefix === "" || strrpos($string, $prefix, -strlen($string)) !== false;
    }

    /**
     * Check if a string ends with a suffix
     *
     * @param string $string
     * @param string $suffix
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
