<?php
/**
 * Enhanced Account Switcher for MyBB 1.8
 * Copyright (c) 2012-2015 doylecc
 * http://mybbplugins.de.vu
 *
 * based on the Plugin:
 * Account Switcher 1.0 by Harest
 * Copyright (c) 2011 Harest
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

define("KILL_GLOBALS", 1);
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'accountlist.php');
define("EAS_PROFILEFIELD", 1);
//define("NO_ONLINE", 1); // Remove from online list

$templatelist = 'accountswitcher_accountlist,accountswitcher_accountlist_master,accountswitcher_accountlist_attached,accountswitcher_accountlist_shared,accountswitcher_accountlist_endbit,accountswitcher_profilefield,accountswitcher_avatar,accountswitcher_profilefield_head,accountswitcher_profilefield_attached';

require_once "./global.php";

// Deny guest access
if ($mybb->user['uid'] == 0)
{
	error_no_permission();
}

// Redirect back if accountlist disabled
if ($mybb->settings['aj_list'] != 1)
{
	redirect("index.php", $lang->aj_list_disabled);
}

// Load language file
$lang->load("accountswitcher");

// Add breadcrumb navigation
add_breadcrumb($lang->aj_accountlist);

// Declare variables
$masters = array();
$count = 0;
$accountlist = $accountlist_masterbit = $masterlink = $attachedlink = $profile_head = $profilefield_attached = $profile_field = $profile_name = $viewableby = $as_accountlist_hidden = '';
$colspan_head = 'colspan="2"';
$colspan = '';
$master_width = 'width="50%"';
$avadims = 'width="auto" height="44"';
$tb_row = '</tr>';

// Incoming results per page?
$mybb->input['perpage'] = $mybb->get_input('perpage', 1);
if ($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 50)
{
	$per_page = $mybb->input['perpage'];
}
else
{
	$per_page = $mybb->input['perpage'] = 5;
}

// Page
$page = $mybb->get_input('page', 1);
if ($page && $page > 0)
{
	$start = ($page - 1) * $per_page;
}
else
{
	$start = 0;
	$page = 1;
}

// If profile field enabled, change colspans and get user fields
if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0)
{
	$colspan_head = 'colspan="4"';
	$colspan = 'colspan="2"';
	$tb_row = '';
}

// Load account data from cache
$accounts = $eas->accountswitcher_cache;

if (is_array($accounts))
{
	// Find all master accounts
	foreach ($accounts as $key => $account)
	{
		$masters[] = $account['as_uid'];
	}

	$masters = array_unique($masters);
	$masters = array_values($masters);
	// Count all master accounts
	$num_masters = count($masters);

	// Show only number of master acounts per page
	$masters = array_slice($masters, $start, $per_page);

	if (is_array($masters))
	{
		foreach ($masters as $master_acc)
		{
			$master = get_user($master_acc);
			if (!empty($master['uid']))
			{
				$profilefield = '&nbsp;';
				$hidden = 0;
				// Hide users with privacy setting enabled
				if (($mybb->usergroup['cancp'] != 1 && $mybb->user['uid'] != $master['uid'] && $mybb->settings['aj_privacy'] == 1 && $master['as_privacy'] == 1)
				&& (($mybb->user['as_uid'] > 0 && $mybb->user['as_uid'] != $master['uid'])
				|| ($mybb->user['as_uid'] == 0 && $mybb->user['uid'] != $master['as_uid'])))
				{
					$masterAvatar = $eas->attached_avatar($mybb->settings['default_avatar'], $mybb->settings['useravatardims']);
					$masterlink = $masterAvatar.$lang->aj_hidden_master;
				}
				else
				{
					// Display master account
					$attachedPostUser = htmlspecialchars_uni($master['username']);
					$masterAvatar = $eas->attached_avatar($master['avatar'], $master['avatardimensions']);
					$masterlink = $masterAvatar.'&nbsp;&nbsp;<span style="font-weight: bold;" title="Master Account">'.build_profile_link(format_name($attachedPostUser, $master['usergroup'], $master['displaygroup']), (int)$master['uid']).'</span>';
					// Get profile field
					if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0)
					{
						$master_width = 'width="28%"';
						$profile_field = $eas->get_profilefield($master['uid']);
					}
				}
				$accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_master'));
			}
			else
			{
				// Display shared account
				if ($account['as_buddyshare'] != 0)
				{
					$lang->as_isshared = $lang->as_isshared_buddy;
				}
				if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0)
				{
					$profilefield = '&nbsp;';
					$profile_field = eval($templates->render('accountswitcher_profilefield'));
				}
				$accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_shared'));
			}

			// Sort accounts by first, secondary, shared accounts and by uid or username
			$accounts = $eas->sort_attached();

			// Get all attached accounts
			foreach ($accounts as $key => $account)
			{
				if ($account['as_uid'] == $master_acc)
				{
					$profilefield = '&nbsp;';
					// Hide users with privacy setting enabled
					if ($mybb->usergroup['cancp'] != 1 && $mybb->user['uid'] != $account['uid'] && $mybb->settings['aj_privacy'] == 1 && $account['as_privacy'] == 1)
					{
						if (($mybb->user['as_uid'] != 0 && $mybb->user['as_uid'] != $account['as_uid'] && $mybb->user['as_uid'] != $account['uid'])
						|| ($mybb->user['as_uid'] == 0 && $mybb->user['uid'] != $account['as_uid']))
						{
							++$hidden;
							continue;
						}
					}
					++$count;
					if ($count > 0)
					{
						// Display attached account
						$attachedPostUser = htmlspecialchars_uni($account['username']);
						if ($mybb->settings['aj_sharestyle'] == 1 && $account['as_share'] != 0)
						{
							$attachedbit = eval($templates->render('accountswitcher_shared_accountsbit'));
						}
						elseif ($mybb->settings['aj_secstyle'] == 1 && $account['as_sec'] != 0 && $account['as_share'] == 0)
						{
							$user_sec_reason = htmlspecialchars_uni($account['as_secreason']);
							$attachedbit = eval($templates->render('accountswitcher_sec_accountsbit'));
						}
						else
						{
							$attachedbit = format_name($attachedPostUser, (int)$account['usergroup'], (int)$account['displaygroup']);
						}
						$attachedAvatar = $eas->attached_avatar($account['avatar'], $account['avatardimensions']);
						$attachedlink = $attachedAvatar.'&nbsp;&nbsp;'.build_profile_link($attachedbit, (int)$account['uid']);
						// Get profile field
						if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0)
						{
							$profile_field = $eas->get_profilefield($account['uid'], true);
						}
						$accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_attached'));
					}
				}
			}
			// Show number of hidden attached accounts
			if ($hidden > 0)
			{
				$as_accountlist_hidden = '<tr><td class="trow1" style="padding: 8px 0 0 65px;">'.$lang->sprintf($lang->aj_hidden, $hidden).'</td></tr>';
				$accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_endbit'));
			}
			else
			{
				$as_accountlist_hidden = '';
				$accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_endbit'));
			}
		}
	}

	// Multipage
	$search_url = htmlspecialchars_uni("accountlist.php?perpage={$mybb->input['perpage']}");
	$multipage = multipage($num_masters, $per_page, $page, $search_url);
}

// Output accountlist
$accountlist .= eval($templates->render('accountswitcher_accountlist'));

output_page($accountlist);
