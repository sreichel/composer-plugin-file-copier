<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopy;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use InvalidArgumentException;

/**
 * Class ScriptHandler
 */
class ScriptHandler implements PluginInterface, EventSubscriberInterface
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
     * @see PluginInterface::activate
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @see EventSubscriberInterface::getSubscribedEvents
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd'  => [['onPostCmd', 0]],
            'post-update-cmd'   => [['onPostCmd', 0]]
        ];
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onPostCmd(Event $event): void
    {
        self::buildParameters($event);
    }

    /**
     * @param Event $event
     * @return void
     */
    public static function buildParameters(Event $event): void
    {
        $io = $event->getIO();
        $composer = $event->getComposer();

        $extras = $composer->getPackage()->getExtra();
        if (!isset($extras[ConfigInterface::COMPOSER_EXTRA_NAME])) {
            $io->write('The parameter handler needs to be configured through the extra.file-copy setting.');
        } else {
            $configs = $extras[ConfigInterface::COMPOSER_EXTRA_NAME];
            if (!is_array($configs)) {
                throw new InvalidArgumentException('The extra.file-copy setting must be an array or a configuration object.');
            }

            $processor = new Processor($event);

            foreach ($configs as $config) {
                if (!is_array($config)) {
                    throw new InvalidArgumentException('The extra.file-copy setting must be an array of configuration objects.');
                }

                /** @var array<string, string> $config */
                $processor->processCopy($config);
            }
        }
    }
}
