<?php

class Shout_Driver_Ldap extends Shout_Driver
{
    var $_ldapKey;  // Index used for storing objects
    var $_appKey;   // Index used for moving info to/from the app

    /**
     * Handle for the current database connection.
     * @var object LDAP $_LDAP
     */
    private $_LDAP;

    /**
     * Boolean indicating whether or not we're connected to the LDAP
     * server.
     * @var boolean $_connected
     */
    private $_connected = false;


    /**
    * Constructs a new Shout LDAP driver object.
    *
    * @param array  $params    A hash containing connection parameters.
    */
    function __construct($params = array())
    {
        parent::__construct($params);
        $this->_connect();

        /* These next lines will translate between indexes used in the
         * application and LDAP.  The rationale is that translation here will
         * help make Congregation more driver-independant.  The keys used to
         * contruct user arrays should be more appropriate to human-legibility
         * (name instead of 'cn' and email instead of 'mail').  This translation
         * is only needed because LDAP indexes users based on an arbitrary
         * attribute and the application indexes by extension/context.  In my
         * environment users are indexed by their 'mail' attribute and others
         * may index based on 'cn' or 'uid'.  Any time a new $prefs['uid'] needs
         * to be supported, this function should be checked and possibly
         * extended to handle that translation.
         */
        switch($this->_params['uid']) {
        case 'cn':
            $this->_ldapKey = 'cn';
            $this->_appKey = 'name';
            break;
        case 'mail':
            $this->_ldapKey = 'mail';
            $this->_appKey = 'email';
            break;
        case 'uid':
            # FIXME Probably a better app key to map here
            # There is no value that maps uid to LDAP so we can choose to use
            # either extension or name, or anything really.  I want to
            # support it since it's a very common DN attribute.
            # Since it's entirely administrator's preference, I'll
            # set it to name for now
            $this->_ldapKey = 'uid';
            $this->_appKey = 'name';
            break;
        case 'voiceMailbox':
            $this->_ldapKey = 'voiceMailbox';
            $this->_appKey = 'extension';
            break;
        }
    }

    /**
     * Get a list of users valid for the contexts
     *
     * @param string $context  Context in which to search
     *
     * @return array User information indexed by voice mailbox number
     */
    public function getExtensions($context)
    {

        static $entries = array();
        if (isset($entries[$context])) {
            return $entries[$context];
        }

        $this->_params['basedn'];

        $filter  = '(&';
        $filter .= '(objectClass=AsteriskVoiceMail)';
        $filter .= '(AstContext='.$context.')';
        $filter .= ')';

        $attributes = array(
            'cn',
            'mail',
            'AstVoicemailMailbox',
            'AstVoicemailPassword',
            'AstVoicemailOptions',
            'AstVoicemailPager'
        );

        $search = ldap_search($this->_LDAP, $this->_params['basedn'], $filter, $attributes);
        if ($search === false) {
            throw new Shout_Exception("Unable to search directory: " .
                ldap_error($this->_LDAP), ldap_errno($this->_LDAP));
        }

        $res = ldap_get_entries($this->_LDAP, $search);
        if ($res === false) {
            throw new Shout_Exception("Unable to fetch results from directory: " .
                ldap_error($this->_LDAP), ldap_errno($this->_LDAP));
        }

        // ATTRIBUTES RETURNED FROM ldap_get_entries ARE ALL LOWER CASE!!
        // It's a PHP thing.
        $entries[$context] = array();
        $i = 0;
        while ($i < $res['count']) {
            list($extension) = explode('@', $res[$i]['astvoicemailmailbox'][0]);
            $entries[$context][$extension] = array('extension' => $extension);

            $j = 0;
            $entries[$context][$extension]['mailboxopts'] = array();
            while ($j < @$res[$i]['astvoicemailoptions']['count']) {
                $entries[$context][$extension]['mailboxopts'][] =
                    $res[$i]['astvoicemailoptions'][$j];
                $j++;
            }

            $entries[$context][$extension]['mailboxpin'] =
                $res[$i]['astvoicemailpassword'][0];

            $entries[$context][$extension]['name'] =
                $res[$i]['cn'][0];

            @$entries[$context][$extension]['email'] =
                $res[$i]['mail'][0];

            @$entries[$context][$extension]['pageremail'] =
                $res[$i]['astvoicemailpager'][0];

            $i++;

        }

        ksort($entries[$context]);

        return($entries[$context]);
    }

