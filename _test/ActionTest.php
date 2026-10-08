<?php

namespace dokuwiki\plugin\robot404\test;

use action_plugin_robot404;
use dokuwiki\Extension\Event;
use DokuWikiTest;
use TestRequest;
use TestResponse;

/**
 * Tests for the action component of the robot404 plugin
 *
 * Requests that a robot gets refused end the script, so they cannot run here; they
 * are covered by docker/smoke.sh. Status codes and headers are only visible with
 * Xdebug loaded, which the docker test service provides.
 *
 * @group plugin_robot404
 * @group plugins
 */
class ActionTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['robot404'];

    protected const BROWSER = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/129.0.0.0 Safari/537.36';
    protected const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();
        global $conf;
        $conf['indexdelay'] = 0; // so regular pages are announced as index,follow
        $conf['hidepages'] = '^:hidden:';
    }

    /**
     * A fresh plugin instance, so it picks up the configuration set by the test
     *
     * @param array $config plugin settings to override
     * @return action_plugin_robot404
     */
    protected function plugin(array $config = []): action_plugin_robot404
    {
        global $conf;
        $conf['plugin']['robot404'] = $config;
        return new action_plugin_robot404();
    }

    /**
     * Run a request against doku.php
     *
     * @param string $query query string, without the leading '?'
     * @param string $agent User-Agent header to send
     * @param array $config plugin settings to override
     * @return TestResponse
     */
    protected function request(string $query, string $agent = self::BROWSER, array $config = []): TestResponse
    {
        global $conf;
        $conf['plugin']['robot404'] = $config;
        $request = new TestRequest();
        $request->setServer('HTTP_USER_AGENT', $agent);
        return $request->get([], '/doku.php?' . $query);
    }

    /**
     * @return array[] [User-Agent, expected]
     */
    public static function provideUserAgents(): array
    {
        return [
            'Googlebot' => [self::GOOGLEBOT, true],
            'Bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', true],
            'AdSense' => ['Mediapartners-Google', true],
            'Yahoo Slurp' => ['Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)', true],
            'Baidu' => ['Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)', true],
            'GPTBot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.2', true],
            'Chrome' => [self::BROWSER, false],
            'Firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', false],
            'none' => ['', false],
        ];
    }

    /**
     * @dataProvider provideUserAgents
     */
    public function testIsRobotByUserAgent(string $agent, bool $expected): void
    {
        global $INPUT;
        $INPUT->server->set('HTTP_USER_AGENT', $agent);

        $this->assertSame($expected, $this->plugin()->isRobot());
    }

    public function testIsRobotWithConfiguredUserAgents(): void
    {
        global $INPUT;
        $plugin = $this->plugin(['useragents' => '^curl\/|MyCrawler']);

        $INPUT->server->set('HTTP_USER_AGENT', 'curl/8.10.1');
        $this->assertTrue($plugin->isRobot());

        $INPUT->server->set('HTTP_USER_AGENT', 'Mozilla/5.0 (compatible; mycrawler/1.0)');
        $this->assertTrue($plugin->isRobot(), 'matching is case insensitive');

        $INPUT->server->set('HTTP_USER_AGENT', self::GOOGLEBOT);
        $this->assertFalse($plugin->isRobot(), 'the default list is replaced');
    }

    public function testEmptyUserAgentsMatchesNobody(): void
    {
        global $INPUT;
        $INPUT->server->set('HTTP_USER_AGENT', self::GOOGLEBOT);

        $this->assertFalse($this->plugin(['useragents' => ''])->isRobot());
    }

    public function testIsRobotByParameter(): void
    {
        global $INPUT;
        $INPUT->server->set('HTTP_USER_AGENT', self::BROWSER);
        $INPUT->set('isrobot404', '1');

        $this->assertTrue($this->plugin()->isRobot());
    }

    public function testIsDisallowedAction(): void
    {
        global $conf;
        $plugin = $this->plugin();

        $this->assertTrue($plugin->isDisallowedAction('login'));
        $this->assertTrue($plugin->isDisallowedAction('diff'));
        $this->assertTrue($plugin->isDisallowedAction('export_raw'));
        $this->assertFalse($plugin->isDisallowedAction('show'));
        $this->assertFalse($plugin->isDisallowedAction('export_xhtml'));

        $conf['disableactions'] = 'export_xhtml';
        $this->assertTrue($plugin->isDisallowedAction('export_xhtml'), 'disabled in DokuWiki');
    }

    public function testIsDisallowedActionKnowsActionsDokuWikiCannotOffer(): void
    {
        global $conf;
        $plugin = $this->plugin(['disableactions' => '']);
        $this->assertFalse($plugin->isDisallowedAction('register'));

        $conf['openregister'] = 0;
        $this->assertTrue($plugin->isDisallowedAction('register'));
    }

    public function testIsHiddenPage(): void
    {
        global $ID;

        $ID = 'hidden:page';
        $this->assertTrue($this->plugin()->isHiddenPage());
        $this->assertFalse($this->plugin(['hiddenpages' => 0])->isHiddenPage());

        $ID = 'wiki:syntax';
        $this->assertFalse($this->plugin()->isHiddenPage());
    }

    public function testIsAclPage(): void
    {
        global $ID, $INPUT, $USERINFO, $conf;

        // _test/conf/acl.auth.php: "private:*  @ALL  0"
        $ID = 'private:page';
        $this->assertTrue($this->plugin()->isAclPage());
        $this->assertFalse($this->plugin(['aclpages' => 0])->isAclPage());

        $ID = 'private:';
        $this->assertTrue($this->plugin()->isAclPage(), 'namespace');

        $ID = 'wiki:syntax';
        $this->assertFalse($this->plugin()->isAclPage());

        $ID = 'private:page';
        $INPUT->server->set('REMOTE_USER', 'testuser'); // the superuser in _test/conf/local.php
        $USERINFO = ['grps' => ['admin']];
        $this->assertFalse($this->plugin()->isAclPage(), 'user may read');

        $INPUT->server->remove('REMOTE_USER');
        $conf['useacl'] = 0;
        $this->assertFalse($this->plugin()->isAclPage(), 'ACL disabled');
    }

    public function testRegularPageIsShownToRobots(): void
    {
        // a refused robot would have ended the test run here
        $response = $this->request('id=wiki:syntax', self::GOOGLEBOT);

        $this->assertSame('index,follow', $response->queryHTML('meta[name="robots"]')->attr('content'));
    }

    /**
     * @requires function xdebug_get_headers
     */
    public function testRegularPageGetsNoStatusOrHeader(): void
    {
        $response = $this->request('id=wiki:syntax', self::GOOGLEBOT);

        $this->assertNull($response->getStatusCode());
        $this->assertSame([], $response->getHeader('X-Robots-Tag'));
    }

    /**
     * @requires function xdebug_get_headers
     */
    public function testUnreadablePageIs404ForVisitors(): void
    {
        $response = $this->request('id=private:page');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertSame(1, $response->queryHTML('#dw__login')->count(), 'login form is still offered');
        $this->assertSame('X-Robots-Tag: noindex,nofollow', $response->getHeader('X-Robots-Tag'));
    }

    /**
     * @requires function xdebug_get_headers
     */
    public function testUnreadablePageKeepsStatusWithoutAclpages(): void
    {
        $response = $this->request('id=private:page', self::BROWSER, ['aclpages' => 0]);

        $this->assertNull($response->getStatusCode());
        $this->assertSame(1, $response->queryHTML('#dw__login')->count());
    }

    /**
     * @requires function xdebug_get_headers
     */
    public function testHiddenPageIsMarkedNoindexForVisitors(): void
    {
        saveWikiText('hidden:page', 'hidden', 'test');
        $response = $this->request('id=hidden:page');

        $this->assertNull($response->getStatusCode());
        // DokuWiki itself already sends noindex,nofollow for hidden pages, only the header is the plugin's
        $this->assertSame('noindex,nofollow', $response->queryHTML('meta[name="robots"]')->attr('content'));
        $this->assertSame('X-Robots-Tag: noindex,nofollow', $response->getHeader('X-Robots-Tag'));
    }

    /**
     * A disabled action falls back to "show", which DokuWiki would announce as index,follow
     *
     * @requires function xdebug_get_headers
     */
    public function testDisabledActionIsMarkedNoindexForVisitors(): void
    {
        global $conf;
        $conf['disableactions'] = 'backlink';
        $response = $this->request('id=wiki:syntax&do=backlink', self::BROWSER, ['disableactions' => '']);

        $this->assertSame('noindex,nofollow', $response->queryHTML('meta[name="robots"]')->attr('content'));
        $this->assertSame('X-Robots-Tag: noindex,nofollow', $response->getHeader('X-Robots-Tag'));
    }

    /**
     * doku.php also takes the action from the idx parameter, not only from do
     */
    public function testDisabledIndexByIdxIsMarkedNoindexForVisitors(): void
    {
        global $conf;
        $conf['disableactions'] = 'index';
        $response = $this->request('id=wiki:syntax&idx=wiki', self::BROWSER, ['disableactions' => '']);

        $this->assertSame('noindex,nofollow', $response->queryHTML('meta[name="robots"]')->attr('content'));
    }

    public function testRobotsMetaKeepsOtherDirectives(): void
    {
        global $ID;
        $ID = 'hidden:page';
        $data = ['meta' => [['name' => 'robots', 'content' => 'index,follow,noarchive']]];
        $event = new Event('TPL_METAHEADER_OUTPUT', $data);

        $this->plugin()->handleMetaheaderOutput($event, null);

        $this->assertSame('noindex,nofollow,noarchive', $event->data['meta'][0]['content']);
    }
}
