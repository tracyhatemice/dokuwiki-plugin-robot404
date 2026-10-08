<?php

/**
 * Options for the robot404 plugin
 */

$meta['useragents'] = ['regex'];
// choices and combinations follow the core disableactions setting (lib/plugins/config/settings/config.metadata.php)
$meta['disableactions'] = [
    'disableactions',
    '_choices' => [
        'backlink',
        'diff',
        'index',
        'recent',
        'revisions',
        'search',
        'subscription',
        'register',
        'login',
        'resendpwd',
        'profile',
        'profile_delete',
        'edit',
        'wikicode',
        'check',
        'rss',
        'media',
    ],
    '_combine' => [
        'subscription' => ['subscribe', 'unsubscribe'],
        'wikicode' => ['source', 'export_raw'],
    ],
];
$meta['hiddenpages'] = ['onoff'];
$meta['aclpages'] = ['onoff'];
$meta['aclresponse'] = ['multichoice', '_choices' => ['denied', 'notfound', 'plain']];
