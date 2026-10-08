<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

/**
 * DokuWiki Plugin robot404 (Action Component)
 *
 * Answers web crawlers with "404 Not Found" for disallowed actions, hidden pages and pages
 * they may not read, and gives DokuWiki's "Permission Denied" screen a 404 status for everyone.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Ahmet Sacan <ahmetdevel@umich.edu>
 * @author  tracyhatemice <tracyhatemice@users.noreply.github.com>
 */
class action_plugin_robot404 extends ActionPlugin
{
    /** @inheritDoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleActPreprocess');
        $controller->register_hook('FEED_OPTS_POSTPROCESS', 'BEFORE', $this, 'handleFeedOptsPostprocess');
        // robots we fail to recognize are at least told not to index what they would have been refused
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'handleHeadersSend');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handleMetaheaderOutput');
    }

    /**
     * Refuse robots with a bare 404, and answer everyone else's Permission Denied with a 404
     *
     * Runs again for every action DokuWiki falls back to, e.g. "denied" when the ACL check failed.
     * Depending on the aclresponse setting, visitors get the Permission Denied page with a 404
     * status or a bare 404 like robots.
     *
     * @see https://www.dokuwiki.org/devel:events:ACTION_ACT_PREPROCESS
     * @param Event $event Event object, data is the action name
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleActPreprocess(Event $event, $param)
    {
        $act = act_clean($event->data);

        if ($this->isRobot()) {
            if ($this->isHiddenPage() || $this->isAclPage() || $this->isDisallowedAction($act)) {
                http_status(404);
                exit;
            }
        } elseif ($act === 'denied' && $this->isAclPage()) {
            http_status(404);
            if ($this->getConf('aclresponse') === 'plain') exit;
        }
    }

    /**
     * Refuse feeds to robots when the rss action is disallowed for them
     *
     * feed.php does not go through DokuWiki's actions, so ACTION_ACT_PREPROCESS never sees it.
     *
     * @see https://www.dokuwiki.org/devel:events:FEED_OPTS_POSTPROCESS
     * @param Event $event Event object, data holds the feed options
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleFeedOptsPostprocess(Event $event, $param)
    {
        if ($this->isRobot() && $this->isDisallowedAction('rss')) {
            http_status(404);
            exit;
        }
    }

    /**
     * Add an X-Robots-Tag header to responses a robot would have been refused
     *
     * @see https://www.dokuwiki.org/devel:events:ACTION_HEADERS_SEND
     * @param Event $event Event object, data is the list of headers
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleHeadersSend(Event $event, $param)
    {
        if (!$this->isRefusedToRobots()) return;

        $event->data[] = 'X-Robots-Tag: noindex,nofollow';
    }

    /**
     * Make the robots meta tag say noindex,nofollow on pages a robot would have been refused
     *
     * @see https://www.dokuwiki.org/devel:events:TPL_METAHEADER_OUTPUT
     * @param Event $event Event object, data holds the head elements
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleMetaheaderOutput(Event $event, $param)
    {
        if (!$this->isRefusedToRobots()) return;

        $found = false;
        foreach ($event->data['meta'] ?? [] as $key => $meta) {
            if (($meta['name'] ?? '') !== 'robots') continue;

            // keep any other directive, e.g. noarchive
            $directives = array_map(trim(...), explode(',', $meta['content'] ?? ''));
            $directives = array_diff($directives, ['', 'index', 'follow', 'noindex', 'nofollow']);
            $event->data['meta'][$key]['content'] = implode(',', ['noindex', 'nofollow', ...$directives]);
            $found = true;
        }
        if (!$found) {
            $event->data['meta'][] = ['name' => 'robots', 'content' => 'noindex,nofollow'];
        }
    }

    /**
     * Is the client a robot?
     *
     * Matches the User-Agent against the useragents setting, the same way DokuWiki applies its
     * regex settings. Adding ?isrobot404=1 to a URL simulates a robot for testing.
     *
     * @return bool
     */
    public function isRobot(): bool
    {
        global $INPUT;

        if ($INPUT->bool('isrobot404')) return true;

        $pattern = (string)$this->getConf('useragents');
        $agent = $INPUT->server->str('HTTP_USER_AGENT');
        if ($pattern === '' || $agent === '') return false;

        return (bool)preg_match('/' . $pattern . '/ui', $agent);
    }

    /**
     * Is the current page hidden (hidepages) and should hidden pages be refused?
     *
     * @return bool
     */
    public function isHiddenPage(): bool
    {
        global $ID;

        return $this->getConf('hiddenpages') && isHiddenPage($ID);
    }

    /**
     * May the current user not read the current page (or the namespace it lives in)?
     *
     * Always false unless the aclpages setting is enabled.
     *
     * @return bool
     */
    public function isAclPage(): bool
    {
        global $ID, $INFO;

        if (!$this->getConf('aclpages')) return false;

        // like ActionRouter::checkAction(), reuse the permission pageinfo() already looked up
        $perm = $INFO['perm'] ?? auth_quickaclcheck($ID);
        return $perm < AUTH_READ;
    }

    /**
     * Is the action disabled in DokuWiki or disallowed for robots by this plugin?
     *
     * @param string $act action name
     * @return bool
     */
    public function isDisallowedAction(string $act): bool
    {
        if (!actionOK($act)) return true;

        $disallowed = array_map(trim(...), explode(',', (string)$this->getConf('disableactions')));
        return in_array($act, $disallowed, true);
    }

    /**
     * Would a robot have been refused the current request?
     *
     * @return bool
     */
    protected function isRefusedToRobots(): bool
    {
        global $ACT;

        // a disabled action falls back to "show", so also check the action that was asked for
        return $this->isHiddenPage()
            || $this->isAclPage()
            || $this->isDisallowedAction(act_clean($ACT))
            || $this->isDisallowedAction($this->requestedAction());
    }

    /**
     * The action the client asked for, picked from the request the same way doku.php does
     *
     * @return string
     */
    protected function requestedAction(): string
    {
        global $INPUT;

        if ($INPUT->server->has('HTTP_X_DOKUWIKI_DO')) {
            return act_clean($INPUT->server->str('HTTP_X_DOKUWIKI_DO'));
        }
        if ($INPUT->str('idx', '', true) !== '') return 'index';
        return act_clean($INPUT->param('do'));
    }
}
