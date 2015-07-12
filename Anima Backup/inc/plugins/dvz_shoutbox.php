<?php
/* by Tomasz 'Devilshakerz' Mlynski [devilshakerz.com]; Copyright (C) 2014-2015
 released under Creative Commons BY-NC-SA 4.0 license: http://creativecommons.org/licenses/by-nc-sa/4.0/ */

$plugins->add_hook('global_start', ['dvz_shoutbox', 'global_start']);   // cache shoutbox templates
$plugins->add_hook('global_end',   ['dvz_shoutbox', 'global_end']);    // catch archive page
$plugins->add_hook('xmlhttp',      ['dvz_shoutbox', 'xmlhttp']);      // xmlhttp.php listening
$plugins->add_hook('index_end',    ['dvz_shoutbox', 'load_window']); // load Shoutbox window to {$dvz_shoutbox} variable

$plugins->add_hook('admin_user_users_merge_commit', ['dvz_shoutbox', 'user_merge']);

$plugins->add_hook('fetch_wol_activity_end', ['dvz_shoutbox', 'activity']); // catch activity
$plugins->add_hook('build_friendly_wol_location_end', ['dvz_shoutbox', 'activity_translate']); // translate activity

function dvz_shoutbox_info ()
{
    return [
        'name'          => 'DVZ Shoutbox',
        'description'   => 'Lightweight AJAX chat.',
        'website'       => 'http://devilshakerz.com/',
        'author'        => 'Tomasz \'Devilshakerz\' Mlynski',
        'authorsite'    => 'http://devilshakerz.com/',
        'version'       => '2.1.3',
        'codename'      => 'dvz_shoutbox',
        'compatibility' => '18*',
    ];
}

