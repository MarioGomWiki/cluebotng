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
    class Process
    {
        public static function processEditThread($change)
        {
            $change[ 'edit_status' ] = 'not_reverted';
            $s = null;
            $change[ 'edit_score' ] = &$s;
            if (!in_array('all', $change) || !isVandalism($change[ 'all' ], $s)) {
                Relay::skippedVandalism($change, 'below_threashold');

                return;
            }

            echo 'Is '.$change[ 'user' ].' whitelisted ?'."\n";
            if (Action::isWhitelisted($change[ 'user' ])) {
                Relay::skippedVandalism($change, 'whitelisted');

                return;
            }
            echo 'No.'."\n";
            $reason = 'ANN scored at '.$s;

            // Remove vandalism entries over 2 days old
            $oftVand = unserialize(file_get_contents('oftenvandalized.txt'));
            if (rand(1, 50) == 2) {
                foreach ($oftVand as $art => $artVands) {
                    foreach ($artVands as $key => $time) {
                        if ((time() - $time) > 2 * 24 * 60 * 60) {
                            unset($oftVand[ $art ][ $key ]);
                        }
                    }
                }
            }

            // Save this vandalism entry
            $oftVand[ $change[ 'title' ] ][] = time();
            file_put_contents('oftenvandalized.txt', serialize($oftVand));

            // Report repeat vandalism
            if (count($oftVand[ $change[ 'title' ] ]) >= 30) {
                Relay::repeatVandalism($change, count($oftVand[ $change[ 'title' ] ]));
            }

            $change['mysqlid'] = Db::detectedVandalism($change['user'], $change['title'], $reason, $change['url'], $change['old_revid'], $change['revid']);

            list($shouldRevert, $revertReason) = Action::shouldRevert($change);
            if ($shouldRevert) {
                ;
                $rbret = Action::doRevert($change);
                if ($rbret !== false) {
                    $change[ 'edit_status' ] = 'reverted';

                    Relay::revertEdit($change, $revertReason, (microtime(true) - $change[ 'startTime' ]));
                    Action::doWarn($change, $report);
                    Db::vandalismReverted($change['mysqlid']);
                } else {
                    $change[ 'edit_status' ] = 'beaten';
                    $rv2 = API::$a->revisions($change[ 'title' ], 1);
                    if ($change[ 'user' ] != $rv2[ 0 ][ 'user' ]) {
                        Relay::beatenEdit($change, $rv2[ 0 ][ 'user' ], (microtime(true) - $change[ 'startTime' ]));
                        Db::vandalismRevertBeaten($change['mysqlid'], $change['title'], $rv2[ 0 ][ 'user' ], $change[ 'url' ]);
                    }
                }
            } else {
                Relay::skippedVandalism($change, $revertReason);
            }
        }
        public static function processEdit($change)
        {
            if (
                (time() - globals::$tfas) >= 1800
                and (preg_match('/\(\'\'\'\[\[([^|]*)\|more...\]\]\'\'\'\)/iU', API::$q->getpage('Wikipedia:Today\'s featured article/'.date('F j, Y')), $tfam))
            ) {
                globals::$tfas = time();
                globals::$tfa = $tfam[ 1 ];
            }
            if (config::$fork) {
                $pid = pcntl_fork();
                if ($pid != 0) {
                    echo 'Forked - '.$pid."\n";

                    return;
                }
            }
            $change = parseFeedData($change);
            $change[ 'justtitle' ] = $change[ 'title' ];
            if (in_array('namespace', $change) && $change[ 'namespace' ] != 'Main:') {
                $change[ 'title' ] = $change[ 'namespace' ].$change[ 'title' ];
            }
            self::processEditThread($change);
            if (config::$fork) {
                die();
            }
        }
    }
