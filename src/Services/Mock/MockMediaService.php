<?php

namespace mediablue\Services\Mock;

use mediablue\Config;

class MockMediaService
{
    private array $playlist = [
        [
            'title' => 'Blinding Lights',
            'artist' => 'The Weeknd',
            'album' => 'After Hours',
            'duration' => 200,
            'codec' => 'AAC 256kbps (A2DP)',
            'cover' => 'linear-gradient(135deg, #e52d27 0%, #b31217 100%)',
        ],
        [
            'title' => 'Stay',
            'artist' => 'The Kid LAROI, Justin Bieber',
            'album' => 'F*CK LOVE 3: OVER YOU',
            'duration' => 141,
            'codec' => 'LDAC 990kbps (A2DP)',
            'cover' => 'linear-gradient(135deg, #8a2387 0%, #e94057 50%, #f27121 100%)',
        ],
        [
            'title' => 'Cruel Summer',
            'artist' => 'Taylor Swift',
            'album' => 'Lover',
            'duration' => 178,
            'codec' => 'AAC 256kbps (A2DP)',
            'cover' => 'linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%)',
        ],
        [
            'title' => 'Midnight City',
            'artist' => 'M83',
            'album' => 'Hurry Up, We\'re Dreaming',
            'duration' => 243,
            'codec' => 'aptX HD (A2DP)',
            'cover' => 'linear-gradient(135deg, #1f4037 0%, #99f2c8 100%)',
        ],
    ];

    private function getMediaState(): array
    {
        $state = Config::getState();
        if (!isset($state['mock_media'])) {
            $state['mock_media'] = [
                'current_track_index' => 0,
                'status' => 'playing',
                'position' => 38,
                'last_update' => time(),
                'repeat' => 'all',
                'shuffle' => false,
            ];
            Config::saveState($state);
        }

        // 再生中なら時間進行を計算
        $media = $state['mock_media'];
        $now = time();
        if ($media['status'] === 'playing') {
            $delta = $now - ($media['last_update'] ?? $now);
            if ($delta > 0) {
                $track = $this->playlist[$media['current_track_index']];
                $newPos = $media['position'] + $delta;
                if ($newPos >= $track['duration']) {
                    // 自動で次の曲へ
                    $media['current_track_index'] = ($media['current_track_index'] + 1) % count($this->playlist);
                    $media['position'] = 0;
                } else {
                    $media['position'] = $newPos;
                }
                $media['last_update'] = $now;
                $state['mock_media'] = $media;
                Config::saveState($state);
            }
        } else {
            $media['last_update'] = $now;
            $state['mock_media'] = $media;
            Config::saveState($state);
        }

        return $media;
    }

    private function saveMediaState(array $data): void
    {
        $state = Config::getState();
        $state['mock_media'] = $data;
        Config::saveState($state);
    }

    public function getPlayerStatus(): array
    {
        $media = $this->getMediaState();
        $track = $this->playlist[$media['current_track_index']];

        return [
            'mode' => 'mock',
            'available' => true,
            'title' => $track['title'],
            'artist' => $track['artist'],
            'album' => $track['album'],
            'status' => $media['status'],
            'position' => $media['position'],
            'duration' => $track['duration'],
            'codec' => $track['codec'],
            'cover' => $track['cover'],
            'repeat' => $media['repeat'],
            'shuffle' => $media['shuffle'],
        ];
    }

    public function control(string $action): array
    {
        $media = $this->getMediaState();

        switch ($action) {
            case 'play':
                $media['status'] = 'playing';
                $media['last_update'] = time();
                break;
            case 'pause':
                $media['status'] = 'paused';
                $media['last_update'] = time();
                break;
            case 'play-pause':
            case 'toggle':
                $media['status'] = ($media['status'] === 'playing') ? 'paused' : 'playing';
                $media['last_update'] = time();
                break;
            case 'next':
                $media['current_track_index'] = ($media['current_track_index'] + 1) % count($this->playlist);
                $media['position'] = 0;
                $media['last_update'] = time();
                break;
            case 'previous':
            case 'prev':
                if ($media['position'] > 3) {
                    $media['position'] = 0;
                } else {
                    $media['current_track_index'] = ($media['current_track_index'] - 1 + count($this->playlist)) % count($this->playlist);
                    $media['position'] = 0;
                }
                $media['last_update'] = time();
                break;
            case 'stop':
                $media['status'] = 'stopped';
                $media['position'] = 0;
                $media['last_update'] = time();
                break;
        }

        $this->saveMediaState($media);

        return [
            'success' => true,
            'action' => $action,
            'status' => $media['status'],
        ];
    }
}
