<?php
/**
 * While you were typing
 * Copyright (c) 2011 Aries-Belgium
 * Copyright (c) 2014 doylecc
 *
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('WHILETYPING_PLUGIN_VERSION', 1400);

$plugins->add_hook('global_start', 'whiletyping_global_start');
$plugins->add_hook('global_end',"whiletyping_global_end");
$plugins->add_hook('newreply_start', 'whiletyping_newreply_start');
$plugins->add_hook('newreply_do_newreply_start', 'whiletyping_newreply_do_newreply_start');
$plugins->add_hook('xmlhttp', 'whiletyping_xmlhttp');

/**
 * Info function for MyBB plugin system
 */
function whiletyping_info()
{
	global $lang;

	whiletyping__lang_load();

	$donate_button =
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RQNL345SN45DS" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

	$error = "";
	switch(true)
	{
		case !file_exists(MYBB_ROOT."/inc/plugins/whiletyping"):
			$error .= '<br/><span style="color:red">'.$lang->whiletyping_error_dir_missing.'</span>';
			break;
	}

	return array(
		"name"		=> $lang->whiletyping_name,
		"description"	=> "{$donate_button}{$lang->whiletyping_description}{$error}",
		"website"		=> "",
		"author"		=> "Aries-Belgium",
		"authorsite"	=> "mailto:aries.belgium@gmail.com",
		"version"		=> "1.4",
		"guid" 			=> "70088244b52f7cd48014b7f34a9322c3",
		"compatibility" => "16*,17*,18*"
	);
}

/**
 * Install function for the MyBB plugin system
 */
function whiletyping_install()
{
	whiletyping_settings('install');
}

/**
 * Is installed function for the MyBB plugin system
 */
function whiletyping_is_installed()
{
	global $db;
	$query = $db->simple_select("settinggroups", "gid", "name='whiletyping'");
	return $db->num_rows($query) > 0;
}

/**
 * Uninstall function for the MyBB plugin system
 */
function whiletyping_uninstall()
{
	whiletyping_settings('uninstall');
}

/**
 * Activation function for the MyBB plugin system
 */
function whiletyping_activate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("newreply", "#(".preg_quote("{\$preview}").")#i", "{\$whiletyping}$1");
}

/**
 * Deactivation function for the MyBB plugin system
 */
function whiletyping_deactivate()
{
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("newreply", "#".preg_quote("{\$whiletyping}")."#i", "",0);
}

/**
 * Settings
 */
function whiletyping_settings()
{
	global $db, $mybb, $lang;

	whiletyping__lang_load();

	$settings_group = array(
		"name" => "whiletyping",
		"title" => $db->escape_string($lang->whiletyping_settinggroup_title),
		"description" => $db->escape_string($lang->whiletyping_settinggroup_description),
		"disporder" => 100,
		"isdefault" => 0
	);

	$disporder = 0;
	$settings = array();
	$settings['whiletyping_realtime'] = array(
		"name" => "whiletyping_realtime",
		"title" => $db->escape_string($lang->whiletyping_setting_realtime),
		"description" => $db->escape_string($lang->whiletyping_setting_realtime_description),
		"optionscode" => "onoff",
		"value" => 1,
		"disporder" => $disporder++,
	);

	$op = "all";
	$args = func_get_args();
	if(isset($args[0]))
	{
		if(in_array($args[0],array('install','get','all','uninstall')))
		{
			$op = $args[0];
		}
		else
		{
			$op = "get";
			$args[1] = $args[0];
		}
	}

	switch($op)
	{
		case "install":
			// create the settings group
			$db->insert_query("settinggroups", $settings_group);
			$gid = $db->insert_id();

			// insert the settings
			foreach($settings as $setting)
			{
				$setting['gid'] = $gid;
				$db->insert_query("settings",$setting);
			}

			// rebuild the settings
			rebuild_settings();
			break;
		case "uninstall":
			$query = $db->simple_select("settinggroups", "gid", "name='{$settings_group['name']}'");

			while($group = $db->fetch_array($query))
			{
				$gid = intval($group['gid']);

				// remove the settings
				$db->delete_query("settings","gid='{$gid}'");

				// remove the settings group
				$db->delete_query("settinggroups","gid='{$gid}'");
			}

			// rebuild the settings
			rebuild_settings();
			break;
		case "get":
			$setting = $args[1];
			if(isset($settings[$setting]))
			{
				return isset($mybb->settings[$setting]) ? $mybb->settings[$setting] : $settings[$setting]['value'];
			}
			return false;
			break;
		case "all":
		default:
			return $settings;
			break;
	}
}

