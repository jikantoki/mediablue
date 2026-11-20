<?php

require_once __DIR__ . '/../src/App.php';

use mediablue\App;

// APIエンドポイントへの直接アクセスの場合はAppを実行
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($uri, '/api/')) {
    App::run();
    exit;
}

// 導入解説ガイドへのアクセスの場合はguide.phpを実行
if ($uri === '/guide' || $uri === '/guide.php') {
    require __DIR__ . '/guide.php';
    exit;
}

// 静的ファイル（manifest.json, sw.js, assets等）へのアクセスの場合は適切なContent-Typeで配信
if ($uri !== '/' && $uri !== '/index.php') {
    $staticFile = __DIR__ . $uri;
    if (file_exists($staticFile) && !is_dir($staticFile)) {
        $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
        if ($ext === 'php') {
            require $staticFile;
            exit;
        }
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
    <title data-i18n="app_title">Mediablue - A2DP Sink クライアント</title>
    
    <!-- PWA Settings -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0c0f14">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="A2DP Sink">
    <link rel="apple-touch-icon" href="/assets/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/assets/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">

    <link rel="stylesheet" href="/css/style.css?v=2.1.0">
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
                    <span data-i18n="brand_name">Mediablue</span>
                </span>
                <span id="mode-badge" class="badge <?= $isMock ? 'badge-mock' : 'badge-real' ?>">
                    <?= $isMock ? 'シミュレーションモード' : '実機ホストモード' ?>
                </span>
                <button id="btn-install-pwa" class="btn-action btn-sm hidden" style="background: rgba(0, 210, 255, 0.15); color: var(--color-accent); border: 1px solid var(--border-active); gap: 6px; padding: 4px 10px; cursor: pointer;">
                    <span data-i18n="btn_install_pwa">📲 アプリをインストール</span>
                </button>
            </div>
            
            <div class="status-center">
                <span id="clock" class="digital-clock">--:--</span>
            </div>

            <div class="status-right">
                <div class="status-item bt-stat" id="top-bt-stat" title="Bluetooth接続状態" data-i18n-title="bt_status_title">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m7 7 10 10-5 5V2l5 5L7 17"/>
                    </svg>
                    <span id="top-bt-device" class="connected-dev-text" data-i18n="no_device_connected">未接続</span>
                </div>
            </div>
        </header>

        <!-- Main Body: Navigation Rail + Content Area -->
        <div class="main-layout">
            <!-- Automotive Sidebar Navigation (Media, Guide, Settings, About) -->
            <nav class="side-nav">
                <button class="nav-btn active" data-tab="tab-player" title="メディアプレーヤー (A2DP)" data-i18n-title="nav_media_title">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18V5l12-2v13"/>
                        <circle cx="6" cy="18" r="3"/>
                        <circle cx="18" cy="16" r="3"/>
                    </svg>
                    <span data-i18n="nav_media">Media</span>
                </button>
                <button class="nav-btn" data-tab="tab-guide" title="導入手順・解説ガイド" data-i18n-title="nav_guide_title">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                        <line x1="9" y1="7" x2="16" y2="7"/>
                        <line x1="9" y1="11" x2="14" y2="11"/>
                    </svg>
                    <span data-i18n="nav_guide">Guide</span>
                </button>
                <button class="nav-btn" data-tab="tab-settings" title="システム設定" data-i18n-title="nav_settings_title">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span data-i18n="nav_settings">Settings</span>
                </button>
                <button class="nav-btn" data-tab="tab-about" title="アプリ情報・開発元" data-i18n-title="nav_about_title">
                    <svg class="icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span data-i18n="nav_about">About</span>
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
                                <span class="badge badge-profile" data-i18n="player_profile">A2DP Sink</span>
                                <span id="player-codec" class="badge badge-codec">AAC 256kbps</span>
                                <span id="player-app-badge" class="badge" style="background: rgba(16, 185, 129, 0.2); color: var(--color-green); border: 1px solid rgba(16, 185, 129, 0.4); display: none;">App</span>
                                <span id="player-source-dev" class="source-device-name" data-i18n="no_device_connected">未接続</span>
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

                            <!-- AVRCP Media Controls (Volume controls removed) -->
                            <div class="media-controls">
                                <button id="btn-shuffle" class="ctrl-btn ctrl-sec" title="シャッフル" data-i18n-title="ctrl_shuffle">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="16 3 21 3 21 8"/>
                                        <line x1="4" y1="20" x2="21" y2="3"/>
                                        <polyline points="21 16 21 21 16 21"/>
                                        <line x1="15" y1="15" x2="21" y2="21"/>
                                        <line x1="4" y1="4" x2="9" y2="9"/>
                                    </svg>
                                </button>
                                <button id="btn-prev" class="ctrl-btn ctrl-main" title="前の曲 (AVRCP)" data-i18n-title="ctrl_prev">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="19 20 9 12 19 4 19 20"/>
                                        <line x1="5" y1="19" x2="5" y2="5"/>
                                    </svg>
                                </button>
                                <button id="btn-play-pause" class="ctrl-btn ctrl-play" title="再生 / 一時停止 (AVRCP)" data-i18n-title="ctrl_play">
                                    <svg id="icon-play" class="hidden" viewBox="0 0 24 24" width="40" height="40" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                    </svg>
                                    <svg id="icon-pause" viewBox="0 0 24 24" width="40" height="40" fill="currentColor">
                                        <rect x="6" y="4" width="4" height="16"/>
                                        <rect x="14" y="4" width="4" height="16"/>
                                    </svg>
                                </button>
                                <button id="btn-next" class="ctrl-btn ctrl-main" title="次の曲 (AVRCP)" data-i18n-title="ctrl_next">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 4 15 12 5 20 5 4"/>
                                        <line x1="19" y1="5" x2="19" y2="19"/>
                                    </svg>
                                </button>
                                <button id="btn-repeat" class="ctrl-btn ctrl-sec" title="リピート" data-i18n-title="ctrl_repeat">
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="17 1 21 5 17 9"/>
                                        <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                        <polyline points="7 23 3 19 7 15"/>
                                        <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. Guide Tab (A2DP / HFP Setup Guide) -->
                <section id="tab-guide" class="tab-panel">
                    <?php include __DIR__ . '/../src/Views/guide_content.php'; ?>
                </section>

                <!-- 3. Settings Tab -->
                <section id="tab-settings" class="tab-panel">
                    <div class="settings-layout">
                        <!-- Language Settings -->
                        <div class="card">
                            <h3 data-i18n="card_lang_title">言語設定 / Language</h3>
                            <div class="settings-row">
                                <div>
                                    <strong data-i18n="setting_lang_label">表示言語</strong>
                                    <p class="subtext" data-i18n="setting_lang_desc">UI全体の表示言語を切り替えます（デフォルト: 日本語）</p>
                                </div>
                                <div class="lang-toggles">
                                    <button id="btn-lang-ja" class="btn-toggle active" data-i18n="lang_ja_btn">日本語</button>
                                    <button id="btn-lang-en" class="btn-toggle" data-i18n="lang_en_btn">English</button>
                                </div>
                            </div>
                        </div>

                        <!-- Mode & Simulation Controls -->
                        <div class="card">
                            <h3 data-i18n="card_mode_title">動作モード & シミュレーション</h3>
                            <div class="settings-row">
                                <div>
                                    <strong data-i18n="setting_mode_label">動作モード</strong>
                                    <p class="subtext" data-i18n="setting_mode_desc">実機ハードウェア連携（BlueZ / D-Bus）とデモシミュレーションを切り替えます</p>
                                </div>
                                <div class="mode-toggles">
                                    <button id="btn-set-mock" class="btn-toggle <?= $isMock ? 'active' : '' ?>" data-i18n="btn_mode_sim">シミュレーションモード</button>
                                    <button id="btn-set-real" class="btn-toggle <?= !$isMock ? 'active' : '' ?>" data-i18n="btn_mode_real">実機ホストモード</button>
                                </div>
                            </div>

                            <div class="settings-row simulation-tools <?= $isMock ? '' : 'hidden' ?>" id="sim-tools-row">
                                <div>
                                    <strong data-i18n="setting_sim_tools">シミュレーションツール</strong>
                                    <p class="subtext" data-i18n="setting_sim_tools_desc">デモシミュレーションの再生状態を初期状態に戻します</p>
                                </div>
                                <div class="sim-actions">
                                    <button id="btn-reset-mock" class="btn-action btn-secondary" data-i18n="btn_reset_mock">🔄 状態をリセット</button>
                                </div>
                            </div>

                            <div class="settings-row simulation-tools <?= $isMock ? '' : 'hidden' ?>" id="sim-bt-power-row">
                                <div>
                                    <strong data-i18n="setting_bt_power_test">Bluetooth電源テスト (Power On/Off)</strong>
                                    <p class="subtext" data-i18n="setting_bt_power_test_desc">Bluetoothのオン/オフを切り替えて警告画面の動作をテストできます</p>
                                </div>
                                <div class="sim-actions">
                                    <button id="btn-test-bt-off" class="btn-action btn-secondary" style="border-color: rgba(239, 68, 68, 0.4); color: var(--color-red);">⚡ OFF (テスト)</button>
                                    <button id="btn-test-bt-on" class="btn-action" style="border-color: rgba(16, 185, 129, 0.4); color: var(--color-green);">📶 ON</button>
                                </div>
                            </div>
                        </div>

                        <!-- PWA Settings -->
                        <div class="card">
                            <h3 data-i18n="card_pwa_title">プログレッシブウェブアプリ (PWA)</h3>
                            <div class="settings-row">
                                <div>
                                    <strong data-i18n="setting_pwa_install">スタンドアロンアプリのインストール</strong>
                                    <p class="subtext" data-i18n="setting_pwa_install_desc">車載ディスプレイやホーム画面にインストールして全画面ネイティブ表示で利用できます</p>
                                </div>
                                <div class="sim-actions">
                                    <button id="btn-settings-install-pwa" class="btn-action" data-i18n="btn_pwa_install">📲 アプリをインストール</button>
                                    <button id="btn-clear-cache" class="btn-action btn-secondary" data-i18n="btn_pwa_clear">🧹 キャッシュをクリア</button>
                                </div>
                            </div>
                            <div class="settings-row">
                                <div>
                                    <strong data-i18n="setting_pwa_sw">オフライン機能 & Service Worker</strong>
                                    <p class="subtext" data-i18n="setting_pwa_sw_desc">UIシェルをローカルキャッシュし、瞬時の起動とネットワーク耐性を提供します</p>
                                </div>
                                <div>
                                    <span id="pwa-sw-status" class="badge badge-active" data-i18n="badge_sw_active">SW 稼働中</span>
                                </div>
                            </div>
                        </div>

                        <!-- System Diagnostics -->
                        <div class="card">
                            <h3 data-i18n="card_diag_title">システム診断 & ホスト状態</h3>
                            <table class="diag-table">
                                <tbody>
                                    <tr>
                                        <td data-i18n="diag_os">オペレーティングシステム</td>
                                        <td id="diag-os">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_php">PHP ランタイム</td>
                                        <td id="diag-php">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_btctl">BlueZ (bluetoothctl)</td>
                                        <td id="diag-btctl">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_pactl">PulseAudio / PipeWire (pactl)</td>
                                        <td id="diag-pactl">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_playerctl">Media MPRIS (playerctl)</td>
                                        <td id="diag-playerctl">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_wpctl">WirePlumber (wpctl)</td>
                                        <td id="diag-wpctl">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_dbus">D-Bus システムソケット</td>
                                        <td id="diag-dbus">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_bluez_dbus">BlueZ D-Bus 接続</td>
                                        <td id="diag-bluez-dbus">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_media_player">BlueZ MediaPlayer1 (AVRCP)</td>
                                        <td id="diag-media-player">-</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="diag_pulse_server">PULSE_SERVER</td>
                                        <td id="diag-pulse-server">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 3. About Tab -->
                <section id="tab-about" class="tab-panel">
                    <div class="about-layout">
                        <!-- Hero Banner -->
                        <div class="card about-hero-card">
                            <span class="about-brand-tag" data-i18n="about_hero_tag">Mediablue - AUTOMOTIVE SOLUTIONS</span>
                            <h1 class="about-title" data-i18n="about_title">Mediablue - A2DP Sink クライアント</h1>
                            <p class="about-subtitle" data-i18n="about_subtitle">車載向け 高品位 Bluetooth オーディオ受信システム</p>
                        </div>

                        <!-- Product Overview Description -->
                        <div class="card about-desc-card">
                            <h3 data-i18n="about_desc_title">製品概要</h3>
                            <p data-i18n="about_desc_body">
                                本アプリケーションは、「エノキ電気 ENOKI ELECTRONICS」が開発した車載向け A2DP Sink クライアントアプリです。
                                スマートフォンやタブレット、ポータブルメディアプレーヤー等のBluetooth送信機（A2DP Source）から高品位なオーディオストリーミングを受信し、AVRCPによるリアルタイムな楽曲情報取得（曲名・アーティスト・アルバム・再生時間）および再生制御（再生・一時停止・トラック送り・戻し）を提供します。
                            </p>
                        </div>

                        <!-- Highlights / Features Grid -->
                        <div class="card">
                            <h3 data-i18n="about_features_title">主な仕様と設計思想</h3>
                            <div class="features-grid">
                                <div class="feature-box">
                                    <h4 data-i18n="feat_1_title">🎵 A2DP Sink オーディオ受信</h4>
                                    <p data-i18n="feat_1_desc">Linux BlueZ およびオーディオサーバー（PipeWire / PulseAudio）と直接連動し、AAC / SBC / LDAC / aptX等の各種高品位コーデックストリーミングを受信します。</p>
                                </div>
                                <div class="feature-box">
                                    <h4 data-i18n="feat_2_title">🎛️ AVRCP メディアコントロール</h4>
                                    <p data-i18n="feat_2_desc">BlueZ MediaPlayer1 / MediaControl1 インターフェースにより、車載タッチパネルからスマートフォン側の再生アプリを直感的に遠隔操作できます。</p>
                                </div>
                                <div class="feature-box">
                                    <h4 data-i18n="feat_3_title">🔇 外部ボリューム統合設計</h4>
                                    <p data-i18n="feat_3_desc">車載オーディオの安全かつ統一的な音響設計に基づき、音量調整は車両側のマスターボリュームやステアリングスイッチで実施するため、本アプリ内からはボリュームコントロールを排他・削除しています。</p>
                                </div>
                                <div class="feature-box">
                                    <h4 data-i18n="feat_4_title">📱 PWA & 車載ハイコントラストUI</h4>
                                    <p data-i18n="feat_4_desc">運転時の視認性に配慮したハイコントラスト・ダークテーマ。PWAとして車載ディスプレイやタブレットにインストールすることで、全画面スタンドアロンで動作します。</p>
                                </div>
                            </div>
                        </div>

                        <!-- Specifications & Manufacturer Information Table -->
                        <div class="card">
                            <h3 data-i18n="about_info_title">製品仕様・開発元情報</h3>
                            <table class="diag-table">
                                <tbody>
                                    <tr>
                                        <td data-i18n="info_vendor_label">製造・開発元</td>
                                        <td data-i18n="info_vendor_val">エノキ電気 (ENOKI ELECTRONICS)</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="info_product_label">製品名</td>
                                        <td data-i18n="info_product_val">車載 A2DP Sink クライアントアプリケーション</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="info_version_label">バージョン</td>
                                        <td data-i18n="info_version_val">v2.0.0 (2026 Edition)</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="info_type_label">Bluetoothプロファイル</td>
                                        <td data-i18n="info_type_val">A2DP Sink (Audio Sink) / AVRCP Target</td>
                                    </tr>
                                    <tr>
                                        <td data-i18n="info_license_label">著作権表示</td>
                                        <td data-i18n="info_license_val">© 2026 エノキ電気 (ENOKI ELECTRONICS). All rights reserved.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <!-- Bluetooth Off Alert Modal -->
        <div id="modal-bt-off" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="bt-off-title">
            <div class="card bt-off-box">
                <div class="bt-off-icon-circle">
                    <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m7 7 10 10-5 5V2l5 5L7 17"/>
                        <line x1="2" y1="2" x2="22" y2="22" stroke="var(--color-red)" stroke-width="2.5"/>
                    </svg>
                </div>
                <h2 id="bt-off-title" class="bt-off-title" data-i18n="bt_off_title">Bluetoothがオフになっています</h2>
                <p id="bt-off-desc" class="bt-off-desc" data-i18n="bt_off_desc">
                    高音質オーディオ（A2DP Sink）を受信するには、ホスト端末のBluetoothをオンにする必要があります。
                </p>
                <div id="bt-off-missing-warning" class="bt-off-warning-box hidden">
                    <span data-i18n="bt_off_missing">⚠️ Bluetoothアダプタが見つかりません。USB Bluetoothドングルの接続等をご確認ください。</span>
                </div>

                <div class="bt-off-actions">
                    <button id="btn-turn-on-bt" class="btn-turn-on-bt">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m7 7 10 10-5 5V2l5 5L7 17"/>
                        </svg>
                        <span id="btn-turn-on-bt-text" data-i18n="bt_off_btn_on">📶 Bluetoothをオンにする</span>
                    </button>
                    <button id="btn-goto-guide" class="btn-action btn-secondary btn-guide-link">
                        <span data-i18n="bt_off_btn_guide">📖 導入手順ガイドを見る</span>
                    </button>
                </div>

                <!-- Host CLI Instructions Accordion -->
                <div class="bt-manual-section">
                    <button id="btn-toggle-bt-manual" class="btn-manual-toggle" type="button">
                        <span data-i18n="bt_off_manual_toggle">💡 ホスト端末の手動有効化コマンド (CLI)</span>
                        <svg class="chevron-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div id="bt-manual-content" class="bt-manual-content hidden">
                        <p class="bt-manual-sub" data-i18n="bt_off_manual_desc">Linuxホスト端末上で直接オンにする場合は以下を実行してください：</p>
                        <div class="code-copy-container">
                            <pre><code id="bt-cli-command">sudo rfkill unblock bluetooth && bluetoothctl power on</code></pre>
                            <button id="btn-copy-bt-cli" class="btn-copy-icon" title="コマンドをコピー" data-i18n-title="bt_off_btn_copy">
                                📋
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bt-off-footer">
                    <button id="btn-dismiss-bt-off" class="btn-text-link" data-i18n="bt_off_dismiss">閉じる（UIの確認のみ）</button>
                </div>
            </div>
        </div>

        <!-- Notification Toast -->
        <div id="toast" class="toast hidden"></div>
    </div>

    <!-- Scripts -->
    <script src="/js/i18n.js?v=2.1.0"></script>
    <script src="/js/app.js?v=2.1.0"></script>
    <script src="/js/player.js?v=2.1.0"></script>
    <script src="/js/bluetooth.js?v=2.1.0"></script>
</body>
</html>
