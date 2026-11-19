<?php

namespace mediablue\Services;

use mediablue\Utils\CommandRunner;

class MediaService
{
    /**
     * D-Busから現在利用可能なすべてのBlueZ MediaPlayer1オブジェクトを取得・パースする
     *
     * @return array[] 検出された各プレイヤーの情報配列
     */
    public function getAllMediaPlayers(): array
    {
        $players = [];

        // 1. D-Bus ObjectManager から全オブジェクトを取得 (最も包括的かつ高速)
        $res = CommandRunner::run("dbus-send --system --print-reply --dest=org.bluez / org.freedesktop.DBus.ObjectManager.GetManagedObjects 2>/dev/null");
        if ($res['success'] && !empty($res['stdout'])) {
            $raw = $res['stdout'];

            // "object path "/org/bluez/.../playerX" ごとにブロックを分割
            $blocks = preg_split('/(?=object path "\/org\/bluez\/[^\/]+\/dev_[^\/]+\/player\d+")/i', $raw);

            foreach ($blocks as $block) {
                if (!preg_match('/object path "(\/org\/bluez\/[^\/]+\/dev_[^\/]+\/player(\d+))"/i', $block, $pm)) {
                    continue;
                }

                // org.bluez.MediaPlayer1 インターフェースを含むブロックのみ
                if (!str_contains($block, 'org.bluez.MediaPlayer1')) {
                    continue;
                }

                $path = $pm[1];
                $playerNum = (int)$pm[2];

                $status = 'stopped';
                $name = '';
                $title = '';
                $artist = '';
                $album = '';
                $position = 0;
                $duration = 0;

                if (preg_match('/string\s+"Status"\s+variant\s+string\s+"([^"]+)"/i', $block, $m)) {
                    $status = strtolower(trim($m[1]));
                }
                if (preg_match('/string\s+"Name"\s+variant\s+string\s+"([^"]+)"/i', $block, $m)) {
                    $name = trim($m[1]);
                }
                if (preg_match('/string\s+"Title"\s+variant\s+string\s+"([^"]+)"/i', $block, $m)) {
                    $title = trim($m[1]);
                }
                if (preg_match('/string\s+"Artist"\s+variant\s+string\s+"([^"]+)"/i', $block, $m)) {
                    $artist = trim($m[1]);
                }
                if (preg_match('/string\s+"Album"\s+variant\s+string\s+"([^"]+)"/i', $block, $m)) {
                    $album = trim($m[1]);
                }
                if (preg_match('/string\s+"Position"\s+variant\s+uint32\s+(\d+)/i', $block, $m)) {
                    $position = (int)round((int)$m[1] / 1000);
                }
                if (preg_match('/string\s+"Duration"\s+variant\s+uint32\s+(\d+)/i', $block, $m)) {
                    $duration = (int)round((int)$m[1] / 1000);
                }

                // プレイヤーの優先度スコアリング
                // 1. 現在再生中(playing)のものを最優先
                // 2. 次に一時停止中(paused)で曲名があるもの
                // 3. プレイヤー番号が大きい（新しく生成された）ものを優先
                $score = 0;
                if ($status === 'playing') {
                    $score += 10000;
                } elseif ($status === 'paused') {
                    $score += 2000;
                }

                if (!empty($title)) {
                    $score += 500;
                }
                if ($position > 0) {
                    $score += 100;
                }
                $score += $playerNum * 10;

                $players[] = [
                    'path' => $path,
                    'player_num' => $playerNum,
                    'app_name' => $name ?: 'Bluetooth Player',
                    'status' => in_array($status, ['playing', 'paused']) ? $status : 'stopped',
                    'title' => $title,
                    'artist' => $artist,
                    'album' => $album,
                    'position' => $position,
                    'duration' => $duration ?: 180,
                    'score' => $score,
                    'source' => 'dbus-objectmanager'
                ];
            }
        }

        // 2. ObjectManager で見つからなかった場合のフォールバック:
        // 接続中デバイスのMACから player0 〜 player4 を直接スキャン
        if (empty($players)) {
            $btRes = CommandRunner::run("bluetoothctl info 2>/dev/null");
            if ($btRes['success'] && preg_match('/Device\s+([0-9A-Fa-f:]{17})/i', $btRes['stdout'], $m)) {
                $formattedMac = 'dev_' . str_replace(':', '_', strtoupper(trim($m[1])));

                // player0 から player4 までチェック
                for ($i = 0; $i <= 4; $i++) {
                    $candidate = "/org/bluez/hci0/{$formattedMac}/player{$i}";
                    $cmd = "dbus-send --system --print-reply --dest=org.bluez " . escapeshellarg($candidate) . " org.freedesktop.DBus.Properties.GetAll string:org.bluez.MediaPlayer1 2>/dev/null";
                    $pRes = CommandRunner::run($cmd);
                    if ($pRes['success'] && !empty($pRes['stdout'])) {
                        $pOut = $pRes['stdout'];
                        $status = 'stopped';
                        $name = '';
                        $title = '';
                        $artist = '';
                        $album = '';
                        $position = 0;
                        $duration = 0;

                        if (preg_match('/string\s+"Status"\s+variant\s+string\s+"([^"]+)"/i', $pOut, $sm)) {
                            $status = strtolower(trim($sm[1]));
                        }
                        if (preg_match('/string\s+"Name"\s+variant\s+string\s+"([^"]+)"/i', $pOut, $nm)) {
                            $name = trim($nm[1]);
                        }
                        if (preg_match('/string\s+"Title"\s+variant\s+string\s+"([^"]+)"/i', $pOut, $tm)) {
                            $title = trim($tm[1]);
                        }
                        if (preg_match('/string\s+"Artist"\s+variant\s+string\s+"([^"]+)"/i', $pOut, $am)) {
                            $artist = trim($am[1]);
                        }
                        if (preg_match('/string\s+"Album"\s+variant\s+string\s+"([^"]+)"/i', $pOut, $alm)) {
                            $album = trim($alm[1]);
                        }
                        if (preg_match('/string\s+"Position"\s+variant\s+uint32\s+(\d+)/i', $pOut, $posm)) {
                            $position = (int)round((int)$posm[1] / 1000);
                        }
                        if (preg_match('/string\s+"Duration"\s+variant\s+uint32\s+(\d+)/i', $pOut, $durm)) {
                            $duration = (int)round((int)$durm[1] / 1000);
                        }

                        $score = 0;
                        if ($status === 'playing') $score += 10000;
                        elseif ($status === 'paused') $score += 2000;
                        if (!empty($title)) $score += 500;
                        $score += $i * 10;

                        $players[] = [
                            'path' => $candidate,
                            'player_num' => $i,
                            'app_name' => $name ?: 'Bluetooth Player',
                            'status' => in_array($status, ['playing', 'paused']) ? $status : 'stopped',
                            'title' => $title,
                            'artist' => $artist,
                            'album' => $album,
                            'position' => $position,
                            'duration' => $duration ?: 180,
                            'score' => $score,
                            'source' => 'dbus-direct'
                        ];
                    }
                }
            }
        }

        // スコアの高い順（再生中のプレイヤー、最新のプレイヤー）にソート
        usort($players, fn($a, $b) => $b['score'] <=> $a['score']);

        return $players;
    }