function dvz_shoutbox_install ()
{
    global $db;

    // table
    $db->write_query("
        CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "dvz_shoutbox` (
            `id` int(11) NOT NULL auto_increment,
            `uid` int(11) NOT NULL,
            `text` text NOT NULL,
            `date` int(11) NOT NULL,
            `ipaddress` varbinary(16) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB " . $db->build_create_table_collation() . "
    ");

    // example shout
    $db->insert_query('dvz_shoutbox', [
        'uid'       => 1,
        'text'      => 'DVZ Shoutbox!',
        'date'      => TIME_NOW,
        'ipaddress' => my_inet_pton('127.0.0.1'),
    ]);

    // settings
    $settingGroupId = $db->insert_query('settinggroups', [
        'name'        => 'dvz_shoutbox',
        'title'       => 'DVZ Shoutbox',
        'description' => 'Settings for DVZ Shoutbox.',
    ]);

    $settings = [
        [
            'name'        => 'dvz_sb_num',
            'title'       => 'Shouts to display',
            'description' => 'Number of shouts to be displayed in the Shoutbox window.',
            'optionscode' => 'text',
            'value'       => '20',
        ],
        [
            'name'        => 'dvz_sb_num_archive',
            'title'       => 'Shouts to display on archive',
            'description' => 'Number of shouts to be displayed per page on Archive view.',
            'optionscode' => 'text',
            'value'       => '20',
        ],
        [
            'name'        => 'dvz_sb_reversed',
            'title'       => 'Reversed order',
            'description' => 'Reverses the shouts display order in the Shoutbox window so that new ones appear on the bottom. You may also want to move the <b>{$panel}</b> variable below the  window in the <i>dvz_shoutbox</i> template.',
            'optionscode' => 'yesno',
            'value'       => '0',
        ],
        [
            'name'        => 'dvz_sb_height',
            'title'       => 'Shoutbox height',
            'description' => 'Height of the Shoutbox window in pixels.',
            'optionscode' => 'text',
            'value'       => '160',
        ],
        [
            'name'        => 'dvz_sb_dateformat',
            'title'       => 'Date format',
            'description' => 'Format of the date displayed. This format uses the PHP\'s <a href="http://php.net/manual/en/function.date.php#refsect1-function.date-parameters">date() function syntax</a>.',
            'optionscode' => 'text',
            'value'       => 'd M H:i',
        ],
        [
            'name'        => 'dvz_sb_mycode',
            'title'       => 'Parse MyCode',
            'description' => '',
            'optionscode' => 'yesno',
            'value'       => '1',
        ],
        [
            'name'        => 'dvz_sb_smilies',
            'title'       => 'Parse smilies',
            'description' => '',
            'optionscode' => 'yesno',
            'value'       => '1',
        ],
        [
            'name'        => 'dvz_sb_interval',
            'title'       => 'Refresh interval',
            'description' => 'Number of seconds between Shoutbox updates (lower values provide better synchronization but cause higher server load). Set 0 to disable the auto-refreshing feature.',
            'optionscode' => 'text',
            'value'       => '5',
        ],
        [
            'name'        => 'dvz_sb_away',
            'title'       => 'Away mode',
            'description' => 'Number of seconds after last user action (e.g. click) after which shoutbox will be minimized to prevent unnecessary usage of server resources. Set 0 to disable this feature.',
            'optionscode' => 'text',
            'value'       => '600',
        ],
        [
            'name'        => 'dvz_sb_antiflood',
            'title'       => 'Anti-flood interval',
            'description' => 'Forces a minimum number of seconds to last between user\'s shouts (this does not apply to Shoutbox moderators).',
            'optionscode' => 'text',
            'value'       => '5',
        ],
        [
            'name'        => 'dvz_sb_lazyload',
            'title'       => 'Lazy load',
            'description' => 'Start loading data only when the Shoutbox window is actually being displayed on the screen (the page is scrolled to the Shoutbox position).',
            'optionscode' => 'select
off=Disabled
start=Check if on display to start
always=Always check if on display to refresh',
            'value'       => 'off',
        ],
        [
            'name'        => 'dvz_sb_status',
            'title'       => 'Shoutbox default status',
            'description' => 'Choose whether Shoutbox window should be expanded or collapsed by default.',
            'optionscode' => 'onoff',
            'value'       => '1',
        ],
        [
            'name'        => 'dvz_sb_minposts',
            'title'       => 'Minimum posts required to shout',
            'description' => 'Set 0 to allow everyone.',
            'optionscode' => 'text',
            'value'       => '0',
        ],
        [
            'name'        => 'dvz_sb_groups_view',
            'title'       => 'Group permissions: View',
            'description' => 'User groups that can view Shoutbox.',
            'optionscode' => 'groupselect',
            'value'       => '-1',
        ],
        [
            'name'        => 'dvz_sb_groups_shout',
            'title'       => 'Group permissions: Shout',
            'description' => 'User groups that can post shouts in Shoutbox (logged in users only).',
            'optionscode' => 'groupselect',
            'value'       => '-1',
        ],
        [
            'name'        => 'dvz_sb_groups_refresh',
            'title'       => 'Group permissions: Auto-refresh',
            'description' => 'User groups that shoutbox will be refreshing for.',
            'optionscode' => 'groupselect',
            'value'       => '-1',
        ],
        [
            'name'        => 'dvz_sb_groups_mod',
            'title'       => 'Group permissions: Moderation',
            'description' => 'User groups that can moderate the Shoutbox (edit and delete shouts).',
            'optionscode' => 'groupselect',
            'value'       => '',
        ],
        [
            'name'        => 'dvz_sb_groups_mod_own',
            'title'       => 'Group permissions: Moderation of own shouts',
            'description' => 'Users groups whose members can edit and delete their own shouts.',
            'optionscode' => 'groupselect',
            'value'       => '',
        ],
        [
            'name'        => 'dvz_sb_supermods',
            'title'       => 'Super moderators are Shoutbox moderators',
            'description' => 'Automatically allow forum super moderators to moderate Shoutbox as well.',
            'optionscode' => 'yesno',
            'value'       => '1',
        ],
        [
            'name'        => 'dvz_sb_blocked_users',
            'title'       => 'Banned users',
            'description' => 'Comma-separated list of user IDs that are banned from posting messages.',
            'optionscode' => 'textarea',
            'value'       => '',
        ],
    ];

    $i = 1;

    foreach ($settings as &$row) {
        $row['gid']         = $settingGroupId;
        $row['title']       = $db->escape_string($row['title']);
        $row['description'] = $db->escape_string($row['description']);
        $row['disporder']   = $i++;
    }

    $db->insert_query_multiple('settings', $settings);

    rebuild_settings();

    // templates
    $templates = [
        'dvz_shoutbox_panel' => '<div class="panel">
<form>
<input type="text" class="text" placeholder="{$lang->dvz_sb_default}" autocomplete="off" />
<input type="submit" style="display:none" />
</form>
</div>',

        'dvz_shoutbox' => '<div id="shoutbox" class="front{$classes}">

<div class="head">
<strong>{$lang->dvz_sb_shoutbox}</strong>
<p class="right"><a href="{$mybb->settings[\'bburl\']}/index.php?action=shoutbox_archive">&laquo; {$lang->dvz_sb_archivelink}</a></p>
</div>

<div class="body">

{$panel}

<div class="window" style="height:{$mybb->settings[\'dvz_sb_height\']}px">
<div class="data"></div>
</div>

</div>

<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/dvz_shoutbox.js"></script>
{$javascript}

</div>',

        'dvz_shoutbox_archive' => '<html>
<head>
<title>{$lang->dvz_sb_archive}</title>
{$headerinclude}
</head>
<body>
{$header}

<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/dvz_shoutbox.js"></script>
{$javascript}

{$modoptions}

{$multipage}

<br />

<div id="shoutbox">

<div class="head"><strong>{$lang->dvz_sb_archive}</strong></div>

<div class="data">
{$archive}
</div>
</div>

<br />

{$multipage}

{$footer}
</body>
</html>',

        'dvz_shoutbox_archive_modoptions' => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr><td class="thead" colspan="2"><strong>{$lang->dvz_sb_mod}</strong></td></tr>
<tr><td class="tcat">{$lang->dvz_sb_mod_banlist}</td><td class="tcat">{$lang->dvz_sb_mod_clear}</td></tr>
<tr>
<td class="trow1">
<form action="" method="post">
<input type="text" class="textbox" style="width:80%" name="banlist" value="{$blocked_users}"></textarea>
<input type="hidden" name="postkey" value="{$mybb->post_code}" />
<input type="submit" class="button" value="{$lang->dvz_sb_mod_banlist_button}" />
</form>
</td>
<td class="trow1">
<form action="" method="post">
<select name="days">
<option value="2">2 {$lang->days}</option>
<option value="7">7 {$lang->days}</option>
<option value="30">30 {$lang->days}</option>
<option value="90">90 {$lang->days}</option>
<option value="all">* {$lang->dvz_sb_mod_clear_all} *</option>
</select>
<input type="hidden" name="postkey" value="{$mybb->post_code}" />
<input type="submit" class="button" value="{$lang->dvz_sb_mod_clear_button}" />
</form>
</td>
</tr>
</table>
<br />',
    ];

    $data = [];

    foreach ($templates as $name => $content) {
        $data[] = [
            'title'    => $name,
            'template' => $db->escape_string($content),
            'sid'      => -1,
            'version'  => 1,
            'status'   => '',
            'dateline' => TIME_NOW,
        ];
    }

    $db->insert_query_multiple('templates', $data);
}

function dvz_shoutbox_uninstall ()
{
    global $db;

    $settingGroupId = $db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='dvz_shoutbox'"),
        'gid'
    );

    // delete settings
    $db->delete_query('settinggroups', 'gid=' . $settingGroupId);
    $db->delete_query('settings', 'gid=' . $settingGroupId);

    // delete templates
    $db->delete_query('templates', "title IN('dvz_shoutbox', 'dvz_shoutbox_panel', 'dvz_shoutbox_archive', 'dvz_shoutbox_archive_modoptions')");

    // delete data
    $db->drop_table('dvz_shoutbox');
}

