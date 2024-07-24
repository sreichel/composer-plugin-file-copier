<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopy;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Directory;
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

/**
 * Class Processor
 */
class Processor
{
    /**
     * @var Composer $composer
     */
    protected Composer $composer;

    /**
     * @var IOInterface $io
     */
    protected IOInterface $io;

    /**
     * @var string|null
     */
    protected ?string $projectPath = null;

    /**
     * @var string $io
     */
    protected string $vendor;

    /**
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->io       = $event->getIO();
        $this->composer = $event->getComposer();

        $vendorDir      = $event->getComposer()->getConfig()->get('vendor-dir');
        $this->vendor   = $vendorDir;
    }

    /**
     * @param array<string, string> $config
     * @return void
     */
    public function processCopy(array $config): void
    {
        $config = $this->processConfig($config);
        $projectPath = $this->getProjectPath();

        $debug = $config[ConfigInterface::CONFIG_DEBUG];
        $debug = is_bool($debug) ? $debug : false;

        if ($debug) {
            $this->io->write('Base path : ' . $projectPath);
        }

        $configSource = $config[ConfigInterface::CONFIG_SOURCE];
        if (!str_starts_with($configSource, '/')) {
            $configSource = $this->vendor . '/' . $configSource;
        }

        $sources = glob($configSource, GLOB_MARK + GLOB_BRACE);
        if ($sources === [] || $sources === false) {
            $this->io->write('No source files found: ' . $configSource);
            return;
        }

        $target = $this->getTargetFromConfig($config);
        if ($debug) {
            $this->io->write('Source: ' . $configSource);
            $this->io->write('Target: ' . $target);
        }

        foreach ($sources as $source) {
            $this->copyr($source, $target, $projectPath, $debug);
        }
    }

    /**
     * @return string
     */
    protected function getProjectPath(): string
    {
        if ($this->projectPath === null) {
            $path = realpath($this->vendor . '/../') . '/';

            $extras = $this->composer->getPackage()->getExtra();
            if (isset($extras['magento-root-dir'])) {
                $path .= $extras['magento-root-dir'] . '/';
            }
            $this->projectPath = $path;
        }

        return $this->projectPath;
    }

    /**
     * @param array<string, bool|string> $config
     * @return string[]
     */
    private function processConfig(array $config): array
    {
        if (empty($config[ConfigInterface::CONFIG_SOURCE])) {
            throw new InvalidArgumentException('The extra.file-copy.source setting is required to use this script handler.');
        }

        if (empty($config[ConfigInterface::CONFIG_TARGET])) {
            throw new InvalidArgumentException('The extra.file-copy.target setting is required to use this script handler.');
        }

        $this->setDebugFromConfig($config);

        return $config;
    }

    /**
     * @param array<string, bool|string> $config
     * @return void
     */
    private function setDebugFromConfig(array &$config): void
    {
        if ($this->io->isVerbose()) {
            $config[ConfigInterface::CONFIG_DEBUG] = true;
            return;
        }

        if (empty($config[ConfigInterface::CONFIG_DEBUG])) {
            $config[ConfigInterface::CONFIG_DEBUG] = false;
            return;
        }

        $config[ConfigInterface::CONFIG_DEBUG] = in_array($config[ConfigInterface::CONFIG_DEBUG], ['true', true], true);
    }

    /**
     * @param string[] $config
     * @return string
     */
    private function getTargetFromConfig(array $config): string
    {
        $target = $config[ConfigInterface::CONFIG_TARGET];

        if ($target === '' || !str_starts_with($target, '/')) {
            $target = $this->getProjectPath() . $target;
        }

        if (realpath($target) === false) {
            mkdir($target, 0755, true);
        }

        $target = realpath($target);
        if ($target === false) {
            throw new InvalidArgumentException('Target is invalid.');
        }

        return $target;
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

        if (realpath($source) === false && $debug) {
            $this->io->write('No copy : source (' . $source . ') does not exist');
        }

        $source = realpath($source);
        if ($source === $target && is_dir($source)) {
            if ($debug) {
                $this->io->write('No copy : source (' . $source . ') and target (' . $target . ') are identical');
            }

            return true;
        }


        // Check for symlinks
        if ($source && is_link($source)) {
            if ($debug) {
                $this->io->write('Copying Symlink ' . $source . ' to ' . $target);
            }

            $source_entry = basename($source);
            $link = readlink($source);
            if ($link) {
                return symlink($link, $target . '/ ' . $source_entry);
            }
        }

        if ($source && is_dir($source)) {
            // Loop through the folder
            $source_entry = basename($source);
            if ($projectPath . $source_entry === $source) {
                $target = $target . '/' . $source_entry;
            }

            // Make destination directory
            if (!is_dir($target)) {
                if ($debug) {
                    $this->io->write('New Folder ' . $target);
                }

                mkdir($target);
            }

            if ($debug) {
                $this->io->write('Scanning Folder ' . $source);
            }

            $dir = dir($source);
            if ($dir instanceof Directory) {
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
        }

        // Simple copy for a file
        if ($source && is_file($source)) {
            $source_entry = basename($source);
            if ($projectPath . $source_entry === $source || is_dir($target)) {
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
