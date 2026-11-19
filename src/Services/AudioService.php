<?php

namespace mediablue\Services;

use mediablue\Config;
use mediablue\Utils\CommandRunner;

class AudioService
{
    private string $pulseServer;

    public function __construct()
    {
        $this->pulseServer = Config::getPulseServer();
    }

    private function getPactlEnv(): string
    {
        return "PULSE_SERVER=" . escapeshellarg($this->pulseServer) . " ";
    }

    public function getStatus(): array
    {
        $sinks = $this->getSinks();
        $sources = $this->getSources();
        $defaultSink = $this->getDefaultSink();

        $masterVolume = 70;
        $muted = false;

        foreach ($sinks as $sink) {
            if ($sink['is_default']) {
                $masterVolume = $sink['volume'];
                $muted = $sink['muted'];
                break;
            }
        }

        return [
            'mode' => 'real',
            'master_volume' => $masterVolume,
            'muted' => $muted,
            'default_sink' => $defaultSink,
            'sinks' => $sinks,
            'sources' => $sources,
        ];
    }

    public function getSinks(): array
    {
        $res = CommandRunner::run($this->getPactlEnv() . "pactl list sinks");
        if (!$res['success']) {
            return [];
        }

        $sinks = [];
        $currentSink = null;
        $lines = explode("\n", $res['stdout']);

        foreach ($lines as $line) {
            if (preg_match('/^Sink #(\d+)/', $line, $m)) {
                if ($currentSink) {
                    $sinks[] = $currentSink;
                }
                $currentSink = [
                    'id' => (int)$m[1],
                    'name' => '',
                    'description' => '',
                    'volume' => 0,
                    'muted' => false,
                    'is_default' => false,
                ];
            } elseif ($currentSink) {
                if (preg_match('/^\s+Name:\s*(.+)$/', $line, $m)) {
                    $currentSink['name'] = trim($m[1]);
                } elseif (preg_match('/^\s+Description:\s*(.+)$/', $line, $m)) {
                    $currentSink['description'] = trim($m[1]);
                } elseif (preg_match('/^\s+Mute:\s*(yes|no)/i', $line, $m)) {
                    $currentSink['muted'] = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^\s+Volume:[^%]+?(\d+)%/i', $line, $m)) {
                    $currentSink['volume'] = (int)$m[1];
                }
            }
        }
        if ($currentSink) {
            $sinks[] = $currentSink;
        }

        $defaultName = $this->getDefaultSink();
        foreach ($sinks as &$s) {
            if ($s['name'] === $defaultName) {
                $s['is_default'] = true;
            }
        }

        return $sinks;
    }

    public function getSources(): array
    {
        $res = CommandRunner::run($this->getPactlEnv() . "pactl list sources");
        if (!$res['success']) {
            return [];
        }

        $sources = [];
        $current = null;
        $lines = explode("\n", $res['stdout']);

        foreach ($lines as $line) {
            if (preg_match('/^Source #(\d+)/', $line, $m)) {
                if ($current) {
                    $sources[] = $current;
                }
                $current = [
                    'id' => (int)$m[1],
                    'name' => '',
                    'description' => '',
                    'volume' => 0,
                    'muted' => false,
                ];
            } elseif ($current) {
                if (preg_match('/^\s+Name:\s*(.+)$/', $line, $m)) {
                    $current['name'] = trim($m[1]);
                } elseif (preg_match('/^\s+Description:\s*(.+)$/', $line, $m)) {
                    $current['description'] = trim($m[1]);
                } elseif (preg_match('/^\s+Mute:\s*(yes|no)/i', $line, $m)) {
                    $current['muted'] = (strtolower($m[1]) === 'yes');
                } elseif (preg_match('/^\s+Volume:[^%]+?(\d+)%/i', $line, $m)) {
                    $current['volume'] = (int)$m[1];
                }
            }
        }
        if ($current) {
            $sources[] = $current;
        }

        return $sources;
    }

    public function getDefaultSink(): string
    {
        $res = CommandRunner::run($this->getPactlEnv() . "pactl get-default-sink");
        return $res['success'] ? trim($res['stdout']) : '@DEFAULT_SINK@';
    }

    public function setVolume(int $percent, ?string $sinkName = null): bool
    {
        $percent = max(0, min(100, $percent));
        $target = $sinkName ? escapeshellarg($sinkName) : '@DEFAULT_SINK@';
        $res = CommandRunner::run($this->getPactlEnv() . "pactl set-sink-volume {$target} {$percent}%");
        return $res['success'];
    }

    public function setMute(bool $mute, ?string $sinkName = null): bool
    {
        $target = $sinkName ? escapeshellarg($sinkName) : '@DEFAULT_SINK@';
        $val = $mute ? '1' : '0';
        $res = CommandRunner::run($this->getPactlEnv() . "pactl set-sink-mute {$target} {$val}");
        return $res['success'];
    }

    public function setDefaultSink(string $sinkName): bool
    {
        $res = CommandRunner::run($this->getPactlEnv() . "pactl set-default-sink " . escapeshellarg($sinkName));
        return $res['success'];
    }
}
