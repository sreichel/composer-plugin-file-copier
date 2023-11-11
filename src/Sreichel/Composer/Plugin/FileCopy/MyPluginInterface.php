<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopy;

interface MyPluginInterface
{
    public const COMPOSER_EXTRA_NAME = 'file-copy';

    public const CONFIG_DEBUG  = 'debug';
    public const CONFIG_SOURCE = 'source';
    public const CONFIG_TARGET = 'target';
}
