<?php

use Dan\Irc\Location\Channel;
use Dan\Irc\Location\User;

command(['flip'])
    ->allowPrivate()
    ->helpText('Flips a coin')
    ->rank('*')
    ->handler(function (User $user, Channel $channel = null) {
        $location = $channel ?? $user;

        $location->action('flips a coin and gets <b>'.array_random(['heads', 'tails']).'</b>');
    });