/**
 * Implementation of the global_start() hook
 */
function whiletyping_global_start()
{
	global $mybb, $posts_while_typing, $headerinclude;

	@session_start();

	if(
		empty($mybb->input['updateattachment']) &&
		empty($mybb->input['newattachment']) &&
		empty($mybb->input['rem']) &&
		empty($mybb->input['attachmentact']) &&
		empty($mybb->input['savedraft'])
	)
	{
		if(THIS_SCRIPT == "newreply.php" && (isset($mybb->input['processed']) || isset($mybb->input['ajax'])) && isset($_SESSION['page_access_time']))
		{
			$posts_while_typing = _whiletyping_check_replies($mybb->input['tid'], $_SESSION['page_access_time']);
			if(count($posts_while_typing) > 0)
			{
				$mybb->input['previewpost'] = true;
			}
		}

		// only reset the time if the page is newreply or showthread
		if(THIS_SCRIPT == "newreply.php" || THIS_SCRIPT == "showthread.php")
			$_SESSION['page_access_time'] = time();
	}
}

/**
 * Implementation of the global_end() hook
 */
function whiletyping_global_end()
{
	global $mybb, $headerinclude;

	if(
		(THIS_SCRIPT == "showthread.php" // for quick reply
		|| THIS_SCRIPT == "newreply.php") // for full reply
		&& isset($mybb->input['tid']) && (int)$mybb->input['tid'] > 0 // check if the tid is set and is valid
	)
	{
		if(whiletyping_settings('whiletyping_realtime') == 1)
		{
			$headerinclude .= "\n" . '<script type="text/javascript" src="' . $mybb->settings['bburl'] . '/inc/plugins/whiletyping/js/whiletyping.realtime.js?v='.WHILETYPING_PLUGIN_VERSION.'"></script>';
		}
		$headerinclude .= "\n" . '<script type="text/javascript" src="' . $mybb->settings['bburl'] . '/inc/plugins/whiletyping/js/whiletyping.js?v='.WHILETYPING_PLUGIN_VERSION.'"></script>';
		$headerinclude .= "\n" . '<script type="text/javascript">var MYBB_TID = ' . (int)$mybb->input['tid'] . ';</script>';
		$headerinclude .= "\n" . '<script type="text/javascript">var THIS_SCRIPT = "' . THIS_SCRIPT . '";</script>';
	}
}

/**
 * Implementation of the newreply_start() hook
 */
function whiletyping_newreply_start()
{
	global $whiletyping, $posts_while_typing, $lang, $postcounter;

	whiletyping__lang_load("whiletyping");

	if(isset($posts_while_typing) && is_array($posts_while_typing) && count($posts_while_typing) > 0)
	{
		$count = count($posts_while_typing);

		$context = $count == 1 ? $lang->whiletyping_context_single : $lang->whiletyping_context_more;
		$whiletyping = '<div class="whiletyping">';
		$whiletyping .= '<table border="0" cellspacing="1" cellpadding="4" class="tborder" style="clear: both; border-bottom-width: 0;">
		<tbody><tr>
		<td class="thead" colspan="2"><strong>'.sprintf($context, $count).'</strong></td>
		</tr>
		</tbody></table>';

		foreach($posts_while_typing as $post)
		{
			$whiletyping .= _whiletyping_build_postbit($post,1);
		}

		$whiletyping .= "</div><br>";

		$lang->post_preview = $lang->whiletyping_your_post;
		$postcounter = 0;
	}
}

