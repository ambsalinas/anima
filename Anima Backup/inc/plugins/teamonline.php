<?php
/**
    ===============================================================
    @author     : Snake_;    
    @version    : 1.2.1 ;
    @mybb       : compatibility MyBB 1.6.x;
    @description: The Plugin displays the team forum at any given time. 
    @homepage   : http://mybboard.pl Polish support MyBB!
    ===============================================================
 **/
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
$plugins->add_hook("index_end", "teamonline_show");
$plugins->add_hook("portal_start", "teamonline_show");
$plugins->add_hook('global_start', 'teamonline_templatelist');

function teamonline_info()
{
	global $lang, $db;
	$lang->load('config_teamonline');

	$query = $db->simple_select('settinggroups', '*', "name='plugin_teamonline'");
	if (count($db->fetch_array($query)))
		$settings_link = '(<a href="index.php?module=config&action=change&search=plugin_teamonline" style="color:#FF1493;font-weight:bold;">'.'Ustawienia'.'</a>)';

	return array(
		"name"			=> $lang->name,
		"description"	=> $lang->desc . $settings_link,
		"website"		=> "http://mybboard.pl",
		"author"		=> "Snake_ & Glover",
		"authorsite"	=> "http://mybboard.pl",
		"version"		=> "1.2.1",
		"guid" 			=> "02f1cc6ad5e3401189fe40da44f12c2b",
		"compatibility" => "18*"
	);
}
function teamonline_install()
{
	global $db, $mybb, $lang;
	
	$lang->load('config_teamonline');

	$settingsgroup = array(
		"gid" => "NULL",
		"name" => "plugin_teamonline",
		"title" => $lang->name,
		"description" => $lang->desc_set,
		"disporder" => "250",
		"isdefault" => "no",
		);
	$db->insert_query("settinggroups", $settingsgroup);
	
	$d = -1;
	$gid = (int)$db->insert_id();
	
	$setting_array[] = array(
		"sid" => "NULL",
		"name" => "teamonline_gid",
		"title" => $lang->name_set1,
		"description" => $lang->desc_set1,
		"optionscode" => "text",
		"value" => "4",
		"disporder" => ++$disporder,
		"gid" => $gid,
		);


	$setting_array[] = array(
		"sid" => "NULL",
		"name" => "teamonline_no_text",
		"title" => $lang->name_set2,
		"description" => $lang->desc_set2,
		"optionscode" => "text",
		"value" => $lang->value_set2,
		"disporder" => ++$disporder,
		"gid" => $gid,
		);


	$setting_array[] = array(
		"sid" => "NULL",
		"name" => "teamonline_group_color",
		"title" => $lang->name_set3,
		"description" => $lang->desc_set3,
		"optionscode" => "text",
		"value" => "#393939",
		"disporder" => ++$disporder,
		"gid" => $gid,
		);	
	
	$setting_array[] = array(
		"sid" => "NULL",
		"name" => "teamonline_defaultavatar",
		"title" => $lang->name_set4,
		"description" => $lang->desc_set4,
		"optionscode" => "text",
		"value" => "images/avatars/invalid_url.gif",
		"disporder" => ++$disporder,
		"gid" => $gid,
		);
	$db->insert_query("settings", $settings4);

	foreach($setting_array as &$current_setting)
	{
			$current_setting['sid'] = NULL;
			$current_setting['disporder'] = ++$d;
			$current_setting['gid'] = $gid;		
	}
	$db->insert_query_multiple('settings', $setting_array);
	
	rebuild_settings();

	$template['teamonline'] = '
		<table border="0" cellspacing="' . $theme['borderwidth'] . '" cellpadding="' . $theme['tablespace'] . '" class="tborder">
		<thead>
		<tr>
		<td class="thead" colspan="2">
<strong>{$lang->title}</strong>
		</tr>
		</thead>
		<tbody id="teamonline_e" style="{$expdisplay}">
		{$teamonline_row}
		{$teamonline_no}
		</tbody>
		<tr><td class="{$trowbg}" colspan="2">{$lang->online} {$membercount}</td></tr><tr><td class="{$trowbg}" colspan="2">{$lang->invisible} {$invisible}</td></tr></table> <br />';
	$template['teamonline_no'] = '
	<tr><td class="{$trowbg}">{$mybb->settings[\'teamonline_no_text\']}</td></tr>';
	$template['teamonline_row'] = '<tr><td class="{$trowbg}"><img src="{$avatar_teamonline[\'image\']}" style="max-width: 35px; max-height: 35px; text-align:center;" /></td>
				<td class="{$trowbg}" style="width: 100%;">  {$online[\'profilelink\']}<br /><font color="{$mybb->settings[\'teamonline_group_color\']}">{$online[\'groupname\']}</font>
				</td></tr>';

	foreach($template as $title => $tname)
	{
		$tp = array(
			'title'		=> $title,
			'template'	=> $db->escape_string($tname),
			'sid'		=> '-1',
			'version'	=> '1612',
			'dateline'	=> TIME_NOW
		);
		$db->insert_query("templates", $tp);
	}

	require "../inc/adminfunctions_templates.php";
	find_replace_templatesets( "index", '#'.preg_quote('{$forums}').'#', '{$forums}{$teamonline}' );
	find_replace_templatesets( "portal", '#'.preg_quote('{$welcome}').'#', '{$welcome}{$teamonline}' );
}

function teamonline_is_installed()
{
	global $db;
	
	return $db->fetch_field($db->simple_select("settinggroups", "COUNT(1) AS cnt", "name='plugin_teamonline'"), 'cnt');
}

function teamonline_uninstall()
{
	global $db;
	$db->delete_query('settings', "name LIKE ('teamonline\_%')");
	$db->delete_query('settinggroups', "name = 'plugin_teamonline'");
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets( "index", '#'.preg_quote('{$teamonline}').'#', '' );
	find_replace_templatesets( "portal", '#'.preg_quote('{$teamonline}').'#', '' );
	$deletetemplates = array('teamonline','teamonline_row','teamonline_no');
	foreach($deletetemplates as $title)
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE title='" . $title. "'");
	}
}

function teamonline_show()
{
	global $cache, $groupscache, $db, $mybb, $teamonline, $lang, $theme, $templates, $online;
	$lang->load('teamonline');
	if($mybb->settings['teamonline_gid'])
	{
		$gid = " IN (" . $mybb->settings['teamonline_gid'] . ")";
		$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
		$teamonline_row = '';
		$trowbg = alt_trow();
		$query = $db->query("
			SELECT s.sid, s.ip, s.uid, u.username, s.time, u.avatar, u.usergroup, u.displaygroup, u.invisible
			FROM ".TABLE_PREFIX."sessions s
			LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
			WHERE u.usergroup $gid AND time>'{$timesearch}'
			ORDER BY u.username ASC, s.time DESC
			");	

		if(!$db->num_rows($query))
		{
			eval("\$teamonline_no = \"".$templates->get("teamonline_no")."\";");
			$invisible = 0;
			$membercount = 0;
		}
		else
		{

		if(!is_array($groupscache))
			$groupscache = $cache->read("usergroups");

			while($online = $db->fetch_array($query))
			{
				$invisible_mark = '';
				if($online['invisible'] == 1)
					$invisible_mark = '*';
				if($online['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $online['uid'] == $mybb->user['uid'])
				{
					$avatar_teamonline = format_avatar($online['avatar']);
					$online['username'] = format_name($online['username'], $online['usergroup'], $online['displaygroup']);
					$online['profilelink'] = build_profile_link($online['username'], $online['uid']).$invisible_mark;
					$online['groupname'] = $groupscache[$online['usergroup']]['title'];
					eval("\$teamonline_row .= \"".$templates->get("teamonline_row")."\";");
				}
				$invisible += $online['invisible'];
				$membercount++;
			}
		}
	eval("\$teamonline = \"".$templates->get("teamonline")."\";");
	}
}
function teamonline_templatelist()
{
	global $mybb;
	if(isset($GLOBALS['templatelist']))
	{
		if(THIS_SCRIPT == 'index.php' OR 'portal.php')
		{
			$GLOBALS['templatelist'] .= ",teamonline,teamonline_row,teamonline_no";
		}
	}
}

?>