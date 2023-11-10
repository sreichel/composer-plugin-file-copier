<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopy;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

abstract class AbstractCopy
{
    protected const COMPOSER_EXTRA_NAME = 'file-copy';

    protected const CONFIG_DEBUG  = 'debug';
    protected const CONFIG_SOURCE = 'source';
    protected const CONFIG_TARGET = 'target';

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
     * @return string
     */
    protected function getProjectPath(): string
    {
        if ($this->projectPath === null) {
            $this->projectPath = realpath($this->vendor . '/../') . '/';
        }

        return $this->projectPath;
    }
}
