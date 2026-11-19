/**
 * i18n Localization Data & Controller
 * Supports Japanese (default) and English
 * Manufacturer: エノキ電気 ENOKI ELECTRONICS
 */

const I18N_DATA = {
  ja: {
    // App & Brand
    app_title: 'Mediablue - A2DP Sink クライアント',
    brand_name: 'エノキ電気 ENOKI ELECTRONICS',
    brand_sub: 'Mediablue',

    // Navigation
    nav_media: 'Media',
    nav_media_title: 'メディアプレーヤー (A2DP)',
    nav_settings: 'Settings',
    nav_settings_title: 'システム設定',
    nav_about: 'About',
    nav_about_title: 'アプリ情報・開発元',

    // Status bar
    mode_mock: 'シミュレーションモード',
    mode_real: '実機ホストモード',
    btn_install_pwa: '📲 アプリをインストール',
    bt_status_title: 'Bluetooth接続状態',
    no_device_connected: '未接続',
    disconnected: '切断中',
    connected_device: '接続中: ',

    // Media Player
    player_profile: 'A2DP Sink',
    no_media_playing: '再生中のメディアはありません',
    connect_bt_prompt: 'Bluetooth機器を接続してください',
    ctrl_shuffle: 'シャッフル',
    ctrl_prev: '前の曲 (AVRCP)',
    ctrl_play: '再生 (AVRCP)',
    ctrl_pause: '一時停止 (AVRCP)',
    ctrl_next: '次の曲 (AVRCP)',
    ctrl_repeat: 'リピート',

    // Settings
    settings_heading: 'システム設定',
    // Language card
    card_lang_title: '言語設定 / Language',
    setting_lang_label: '表示言語',
    setting_lang_desc: 'UI全体の表示言語を切り替えます（デフォルト: 日本語）',
    lang_ja_btn: '日本語',
    lang_en_btn: 'English',
    // Mode card
    card_mode_title: '動作モード & シミュレーション',
    setting_mode_label: '動作モード',
    setting_mode_desc:
      '実機ハードウェア連携（BlueZ / D-Bus）とデモシミュレーションを切り替えます',
    btn_mode_sim: 'シミュレーションモード',
    btn_mode_real: '実機ホストモード',
    setting_sim_tools: 'シミュレーションツール',
    setting_sim_tools_desc:
      'デモシミュレーションの再生状態を初期状態に戻します',
    btn_reset_mock: '🔄 状態をリセット',
    // PWA card
    card_pwa_title: 'プログレッシブウェブアプリ (PWA)',
    setting_pwa_install: 'スタンドアロンアプリのインストール',
    setting_pwa_install_desc:
      '車載ディスプレイやホーム画面にインストールして全画面ネイティブ表示で利用できます',
    btn_pwa_install: '📲 アプリをインストール',
    btn_pwa_clear: '🧹 キャッシュをクリア',
    setting_pwa_sw: 'オフライン機能 & Service Worker',
    setting_pwa_sw_desc:
      'UIシェルをローカルキャッシュし、瞬時の起動とネットワーク耐性を提供します',
    badge_sw_active: 'SW 稼働中',
    // Diagnostics card
    card_diag_title: 'システム診断 & ホスト状態',
    diag_os: 'オペレーティングシステム',
    diag_php: 'PHP ランタイム',
    diag_btctl: 'BlueZ (bluetoothctl)',
    diag_pactl: 'PulseAudio / PipeWire (pactl)',
    diag_playerctl: 'Media MPRIS (playerctl)',
    diag_wpctl: 'WirePlumber (wpctl)',
    diag_dbus: 'D-Bus システムソケット',
    diag_bluez_dbus: 'BlueZ D-Bus 接続',
    diag_media_player: 'BlueZ MediaPlayer1 (AVRCP)',
    diag_pulse_server: 'PULSE_SERVER',

    // About Page
    about_heading: 'アプリケーション情報',
    about_hero_tag: 'ENOKI ELECTRONICS - AUTOMOTIVE SOLUTIONS',
    about_title: 'エノキ電気 A2DP Sink クライアント',
    about_subtitle: '車載向け 高品位 Bluetooth オーディオ受信システム',
    about_desc_title: '製品概要',
    about_desc_body:
      '本アプリケーションは、「エノキ電気 ENOKI ELECTRONICS」が開発した車載向け A2DP Sink クライアントアプリです。\nスマートフォンやタブレット、ポータブルメディアプレーヤー等のBluetooth送信機（A2DP Source）から高品位なオーディオストリーミングを受信し、AVRCPによるリアルタイムな楽曲情報取得（曲名・アーティスト・アルバム・再生時間）および再生制御（再生・一時停止・トラック送り・戻し）を提供します。',
    about_features_title: '主な仕様と設計思想',
    feat_1_title: '🎵 A2DP Sink オーディオ受信',
    feat_1_desc:
      'Linux BlueZ およびオーディオサーバー（PipeWire / PulseAudio）と直接連動し、AAC / SBC / LDAC / aptX等の各種高品位コーデックストリーミングを受信します。',
    feat_2_title: '🎛️ AVRCP メディアコントロール',
    feat_2_desc:
      'BlueZ MediaPlayer1 / MediaControl1 インターフェースにより、車載タッチパネルからスマートフォン側の再生アプリを直感的に遠隔操作できます。',
    feat_3_title: '🔇 外部ボリューム統合設計',
    feat_3_desc:
      '車載オーディオの安全かつ統一的な音響設計に基づき、音量調整は車両側のマスターボリュームやステアリングスイッチで実施するため、本アプリ内からはボリュームコントロールを排他・削除しています。',
    feat_4_title: '📱 PWA & 車載ハイコントラストUI',
    feat_4_desc:
      '運転時の視認性に配慮したハイコントラスト・ダークテーマ。PWAとして車載ディスプレイやタブレットにインストールすることで、全画面スタンドアロンで動作します。',
    about_info_title: '製品仕様・開発元情報',
    info_vendor_label: '製造・開発元',
    info_vendor_val: 'エノキ電気 (ENOKI ELECTRONICS)',
    info_product_label: '製品名',
    info_product_val: '車載 A2DP Sink クライアントアプリケーション',
    info_version_label: 'バージョン',
    info_version_val: 'v2.0.0 (2026 Edition)',
    info_type_label: 'Bluetoothプロファイル',
    info_type_val: 'A2DP Sink (Audio Sink) / AVRCP Target',
    info_license_label: '著作権表示',
    info_license_val:
      '© 2026 エノキ電気 (ENOKI ELECTRONICS). All rights reserved.',

    // Toasts & Alerts
    toast_switched_sim: 'シミュレーションモードに切り替えました',
    toast_switched_real: '実機ホストモードに切り替えました',
    toast_reset_sim: 'シミュレーション状態を初期化しました',
    toast_cache_cleared: 'キャッシュをクリアしました。再読み込みします...',
    toast_lang_changed: '表示言語を「日本語」に変更しました',
    toast_cmd_failed: '操作の送信に失敗しました',
  },
  en: {
    // App & Brand
    app_title: 'Mediablue - A2DP Sink Client',
    brand_name: 'ENOKI ELECTRONICS',
    brand_sub: 'A2DP Sink Client',

    // Navigation
    nav_media: 'Media',
    nav_media_title: 'Media Player (A2DP)',
    nav_settings: 'Settings',
    nav_settings_title: 'System Settings',
    nav_about: 'About',
    nav_about_title: 'About & Developer',

    // Status bar
    mode_mock: 'SIMULATION MODE',
    mode_real: 'LIVE HOST MODE',
    btn_install_pwa: '📲 Install App',
    bt_status_title: 'Bluetooth Status',
    no_device_connected: 'No Device',
    disconnected: 'Disconnected',
    connected_device: 'Connected: ',

    // Media Player
    player_profile: 'A2DP Sink',
    no_media_playing: 'No Media Playing',
    connect_bt_prompt: 'Connect Bluetooth Device',
    ctrl_shuffle: 'Shuffle',
    ctrl_prev: 'Previous Track (AVRCP)',
    ctrl_play: 'Play (AVRCP)',
    ctrl_pause: 'Pause (AVRCP)',
    ctrl_next: 'Next Track (AVRCP)',
    ctrl_repeat: 'Repeat',

    // Settings
    settings_heading: 'System Settings',
    // Language card
    card_lang_title: 'Language Settings',
    setting_lang_label: 'Display Language',
    setting_lang_desc:
      'Switch the display language for the user interface (Default: Japanese)',
    lang_ja_btn: '日本語',
    lang_en_btn: 'English',
    // Mode card
    card_mode_title: 'Operating Mode & Simulation',
    setting_mode_label: 'Operating Mode',
    setting_mode_desc:
      'Toggle between live host hardware (BlueZ / D-Bus) and demo simulation',
    btn_mode_sim: 'Simulation Mode',
    btn_mode_real: 'Live Host Mode',
    setting_sim_tools: 'Simulation Tools',
    setting_sim_tools_desc: 'Reset demo simulation state to initial defaults',
    btn_reset_mock: '🔄 Reset State',
    // PWA card
    card_pwa_title: 'Progressive Web App (PWA)',
    setting_pwa_install: 'Standalone App Installation',
    setting_pwa_install_desc:
      'Install onto vehicle display or home screen for a fullscreen native experience',
    btn_pwa_install: '📲 Install App',
    btn_pwa_clear: '🧹 Clear Cache',
    setting_pwa_sw: 'Offline Capabilities & Service Worker',
    setting_pwa_sw_desc:
      'Caches UI shell for instant boot and network resilience',
    badge_sw_active: 'SW Active',
    // Diagnostics card
    card_diag_title: 'System Diagnostics & Host Health',
    diag_os: 'Operating System',
    diag_php: 'PHP Runtime',
    diag_btctl: 'BlueZ (bluetoothctl)',
    diag_pactl: 'PulseAudio / PipeWire (pactl)',
    diag_playerctl: 'Media MPRIS (playerctl)',
    diag_wpctl: 'WirePlumber (wpctl)',
    diag_dbus: 'D-Bus System Socket',
    diag_bluez_dbus: 'BlueZ D-Bus Service',
    diag_media_player: 'BlueZ MediaPlayer1 (AVRCP)',
    diag_pulse_server: 'PULSE_SERVER',

    // About Page
    about_heading: 'Application Details',
    about_hero_tag: 'ENOKI ELECTRONICS - AUTOMOTIVE SOLUTIONS',
    about_title: 'ENOKI ELECTRONICS A2DP Sink Client',
    about_subtitle: 'In-Vehicle High-Fidelity Bluetooth Audio Receiver System',
    about_desc_title: 'Product Overview',
    about_desc_body:
      'This application is an in-vehicle A2DP Sink client app manufactured by ENOKI ELECTRONICS (エノキ電気).\nIt receives high-quality Bluetooth audio streaming (A2DP) from smartphones, tablets, and portable media players (A2DP Source), providing real-time metadata acquisition (track title, artist, album, timeline position) and media playback control (Play, Pause, Next, Previous) via AVRCP.',
    about_features_title: 'Key Specifications & Design Philosophy',
    feat_1_title: '🎵 A2DP Sink Audio Streaming',
    feat_1_desc:
      'Deep integration with BlueZ and Linux audio servers (PipeWire / PulseAudio) to receive low-latency high-resolution streams via AAC, SBC, LDAC, and aptX codecs.',
    feat_2_title: '🎛️ AVRCP Media Controls',
    feat_2_desc:
      'Direct remote control of smartphone playback apps via BlueZ MediaPlayer1 / MediaControl1 interfaces from the vehicle touch display.',
    feat_3_title: '🔇 External Volume Architecture',
    feat_3_desc:
      'In line with automotive audio safety and unified acoustic design, volume is handled externally by vehicle master controls (rotary dials, steering wheel buttons). In-app volume controls are intentionally excluded.',
    feat_4_title: '📱 PWA & Automotive Dark UI',
    feat_4_desc:
      'High-contrast dark theme engineered for automotive glanceability and touch ergonomics. Installable as a PWA for full-screen car display operation.',
    about_info_title: 'System Details & Manufacturer',
    info_vendor_label: 'Developer / Manufacturer',
    info_vendor_val: 'ENOKI ELECTRONICS (エノキ電気)',
    info_product_label: 'Product Name',
    info_product_val: 'In-Vehicle A2DP Sink Client Application',
    info_version_label: 'Version',
    info_version_val: 'v2.0.0 (2026 Edition)',
    info_type_label: 'Bluetooth Profiles',
    info_type_val: 'A2DP Sink (Audio Sink) / AVRCP Target',
    info_license_label: 'Copyright',
    info_license_val: '© 2026 ENOKI ELECTRONICS. All rights reserved.',

    // Toasts & Alerts
    toast_switched_sim: 'Switched to Simulation Mode',
    toast_switched_real: 'Switched to Live Host Mode',
    toast_reset_sim: 'Simulation state reset to default',
    toast_cache_cleared: 'Cache cleared. Reloading app...',
    toast_lang_changed: 'Language changed to English',
    toast_cmd_failed: 'Failed to send control command',
  },
}

