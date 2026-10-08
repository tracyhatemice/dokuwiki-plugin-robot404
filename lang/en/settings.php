<?php

/**
 * English language file for the robot404 plugin settings
 */

$lang['useragents'] = 'Regular expression (case insensitive, without delimiters) matched against the User-Agent header to recognize robots. Add <code>?isrobot404=1</code> to a URL to test as a robot.';
$lang['disableactions'] = 'Which actions should be considered disallowed for robots. XML Syndication refuses the feed (feed.php) to robots; mind that feed readers often identify like robots. Note that the actions that are disabled in the main DokuWiki configuration are also considered disallowed for robots in this plugin (even if not selected here).';
$lang['hiddenpages'] = 'Should hidden pages (see <code>hidepages</code>) also result in 404 (page not found) for robots?';
$lang['aclpages'] = 'Should pages and namespaces the visitor may not read result in 404 (page not found)? Robots get a bare 404; see <code>aclresponse</code> for everyone else.';
$lang['aclresponse'] = 'With <code>aclpages</code> on, what visitors other than robots get for pages they may not read.';
$lang['aclresponse_o_denied'] = 'The "Permission Denied" page with its login form, with status 404';
$lang['aclresponse_o_plain'] = 'A bare 404 like robots get (logging in still works through ?do=login)';

// labels for the choices of disableactions that DokuWiki has no button text for
$lang['disableactions_diff'] = 'Show differences';
$lang['disableactions_subscription'] = 'Subscribe/Unsubscribe';
$lang['disableactions_profile_delete'] = 'Delete Own Account';
$lang['disableactions_wikicode'] = 'View source/Export Raw';
$lang['disableactions_check'] = 'Check';
$lang['disableactions_rss'] = 'XML Syndication (RSS)';
$lang['disableactions_other'] = 'Other actions (comma separated)';