/**
 * Implementation of the newreply_do_newreply_start() hook
 */
function whiletyping_newreply_do_newreply_start()
{
	global $mybb, $posts_while_typing, $lang, $postcounter, $cache;

	if(isset($mybb->input['ajax']) && $mybb->input['ajax'] == 1)
	{
		whiletyping__lang_load("whiletyping");
		$postcounter= 1;

		if(isset($posts_while_typing) && is_array($posts_while_typing) && count($posts_while_typing) > 0)
		{
			$count = count($posts_while_typing);
			$context = $count == 1 ? $lang->whiletyping_context_single : $lang->whiletyping_context_more;
			$data = '<div class="whiletyping whiletyping_quickreply">';
			$data .= '<table id="whiletyping_quickreply_message" border="0" cellspacing="1" cellpadding="4" class="tborder" style="clear: both; margin-top: 5px;">
			<tbody><tr>
			<td class="thead" colspan="2"><strong>'.sprintf($context, $count).'</strong></td>
			</tr>
			</tbody></table>';

			$last_pid = 0;
			foreach($posts_while_typing as $post)
			{
				$data .= _whiletyping_build_postbit($post);
				$last_pid = $post['pid'];
			}

			$data .= '</div>';
			$return_message = addcslashes(addslashes($mybb->input['message']),"\r\n");
			$data .= '<script>if($("#whiletyping_quickreply_message")){ $("#whiletyping_quickreply_message").scrollTo(); } setTimeout(function(){ ';
			$data .= 'if($("#message")){ $("#message").html('.json_encode($return_message).'); } }, 500);</script>';

			header("Content-type: application/json; charset={$lang->settings['charset']}");
			print json_encode(array("data" => $data));

			exit;

			$_SESSION['page_access_time'] = time();

			die();
		}
	}
}

function whiletyping_newreply_do_newreply_end()
{
	global $mybb, $visible;

	if($mybb->input['method'] == "quickreply" && $visible)
	{
		$data = '<script type="text/javascript">if($("#whiletyping_quickreply_message")){ $("#whiletyping_quickreply_message").remove(); } ';
		$data .= 'if($("#message")) { $("#message").html(""); }</script>';
		header("Content-type: application/json; charset={$lang->settings['charset']}");
		print json_encode(array("data" => $data));

		exit;
	}
}

