<?php

/**
 * Restart command. Restarts the bot if the pcntl_exec function is available.
 *
 * Do not directly edit this file.
 * If you want to change the rank, see commands.permissions in the configuration.
 */

use Dan\Core\Dan;
use Illuminate\Support\Collection;

hook('restart')
    ->command(['restart', 'reload', 'reboot'])
    ->console()
    ->rank('S')
    ->help('Makes the bot restart')
    ->func(function(Collection $args) {
        if(!function_exists('pcntl_exec'))
        {
            message($args['channel'], "Unable to restart. PHP needs to be compiled with --enable-pcntl for automatic restarts.");
            return;
        }

        Dan::quit("Restarting bot.");
        pcntl_exec(ROOT_DIR . '/dan');
        return;
    });