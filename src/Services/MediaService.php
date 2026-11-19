<?php

namespace mediablue\Services;

use mediablue\Utils\CommandRunner;

class MediaService
{
    public function getPlayerStatus(): array
    {
        $hasPlayerctl = CommandRunner::hasCommand('playerctl');

        if ($hasPlayerctl) {
            $format = "{{title}}::;::{{artist}}::;::{{album}}::;::{{status}}::;::{{position}}::;::{{mpris:length}}";
            $res = CommandRunner::run("playerctl metadata --format " . escapeshellarg($format));
            
            if ($res['success'] && !empty($res['stdout'])) {
                $parts = explode("::;::", $res['stdout']);
                $title = $parts[0] ?? 'Unknown Title';
                $artist = $parts[1] ?? 'Unknown Artist';
                $album = $parts[2] ?? 'Unknown Album';
                $status = strtolower($parts[3] ?? 'stopped');
                $position = isset($parts[4]) ? (int)round((float)$parts[4] / 1000000) : 0;
                $duration = isset($parts[5]) ? (int)round((float)$parts[5] / 1000000) : 0;

                return [
                    'mode' => 'real',
                    'available' => true,
                    'title' => $title ?: 'Streaming Audio',
                    'artist' => $artist ?: 'Bluetooth Device',
                    'album' => $album ?: 'A2DP Sink',
                    'status' => in_array($status, ['playing', 'paused']) ? $status : 'stopped',
                    'position' => $position,
                    'duration' => $duration ?: 180,
                    'codec' => 'AAC / SBC',
                ];
            }
        }

        // playerctl がない場合、bluetoothctl からメタデータを探す
        $res = CommandRunner::run("bluetoothctl info");
        if ($res['success'] && str_contains($res['stdout'], 'Connected: yes')) {
            return [
                'mode' => 'real',
                'available' => true,
                'title' => 'Bluetooth Audio Stream',
                'artist' => 'Connected Smartphone',
                'album' => 'A2DP Profile',
                'status' => 'playing',
                'position' => 0,
                'duration' => 0,
                'codec' => 'A2DP (SBC/AAC)',
            ];
        }

        return [
            'mode' => 'real',
            'available' => false,
            'title' => 'No Media Playing',
            'artist' => 'Connect a phone via Bluetooth',
            'album' => '',
            'status' => 'stopped',
            'position' => 0,
            'duration' => 0,
            'codec' => '-',
        ];
    }

    public function control(string $action): array
    {
        $hasPlayerctl = CommandRunner::hasCommand('playerctl');

        if ($hasPlayerctl) {
            $cmd = match ($action) {
                'play' => 'playerctl play',
                'pause' => 'playerctl pause',
                'play-pause', 'toggle' => 'playerctl play-pause',
                'next' => 'playerctl next',
                'previous', 'prev' => 'playerctl previous',
                'stop' => 'playerctl stop',
                default => null,
            };

            if ($cmd) {
                $res = CommandRunner::run($cmd);
                return [
                    'success' => $res['success'],
                    'action' => $action,
                    'output' => $res['stdout'] . $res['stderr']
                ];
            }
        }

        // D-Bus または bluetoothctl 経由
        // bluetoothctl player.play / player.pause / player.next / player.previous
        $btCmd = match ($action) {
            'play' => 'bluetoothctl -- player.play',
            'pause' => 'bluetoothctl -- player.pause',
            'next' => 'bluetoothctl -- player.next',
            'previous', 'prev' => 'bluetoothctl -- player.previous',
            default => null,
        };

        if ($btCmd) {
            $res = CommandRunner::run($btCmd);
            return [
                'success' => $res['success'],
                'action' => $action,
                'output' => $res['stdout'] . $res['stderr']
            ];
        }

        return ['success' => false, 'error' => "Unsupported action: {$action}"];
    }
}