function dvz_shoutbox_is_installed ()
{
    global $db;
    return (bool)$db->num_rows( $db->simple_select('settinggroups', 'gid', "name='dvz_shoutbox'") );
}


class dvz_shoutbox
{

    // hooks
    static function global_start ()
    {
        global $mybb, $templatelist;

        if (THIS_SCRIPT == 'index.php' && self::access_view()) {

            if (!empty($templatelist)) {
                $templatelist .= ',';
            }

            if ($mybb->input['action'] == 'shoutbox_archive') {
                // archive templates

                $templatelist .= 'dvz_shoutbox_archive,multipage,multipage_page,multipage_page_current,multipage_nextpage';

                if (self::access_mod()) {
                    $templatelist .= ',dvz_shoutbox_archive_modoptions';
                }

            } else {
                // index templates
                $templatelist .= 'dvz_shoutbox,dvz_shoutbox_panel';
            }

        }
    }

    static function global_end ()
    {
        global $mybb;

        if ($mybb->input['action'] == 'shoutbox_archive' && self::access_view()) {
            return self::show_archive();
        }
    }

    static function xmlhttp ()
    {
        global $mybb, $db, $charset;

        switch ($mybb->input['action']) {

            case 'dvz_sb_get_shouts':

                $permissions = (
                    ( self::access_view() && !isset($mybb->input['from']) ) ||
                    self::access_refresh()
                );

                $handler = function() use ($mybb, $db) {
                    $data = self::get_multiple("WHERE s.id > " . (int)$mybb->input['from'] . " ORDER BY s.id DESC LIMIT " . abs((int)$mybb->settings['dvz_sb_num']));

                    $html = null; // JS-handled empty response
                    $lastId = 0;

                    while ($row = $db->fetch_array($data)) {

                        if ($lastId == 0) {
                            $lastId = $row['id'];
                        }

                        $shout = self::render_shout($row);

                        $html  = $mybb->settings['dvz_sb_reversed']
                            ? $shout . $html
                            : $html  . $shout
                        ;
                    }

                    if ($html != null) {
                        echo json_encode([
                            'html' => $html,
                            'last' => $lastId,
                        ]);
                    }
                };

            break;
            case 'dvz_sb_shout':

                $permissions = (
                    self::access_shout() &&
                    verify_post_check($mybb->input['key'], true)
                );

                $handler = function() use ($mybb) {
                    if (!self::antiflood_pass() && !self::access_mod()) die('A'); // JS-handled error (Anti-flood)

                    self::shout([
                        'uid'       => $mybb->user['uid'],
                        'text'      => $mybb->input['text'],
                        'ipaddress' => my_inet_pton(get_ip()),
                    ]);
                };

            break;
            case 'dvz_sb_get':

                $data = self::get($mybb->input['id']);

                $permissions = (
                    (
                        self::access_mod() ||
                        (self::access_mod_own() && $data['uid'] == $mybb->user['uid'])
                    ) &&
                    verify_post_check($mybb->input['key'], true)
                );

                $handler = function() use ($data) {
                    echo json_encode([
                        'text' => $data['text'],
                    ]);
                };

            break;
            case 'dvz_sb_update':

                $permissions = (
                    self::can_mod($mybb->input['id']) &&
                    verify_post_check($mybb->input['key'], true)
                );

                $handler = function() use ($mybb) {
                    self::update($mybb->input['id'], $mybb->input['text']);
                    echo self::parse($mybb->input['text'], self::get_username($mybb->input['id']));

                };

            break;
            case 'dvz_sb_delete':

                $permissions = (
                    self::can_mod($mybb->input['id']) &&
                    verify_post_check($mybb->input['key'], true)
                );

                $handler = function() use ($mybb) {
                    self::delete($mybb->input['id']);
                };

            break;

        }

        if (isset($permissions)) {

            if ($permissions == false) {
                echo 'P'; // JS-handled error (Permissions)
            } else {

                header('Content-type: text/plain; charset=' . $charset);
                header('Cache-Control: no-store'); // force update on load
                $handler();

            }

        }
    }

