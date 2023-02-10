<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

function eAthena_info()
{
	return array(
		"name"			=> "eAthena Integration",
		"description"	=> "Ragnarok Online Emulator Integrations",
		"website"		=> "https://ragnacloud.net/",
		"author"		=> "Litro",
		"authorsite"	=> "https://ragnacloud.net/",
		"version"		=> "1.0",
		"codename"		=> "eAthena",
		"compatibility" => "*"
	);
}

function eAthena_install()
{
    global $db, $mybb;

	$setting_group = array(
		'name' => 'eAthena',
		'title' => 'eAthena Settings',
		'description' => 'This is my plugin and it does some things',
		'disporder' => 1, // The order your setting group will display
		'isdefault' => 0
	);

	$gid = $db->insert_query("settinggroups", $setting_group);

    $gid = intval($db->insert_id());
    
    $settings[] = array(
		'name'          => 'eAthena_enabled',
		'title'         => 'Enable / Disable eAthena Plugin',
		'description'   => 'Do you want to enable eAthena Integration Plugin?',
		'optionscode'   => 'yesno',
		'value'         => '1',
		'disporder'     => '1',
		'gid'           => $gid
	);

    $settings[] = array(
		'name'          => 'eAthena_db_host',
		'title'         => 'Database Host',
		'description'   => '',
		'optionscode'   => 'text',
		'value'         => 'localhost',
		'disporder'     => '2',
		'gid'           => $gid
	);

    $settings[] = array(
		'name'          => 'eAthena_db_name',
		'title'         => 'Database Name',
		'description'   => '',
		'optionscode'   => 'text',
		'value'         => 'ragnarok',
		'disporder'     => '3',
		'gid'           => $gid
	);

    $settings[] = array(
		'name'          => 'eAthena_db_user',
		'title'         => 'Database Username',
		'description'   => '',
		'optionscode'   => 'text',
		'value'         => 'ragnarok',
		'disporder'     => '4',
		'gid'           => $gid
	);

    $settings[] = array(
		'name'          => 'eAthena_db_pass',
		'title'         => 'Database Password',
		'description'   => '',
		'optionscode'   => 'text',
		'value'         => 'ragnarok',
		'disporder'     => '5',
		'gid'           => $gid
	);

    foreach($settings as $setting)
        $db->insert_query('settings', $setting);

	// This is required so it updates the settings.php file as well and not only the database - they must be synchronized!
	rebuild_settings();
}

function eAthena_is_installed()
{
    global $db;

    $r = $db->simple_select('settings', 'name', "name = 'eAthena_enabled'");
    if ($db->num_rows($r) >= 1)
        return true;
    return false;
}

function eAthena_uninstall()
{
    global $db;

    $query = $db->write_query("SELECT `gid` FROM `". TABLE_PREFIX ."settinggroups` WHERE name = 'eAthena'");
    $g = $db->fetch_array($query);
    $db->write_query("DELETE FROM `". TABLE_PREFIX ."settinggroups` WHERE gid = '".$g['gid']."'");
    $db->write_query("DELETE FROM `". TABLE_PREFIX ."settings` WHERE gid = '".$g['gid']."'");

    rebuild_settings();
}

function eAthena_activate()
{
    global $mybb;

    $mybb->settings['eAthena_enabled'] = 1;

    rebuild_settings();
}

function eAthena_deactivate()
{
    global $mybb;

    $mybb->settings['eAthena_enabled'] = 0;

    rebuild_settings();
}

global $mybb;
$eAthenaDB = new stdClass();

if ($mybb->settings['eAthena_enabled'] && !defined('IN_ADMINCP'))
{
    // Global
    $plugins->add_hook('global_start', 'eAthena_global');

    $plugins->add_hook('datahandler_login_validate_end', 'eAthena_datahandler_login_validate_end');
}

function eAthena_global()
{
	global $mybb, $eAthenaDB;

	// Load DB interface
	require_once MYBB_ROOT ."inc/db_base.php";
	require_once MYBB_ROOT ."inc/AbstractPdoDbDriver.php";

	require_once MYBB_ROOT ."inc/db_mysqli.php";

	$eAthenaDB = new DB_MySQLi;

	// Check if our DB engine is loaded
	if(!extension_loaded($eAthenaDB->engine))
	{
		// Throw our super awesome db loading error
		$mybb->trigger_generic_error("sql_load_error");
	}
	
	$eAthenaConfig['database']['type'] = 'mysqli';
	$eAthenaConfig['database']['database'] = $mybb->settings['eAthena_db_name'];
	$eAthenaConfig['database']['table_prefix'] = '';

	$eAthenaConfig['database']['hostname'] = $mybb->settings['eAthena_db_host'];
	$eAthenaConfig['database']['username'] = $mybb->settings['eAthena_db_user'];
	$eAthenaConfig['database']['password'] = $mybb->settings['eAthena_db_pass'];

	// Connect to Database
	$eAthenaDB->connect($eAthenaConfig['database']);
	$eAthenaDB->type = $eAthenaConfig['database']['type'];
}

function eAthena_datahandler_login_validate_end(&$thisData)
{
	global $eAthenaDB;

	$clause = array(
		"`userid` = '{$thisData->data['username']}'",
		"`user_pass` = '{$thisData->data['password']}'",
	);
	
	$r = $eAthenaDB->simple_select('login', 'group_id, email, state', implode(' AND ', $clause), array('LIMIT' => 1));

	if (empty($thisData->login_data))
	{
		if ($eAthenaDB->num_rows($r) >= 1)
		{
			$eAthenaUser = $eAthenaDB->fetch_array($r);
			
			if ($eAthenaUser['state'] == 0)
			{
				// register the user

				// Set up user handler.
				require_once MYBB_ROOT."inc/datahandlers/user.php";
				$userhandler = new UserDataHandler('insert');

				// Set the data for the new user.
				$new_user = array(
					"username" => $thisData->data['username'],
					"password" => $thisData->data['password'],
					"email" => $eAthenaUser['email'],
					"usergroup" => 2,
					"displaygroup" => 2,
					"regip" => $session->packedip,
				);

				$new_user['options'] = array(
					"allownotices" => 1,
					"receivepms" => 1,
					"pmnotice" => 1,
				);

				// Set the data of the user in the datahandler.
				$userhandler->set_data($new_user);

				// Validate the user and get any errors that might have occurred.
				if($userhandler->validate_user())
				{
					$user_info = $userhandler->insert_user();
					my_setcookie("mybbuser", $user_info['uid']."_".$user_info['loginkey'], null, true, "lax");
					redirect("index.php");
				}
			}
 		}
	}
}

