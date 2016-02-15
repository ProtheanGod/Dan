<?php

use Dan\Contracts\UserContract;
use Dan\Core\Dan;
use Dan\Irc\Location\Channel;

command(['version', 'v'])
    ->allowPrivate()
    ->allowConsole()
    ->rank('*')
    ->helpText('Gets dan version')
    ->handler(function (UserContract $user, Channel $channel = null) {
        $location = $channel ?? $user;

        $location->message(Dan::VERSION.' by UclCommander - https://links.ml/XOH4');
    });