    /**
     * Get a context's properties
     *
     * @param string $context Context to get properties for
     *
     * @return integer Bitfield of properties valid for this context
     */
    public function getContextProperties($context)
    {

        $res = @ldap_search($this->_LDAP,
            SHOUT_ASTERISK_BRANCH.','.$this->_params['basedn'],
            "(&(objectClass=asteriskObject)(context=$context))",
            array('objectClass'));
        if(!$res) {
            return PEAR::raiseError(_("Unable to get properties for $context"));
        }

        $res = ldap_get_entries($this->_LDAP, $res);

        $properties = 0;
        if ($res['count'] != 1) {
            return PEAR::raiseError(_("Incorrect number of properties found
for $context"));
        }

        foreach ($res[0]['objectclass'] as $objectClass) {
            switch ($objectClass) {
                case "vofficeCustomer":
                    # FIXME What does this objectClass really do for us?
                    $properties = $properties | SHOUT_CONTEXT_CUSTOMERS;
                    break;

                case "asteriskExtensions":
                    $properties = $properties | SHOUT_CONTEXT_EXTENSIONS;
                    break;

                case "asteriskMusicOnHold":
                    $properties = $properties | SHOUT_CONTEXT_MOH;
                    break;

                case "asteriskMeetMe":
                    $properties = $properties | SHOUT_CONTEXT_CONFERENCE;
                    break;
            }
        }
        return $properties;
    }

    /**
     * Get a context's dialplan and return as a multi-dimensional associative
     * array
     *
     * @param string $context Context to return extensions for
     *
     * @param boolean $preprocess Parse includes and barelines and add their
     *                            information into the extensions array
     *
     * @return array Multi-dimensional associative array of extensions data
     *
     */
    public function getDialplan($context, $preprocess = false)
    {
        # FIXME Implement preprocess functionality.  Don't forget to cache!
        static $dialplans = array();
        if (isset($dialplans[$context])) {
            return $dialplans[$context];
        }

        $res = @ldap_search($this->_LDAP,
            SHOUT_ASTERISK_BRANCH.','.$this->_params['basedn'],
            "(&(objectClass=".SHOUT_CONTEXT_EXTENSIONS_OBJECTCLASS.")(context=$context))",
            array(SHOUT_DIALPLAN_EXTENSIONLINE_ATTRIBUTE, SHOUT_DIALPLAN_INCLUDE_ATTRIBUTE,
                SHOUT_DIALPLAN_IGNOREPAT_ATTRIBUTE, 'description',
                SHOUT_DIALPLAN_BARELINE_ATTRIBUTE));
        if (!$res) {
            return PEAR::raiseError("Unable to locate any extensions " .
            "underneath ".SHOUT_ASTERISK_BRANCH.",".$this->_params['basedn'] .
            " matching those search filters");
        }

        $res = ldap_get_entries($this->_LDAP, $res);
        $dialplans[$context] = array();
        $dialplans[$context]['name'] = $context;
        $i = 0;
        while ($i < $res['count']) {
            # Handle extension lines
            if (isset($res[$i][strtolower(SHOUT_DIALPLAN_EXTENSIONLINE_ATTRIBUTE)])) {
                $j = 0;
                while ($j < $res[$i][strtolower(SHOUT_DIALPLAN_EXTENSIONLINE_ATTRIBUTE)]['count']) {
                    @$line = $res[$i][strtolower(SHOUT_DIALPLAN_EXTENSIONLINE_ATTRIBUTE)][$j];

                    # Basic sanity check for length.  FIXME
                    if (strlen($line) < 5) {
                        break;
                    }
                    # Can't use strtok here because there may be commass in the
                    # arg string

                    # Get the extension
                    $token1 = strpos($line, ',');
                    $token2 = strpos($line, ',', $token1 + 1);
                    $token3 = strpos($line, '(', $token2 + 1);

                    $extension = substr($line, 0, $token1);
                    if (!isset($dialplans[$context]['extensions'][$extension])) {
                        $dialplan[$context]['extensions'][$extension] = array();
                    }
                    $token1++;
                    # Get the priority
                    $priority = substr($line, $token1, $token2 - $token1);
                    $dialplans[$context]['extensions'][$extension][$priority] =
                        array();
                    $token2++;

                    # Get Application and args
                    $application = substr($line, $token2, $token3 - $token2);

                    if ($token3) {
                        $application = substr($line, $token2, $token3 - $token2);
                        $args = substr($line, $token3);
                        $args = preg_replace('/^\(/', '', $args);
                        $args = preg_replace('/\)$/', '', $args);
                    } else {
                        # This application must not have any args
                        $application = substr($line, $token2);
                        $args = '';
                    }

                    # Merge all that data into the returning array
                    $dialplans[$context]['extensions'][$extension][$priority]['application'] =
                        $application;
                    $dialplans[$context]['extensions'][$extension][$priority]['args'] =
                        $args;
                    $j++;
                }

                # Sort the extensions data
                foreach ($dialplans[$context]['extensions'] as
                    $extension => $data) {
                    ksort($dialplans[$context]['extensions'][$extension]);
                }
                uksort($dialplans[$context]['extensions'],
                    array(new Shout, "extensort"));
            }
            # Handle include lines
            if (isset($res[$i]['asteriskincludeline'])) {
                $j = 0;
                while ($j < $res[$i]['asteriskincludeline']['count']) {
                    @$line = $res[$i]['asteriskincludeline'][$j];
                    $dialplans[$context]['includes'][$j] = $line;
                    $j++;
                }
            }

            # Handle ignorepat
            if (isset($res[$i]['asteriskignorepat'])) {
                $j = 0;
                while ($j < $res[$i]['asteriskignorepat']['count']) {
                    @$line = $res[$i]['asteriskignorepat'][$j];
                    $dialplans[$context]['ignorepats'][$j] = $line;
                    $j++;
                }
            }
            # Handle ignorepat
            if (isset($res[$i]['asteriskextensionbareline'])) {
                $j = 0;
                while ($j < $res[$i]['asteriskextensionbareline']['count']) {
                    @$line = $res[$i]['asteriskextensionbareline'][$j];
                    $dialplans[$context]['barelines'][$j] = $line;
                    $j++;
                }
            }

            # Increment object
            $i++;
        }
        return $dialplans[$context];
    }

    /**
     * Get the limits for the current user, the user's context, and global
     * Return the most specific values in every case.  Return default values
     * where no data is found.  If $extension is specified, $context must
     * also be specified.
     *
     * @param optional string $context Context to search
     *
     * @param optional string $extension Extension/user to search
     *
     * @return array Array with elements indicating various limits
     */
     # FIXME Figure out how this fits into Shout/Congregation better
    public function getLimits($context = null, $extension = null)
    {

        $limits = array('telephonenumbersmax',
                        'voicemailboxesmax',
                        'asteriskusersmax');

        if(!is_null($extension) && is_null($context)) {
            return PEAR::raiseError("Extension specified but no context " .
                "given.");
        }

        if (!is_null($context) && isset($limits[$context])) {
            if (!is_null($extension) &&
                isset($limits[$context][$extension])) {
                return $limits[$context][$extension];
            }
            return $limits[$context];
        }

        # Set some default limits (to unlimited)
        static $cachedlimits = array();
        # Initialize the limits with defaults
        if (count($cachedlimits) < 1) {
            foreach ($limits as $limit) {
                $cachedlimits[$limit] = 99999;
            }
        }

        # Collect the global limits
        $res = @ldap_search($this->_LDAP,
            SHOUT_ASTERISK_BRANCH.','.$this->_params['basedn'],
            '(&(objectClass=asteriskLimits)(cn=globals))',
            $limits);

        if (!$res) {
            return PEAR::raiseError('Unable to search the LDAP server for ' .
                'global limits');
        }

        $res = ldap_get_entries($this->_LDAP, $res);
        # There should only have been one object returned so we'll just take the
        # first result returned
        if ($res['count'] > 0) {
            foreach ($limits as $limit) {
                if (isset($res[0][$limit][0])) {
                    $cachedlimits[$limit] = $res[0][$limit][0];
                }
            }
        } else {
            return PEAR::raiseError("No global object found.");
        }

        # Get limits for the context, if provided
        if (isset($context)) {
            $res = ldap_search($this->_LDAP,
                SHOUT_ASTERISK_BRANCH.','.$this->_params['basedn'],
                "(&(objectClass=asteriskLimits)(cn=$context))");

            if (!$res) {
                return PEAR::raiseError('Unable to search the LDAP server ' .
                    "for $context specific limits");
            }

            $cachedlimits[$context][$extension] = array();
            if ($res['count'] > 0) {
                foreach ($limits as $limit) {
                    if (isset($res[0][$limit][0])) {
                        $cachedlimits[$context][$limit] = $res[0][$limit][0];
                    } else {
                        # If no value is provided use the global limit
                        $cachedlimits[$context][$limit] = $cachedlimits[$limit];
                    }
                }
            } else {

                foreach ($limits as $limit) {
                    $cachedlimits[$context][$limit] =
                        $cachedlimits[$limit];
                }
            }

            if (isset($extension)) {
                $res = @ldap_search($this->_LDAP,
                    SHOUT_USERS_BRANCH.','.$this->_params['basedn'],
                    "(&(objectClass=asteriskLimits)(voiceMailbox=$extension)".
                    "(context=$context))");

                if (!$res) {
                    return PEAR::raiseError('Unable to search the LDAP server '.
                        "for Extension $extension, $context specific limits");
                }

                $cachedlimits[$context][$extension] = array();
                if ($res['count'] > 0) {
                    foreach ($limits as $limit) {
                        if (isset($res[0][$limit][0])) {
                            $cachedlimits[$context][$extension][$limit] =
                                $res[0][$limit][0];
                        } else {
                            # If no value is provided use the context limit
                            $cachedlimits[$context][$extension][$limit] =
                                $cachedlimits[$context][$limit];
                        }
                    }
                } else {
                    foreach ($limits as $limit) {
                        $cachedlimits[$context][$extension][$limit] =
                            $cachedlimits[$context][$limit];
                    }
                }
                return $cachedlimits[$context][$extension];
            }
            return $cachedlimits[$context];
        }
    }

    /**
     * Save a user to the LDAP tree
     *
     * @param string $context Context to which the user should be added
     *
     * @param string $extension Extension to be saved
     *
     * @param array $userdetails Phone numbers, PIN, options, etc to be saved
     *
     * @return TRUE on success, PEAR::Error object on error
     */
    public function saveUser($context, $extension, $userdetails)
    {
        $ldapKey = &$this->_ldapKey;
        $appKey = &$this->_appKey;
        # FIXME Access Control/Authorization
        if (
            !(Shout::checkRights("shout:contexts:$context:users", PERMS_EDIT, 1))
            &&
            !($userdetails[$appKey] == Auth::getAuth())
            ) {
            return PEAR::raiseError("No permission to modify users in this " .
                "context.");
        }

        $contexts = &$this->getContexts();
//         $domain = $contexts[$context]['domain'];

        # Check to ensure the extension is unique within this context
        $filter = "(&(objectClass=asteriskVoiceMailbox)(context=$context))";
        $reqattrs = array('dn', $ldapKey);
        $res = @ldap_search($this->_LDAP,
            SHOUT_USERS_BRANCH . ',' . $this->_params['basedn'],
            $filter, $reqattrs);
        if (!$res) {
            return PEAR::raiseError('Unable to check directory for duplicate extension: ' .
                ldap_error($this->_LDAP));
        }
        if (($res['count'] > 1) ||
            ($res['count'] != 0 &&
            !in_array($res[0][$ldapKey], $userdetails[$appKey]))) {
            return PEAR::raiseError('Duplicate extension found.  Not saving changes.');
        }

        $entry = array(
            'cn' => $userdetails['name'],
            'sn' => $userdetails['name'],
            'mail' => $userdetails['email'],
            'uid' => $userdetails['email'],
            'voiceMailbox' => $userdetails['newextension'],
            'voiceMailboxPin' => $userdetails['mailboxpin'],
            'context' => $context,
            'asteriskUserDialOptions' => $userdetails['dialopts'],
        );

        if (!empty ($userdetails['telephonenumber'])) {
            $entry['telephoneNumber'] = $userdetails['telephonenumber'];
        }

        $validusers = &$this->getUsers($context);
        if (!isset($validusers[$extension])) {
            # Test to see if we're modifying an existing user that has
            # no telephone system objectClasses and update that object/user
            $rdn = $ldapKey.'='.$userdetails[$appKey].',';
            $branch = SHOUT_USERS_BRANCH.','.$this->_params['basedn'];

            # This test is something of a hack.  I want a cheap way to check
            # for the existance of an object.  I don't want to do a full search
            # so instead I compare that the dn equals the dn.  If the object
            # exists then it'll return true.  If the object doesn't exist,
            # it'll return error.  If it ever returns false something wierd
            # is going on.
            $res = @ldap_compare($this->_LDAP, $rdn.$branch,
                    $ldapKey, $userdetails[$appKey]);
            if ($res === false) {
                # We should never get here: a DN should ALWAYS match itself
                return PEAR::raiseError("Internal Error: " . __FILE__ . " at " .
                    __LINE__);
            } elseif ($res === true) {
                # The object/user exists but doesn't have the Asterisk
                # objectClasses
                $extension = $userdetails['newextension'];

                # $tmp is the minimal information required to establish
                # an account in LDAP as required by the objectClasses.
                # The entry will be fully populated below.
                $tmp = array();
                $tmp['objectClass'] = array(
                    'asteriskUser',
                    'asteriskVoiceMailbox'
                );
                $tmp['voiceMailbox'] = $extension;
                $tmp['context'] = $context;
                $res = @ldap_mod_replace($this->_LDAP, $rdn.$branch, $tmp);
                if (!$res) {
                    return PEAR::raiseError("Unable to modify the user: " .
                        ldap_error($this->_LDAP));
                }

                # Populate the $validusers array to make the edit go smoothly
                # below
                $validusers[$extension] = array();
                $validusers[$extension][$appKey] = $userdetails[$appKey];

                # The remainder of the work is done at the outside of the
                # parent if() like a normal edit.

            } elseif ($res === -1) {
                # We must be adding a new user.
                $entry['objectClass'] = array(
                    'top',
                    'person',
                    'organizationalPerson',
                    'inetOrgPerson',
                    'hordePerson',
                    'asteriskUser',
                    'asteriskVoiceMailbox'
                );

                # Check to see if the maximum number of users for this context
                # has been reached
                $limits = $this->getLimits($context);
                if (is_a($limits, "PEAR_Error")) {
                    return $limits;
                }
                if (count($validusers) >= $limits['asteriskusersmax']) {
                    return PEAR::raiseError('Maximum number of users reached.');
                }

                $res = @ldap_add($this->_LDAP, $rdn.$branch, $entry);
                if (!$res) {
                    return PEAR::raiseError('LDAP Add failed: ' .
                        ldap_error($this->_LDAP));
                }

                return true;
            } elseif (is_a($res, 'PEAR_Error')) {
                # Some kind of internal error; not even sure if this is a
                # possible outcome or not but I'll play it safe.
                return $res;
            }
        }

        # Anything after this point is an edit.

        # Check to see if the object needs to be renamed (DN changed)
        if ($validusers[$extension][$appKey] != $entry[$ldapKey]) {
            $oldrdn = $ldapKey.'='.$validusers[$extension][$appKey];
            $oldparent = SHOUT_USERS_BRANCH.','.$this->_params['basedn'];
            $newrdn = $ldapKey.'='.$entry[$ldapKey];
            $res = @ldap_rename($this->_LDAP, "$oldrdn,$oldparent",
                $newrdn, $oldparent, true);
            if (!$res) {
                return PEAR::raiseError('LDAP Rename failed: ' .
                    ldap_error($this->_LDAP));
            }
        }

        # Update the object/user
        $dn = $ldapKey.'='.$entry[$ldapKey];
        $dn .= ','.SHOUT_USERS_BRANCH.','.$this->_params['basedn'];
        $res = @ldap_modify($this->_LDAP, $dn, $entry);
        if (!$res) {
            return PEAR::raiseError('LDAP Modify failed: ' .
                ldap_error($this->_LDAP));
        }

        # We must have been successful
        return true;
    }

    /**
     * Deletes a user from the LDAP tree
     *
     * @param string $context Context to delete the user from
     * @param string $extension Extension of the user to be deleted
     *
     * @return boolean True on success, PEAR::Error object on error
     */
    public function deleteUser($context, $extension)
    {
        $ldapKey = &$this->_ldapKey;
        $appKey = &$this->_appKey;

        if (!Shout::checkRights("shout:contexts:$context:users",
            PERMS_DELETE, 1)) {
            return PEAR::raiseError("No permission to delete users in this " .
                "context.");
        }

        $validusers = $this->getUsers($context);
        if (!isset($validusers[$extension])) {
            return PEAR::raiseError("That extension does not exist.");
        }

        $dn = "$ldapKey=".$validusers[$extension][$appKey];
        $dn .= ',' . SHOUT_USERS_BRANCH . ',' . $this->_params['basedn'];

        $res = @ldap_delete($this->_LDAP, $dn);
        if (!$res) {
            return PEAR::raiseError("Unable to delete $extension from " .
                "$context: " . ldap_error($this->_LDAP));
        }
        return true;
    }


    /* Needed because uksort can't take a classed function as its callback arg */
    protected function _sortexten($e1, $e2)
    {
        print "$e1 and $e2\n";
        $ret =  Shout::extensort($e1, $e2);
        print "returning $ret";
        return $ret;
    }

    /**
     * Attempts to open a connection to the LDAP server.
     *
     * @return boolean    True on success; exits (Horde::fatal()) on error.
     *
     * @access private
     */
    protected function _connect()
    {
        if ($this->_connected) {
            return;
        }

        if (!Horde_Util::extensionExists('ldap')) {
            throw new Horde_Exception('Required LDAP extension not found.');
        }

        Horde::assertDriverConfig($this->_params, $this->_params['class'],
            array('hostspec', 'basedn', 'writedn'));

        /* Open an unbound connection to the LDAP server */
        $conn = ldap_connect($this->_params['hostspec'], $this->_params['port']);
        if (!$conn) {
             Horde::logMessage(
                sprintf('Failed to open an LDAP connection to %s.',
                        $this->_params['hostspec']),
                __FILE__, __LINE__, PEAR_LOG_ERR);
            throw new Horde_Exception('Internal LDAP error. Details have been logged for the administrator.');
        }

        /* Set hte LDAP protocol version. */
        if (isset($this->_params['version'])) {
            $result = ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION,
                                       $this->_params['version']);
            if ($result === false) {
                Horde::logMessage(
                    sprintf('Set LDAP protocol version to %d failed: [%d] %s',
                            $this->_params['version'],
                            ldap_errno($conn),
                            ldap_error($conn)),
                    __FILE__, __LINE__, PEAR_LOG_WARNING);
                throw new Horde_Exception('Internal LDAP error. Details have been logged for the administrator.', ldap_errno($conn));
            }
        }

        /* Start TLS if we're using it. */
        if (!empty($this->_params['tls'])) {
            if (!@ldap_start_tls($conn)) {
                Horde::logMessage(
                    sprintf('STARTTLS failed: [%d] %s',
                            @ldap_errno($this->_ds),
                            @ldap_error($this->_ds)),
                    __FILE__, __LINE__, PEAR_LOG_ERR);
            }
        }

        /* If necessary, bind to the LDAP server as the user with search
         * permissions. */
        if (!empty($this->_params['searchdn'])) {
            $bind = ldap_bind($conn, $this->_params['searchdn'],
                              $this->_params['searchpw']);
            if ($bind === false) {
                Horde::logMessage(
                    sprintf('Bind to server %s:%d with DN %s failed: [%d] %s',
                            $this->_params['hostspec'],
                            $this->_params['port'],
                            $this->_params['searchdn'],
                            @ldap_errno($conn),
                            @ldap_error($conn)),
                    __FILE__, __LINE__, PEAR_LOG_ERR);
                throw new Horde_Exception('Internal LDAP error. Details have been logged for the administrator.', ldap_errno($conn));
            }
        }

        /* Store the connection handle at the instance level. */
        $this->_LDAP = $conn;
    }

}
