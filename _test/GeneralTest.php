<?php

namespace dokuwiki\plugin\robot404\test;

use dokuwiki\MailUtils;
use dokuwiki\plugin\config\core\ConfigParser;
use DokuWikiTest;

/**
 * General tests for the robot404 plugin
 *
 * @group plugin_robot404
 * @group plugins
 */
class GeneralTest extends DokuWikiTest
{
    /**
     * Simple test to make sure the plugin.info.txt is in correct format
     */
    public function testPluginInfo(): void
    {
        $file = __DIR__ . '/../plugin.info.txt';
        $this->assertFileExists($file);

        $info = confToHash($file);

        $this->assertArrayHasKey('base', $info);
        $this->assertArrayHasKey('author', $info);
        $this->assertArrayHasKey('email', $info);
        $this->assertArrayHasKey('date', $info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('desc', $info);
        $this->assertArrayHasKey('url', $info);

        $this->assertEquals('robot404', $info['base']);
        $this->assertMatchesRegularExpression('/^https?:\/\//', $info['url']);
        $this->assertTrue(MailUtils::isValid($info['email']));
        $this->assertMatchesRegularExpression('/^\d\d\d\d-\d\d-\d\d$/', $info['date']);
        $this->assertTrue(false !== strtotime($info['date']));
    }

    /**
     * Test to ensure that every conf['...'] entry in conf/default.php has a corresponding meta['...'] entry in
     * conf/metadata.php.
     */
    public function testPluginConf(): void
    {
        $conf_file = __DIR__ . '/../conf/default.php';
        $meta_file = __DIR__ . '/../conf/metadata.php';

        if (!file_exists($conf_file) && !file_exists($meta_file)) {
            self::markTestSkipped('No config files exist -> skipping test');
        }

        if (file_exists($conf_file)) {
            include($conf_file);
        }
        if (file_exists($meta_file)) {
            include($meta_file);
        }

        $this->assertEquals(
            gettype($conf),
            gettype($meta),
            'Both ' . DOKU_PLUGIN . 'robot404/conf/default.php and ' . DOKU_PLUGIN . 'robot404/conf/metadata.php have to exist and contain the same keys.'
        );

        if ($conf !== null && $meta !== null) {
            foreach ($conf as $key => $value) {
                $this->assertArrayHasKey(
                    $key,
                    $meta,
                    'Key $meta[\'' . $key . '\'] missing in ' . DOKU_PLUGIN . 'robot404/conf/metadata.php'
                );
            }

            foreach ($meta as $key => $value) {
                $this->assertArrayHasKey(
                    $key,
                    $conf,
                    'Key $conf[\'' . $key . '\'] missing in ' . DOKU_PLUGIN . 'robot404/conf/default.php'
                );
            }
        }
    }

    /**
     * The configuration manager parses conf/default.php as text instead of including it, so it
     * has to come up with the same defaults DokuWiki uses
     */
    public function testPluginConfParsedLikeIncluded(): void
    {
        $conf = [];
        include __DIR__ . '/../conf/default.php';

        $this->assertEquals($conf, (new ConfigParser())->parse(__DIR__ . '/../conf/default.php'));
    }
}
