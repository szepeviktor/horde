<?php
/**
 * Remote access to a PEAR server.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Pear
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Pear
 */

/**
 * Remote access to a PEAR server.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Pear
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Pear
 */
class Horde_Pear_Remote
{
    /**
     * The tool generator for accessing the REST interface of the PEAR server.
     *
     * @var Horde_Pear_Rest_Access
     */
    private $_access;

    /**
     * Constructor
     *
     * @param string                 $server The server name.
     * @param Horde_Pear_Rest_Access $access The accessor to the PEAR server
     *                                       rest interface.
     */
    public function __construct(
        $server = 'pear.horde.org',
        Horde_Pear_Rest_Access $access = null
    )
    {
        if ($access === null) {
            $this->_access = new Horde_Pear_Rest_Access();
        } else {
            $this->_access = $access;
        }
        $this->_access->setServer($server);
    }

    /**
     * Return the list of package names.
     *
     * @return array The package names.
     */
    public function listPackages()
    {
        return $this->_access->getPackageList()->listPackages();
    }

    /**
     * Retrieve the dowlnload location for the latest package release.
     *
     * @param string $package   The package name.
     * @param string $stability The stability the release should have.
     *
     * @return string The URI for downloading the release.
     *
     * @throws Horde_Pear_Exception In case there is no release for
     *                              this package with the specified
     *                              stability level.
     */
    public function getLatestDownloadUri($package, $stability = 'stable')
    {
        if ($latest = $this->_access->getLatestRelease($package, $stability)) {
            return $this->_access->getRelease($package, $latest)->getDownloadUri();
        } else {
            throw new Horde_Pear_Exception(
                sprintf(
                    'No release of stability "%s" for package "%s".',
                    $stability,
                    $package
                )
            );
        }
    }
}