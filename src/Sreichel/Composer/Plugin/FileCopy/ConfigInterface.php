<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopy;

/**
 * Interface ConfigInterface
 */
interface ConfigInterface
{
    public const COMPOSER_EXTRA_NAME        = 'file-copy';

    public const CONFIG_DEBUG               = 'debug';

    public const CONFIG_SOURCE              = 'source';

    public const CONFIG_TARGET              = 'target';
}
