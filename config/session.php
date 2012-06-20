<?php defined('SYSPATH') or die('No direct script access.');

return array(
    'cookie' => array(
        'name' => 'poken.cookie',
        'encrypted' => TRUE,
        'lifetime' => 86400,
    ),
    'native' => array(
        'name' => 'poken.session',
        'encrypted' => TRUE,
        'lifetime' => 86400,
    )
);