    /**
     * 現在アクティブ（再生中、あるいは最新）のメディアプレイヤーを特定する
     */
    public function findActivePlayer(): ?array
    {
        $players = $this->getAllMediaPlayers();
        return !empty($players) ? $players[0] : null;
    }

    /**
     * A2DP メディアプレーヤーの再生情報・メタデータを取得
     */
    public function getPlayerStatus(): array
    {
        // 1. D-Bus 経由でアクティブプレイヤーを取得
        $activePlayer = $this->findActivePlayer();

        if ($activePlayer && (!empty($activePlayer['title']) || $activePlayer['status'] === 'playing')) {
            return [
                'mode' => 'real',
                'available' => true,
                'title' => $activePlayer['title'] ?: 'Streaming Audio',
                'artist' => $activePlayer['artist'] ?: 'Bluetooth Device',
                'album' => $activePlayer['album'] ?: 'A2DP Sink',
                'app_name' => $activePlayer['app_name'],
                'status' => $activePlayer['status'],
                'position' => $activePlayer['position'],
                'duration' => $activePlayer['duration'],
                'codec' => 'A2DP (SBC/AAC)',
                'player_path' => $activePlayer['path'],
            ];
        }

        // 2. playerctl 経由 (セッションバス / WirePlumber MPRIS2 が有効な場合)
        if (CommandRunner::hasCommand('playerctl')) {
            // 利用可能なプレイヤー一覧を取得し、再生中のプレイヤーを優先
            $listRes = CommandRunner::run("playerctl -l 2>/dev/null");
            if ($listRes['success'] && !empty($listRes['stdout'])) {
                $playerNames = explode("\n", trim($listRes['stdout']));
                $bestPlayer = null;

                foreach ($playerNames as $pName) {
                    $pName = trim($pName);
                    if (!$pName) continue;

                    $statRes = CommandRunner::run("playerctl -p " . escapeshellarg($pName) . " status 2>/dev/null");
                    $pStatus = strtolower(trim($statRes['stdout'] ?? ''));

                    $format = "{{title}}::;::{{artist}}::;::{{album}}::;::{{status}}::;::{{position}}::;::{{mpris:length}}";
                    $metaRes = CommandRunner::run("playerctl -p " . escapeshellarg($pName) . " metadata --format " . escapeshellarg($format) . " 2>/dev/null");
                    
                    if ($metaRes['success'] && !empty($metaRes['stdout'])) {
                        $parts = explode("::;::", $metaRes['stdout']);
                        $title = $parts[0] ?? '';
                        $artist = $parts[1] ?? '';
                        $album = $parts[2] ?? '';
                        $position = isset($parts[4]) ? (int)round((float)$parts[4] / 1000000) : 0;
                        $duration = isset($parts[5]) ? (int)round((float)$parts[5] / 1000000) : 0;

                        $candidate = [
                            'mode' => 'real',
                            'available' => true,
                            'title' => $title ?: 'Streaming Audio',
                            'artist' => $artist ?: 'Bluetooth Device',
                            'album' => $album ?: 'A2DP Sink',
                            'app_name' => ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', $pName)),
                            'status' => in_array($pStatus, ['playing', 'paused']) ? $pStatus : 'stopped',
                            'position' => $position,
                            'duration' => $duration ?: 180,
                            'codec' => 'A2DP (playerctl)',
                        ];

                        if ($pStatus === 'playing') {
                            return $candidate; // 再生中なら即座に決定
                        }
                        if (!$bestPlayer) {
                            $bestPlayer = $candidate;
                        }
                    }
                }

                if ($bestPlayer) {
                    return $bestPlayer;
                }
            }
        }

        // 3. アクティブプレイヤーはあるが曲名が未通知の場合
        if ($activePlayer) {
            return [
                'mode' => 'real',
                'available' => true,
                'title' => 'Streaming Audio',
                'artist' => $activePlayer['app_name'],
                'album' => 'A2DP Audio Stream',
                'app_name' => $activePlayer['app_name'],
                'status' => $activePlayer['status'],
                'position' => $activePlayer['position'],
                'duration' => $activePlayer['duration'],
                'codec' => 'A2DP (SBC/AAC)',
                'player_path' => $activePlayer['path'],
            ];
        }

        // 4. bluetoothctl info から接続端末を特定
        $res = CommandRunner::run("bluetoothctl info 2>/dev/null");
        if ($res['success'] && str_contains($res['stdout'], 'Connected: yes')) {
            $deviceName = 'Connected Smartphone';
            if (preg_match('/Name:\s*(.+)$/m', $res['stdout'], $m)) {
                $deviceName = trim($m[1]);
            }
            return [
                'mode' => 'real',
                'available' => true,
                'title' => 'Bluetooth Audio Connected',
                'artist' => $deviceName,
                'album' => 'Open music app on phone to play',
                'app_name' => 'Smartphone',
                'status' => 'paused',
                'position' => 0,
                'duration' => 0,
                'codec' => 'A2DP Sink',
            ];
        }

        return [
            'mode' => 'real',
            'available' => false,
            'title' => 'No Media Playing',
            'artist' => 'Connect a phone via Bluetooth',
            'album' => '',
            'app_name' => '',
            'status' => 'stopped',
            'position' => 0,
            'duration' => 0,
            'codec' => '-',
        ];
    }

