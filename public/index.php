<?php

require_once __DIR__ . '/../src/App.php';

use mediablue\App;

// APIエンドポイントへの直接アクセスの場合はAppを実行
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($uri, '/api/')) {
    App::run();
    exit;
}

// 静的ファイル（manifest.json, sw.js, assets等）へのアクセスの場合は適切なContent-Typeで配信
if ($uri !== '/' && $uri !== '/index.php') {
    $staticFile = __DIR__ . $uri;
    if (file_exists($staticFile) && !is_dir($staticFile)) {
        $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
        $mimes = [
            'json' => 'application/json; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'ico'  => 'image/x-icon',
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($staticFile);
        exit;
    }
}

// オートローダー登録
App::registerAutoloader();
\mediablue\Config::init();
$isMock = \mediablue\Config::isMockMode();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>mediablue - A2DP Sink & HFP Client</title>
    
    <!-- PWA Settings -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0c0f14">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="mediablue">
    <link rel="apple-touch-icon" href="/assets/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/assets/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">

    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="dark-theme">
    <div id="app" class="car-container">
        <!-- Top Status Bar -->
        <header class="status-bar">
            <div class="status-left">
                <span class="brand-logo">
                    <svg class="icon logo-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8C2.1 11.1 2 11.5 2 12v4c0 .6.4 1 1 1h2"/>
                        <circle cx="7" cy="17" r="2"/>
                        <path d="M9 17h6"/>
                        <circle cx="17" cy="17" r="2"/>
                    </svg>
                    mediablue OS
                </span>
                <span id="mode-badge" class="badge <?= $isMock ? 'badge-mock' : 'badge-real' ?>">
                    <?= $isMock ? 'SIMULATION MODE' : 'LIVE HOST MODE' ?>
                </span>
                <button id="btn-install-pwa" class="btn-action btn-sm hidden" style="background: rgba(0, 210, 255, 0.15); color: var(--color-accent); border: 1px solid var(--border-active); gap: 6px; padding: 4px 10px; cursor: pointer;">
                    📲 Install App
                </button>
            </div>
            
            <div class="status-center">
                <span id="clock" class="digital-clock">--:--</span>
            </div>

            <div class="status-right">
                <div class="status-item phone-stat" id="top-phone-stat" title="Smartphone connection">
                    <span id="top-carrier" class="carrier-text">-</span>
                    <span id="top-signal" class="signal-bars">
                        <span class="bar bar1 active"></span>
                        <span class="bar bar2 active"></span>
                        <span class="bar bar3 active"></span>
                        <span class="bar bar4 active"></span>
                    </span>
                    <span class="battery-wrapper">
                        <span class="battery-body">
                            <span id="top-battery-level" class="battery-level" style="width: 80%;"></span>
                        </span>
                        <span id="top-battery-text" class="battery-text">80%</span>
                    </span>
                </div>
                <div class="status-item bt-stat" id="top-bt-stat" title="Bluetooth Status">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m7 7 10 10-5 5V2l5 5L7 17"/>
                    </svg>
                    <span id="top-bt-device" class="connected-dev-text">No Device</span>
                </div>
                <div class="status-item vol-stat" id="top-vol-stat">
                    <svg id="top-vol-icon" class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                    </svg>
                    <span id="top-vol-text">75%</span>
                </div>
            </div>
        </header>

        <!-- Main Body: Navigation Rail + Content Area -->
        <div class="main-layout">
            <!-- Automotive Sidebar Navigation -->
            <nav class="side-nav">
                <button class="nav-btn active" data-tab="tab-player" title="Media Player (A2DP)">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18V5l12-2v13"/>
                        <circle cx="6" cy="18" r="3"/>
                        <circle cx="18" cy="16" r="3"/>
                    </svg>
                    <span>Media</span>
                </button>
                <button class="nav-btn" data-tab="tab-phone" title="Hands-Free Phone (HFP)">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span id="phone-nav-badge" class="nav-bubble hidden">!</span>
                    <span>Phone</span>
                </button>
                <button class="nav-btn" data-tab="tab-bluetooth" title="Bluetooth Management">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m7 7 10 10-5 5V2l5 5L7 17"/>
                    </svg>
                    <span>Bluetooth</span>
                </button>
                <button class="nav-btn" data-tab="tab-audio" title="Audio Mixer & Routing">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="4" y1="21" x2="4" y2="14"/>
                        <line x1="4" y1="10" x2="4" y2="3"/>
                        <line x1="12" y1="21" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12" y2="3"/>
                        <line x1="20" y1="21" x2="20" y2="16"/>
                        <line x1="20" y1="12" x2="20" y2="3"/>
                        <line x1="1" y1="14" x2="7" y2="14"/>
                        <line x1="9" y1="8" x2="15" y2="8"/>
                        <line x1="17" y1="16" x2="23" y2="16"/>
                    </svg>
                    <span>Audio</span>
                </button>
                <button class="nav-btn" data-tab="tab-settings" title="System Settings">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span>Settings</span>
                </button>
            </nav>

            <!-- Main Content Area with View Panels -->
            <main class="content-viewport">

                <!-- 1. Media Player Tab (A2DP / AVRCP) -->
                <section id="tab-player" class="tab-panel active">
                    <div class="player-container">
                        <div class="player-visual">
                            <div id="art-container" class="art-container">
                                <div id="album-cover" class="album-cover spinning-vinyl">
                                    <div class="vinyl-center">
                                        <svg viewBox="0 0 24 24" width="28" height="28" fill="#fff">
                                            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="player-details">
                            <div class="codec-tag-wrapper">
                                <span class="badge badge-profile">A2DP Sink</span>
                                <span id="player-codec" class="badge badge-codec">AAC 256kbps</span>
                                <span id="player-source-dev" class="source-device-name">Ken's iPhone 15 Pro</span>
                            </div>

                            <div class="track-info">
                                <h1 id="track-title" class="track-title">Blinding Lights</h1>
                                <h2 id="track-artist" class="track-artist">The Weeknd</h2>
                                <h3 id="track-album" class="track-album">After Hours</h3>
                            </div>

                            <!-- Progress & Timeline -->
                            <div class="progress-section">
                                <div class="progress-bar-container" id="progress-bar">
                                    <div id="progress-fill" class="progress-fill" style="width: 30%;"></div>
                                </div>
                                <div class="time-row">
                                    <span id="time-current">01:00</span>
                                    <span id="time-duration">03:20</span>
                                </div>
                            </div>

                            <!-- AVRCP Media Controls -->
                            <div class="media-controls">
                                <button id="btn-shuffle" class="ctrl-btn ctrl-sec" title="Shuffle">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="16 3 21 3 21 8"/>
                                        <line x1="4" y1="20" x2="21" y2="3"/>
                                        <polyline points="21 16 21 21 16 21"/>
                                        <line x1="15" y1="15" x2="21" y2="21"/>
                                        <line x1="4" y1="4" x2="9" y2="9"/>
                                    </svg>
                                </button>
                                <button id="btn-prev" class="ctrl-btn ctrl-main" title="Previous Track (AVRCP)">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="19 20 9 12 19 4 19 20"/>
                                        <line x1="5" y1="19" x2="5" y2="5"/>
                                    </svg>
                                </button>
                                <button id="btn-play-pause" class="ctrl-btn ctrl-play" title="Play / Pause (AVRCP)">
                                    <svg id="icon-play" class="hidden" viewBox="0 0 24 24" width="40" height="40" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                    </svg>
                                    <svg id="icon-pause" viewBox="0 0 24 24" width="40" height="40" fill="currentColor">
                                        <rect x="6" y="4" width="4" height="16"/>
                                        <rect x="14" y="4" width="4" height="16"/>
                                    </svg>
                                </button>
                                <button id="btn-next" class="ctrl-btn ctrl-main" title="Next Track (AVRCP)">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 4 15 12 5 20 5 4"/>
                                        <line x1="19" y1="5" x2="19" y2="19"/>
                                    </svg>
                                </button>
                                <button id="btn-repeat" class="ctrl-btn ctrl-sec" title="Repeat">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="17 1 21 5 17 9"/>
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                        <polyline points="7 23 3 19 7 15"/>
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- In-Player Volume -->
                            <div class="player-vol-row">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                                </svg>
                                <input type="range" id="player-volume-slider" min="0" max="100" value="75" class="custom-slider">
                                <span id="player-volume-text">75%</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. Phone Tab (HFP - Hands-Free Profile) -->
                <section id="tab-phone" class="tab-panel">
                    <div class="phone-layout">
                        <!-- Left: Status & Active Call Display -->
                        <div class="phone-left">
                            <div class="card phone-status-card">
                                <div class="card-header">
                                    <span class="badge badge-profile">HFP (Hands-Free Profile)</span>
                                    <span id="call-state-badge" class="badge badge-idle">IDLE</span>
                                </div>
                                <div class="active-call-display">
                                    <div class="caller-avatar" id="call-avatar">
                                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    </div>
                                    <h2 id="active-caller-name" class="caller-name">No Active Call</h2>
                                    <p id="active-caller-number" class="caller-number">-</p>
                                    <p id="call-timer" class="call-timer">00:00</p>
                                </div>

                                <!-- In-Call Controls -->
                                <div id="incall-actions" class="incall-actions hidden">
                                    <button id="btn-call-mute" class="round-action-btn" title="Mute Microphone">
                                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                            <path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/>
                                            <path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/>
                                            <line x1="12" y1="19" x2="12" y2="23"/>
                                            <line x1="8" y1="23" x2="16" y2="23"/>
                                        </svg>
                                        <span>Mute</span>
                                    </button>
                                    <button id="btn-call-hangup" class="round-action-btn btn-danger" title="Hangup Call">
                                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                                            <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08a.996.996 0 0 1 0-1.41C3.28 8.76 7.4 7 12 7c4.6 0 8.72 1.76 11.71 4.67.39.39.39 1.02 0 1.41l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.74-1.69-1.36-2.67-1.85-.33-.16-.56-.5-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                                        </svg>
                                        <span>End</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Touch Keypad & History -->
                        <div class="phone-right">
                            <div class="card keypad-card">
                                <div class="keypad-screen">
                                    <input type="text" id="dial-display" class="dial-input" placeholder="Enter phone number..." readonly>
                                    <button id="btn-backspace" class="btn-icon-clear" title="Backspace">⌫</button>
                                </div>
                                <div class="keypad-grid">
                                    <button class="key-btn" data-key="1">1</button>
                                    <button class="key-btn" data-key="2">2<span class="subtext">ABC</span></button>
                                    <button class="key-btn" data-key="3">3<span class="subtext">DEF</span></button>
                                    <button class="key-btn" data-key="4">4<span class="subtext">GHI</span></button>
                                    <button class="key-btn" data-key="5">5<span class="subtext">JKL</span></button>
                                    <button class="key-btn" data-key="6">6<span class="subtext">MNO</span></button>
                                    <button class="key-btn" data-key="7">7<span class="subtext">PQRS</span></button>
                                    <button class="key-btn" data-key="8">8<span class="subtext">TUV</span></button>
                                    <button class="key-btn" data-key="9">9<span class="subtext">WXYZ</span></button>
                                    <button class="key-btn" data-key="*">*</button>
                                    <button class="key-btn" data-key="0">0<span class="subtext">+</span></button>
                                    <button class="key-btn" data-key="#">#</button>
                                </div>
                                <div class="keypad-bottom">
                                    <button id="btn-call-start" class="btn-green-call" title="Call Number">
                                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <span>Call</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. Bluetooth Tab (BlueZ Management) -->
                <section id="tab-bluetooth" class="tab-panel">
                    <div class="bt-layout">
                        <!-- Top: Adapter Controls -->
                        <div class="card adapter-card">
                            <div class="card-header">
                                <div class="adapter-title">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m7 7 10 10-5 5V2l5 5L7 17"/>
                                    </svg>
                                    <h3 id="adapter-name">Ubuntu mediablue (hci0)</h3>
                                </div>
                                <div class="adapter-switches">
                                    <label class="switch-item">
                                        <span>Power</span>
                                        <input type="checkbox" id="switch-bt-power" checked>
                                    </label>
                                    <label class="switch-item">
                                        <span>Discoverable</span>
                                        <input type="checkbox" id="switch-bt-discoverable" checked>
                                    </label>
                                    <button id="btn-scan" class="btn-action">
                                        <svg id="scan-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="22" y1="12" x2="18" y2="12"/>
                                            <line x1="6" y1="12" x2="2" y2="12"/>
                                            <line x1="12" y1="6" x2="12" y2="2"/>
                                            <line x1="12" y1="22" x2="12" y2="18"/>
                                        </svg>
                                        <span id="scan-btn-text">Scan for Devices</span>
                                    </button>
                                </div>
                            </div>
                            <p class="adapter-mac-text">MAC: <span id="adapter-mac">DC:A6:32:8B:12:34</span></p>
                        </div>

                        <!-- Device Lists -->
                        <div class="bt-devices-grid">
                            <!-- Paired / Connected Devices -->
                            <div class="card device-list-card">
                                <h3>Paired & Connected Devices</h3>
                                <div id="paired-devices-list" class="device-list">
                                    <!-- Device Cards rendered via JS -->
                                </div>
                            </div>

                            <!-- Discovered Devices -->
                            <div class="card device-list-card">
                                <h3>Available / Discovered Devices</h3>
                                <div id="available-devices-list" class="device-list">
                                    <!-- Discovered Cards rendered via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. Audio Mixer Tab (Audioctl / pactl / PipeWire) -->
                <section id="tab-audio" class="tab-panel">
                    <div class="audio-layout">
                        <div class="card">
                            <h3>Volume & Output Routing (Audioctl / PulseAudio / PipeWire)</h3>
                            
                            <!-- Master Volume -->
                            <div class="mixer-row">
                                <div class="mixer-label">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                                    </svg>
                                    <div>
                                        <strong>Master Volume</strong>
                                        <p class="subtext">Vehicle Main Audio Output</p>
                                    </div>
                                </div>
                                <div class="mixer-ctrl">
                                    <input type="range" id="mixer-master-slider" min="0" max="100" value="75" class="custom-slider">
                                    <span id="mixer-master-val" class="vol-num">75%</span>
                                    <button id="btn-master-mute" class="btn-mute">Mute</button>
                                </div>
                            </div>

                            <!-- Audio Sinks (Output selector) -->
                            <div class="sink-selection-section">
                                <h4>Active Output Devices (Sinks)</h4>
                                <div id="sinks-list" class="sinks-grid">
                                    <!-- Populated via JS -->
                                </div>
                            </div>

                            <!-- Microphone / Handsfree Input -->
                            <div class="source-selection-section">
                                <h4>Input Devices (Microphone / HFP)</h4>
                                <div id="sources-list" class="sources-grid">
                                    <!-- Populated via JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. Settings Tab -->
                <section id="tab-settings" class="tab-panel">
                    <div class="settings-layout">
                        <!-- Mode Switch -->
                        <div class="card">
                            <h3>Mode & Simulation Controls</h3>
                            <div class="settings-row">
                                <div>
                                    <strong>Operating Mode</strong>
                                    <p class="subtext">Toggle between live host hardware (BlueZ / Audioctl) and demo simulation</p>
                                </div>
                                <div class="mode-toggles">
                                    <button id="btn-set-mock" class="btn-toggle <?= $isMock ? 'active' : '' ?>">Simulation Mode</button>
                                    <button id="btn-set-real" class="btn-toggle <?= !$isMock ? 'active' : '' ?>">Live Host Mode</button>
                                </div>
                            </div>

                            <div class="settings-row simulation-tools <?= $isMock ? '' : 'hidden' ?>" id="sim-tools-row">
                                <div>
                                    <strong>Simulation Tools</strong>
                                    <p class="subtext">Simulate real-world phone calls & bluetooth events</p>
                                </div>
                                <div class="sim-actions">
                                    <button id="btn-trigger-call" class="btn-action">📞 Trigger Incoming Call</button>
                                    <button id="btn-reset-mock" class="btn-action btn-secondary">🔄 Reset State</button>
                                </div>
                            </div>
                        </div>

                        <!-- PWA Settings -->
                        <div class="card">
                            <h3>Progressive Web App (PWA)</h3>
                            <div class="settings-row">
                                <div>
                                    <strong>Standalone App Installation</strong>
                                    <p class="subtext">Install mediablue onto your vehicle display or home screen for a fullscreen native experience</p>
                                </div>
                                <div class="sim-actions">
                                    <button id="btn-settings-install-pwa" class="btn-action">📲 Install App</button>
                                    <button id="btn-clear-cache" class="btn-action btn-secondary">🧹 Clear Cache</button>
                                </div>
                            </div>
                            <div class="settings-row">
                                <div>
                                    <strong>Offline Capabilities & Service Worker</strong>
                                    <p class="subtext">Caches UI shell for instant boot and network resilience</p>
                                </div>
                                <div>
                                    <span id="pwa-sw-status" class="badge badge-active">SW Active</span>
                                </div>
                            </div>
                        </div>

                        <!-- System Diagnostics -->
                        <div class="card">
                            <h3>System Diagnostics & Host Health</h3>
                            <table class="diag-table">
                                <tbody>
                                    <tr>
                                        <td>Operating System</td>
                                        <td id="diag-os">-</td>
                                    </tr>
                                    <tr>
                                        <td>PHP Runtime</td>
                                        <td id="diag-php">-</td>
                                    </tr>
                                    <tr>
                                        <td>BlueZ (bluetoothctl)</td>
                                        <td id="diag-btctl">-</td>
                                    </tr>
                                    <tr>
                                        <td>PulseAudio / PipeWire (pactl)</td>
                                        <td id="diag-pactl">-</td>
                                    </tr>
                                    <tr>
                                        <td>Media MPRIS (playerctl)</td>
                                        <td id="diag-playerctl">-</td>
                                    </tr>
                                    <tr>
                                        <td>WirePlumber (wpctl)</td>
                                        <td id="diag-wpctl">-</td>
                                    </tr>
                                    <tr>
                                        <td>D-Bus System Socket</td>
                                        <td id="diag-dbus">-</td>
                                    </tr>
                                    <tr>
                                        <td>BlueZ D-Bus Service</td>
                                        <td id="diag-bluez-dbus">-</td>
                                    </tr>
                                    <tr>
                                        <td>BlueZ MediaPlayer1 (AVRCP)</td>
                                        <td id="diag-media-player">-</td>
                                    </tr>
                                    <tr>
                                        <td>PULSE_SERVER</td>
                                        <td id="diag-pulse-server">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <!-- Incoming Call Modal Overlay -->
        <div id="incoming-modal" class="modal-overlay hidden">
            <div class="modal-card incoming-call-box">
                <div class="call-pulse-circle">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </div>
                <h3>Incoming Phone Call (HFP)</h3>
                <h1 id="modal-caller-name">Yamada Taro</h1>
                <p id="modal-caller-number">090-1234-5678</p>
                <div class="modal-call-actions">
                    <button id="btn-modal-reject" class="btn-modal-action btn-modal-reject">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                            <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08a.996.996 0 0 1 0-1.41C3.28 8.76 7.4 7 12 7c4.6 0 8.72 1.76 11.71 4.67.39.39.39 1.02 0 1.41l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.74-1.69-1.36-2.67-1.85-.33-.16-.56-.5-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                        </svg>
                        <span>Decline</span>
                    </button>
                    <button id="btn-modal-answer" class="btn-modal-action btn-modal-answer">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <span>Answer</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification Toast -->
        <div id="toast" class="toast hidden"></div>
    </div>

    <!-- Scripts -->
    <script src="/js/app.js"></script>
    <script src="/js/player.js"></script>
    <script src="/js/phone.js"></script>
    <script src="/js/bluetooth.js"></script>
    <script src="/js/audio.js"></script>
</body>
</html>
