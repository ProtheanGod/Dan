<?php

use Dan\Irc\Location\Location;
use Dan\Irc\Location\User;

/** @var User $user */
/** @var Location $location */
/** @var string $message */
/** @var string $entry */

if($entry == 'use')
{
    $channel = $location->getLocation();

    if($message && isChannel($message))
        $channel = $message;

    $data = database()->get('channels', ['name' => $channel]);

    message($location, "Messages sent: {$data['messages']} | Max Users: {$data['max_users']}");
}

if($entry == 'help')
{
    return [
        "Gets stats for the current or given channel"
    ];
}