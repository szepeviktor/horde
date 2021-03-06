<?php
/**
 * Copyright 2015-2016 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2015-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

/**
 * Tests for the Horde_Imap_Client_Fetch_Results object using the
 * Horde_Imap_Client_Data_Fetch_Pop3 object for data storage.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2015-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class Horde_Imap_Client_Fetch_Results_FetchPop3Test
extends Horde_Imap_Client_Fetch_Results_TestBase
{
    protected function _setUp()
    {
        $this->ob_class = 'Horde_Imap_Client_Data_Fetch_Pop3';
        $this->ob_ids = array('a', 'b', 'c');
    }

}
