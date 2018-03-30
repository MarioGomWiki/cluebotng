<?php
namespace CluebotNG;

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

class Feed
{
    private static $fd;

    public static function connectLoop()
    {
        $host = Config::$feed_irc_host;
        $port = Config::$feed_irc_port;
        self::$fd = fsockopen($host, $port, $feederrno, $feederrstr, 30);
        if (!self::$fd) {
            return;
        }
        $nick = str_replace(' ', '_', Config::$user);
        self::send('USER ' . $nick . ' "1" "1" :ClueBot Wikipedia Bot 2.0.');
        self::send('NICK ' . $nick);
        while (!feof(self::$fd)) {
            $rawline = fgets(self::$fd, 1024);
            $line = str_replace(array("\n", "\r"), '', $rawline);
            if (!$line) {
                fclose(self::$fd);
                break;
            }
            self::loop($line);
        }
    }

    public static function send($line)
    {
        fwrite(self::$fd, $line . "\n");
    }

    private static function loop($line)
    {
        $channel = Config::$feed_irc_channel;
        global $logger;
        $d = IRC::split($line);
        if ($d === null) {
            return;
        }
        if ($d['type'] == 'direct') {
            switch ($d['command']) {
                case 'ping':
                    self::send('PONG :' . $d['pieces'][0]);
                    break;
            }
        } else {
            switch ($d['command']) {
                case '376':
                case '422':
                    self::send('JOIN ' . $channel);
                    break;
                case 'privmsg':
                    if (strtolower($d['target']) == $channel) {
                        $rawmessage = $d['pieces'][0];
                        $message = str_replace("\002", '', $rawmessage);
                        $message = preg_replace('/\003(\d\d?(,\d\d?)?)?/', '', $message);
                        $data = parseFeed($message);
                        if ($data === false) {
                            return;
                        }
                        $data['line'] = $message;
                        $data['rawline'] = $rawmessage;
                        if (stripos('N', $data['flags']) !== false) {
                            self::bail($data, 'New article');

                            return;
                        }
                        $stalkchannel = array();
                        foreach (Globals::$stalk as $key => $value) {
                            if (myfnmatch(str_replace('_', ' ', $key), str_replace('_', ' ', $data['user']))) {
                                $stalkchannel = array_merge($stalkchannel, explode(',', $value));
                            }
                        }
                        foreach (Globals::$edit as $key => $value) {
                            if (myfnmatch(
                                str_replace('_', ' ', $key),
                                str_replace(
                                    '_',
                                    ' ',
                                    ($data['namespace'] == 'Main:' ? '' : $data['namespace']) .
                                    $data['title']
                                )
                            )
                            ) {
                                $stalkchannel = array_merge($stalkchannel, explode(',', $value));
                            }
                        }
                        $stalkchannel = array_unique($stalkchannel);
                        foreach ($stalkchannel as $chan) {
                            IRC::say(
                                $chan,
                                'New edit: [[' . ($data['namespace'] == 'Main:' ? '' : $data['namespace']) .
                                $data['title'] . ']] https://en.wikipedia.org/w/index.php?title=' .
                                urlencode($data['namespace'] . $data['title']) . '&diff=prev&oldid=' .
                                urlencode($data['revid']) . ' * ' . $data['user'] .
                                ' * ' . $data['comment']
                            );
                        }
                        switch ($data['namespace'] . $data['title']) {
                            case 'User:' . Config::$user . '/Run':
                                Globals::$run = Api::$q->getpage('User:' . Config::$user . '/Run');
                                break;
                            case 'Wikipedia:Huggle/Whitelist':
                                Globals::$wl = Api::$q->getpage('Wikipedia:Huggle/Whitelist');
                                break;
                            case 'User:' . Config::$user . '/Optin':
                                Globals::$optin = Api::$q->getpage('User:' . Config::$user . '/Optin');
                                break;
                            case 'User:' . Config::$user . '/AngryOptin':
                                Globals::$aoptin = Api::$q->getpage('User:' . Config::$user . '/AngryOptin');
                                break;
                        }

                        if ($data['namespace'] != 'Main:' and
                            !preg_match(
                                '/\* \[\[(' . preg_quote($data['namespace'] . $data['title'], '/') .
                                ')\]\] \- .*/i',
                                Globals::$optin
                            )
                        ) {
                            self::bail($data, 'Outside of valid namespaces');

                            return;
                        }
                        $logger->addInfo('Processing: ' . $message);
                        Process::processEdit($data);
                    }
                    break;
            }
        }
    }

    public static function bail($change, $why = '', $score = 'N/A', $reverted = false)
    {
        $rchange = $change;
        $rchange['edit_reason'] = $why;
        $rchange['edit_score'] = $score;
        RedisProxy::send($rchange);

        if (!in_array('raw_line', $change)) {
            return;
        }

        $udp = @fsockopen('udp://' . Db::getCurrentRelayNode(), Config::$udpport);
        if($udp !== false) {
            fwrite(
                $udp,
                $change['rawline'] . "\003 # " . $score . ' # ' . $why . ' # ' . ($reverted ? 'Reverted' : 'Not reverted')
            );
            @fclose($udp);
        }
    }
}
