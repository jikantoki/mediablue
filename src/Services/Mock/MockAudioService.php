<?php

namespace mediablue\Services\Mock;

use mediablue\Config;

class MockAudioService
{
    private function getAudioState(): array
    {
        $state = Config::getState();
        if (!isset($state['mock_audio'])) {
            $state['mock_audio'] = [
                'master_volume' => 75,
                'muted' => false,
                'bt_stream_volume' => 85,
                'mic_volume' => 60,
                'default_sink' => 'alsa_output.pci-0000_00_1f.3.analog-stereo',
                'sinks' => [
                    [
                        'id' => 1,
                        'name' => 'alsa_output.pci-0000_00_1f.3.analog-stereo',
                        'description' => 'Built-in Audio Analog Stereo (Car Speakers)',
                        'volume' => 75,
                        'muted' => false,
                        'is_default' => true,
                    ],
                    [
                        'id' => 2,
                        'name' => 'alsa_output.usb-Hi-Res_Audio_DAC-00.analog-stereo',
                        'description' => 'USB High-Res DAC (External Amp)',
                        'volume' => 90,
                        'muted' => false,
                        'is_default' => false,
                    ],
                    [
                        'id' => 3,
                        'name' => 'bluez_sink.E4_5F_01_8A_2C_99.a2dp_sink',
                        'description' => 'Ken\'s iPhone 15 Pro (A2DP Audio Sink)',
                        'volume' => 85,
                        'muted' => false,
                        'is_default' => false,
                    ]
                ],
                'sources' => [
                    [
                        'id' => 1,
                        'name' => 'alsa_input.pci-0000_00_1f.3.analog-stereo',
                        'description' => 'Built-in Microphone (Handsfree Mic)',
                        'volume' => 60,
                        'muted' => false,
                    ]
                ]
            ];
            Config::saveState($state);
        }
        return $state['mock_audio'];
    }

    private function saveAudioState(array $data): void
    {
        $state = Config::getState();
        $state['mock_audio'] = $data;
        Config::saveState($state);
    }

    public function getStatus(): array
    {
        $data = $this->getAudioState();
        return [
            'mode' => 'mock',
            'master_volume' => $data['master_volume'],
            'muted' => $data['muted'],
            'bt_stream_volume' => $data['bt_stream_volume'],
            'mic_volume' => $data['mic_volume'],
            'default_sink' => $data['default_sink'],
            'sinks' => $data['sinks'],
            'sources' => $data['sources'],
        ];
    }

    public function setVolume(int $percent, ?string $sinkName = null): bool
    {
        $data = $this->getAudioState();
        $percent = max(0, min(100, $percent));
        $data['master_volume'] = $percent;
        foreach ($data['sinks'] as &$sink) {
            if ($sink['is_default']) {
                $sink['volume'] = $percent;
            }
        }
        $this->saveAudioState($data);
        return true;
    }

    public function setMute(bool $mute, ?string $sinkName = null): bool
    {
        $data = $this->getAudioState();
        $data['muted'] = $mute;
        foreach ($data['sinks'] as &$sink) {
            if ($sink['is_default']) {
                $sink['muted'] = $mute;
            }
        }
        $this->saveAudioState($data);
        return true;
    }

    public function setDefaultSink(string $sinkName): bool
    {
        $data = $this->getAudioState();
        $data['default_sink'] = $sinkName;
        foreach ($data['sinks'] as &$sink) {
            $sink['is_default'] = ($sink['name'] === $sinkName);
        }
        $this->saveAudioState($data);
        return true;
    }
}
