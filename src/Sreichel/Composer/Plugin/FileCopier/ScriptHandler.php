<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopier;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use InvalidArgumentException;

class ScriptHandler extends AbstractCopier implements PluginInterface, EventSubscriberInterface
{
    /**
     * @see PluginInterface::activate
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io)
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
    public function onPostCmd(Event $event)
    {
        self::buildParameters($event);
    }

    /**
     * @param Event $event
     * @return void
     */
    public static function buildParameters(Event $event)
    {
        $id = $event->getIO();
        $composer = $event->getComposer();

        $extras = $composer->getPackage()->getExtra();
        if (!isset($extras[static::COMPOSER_EXTRA_NAME])) {
            $id->write('The parameter handler needs to be configured through the extra.file-copier setting.');
        } else {
            $configs = $extras[static::COMPOSER_EXTRA_NAME];
            if (!is_array($configs)) {
                throw new InvalidArgumentException('The extra.file-copier setting must be an array or a configuration object.');
            }

            if (array_keys($configs) !== range(0, count($configs) - 1)) {
                $configs = array(
                    $configs
                );
            }

            $processor = new Processor($event);

            foreach ($configs as $config) {
                if (!is_array($config)) {
                    throw new InvalidArgumentException('The extra.file-copier setting must be an array of configuration objects.');
                }

                $processor->processCopy($config);
            }
        }
    }
}
