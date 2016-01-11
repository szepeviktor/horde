<?php
/**
 * Copyright 2016 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */

/**
 * This class tests the file backend.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Cache
 */
class Horde_Cache_FileTest extends Horde_Cache_TestBase
{
    protected function _getCache($params = array())
    {
        $this->dir = sys_get_temp_dir() . '/horde_cache_test';
        mkdir($this->dir);
        return new Horde_Cache(
            new Horde_Cache_Storage_File(array_merge(
                array(
                    'dir'    => $this->dir,
                    'no_gc'  => true,
                    'prefix' => 'horde_cache_test'
                ),
                $params
            ))
        );
    }

    public function testSubdirectories()
    {
        $this->tearDown();
        $this->cache = $this->_getCache(array('sub' => 2));
        if (!$this->cache) {
            $this->markTestSkipped($this->reason);
        }
        $this->assertNull($this->cache->set('key1', 'data1', 0));
        $this->assertNull($this->cache->set('key2', 'data2', 0));
        $this->assertEquals(
            array($this->dir . '/7/', $this->dir . '/c/'),
            glob($this->dir . '/*', GLOB_MARK)
        );
        $this->assertEquals(
            array($this->dir . '/7/8/'),
            glob($this->dir . '/7/*', GLOB_MARK)
        );
        $this->assertEquals(
            array($this->dir . '/c/2/'),
            glob($this->dir . '/c/*', GLOB_MARK)
        );
        $this->assertEquals(
            array($this->dir . '/7/8/horde_cache_test78f825aaa0103319aaa1a30bf4fe3ada'),
            glob($this->dir . '/7/8/*', GLOB_MARK)
        );
        $this->assertEquals(
            array($this->dir . '/c/2/horde_cache_testc2add694bf942dc77b376592d9c862cd'),
            glob($this->dir . '/c/2/*', GLOB_MARK)
        );
    }

    public function tearDown()
    {
        parent::tearDown();
        system('rm -r ' . $this->dir);
    }
}