    /**
     * AVRCP コントロールコマンドを実行
     * 現在再生中、または最新のアクティブプレイヤーを対象にして操作する
     */
    public function control(string $action): array
    {
        $activePlayer = $this->findActivePlayer();
        $playerPath = $activePlayer['path'] ?? null;
        $errors = [];

        // 1. D-Bus System Bus 経由で実行 (BlueZ MediaPlayer1)
        if ($playerPath) {
            $method = match ($action) {
                'play' => 'Play',
                'pause' => 'Pause',
                'play-pause', 'toggle' => null, // 後で判定
                'next' => 'Next',
                'previous', 'prev' => 'Previous',
                'stop' => 'Stop',
                default => null,
            };

            if ($action === 'play-pause' || $action === 'toggle') {
                $currentStatus = $activePlayer['status'] ?? 'stopped';
                $method = ($currentStatus === 'playing') ? 'Pause' : 'Play';
            }

            if ($method) {
                $cmd = "dbus-send --system --type=method_call --print-reply --dest=org.bluez " 
                     . escapeshellarg($playerPath) 
                     . " org.bluez.MediaPlayer1." . $method . " 2>&1";
                $res = CommandRunner::run($cmd);

                if ($res['success']) {
                    return [
                        'success' => true,
                        'action' => $action,
                        'method' => $method,
                        'player_path' => $playerPath,
                        'app_name' => $activePlayer['app_name'] ?? '',
                        'via' => 'dbus-system-bus',
                        'output' => $res['stdout']
                    ];
                }
                $errors[] = "D-Bus ({$playerPath}) failed: " . ($res['stderr'] ?: $res['stdout']);
            }
        }

        // 2. bluetoothctl 対話モードパイプ (フォールバック 1)
        $btCommand = match ($action) {
            'play' => 'play',
            'pause' => 'pause',
            'play-pause', 'toggle' => 'play',
            'next' => 'next',
            'previous', 'prev' => 'previous',
            'stop' => 'stop',
            default => null,
        };

        if ($btCommand) {
            $pipeScript = "menu player\n{$btCommand}\nback\nquit\n";
            $cmd = "printf " . escapeshellarg($pipeScript) . " | bluetoothctl 2>&1";
            $res = CommandRunner::run($cmd);
            if ($res['success'] && !str_contains($res['stdout'], 'Failed') && !str_contains($res['stdout'], 'not available')) {
                return [
                    'success' => true,
                    'action' => $action,
                    'via' => 'bluetoothctl-pipe',
                    'output' => $res['stdout']
                ];
            }
            $errors[] = "bluetoothctl pipe: " . $res['stdout'];
        }

        // 3. playerctl CLI (フォールバック 2: Session Bus がある場合)
        if (CommandRunner::hasCommand('playerctl')) {
            $pAction = match ($action) {
                'play' => 'play',
                'pause' => 'pause',
                'play-pause', 'toggle' => 'play-pause',
                'next' => 'next',
                'previous', 'prev' => 'previous',
                'stop' => 'stop',
                default => null,
            };

            if ($pAction) {
                $res = CommandRunner::run("playerctl {$pAction} 2>&1");
                if ($res['success']) {
                    return [
                        'success' => true,
                        'action' => $action,
                        'via' => 'playerctl',
                        'output' => $res['stdout']
                    ];
                }
                $errors[] = "playerctl: " . $res['stdout'];
            }
        }

        return [
            'success' => false,
            'error' => implode(' | ', $errors) ?: "Control failed for action '{$action}'"
        ];
    }
}