    static function load_window ()
    {
        global $templates, $dvz_shoutbox, $lang, $mybb, $theme;

        $lang->load('dvz_shoutbox');

        // MyBB template
        $dvz_shoutbox = null;

        // dvz_shoutbox template
        $javascript   = null;
        $panel        = null;
        $classes      = null;

        if (self::access_view()) {

            if (self::is_user()) {

                // message: blocked
                if (self::is_blocked()) {
                    $panel = '<div class="panel blocked"><p>' . $lang->dvz_sb_user_blocked . '</p></div>';
                }
                // message: minimum posts
                else if (!self::access_minposts() && !self::access_mod()) {
                    $panel = '<div class="panel minposts"><p>' . str_replace('{MINPOSTS}', (int)$mybb->settings['dvz_sb_minposts'], $lang->dvz_sb_minposts) . '</p></div>';
                }
                // shout form
                else if (self::access_shout()) {
                    eval('$panel = "' . $templates->get('dvz_shoutbox_panel') . '";');
                }

            }

            $js = null;

            // configuration
            $js .= 'dvz_shoutbox.interval   = ' . (self::access_refresh() ? (float)$mybb->settings['dvz_sb_interval'] : 0) . ';' . PHP_EOL;
            $js .= 'dvz_shoutbox.antiflood  = ' . (self::access_mod() ? 0 : (float)$mybb->settings['dvz_sb_antiflood']) . ';' . PHP_EOL;
            $js .= 'dvz_shoutbox.maxShouts  = ' . (int)$mybb->settings['dvz_sb_num'] . ';' . PHP_EOL;
            $js .= 'dvz_shoutbox.awayTime   = ' . (float)$mybb->settings['dvz_sb_away'] . '*1000;' . PHP_EOL;
            $js .= 'dvz_shoutbox.lang       = [\'' . $lang->dvz_sb_delete_confirm . '\', \'' . str_replace('{ANTIFLOOD}', (float)$mybb->settings['dvz_sb_antiflood'], $lang->dvz_sb_antiflood) . '\', \''.$lang->dvz_sb_permissions.'\'];' . PHP_EOL;

            // reversed order
            if ($mybb->settings['dvz_sb_reversed']) {
                $js .= 'dvz_shoutbox.reversed   = true;' . PHP_EOL;
            }

            // lazyload
            if ($mybb->settings['dvz_sb_lazyload']) {
                $js .= 'dvz_shoutbox.lazyMode   = \'' . (int)$mybb->settings['dvz_sb_lazyload'] . '\';' . PHP_EOL;
                $js .= '$(window).bind(\'scroll resize\', dvz_shoutbox.checkVisibility);' . PHP_EOL;
            }

            // away mode
            if ($mybb->settings['dvz_sb_away']) {
                $js .= '$(window).on(\'mousemove click dblclick keydown scroll\', dvz_shoutbox.updateActivity);' . PHP_EOL;
            }

            // shoutbox status
            $status = isset($_COOKIE['dvz_sb_status'])
                ? (bool)$_COOKIE['dvz_sb_status']
                : (bool)$mybb->settings['dvz_sb_status']
            ;

            $js .= 'dvz_shoutbox.status     = ' . (int)$status . ';' . PHP_EOL;

            if ($status == false) {
                $classes .= ' collapsed';
            }

            $javascript = '
<script>
' . $js . '
dvz_shoutbox.updateActivity();
dvz_shoutbox.loop();
</script>';

            eval('$dvz_shoutbox = "' . $templates->get('dvz_shoutbox') . '";');

        }
    }