function whiletyping_xmlhttp()
{
	global $mybb, $lang, $postcounter, $forum, $forumpermissions;


	if($mybb->input['action'] == "whiletyping" && isset($mybb->input['tid']))
	{
		whiletyping__lang_load("whiletyping");

		@session_start();
		$posts_while_typing = _whiletyping_check_replies($mybb->input['tid'], $_SESSION['page_access_time']);
		if(($count = count($posts_while_typing)))
		{
			$pids = array();
			foreach($posts_while_typing as $post) $pids[] = $post['pid'];
			$context = $count == 1 ? $lang->whiletyping_context_single : $lang->whiletyping_context_more;
			$context_show_posts = $count == 1 ? $lang->whiletyping_show_posts_context_single : $lang->whiletyping_show_posts_context_more;
			print sprintf($context, $count) . '.';
			switch($mybb->input['script'])
			{
				case "newreply": // full reply
					$action = "javascript:whiletypingSubmitPreview();";
					break;
				case "showthread": // quick reply
					$action = 'javascript:void(0);';
					break;
			}
			print ' [<a href="' . $action . '">' . $context_show_posts . '</a>]';
		}
	}

	if($mybb->input['action'] == "whiletyping_get_posts")
	{
		whiletyping__lang_load("whiletyping");

		@session_start();

		$postcounter = 1;
		$postbits = array();
		$posts_while_typing = _whiletyping_check_replies($mybb->input['tid'], $_SESSION['page_access_time']);
		foreach($posts_while_typing as $post)
		{
			$thread = get_thread($post['tid']);
			$forum = get_forum($post['fid']);
			$forumpermissions = forum_permissions($forum['fid']);

			if(is_moderator($forum['fid']))
				$postcounter = $thread['replies'] + $thread['unapprovedposts'];
			else
				$postcounter = $thread['replies'];


			// forum permissions
			if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
			{
				$postbits[] = _whiletyping_error($pid);
				continue;
			}

			if($forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
			{
				$postbits[] = _whiletyping_error($pid);
				continue;
			}

			$postbits[] = _whiletyping_build_postbit($post,0,true);
		}

		print implode("\r\n",$postbits);

		$_SESSION['page_access_time'] = time();
	}
}

/**
 * Helper function to display no permission message
 */
function _whiletyping_error($pid,$print=false)
{
	global $lang;

	$error = '<div class="whiletyping whiletyping_error error">';
	$error .= '<table border="0" cellspacing="1" cellpadding="4" class="tborder" style="clear: both; margin-top: 5px;">
	<tbody><tr>
	<td class="thead" colspan="2"><strong>PID #'.$pid.': '.$lang->post_doesnt_exist.'</strong></td>
	</tr>
	</tbody></table>';
	$error .= '</div>';

	if(!$print) return $error;

	print $error;
}

/**
 * Helper function to check for new replies
 */
function _whiletyping_check_replies($tid,$after)
{
	global $db, $mybb;

	$query = $db->query(
		"SELECT * FROM (".
		"(SELECT * FROM `".TABLE_PREFIX."posts` WHERE tid=" . intval($tid) . " AND dateline > " . intval($after) . " AND uid <> " . intval($mybb->user['uid']) . ") ".
		"UNION (SELECT * FROM `".TABLE_PREFIX."posts` WHERE  tid=" . intval($tid) . " AND edittime > " . intval($after) . " AND edituid <> " . intval($mybb->user['uid']) . ") ".
		") AS p ORDER BY dateline"
	);

	if($db->num_rows($query) > 0)
	{
		$return = array();
		while($r = $db->fetch_array($query))
		{
			$return[] = get_post($r['pid']);
		}

		return $return;
	}

	return array();
}

/**
 * Helper function to build the postbit
 */
function _whiletyping_build_postbit($post,$type=0,$quick_reply=false)
{
	global $lang, $postcounter, $mybb, $ismod;

	$user = get_user($post['uid']);
	$post['userusername'] = $post['username'];
	$post = array_merge($post,$user);

	if(!function_exists('build_postbit'))
	{
		include MYBB_ROOT . "inc/functions_post.php";
	}

	$postbit = build_postbit($post, $type);

	return $postbit;
}

/**
 * Helper function to check if a certain plugin is activated
 */
function whiletyping__plugin_exists($pluginname)
{
	global $cache;

	$plugins = $cache->read("plugins");
	return isset($plugins['active'][$pluginname]);
}

/**
 * Helper function to load language files for the plugin
 */
function whiletyping__lang_load($file="", $supress_error=false)
{
	global $lang;

	$plugin_name = str_replace('__lang_load', '', __FUNCTION__);
	if(empty($file)) $file = $plugin_name;

	if( strpos( $lang->path , "/admin" ) !== false )
	{
		$lfile = MYBB_ROOT . "inc/plugins/{$plugin_name}/lang/{$lang->language}/admin/{$file}.lang.php";
	}
	else
	{
		$lfile = MYBB_ROOT . "inc/plugins/{$plugin_name}/lang/{$lang->language}/{$file}.lang.php";
	}

	if(file_exists($lfile))
	{
		require_once $lfile;
	}
	elseif(file_exists( MYBB_ROOT . "inc/plugins/{$plugin_name}/lang/english/{$file}.lang.php" ))
	{
		require_once MYBB_ROOT . "inc/plugins/{$plugin_name}/lang/english/{$file}.lang.php";
	}
	else
	{
		if($supress_error != true)
		{
			die("$lfile does not exist");
		}
	}

	if(is_array($l))
	{
		foreach($l as $key => $val)
		{
			if(empty($lang->$key) || $lang->$key != $val)
			{
				$lang->$key = $val;
			}
		}
	}
}
