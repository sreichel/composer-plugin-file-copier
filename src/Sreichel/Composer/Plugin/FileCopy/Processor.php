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
class Processor implements ConfigInterface
{
    protected Composer $composer;

    protected IOInterface $io;

    /** @var array<int, string>|null */
    protected ?array $projectPath = [];

    protected string $package;
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
    public function processCopy(string $packageName, array $config): void
    {
        $this->package = $packageName;
        $this->config = $config;
        $this->processConfig();

        $projectPath = $this->getProjectPath(true);

        $sourceFromConfig = $this->getSourceFromConfig();
        $sources = glob($sourceFromConfig, GLOB_MARK + GLOB_BRACE);
        if ($sources === [] || $sources === false) {
            $this->io->write('No source files found: ' . $sourceFromConfig);
            return;
        }

        $targetFromConfig = $this->getTargetFromConfig();

        if ($this->getDebugFromConfig()) {
            $this->io->write('Source: ' . $sourceFromConfig);
            $this->io->write('Target: ' . $targetFromConfig);
        }

        foreach ($sources as $source) {
            $this->copyr($source, $targetFromConfig, $projectPath);
        }
    }

    protected function getProjectPath(bool $withTpye): string
    {
        $withTpyeKey = (int) $withTpye;
        if (!array_key_exists($withTpyeKey, $this->projectPath)) {
            $path = $this->getProjectPathByType($withTpye);
            $this->projectPath[$withTpyeKey] = $path;
        }
        return $this->projectPath[$withTpyeKey];
    }

    private function getProjectPathByType(bool $withTpye): string
    {
        $path = realpath($this->vendor . '/../') . '/';
        if (!$withTpye) {
            return $path;
        }

        $extras = $this->composer->getPackage()->getExtra();
        switch ($extras) {
            case array_key_exists(self::EXTRA_MAGENTO_ROOT_DIR, $extras):
                $path .= $extras[self::EXTRA_MAGENTO_ROOT_DIR] . '/';
                return $path;
            default:
                return $path;
        }
    }

    private function processConfig(): void
    {
        if (empty($this->config[self::CONFIG_SOURCE])) {
            throw new InvalidArgumentException('The extra.file-copy.source setting is required to use this script handler.');
        }

        if (empty($this->config[self::CONFIG_TARGET])) {
            throw new InvalidArgumentException('The extra.file-copy.target setting is required to use this script handler.');
        }

        $this->setDebugFromConfig();
    }

    private function setDebugFromConfig(): void
    {
        if ($this->io->isVerbose()) {
            $this->config[self::CONFIG_DEBUG] = true;
            return;
        }

        if (empty($this->config[self::CONFIG_DEBUG])) {
            $this->config[self::CONFIG_DEBUG] = false;
            return;
        }

        $this->config[self::CONFIG_DEBUG] = in_array($this->config[self::CONFIG_DEBUG], ['true', true], true);
    }

    protected function getDebugFromConfig(): bool
    {
        return $this->config[self::CONFIG_DEBUG];
    }

    protected function getSourceFromConfig(): string
    {
        $configPath = $this->config[self::CONFIG_SOURCE];

        $isLocalPath = str_starts_with($configPath, '/');
        if ($isLocalPath) {
            $packageName = $this->composer->getPackage()->getName();
            if ($this->package === $packageName) {
                $path = realpath($this->vendor . '/../') . '/';
                $configPath = $path . ltrim($configPath, '/');
            } else {
                $configPath = $this->vendor . '/' . $this->package . '/' . ltrim($configPath, '/');
            }
        } else {
            $configPath = $this->vendor . '/' . $configPath;
        }

        return $configPath;
    }

    protected function getTargetFromConfig(): string
    {
        /** @var string $targetFromConfig */
        $targetFromConfig = $this->config[self::CONFIG_TARGET];
        if (str_starts_with('/', $targetFromConfig)) {
            $target = $this->getProjectPath(false) . $targetFromConfig;
        } else {
            $target = $this->getProjectPath(true) . $targetFromConfig;
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