    static function user_merge ()
    {
        global $db, $source_user, $destination_user;
        return $db->update_query('dvz_shoutbox', ['uid' => $destination_user['uid']], 'uid=' . (int)$source_user['uid']);
    }

    static function activity (&$user_activity)
    {
        $location = parse_url($user_activity['location']);
        $filename = basename($location['path']);

        parse_str(html_entity_decode($location['query']), $parameters);

        if ($filename == 'index.php' && $parameters['action'] == 'shoutbox_archive') {
            $user_activity['activity'] = 'dvz_shoutbox_archive';
        }
    }

    static function activity_translate (&$data)
    {
        global $lang;

        $lang->load('dvz_shoutbox');

        if ($data['user_activity']['activity'] == 'dvz_shoutbox_archive') {
            $data['location_name'] = sprintf($lang->dvz_sb_activity, 'index.php?action=shoutbox_archive');
        }
    }

    static function show_archive ()
    {
        global $db, $mybb, $templates, $lang, $theme, $footer, $headerinclude, $header, $charset;

        $lang->load('dvz_shoutbox');

        header('Content-type: text/html; charset=' . $charset);

        add_breadcrumb($lang->dvz_sb_shoutbox, "index.php?action=shoutbox_archive");

        // moderation panel
        if (self::access_mod()) {

            if (isset($mybb->input['banlist']) && verify_post_check($mybb->input['postkey'])) {
                self::banlist_update($mybb->input['banlist']);
            }

            if (isset($mybb->input['days']) && verify_post_check($mybb->input['postkey'])) {
                if ($mybb->input['days'] == 'all') {
                    self::clear();
                } else {
                    $allowed = [2, 7, 30, 90];
                    if (in_array($mybb->input['days'], $allowed)) {
                        self::clear($mybb->input['days']);
                    }
                }
            }

            $blocked_users = htmlspecialchars_uni($mybb->settings['dvz_sb_blocked_users']);
            eval('$modoptions = "' . $templates->get("dvz_shoutbox_archive_modoptions") . '";');

        } else {
            $modoptions = null;
        }

        // pagination
        $items   = self::count();
        $page    = abs((int)$mybb->input['page']);
        $perPage = abs((int)$mybb->settings['dvz_sb_num_archive']);

        if ($perPage == 0) {
            $pages = 0;
        } else {
            $pages   = ceil($items / $perPage);
        }

        if (!$page || $page < 1 || $page > $pages) $page = 1;

        $start = ($page - 1) * $perPage;

        if ($items > $perPage) {
            $multipage = multipage($items, $perPage, $page, 'index.php?action=shoutbox_archive');
        }


        $data = self::get_multiple("ORDER by s.id DESC LIMIT $start,$perPage");

        $archive = null;

        while ($row = $db->fetch_array($data)) {
            $archive .= self::render_shout($row, true);
        }

        $javascript = '
<script>
dvz_shoutbox.lang = [\'' . $lang->dvz_sb_delete_confirm . '\', \'' . str_replace('{ANTIFLOOD}', (float)$mybb->settings['dvz_sb_antiflood'], $lang->dvz_sb_antiflood) . '\', \'' . $lang->dvz_sb_permissions . '\'];
</script>';

        eval('$content = "' . $templates->get("dvz_shoutbox_archive") . '";');

        output_page($content);

        exit;
    }

    // data manipulation
    static function get ($id)
    {
        global $db;
        return $db->fetch_array( $db->simple_select('dvz_shoutbox', '*', 'id=' . (int)$id) );
    }

    static function get_multiple ($clauses)
    {
        global $db;
        return $db->query("
            SELECT
                s.*, u.username, u.usergroup, u.displaygroup, u.avatar
            FROM
                " . TABLE_PREFIX . "dvz_shoutbox s
                LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = s.uid
            " . $clauses . "
        ");
    }

    static function get_username ($id)
    {
        global $db;
        return $db->fetch_field(
            $db->query("SELECT username FROM " . TABLE_PREFIX . "users u, " . TABLE_PREFIX . "dvz_shoutbox s WHERE u.uid=s.uid AND s.id=" . (int)$id),
            'username'
        );
    }

    static function user_last_shout_time ($uid)
    {
        global $db;
        return $db->fetch_field(
            $db->simple_select('dvz_shoutbox', 'date', 'uid=' . (int)$uid, [
                'order_by'  => 'date',
                'order_dir' => 'desc',
                'limit'     => 1,
            ]),
            'date'
        );
    }

    static function count ()
    {
        global $db;
        return $db->fetch_field(
            $db->simple_select('dvz_shoutbox', 'COUNT(*) as n'),
            'n'
        );
    }

    static function shout ($data)
    {
        global $db;

        foreach ($data as &$item) {
            $item = $db->escape_string($item);
        }

        $data['date'] = TIME_NOW;

        return $db->insert_query('dvz_shoutbox', $data);
    }

    static function update ($id, $text)
    {
        global $db;
        return $db->update_query('dvz_shoutbox', ['text' => $db->escape_string($text)], 'id=' . (int)$id);
    }

    static function banlist_update ($new)
    {
        global $db;

        $db->update_query('settings', ['value' => $db->escape_string($new)], "name='dvz_sb_blocked_users'");

        rebuild_settings();
    }

    static function delete ($id)
    {
        global $db;
        return $db->delete_query('dvz_shoutbox', 'id=' . (int)$id);
    }

    static function clear ($days = false)
    {
        global $db;

        if ($days) {
            $where = 'date < ' . ( TIME_NOW - ((int)$days * 86400) );
        } else {
            $where = false;
        }

        return $db->delete_query('dvz_shoutbox', $where);
    }

    // permissions
    static function is_user ()
    {
        global $mybb;
        return $mybb->user['uid'] != 0;
    }

    static function is_blocked ()
    {
        global $mybb;
        return in_array($mybb->user['uid'], self::settings_get_csv('blocked_users'));
    }

    static function access_view ()
    {
        $array = self::settings_get_csv('groups_view');
        return $array[0] == -1 || is_member($array);
    }

    static function access_refresh ()
    {
        $array = self::settings_get_csv('groups_refresh');
        return $array[0] == -1 || is_member($array);
    }

    static function access_shout ()
    {
        $array = self::settings_get_csv('groups_shout');

        return (
            self::is_user() &&
            !self::is_blocked() &&
            (
                self::access_mod() ||
                (
                    self::access_view() &&
                    self::access_minposts() &&
                    $array[0] == -1 || is_member($array)
                )
            )
        );
    }

    static function access_mod ()
    {
        global $mybb;

        $array = self::settings_get_csv('groups_mod');

        return (
            ($array[0] == -1 || is_member($array)) ||
            ($mybb->settings['dvz_sb_supermods'] && $mybb->usergroup['issupermod'])
        );
    }

    static function access_mod_own ()
    {
        $array = self::settings_get_csv('groups_mod_own');

        return $array[0] == -1 || is_member($array);
    }

    static function access_minposts ()
    {
        global $mybb;
        return $mybb->user['postnum'] >= $mybb->settings['dvz_sb_minposts'];
    }

    static function can_mod ($shoutId)
    {
        global $mybb;

        if (self::access_mod()) {
            return true;
        } else if (self::access_mod_own() && self::access_shout()) {

            $data = self::get($shoutId);

            if ($data['uid'] == $mybb->user['uid']) {
                return true;
            }

        }

        return false;

    }

    // core
    static function render_shout ($data, $static = false)
    {
        global $mybb;

        $id       = $data['id'];
        $text     = self::parse($data['text'], $data['username']);
        $date     = htmlspecialchars_uni(my_date($mybb->settings['dvz_sb_dateformat'], $data['date']));
        $username = htmlspecialchars_uni($data['username']);
        $user     = '<a href="member.php?action=profile&uid=' . (int)$data['uid'] . '">' . format_name($username, $data['usergroup'], $data['displaygroup']) . '</a>';
        $avatar   = '<img src="' . (empty($data['avatar']) ? htmlspecialchars_uni($mybb->settings['useravatar']) : htmlspecialchars_uni($data['avatar'])) . '" alt="avatar" />';

        $notes      = null;
        $attributes = null;

        $own = $data['uid'] == $mybb->user['uid'];

        if ($static) {

            if (self::access_mod()) {
                $notes .= '<span class="ip">' . my_inet_ntop($data['ipaddress']) . '</span>';
            }

            if (
                self::access_mod() ||
                (self::access_mod_own() && $own)
            ) {
                $notes .= '<a href="" class="mod edit">E</a><a href="" class="mod del">X</a>';
            }

        }

        if (
            self::access_mod() ||
            (self::access_mod_own() && $own)
        ) {
            $attributes .= ' data-mod';
        }

        if ($own) {
            $attributes .= ' data-own';
        }

        return '
<div class="entry" data-id="' . $id . '" data-username="' . $username . '"' . $attributes . '>
    <div class="avatar">' . $avatar . '</div>
    <div class="user">' . $user . '</div>
    <div class="text">' . $text . '</div>
    <div class="info">' . $notes . '<span class="date">' . $date . '</span></div>
</div>';
    }

    static function parse ($message, $me_username)
    {
        global $mybb;

        require_once MYBB_ROOT . 'inc/class_parser.php';

        $parser = new postParser;
        $options = [
            'allow_mycode'    => $mybb->settings['dvz_sb_mycode'],
            'allow_smilies'   => $mybb->settings['dvz_sb_smilies'],
            'allow_imgcode'   => 0,
            'filter_badwords' => 1,
            'me_username'     => $me_username,
        ];

        return $parser->parse_message($message, $options);
    }

    static function antiflood_pass ()
    {
        global $mybb;
        return (
            !$mybb->settings['dvz_sb_antiflood'] ||
            ( TIME_NOW - self::user_last_shout_time($mybb->user['uid']) ) > $mybb->settings['dvz_sb_antiflood']
        );
    }

    static function settings_get_csv ($name)
    {
        global $mybb;
        return array_filter( explode(',', $mybb->settings['dvz_sb_' . $name]) );
    }

}