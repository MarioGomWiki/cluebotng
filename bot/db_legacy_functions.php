<?php

/*
 * Copyright (C) 2015 Jacobi Carter and Chris Breneman
 *
 * This file is part of ClueBot NG.
 *
 * ClueBot NG is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ClueBot NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ClueBot NG.  If not, see <http://www.gnu.org/licenses/>.
 */
class LegacyDb
{
    // Returns the edit it for the vandalism
    public static function detectedVandalism($user, $title, $reason, $url, $old_rev_id, $rev_id)
    {
        checkLegacyMySQL();
        $query = 'INSERT INTO `vandalism` '.
            '(`id`,`user`,`article`,`reason`,`diff`,`old_id`,`new_id`,`reverted`) '.
            'VALUES '.
            '(NULL,\''.mysql_real_escape_string($user).'\','.
            '\''.mysql_real_escape_string($title).'\','.
            '\''.mysql_real_escape_string($reason).'\','.
            '\''.mysql_real_escape_string($url).'\','.
            '\''.mysql_real_escape_string($old_rev_id).'\','.
            '\''.mysql_real_escape_string($rev_id).'\',0)';
        mysql_query($query, globals::$legacy_mysql);

        return mysql_insert_id(globals::$legacy_mysql);
    }
    // Returns nothing
    public static function vandalismReverted($edit_id)
    {
        checkLegacyMySQL();
        mysql_query('UPDATE `vandalism` SET `reverted` = 1 WHERE `id` = \''.mysql_real_escape_string($edit_id).'\'', globals::$legacy_mysql);
    }
    // Returns nothing
    public static function vandalismRevertBeaten($edit_id, $title, $user, $diff)
    {
        checkLegacyMySQL();
        mysql_query('UPDATE `vandalism` SET `reverted` = 0 WHERE `id` = \''.
                        mysql_real_escape_string($edit_id).
                    '\'', globals::$legacy_mysql);
        mysql_query('INSERT INTO `beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\''.
                        mysql_real_escape_string($title).'\',\''.
                        mysql_real_escape_string($diff).'\',\''.
                        mysql_real_escape_string($user).
                    '\')', globals::$legacy_mysql);
    }
    // Returns the hostname of the current core node
    public static function getCurrentCoreNode()
    {
        checkLegacyMySQL();
        $res = mysql_query('SELECT `node`, `port` from `cluster_node` where type="core"', globals::$legacy_mysql);
        $d = mysql_fetch_assoc($res);

        return array($d['node'], $d['port']);
    }
    // Returns the hostname of the current relay node
    public static function getCurrentRelayNode()
    {
        checkLegacyMySQL();
        $res = mysql_query('SELECT `node`, `port` from `cluster_node` where type="ng_relay"', globals::$legacy_mysql);
        $d = mysql_fetch_assoc($res);

        return array($d['node'], $d['port']);
    }

    public static function addOFVEntry($title)
    {
        checkLegacyMySQL();
        mysql_query('INSERT INTO `often_vandalised` VALUES ('.
                    '\''.mysql_real_escape_string($title).'\','.
                    time().')', globals::$legacy_mysql);
    }

    private static function cleanupOVFEntries()
    {
        if (rand(0, 100) == 2) {
            return;
        }

        checkLegacyMySQL();
        mysql_query('DELETE FROM `often_vandalised` WHERE date < '.
                    (time() - 172800));
    }

    public static function getOFVCount($title)
    {
        checkLegacyMySQL();
        self::cleanupOVFEntries();
        $res = mysql_query('SELECT COUNT(*) as count FROM `often_vandalised` WHERE date > '.
                            (time() - 172800).
                            ' AND title = '.
                            '\''.mysql_real_escape_string($title).'\'');

        if ($res !== false) {
            $d = mysql_fetch_assoc($res);

            return $d['count'];
        }

        return 0;
    }

    public static function addTitleUserRevert($title, $user)
    {
        checkLegacyMySQL();
        mysql_query('REPLACE INTO `page_reverts` VALUES ('.
                    '\''.mysql_real_escape_string($title).'\','.
                    '\''.mysql_real_escape_string($user).'\','.
                    time().')', globals::$legacy_mysql);
    }

    public static function getLastUserRevertForTitle($title, $user)
    {
        checkLegacyMySQL();
        $res = mysql_query('SELECT date FROM `page_reverts` WHERE '.
                                'title = '.'\''.
                                    mysql_real_escape_string($title).'\' '.
                                'AND user = '.'\''.
                                    mysql_real_escape_string($user).'\'',
                            globals::$legacy_mysql);

        if ($res !== false) {
            $d = mysql_fetch_assoc($res);

            return $d['date'];
        }
    }
}
