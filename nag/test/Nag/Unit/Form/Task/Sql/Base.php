<?php
/**
 * Task form test base for the SQL driver.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Nag
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/nag
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License, version 2
 */

/**
 * Task form test base for the SQL driver.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPLv2). If you did not
 * receive this file, see http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @category   Horde
 * @package    Nag
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/nag
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License, version 2
 */
class Nag_Unit_Form_Task_Sql_Base extends Nag_Unit_Form_Task_Base
{
    static $callback;

    static public function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::getDb();
        self::createSqlShares(self::$setup);
        list($share, $other_share) = self::_createDefaultShares();
    }

    static protected function getDb()
    {
        call_user_func_array(self::$callback, array());
    }
}
