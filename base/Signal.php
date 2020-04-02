<?php

namespace guanghua\queue\base;

/**
 * 进程信号帮手
 */
class Signal
{
    private static $exit = false;

    /**
     * Checks exit signals
     * @return bool
     */
    public static function isExit()
    {
        if (function_exists('pcntl_signal')) {
            // Installs a signal handler
            static $handled = false;
            if (!$handled) {
                foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
                    pcntl_signal($signal, function () {
                        static::$exit = true;
                    });
                }
                $handled = true;
            }

            // Checks signal
            if (!static::$exit) {
                pcntl_signal_dispatch();
            }
        }
        return static::$exit;
    }
}