const I18n = {
  currentLang: 'ja',

  init() {
    const saved = localStorage.getItem('carmedia_lang')
    // Default is Japanese ('ja')
    this.currentLang = saved === 'en' ? 'en' : 'ja'
    this.apply(this.currentLang)
    this.bindEvents()
  },

  setLanguage(lang) {
    if (lang !== 'ja' && lang !== 'en') return
    this.currentLang = lang
    localStorage.setItem('carmedia_lang', lang)
    this.apply(lang)
    if (typeof App !== 'undefined' && App.toast) {
      App.toast(this.t('toast_lang_changed'))
    }
  },

  apply(lang) {
    document.documentElement.lang = lang
    document.title = this.t('app_title')

    // Update all elements with data-i18n
    document.querySelectorAll('[data-i18n]').forEach((el) => {
      const key = el.getAttribute('data-i18n')
      const val = this.t(key)
      if (val !== undefined) {
        if (val.includes('\n')) {
          el.innerHTML = val.replace(/\n/g, '<br>')
        } else {
          el.textContent = val
        }
      }
    })

    // Update titles / tooltips
    document.querySelectorAll('[data-i18n-title]').forEach((el) => {
      const key = el.getAttribute('data-i18n-title')
      const val = this.t(key)
      if (val !== undefined) {
        el.setAttribute('title', val)
      }
    })

    // Update buttons in Settings
    const btnJa = document.getElementById('btn-lang-ja')
    const btnEn = document.getElementById('btn-lang-en')
    if (btnJa && btnEn) {
      if (lang === 'ja') {
        btnJa.classList.add('active')
        btnEn.classList.remove('active')
      } else {
        btnEn.classList.add('active')
        btnJa.classList.remove('active')
      }
    }

    // Update mode badge
    if (typeof App !== 'undefined' && App.updateModeBadge) {
      App.updateModeBadge()
    }

    // Update player idle texts if no media is playing
    if (typeof Player !== 'undefined' && Player.updateIdleTexts) {
      Player.updateIdleTexts()
    }
  },

  t(key) {
    const dict = I18N_DATA[this.currentLang] || I18N_DATA['ja']
    return dict[key] !== undefined ? dict[key] : I18N_DATA['ja'][key] || key
  },

  bindEvents() {
    const btnJa = document.getElementById('btn-lang-ja')
    const btnEn = document.getElementById('btn-lang-en')
    if (btnJa) {
      btnJa.addEventListener('click', () => this.setLanguage('ja'))
    }
    if (btnEn) {
      btnEn.addEventListener('click', () => this.setLanguage('en'))
    }
  },
}
