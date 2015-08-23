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
class Relay
{
    private static function send($prefix, $data)
    {
        $safe_prefix = preg_replace("/[^A-Za-z0-9_\-]/", '', $prefix);
        $payload = $safe_prefix . '.' . json_encode($data);

        print "Sending to relay: $payload\n";
        
        ($relay_node, $relay_port) = Db::getCurrentRelayNode();
        $fp = fsockopen($relay_node, $relay_port, $errno, $errstr, 2);
        if($fp == null) {
            print "Could not connect to relay node: $errstr ($errno)\n";
            return;
        }
        fwrite($fp, $payload);
        fclose($fp);
    }

    private static function skippedEdit($edit, $reason) {
        $data = array(
            'skipped_reason' => $reason,
            'edit' => $edit,
        );
        self::send('skipped_edit', $data);
    }

    private static function stalkEdit($edit) {
        // Calculate which channels we need to send to
        $stalkchannel = array();

        foreach (globals::$stalk as $key => $value) {
            if (myfnmatch(str_replace('_', ' ', $key), str_replace('_', ' ', $data[ 'user' ]))) {
                $stalkchannel = array_merge($stalkchannel, explode(',', $value));
            }
        }

        foreach (globals::$edit as $key => $value) {
            if (myfnmatch(str_replace('_', ' ', $key), str_replace('_', ' ', ($data[ 'namespace' ] == 'Main:' ? '' : $data[ 'namespace' ]).$data[ 'title' ]))) {
                $stalkchannel = array_merge($stalkchannel, explode(',', $value));
            }
        }

        // Send a stalk entry for each channel
        $data = array(
            'edit' => $edit,
        );
        foreach (array_unique($stalkchannel) as $chan) {
            $data['channel'] = $chan;
            self::send('stalk', $data);
        }
    }

    private static function reportUserToAVI($user) {
        $data = array(
            'user' => $user,
        );
        self::send('avi_report', $data);
    }

    private static function warnUser($edit, $level) {
        $data = array(
            'user' => $edit[ 'user' ],
            'level' => $level,
            'edit' => $edit,
        );
        self::send('warn_user', $data);
    }

    private static function angryRevert($edit) {
        $data = array(
            'edit' => $edit,
        );
        self::send('angry_revert', $data);
    }

    private static function repeatVandalism($edit, $count) {
        $data = array(
            '2day_count' => $count,
            'edit' => $edit,
        );
        self::send('repeat_vandalism', $data);
    }

    private static function revertEdit($edit, $revertReason, $processingTime) {
        $data = array(
            'reason' => $revertReason,
            'processing_time' => $processingTime,
            'edit' => $edit,
        );
        self::send('vandalism_revert', $data);
    }

    private static function beatenEdit($change, $beatenBy, $processingTime) {
        $data = array(
            'beaten_by' => $beatenBy,
            'processing_time' => $processingTime,
            'edit' => $edit,
        );
        self::send('vandalism_revert_beaten', $data);
    }

    private static function skippedVandalism($edit, $reason) {
        $data = array(
            'skipped_reason' => $reason,
            'edit' => $edit,
        );
        self::send('vandalism_revert_skipped', $data);
    }
}
