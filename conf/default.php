<?php

/**
 * Default settings for the robot404 plugin
 *
 * The configuration manager reads this file as text: keep every value a single literal.
 */

// phpcs:disable Generic.Files.LineLength.TooLong
$conf['useragents'] = 'bot|crawl|slurp|spider|mediapartners|Google|Yahoo|Rambler|accoona|ASPSeek|Lycos|Scooter|AltaVista|eStyle|Scrubby';
$conf['disableactions'] = 'backlink,diff,index,recent,revisions,search,subscribe,unsubscribe,register,login,resendpwd,profile,profile_delete,edit,source,export_raw,check,media';
$conf['hiddenpages'] = 1;
$conf['aclpages'] = 1;
$conf['aclresponse'] = 'denied';
