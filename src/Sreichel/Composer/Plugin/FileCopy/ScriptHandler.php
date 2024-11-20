<?php

declare(strict_types=1);

namespace Sreichel\Composer\Plugin\FileCopy;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

/**
 * Class ScriptHandler
 */
class ScriptHandler implements ConfigInterface, PluginInterface, EventSubscriberInterface
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
        $io         = $event->getIO();
        $composer   = $event->getComposer();
        $locker     = $composer->getLocker();
        $repo       = $locker->getLockedRepository();

        $extrasCollection = [];

        // get extras from project
        $extras = $composer->getPackage()->getExtra();
        if (isset($extras[self::COMPOSER_EXTRA_NAME])) {
            $extrasCollection[$composer->getPackage()->getName()] = $extras[self::COMPOSER_EXTRA_NAME];
        }

        // get extras from installed packages
        foreach ($repo->getPackages() as $package) {
            $extras = $package->getExtra();
            if (isset($extras[self::COMPOSER_EXTRA_NAME])) {
                $extrasCollection[$package->getName()] = $extras[self::COMPOSER_EXTRA_NAME];
            }
        }

        self::validateConfig($io, $extrasCollection);

        if ($extrasCollection === []) {
            $io->write('The parameter handler needs to be configured through the extra.file-copy settings.');
        } else {
            $processor = new Processor($event);

            foreach ($extrasCollection as $packageName => $settings) {
                foreach ($settings as $config) {
                    $processor->processCopy($packageName, $config);
                }
            }
        }
    }

    private static function validateConfig(IOInterface $io, array &$extrasCollection): void
    {
        foreach ($extrasCollection as $package => $settings) {
            if (!is_array($settings)) {
                unset($extrasCollection[$package]);

                $io->write(sprintf(
                    'The extra.file-copy setting must be an array or a configuration object in package %s.',
                    $package
                ));
            }

            foreach ($settings as $key => $config) {
                if (!is_array($config)) {
                    unset($extrasCollection[$package][$key]);

                    $io->write(sprintf(
                        'The extra.file-copy structure must be an array of configuration objects in package %s.',
                        $package
                    ));
                }
            }

            if ($extrasCollection[$package] === []) {
                unset($extrasCollection[$package]);
            }
        }
    }
}
