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
    protected Composer $composer;

    protected IOInterface $io;

    protected ?string $projectPath = null;

    protected string $vendor;

    /**
     * @var array{source: string, target: string, debug: bool}
     */
    protected array $config;

    public function __construct(Event $event)
    {
        $this->io       = $event->getIO();
        $this->composer = $event->getComposer();

        /** @var string $vendorDir */
        $vendorDir      = $event->getComposer()->getConfig()->get('vendor-dir');
        $this->vendor   = $vendorDir;
    }

    /**
     * @param array{source: string, target: string, debug: bool} $config
     */
    public function processCopy(array $config): void
    {
        $this->config = $config;
        $this->processConfig();

        $projectPath = $this->getProjectPath();

        $configSource = $this->getSourceFromConfig();
        $isLocalPath = str_starts_with($configSource, '/');

        if ($isLocalPath) {
            $configSource = ltrim($configSource, '/');
        } else {
            $configSource = $this->vendor . '/' . $configSource;
        }

        $sources = glob($configSource, GLOB_MARK + GLOB_BRACE);
        if ($sources === [] || $sources === false) {
            $this->io->write('No source files found: ' . $configSource);
            return;
        }

        $target = $this->getTargetFromConfig();

        if ($this->getDebugFromConfig()) {
            $this->io->write('Package type: ' . $this->getPackageType());
            $this->io->write('Source: ' . $configSource);
            $this->io->write('Target: ' . $target);
        }

        foreach ($sources as $source) {
            $this->copyr($source, $target, $projectPath);
        }
    }

    protected function getPackageType(): string
    {
        return $this->composer->getPackage()->getType();
    }

    protected function getProjectPath(): string
    {
        if ($this->projectPath === null) {
            $path = $this->getProjectPathByType();
            $this->projectPath = $path;
        }
        return $this->projectPath;
    }

    protected function getProjectPathByType(): string
    {
        $path = realpath($this->vendor . '/../') . '/';
        $type = $this->getPackageType();

        switch ($type) {
            case ConfigInterface::TYPE_MAGENTO_SOURE:
                $extras = $this->composer->getPackage()->getExtra();
                $magentoRootDir = 'magento-root-dir';
                if (array_key_exists($magentoRootDir, $extras)) {
                    $path .= $extras[$magentoRootDir] . '/';
                }
                return $path;
            default:
                return $path;
        }
    }

    private function processConfig(): void
    {
        if (empty($this->config[ConfigInterface::CONFIG_SOURCE])) {
            throw new InvalidArgumentException('The extra.file-copy.source setting is required to use this script handler.');
        }

        if (empty($this->config[ConfigInterface::CONFIG_TARGET])) {
            throw new InvalidArgumentException('The extra.file-copy.target setting is required to use this script handler.');
        }

        $this->setDebugFromConfig();
    }

    private function setDebugFromConfig(): void
    {
        if ($this->io->isVerbose()) {
            $this->config[ConfigInterface::CONFIG_DEBUG] = true;
            return;
        }

        if (empty($this->config[ConfigInterface::CONFIG_DEBUG])) {
            $this->config[ConfigInterface::CONFIG_DEBUG] = false;
            return;
        }

        $this->config[ConfigInterface::CONFIG_DEBUG] = in_array($this->config[ConfigInterface::CONFIG_DEBUG], ['true', true], true);
    }

    protected function getDebugFromConfig(): bool
    {
        return $this->config[ConfigInterface::CONFIG_DEBUG];
    }

    protected function getSourceFromConfig(): string
    {
        return $this->config[ConfigInterface::CONFIG_SOURCE];
    }

    protected function getTargetFromConfig(): string
    {
        /** @var string $target */
        $target = $this->config[ConfigInterface::CONFIG_TARGET];

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

    private function copyr(string $source, string $target, string $projectPath): bool
    {
        if (strlen($source) == 0 || !str_starts_with($source, '/')) {
            $source = $projectPath . $source;
        }

        if (realpath($source) === false && $this->getDebugFromConfig()) {
            $this->io->write('No copy : source (' . $source . ') does not exist');
        }

        $source = realpath($source);
        if ($source === $target && is_dir($source)) {
            if ($this->getDebugFromConfig()) {
                $this->io->write('No copy : source (' . $source . ') and target (' . $target . ') are identical');
            }

            return true;
        }

        if ($source) {
            switch ($source) {
                // Check for symlinks
                case is_link($source):
                    $this->copySymlink($projectPath, $source, $target);
                    break;
                case is_dir($source):
                    $this->copyDirectory($projectPath, $source, $target);
                    break;
                // Simple copy for a file
                case is_file($source):
                    $this->copyFile($projectPath, $source, $target);
                    break;
            }
        }

        return true;
    }

    private function copySymlink(string $projectPath, string $source, string $target): bool
    {
        if ($this->getDebugFromConfig()) {
            $this->io->write('Copying Symlink ' . $source . ' to ' . $target);
        }

        $basename = basename($source);
        $link = readlink($source);
        if ($link) {
            return symlink($link, $target . '/ ' . $basename);
        }

        return true;
    }

    private function copyDirectory(string $projectPath, string $source, string $target): bool
    {
        // Loop through the folder
        $basename = basename($source);
        if ($projectPath . $basename === $source) {
            $target = $target . '/' . $basename;
        }

        // Make destination directory
        if (!is_dir($target)) {
            if ($this->getDebugFromConfig()) {
                $this->io->write('New Folder ' . $target);
            }

            mkdir($target);
        }

        if ($this->getDebugFromConfig()) {
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
                $this->copyr($source . '/' . $entry, $target . '/' . $entry, $projectPath);
            }

            // Clean up
            $dir->close();
            return true;
        }
        return true;
    }

    private function copyFile(string $projectPath, string $source, string $target): bool
    {
        $basename = basename($source);
        if ($projectPath . $basename === $source || is_dir($target)) {
            $target = $target . '/' . $basename;
        }

        if ($this->getDebugFromConfig()) {
            $this->io->write('Copying File ' . $source . ' to ' . $target);
        }

        return copy($source, $target);
    }
}
