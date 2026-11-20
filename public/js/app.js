/**
 * mediablue Application Core
 * Manufacturer: エノキ電気 ENOKI ELECTRONICS
 */
const App = {
  pollingInterval: null,
  deferredPrompt: null,
  isMock: false,

  init() {
    // 1. 国際化 (i18n) 初期化 (デフォルト: 日本語)
    if (typeof I18n !== 'undefined') {
      I18n.init()
    }

    this.initPWA()
    this.initTabs()
    this.initClock()
    this.initSettings()

    // 2. 機能モジュール初期化 (Media Player & Bluetooth Status)
    if (typeof Player !== 'undefined') Player.init()
    if (typeof Bluetooth !== 'undefined') Bluetooth.init()

    // 3. 最初のデータロード
    this.refreshAll()

    // 4. 定期更新 (1秒ごとに再生位置 & AVRCP更新)
    this.pollingInterval = setInterval(() => {
      this.pollFast()
    }, 1000)

    // 5. 3秒ごとにBluetooth電源 & 接続状態更新
    setInterval(() => {
      if (typeof Bluetooth !== 'undefined') Bluetooth.loadStatus()
    }, 3000)
  },

  initTabs() {
    const navBtns = document.querySelectorAll('.nav-btn')
    navBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetTab = btn.getAttribute('data-tab')

        navBtns.forEach((b) => b.classList.remove('active'))
        btn.classList.add('active')

        document.querySelectorAll('.tab-panel').forEach((panel) => {
          panel.classList.remove('active')
        })

        const panel = document.getElementById(targetTab)
        if (panel) {
          panel.classList.add('active')
        }

        // タブ切り替え時のリフレッシュ
        if (targetTab === 'tab-player' && typeof Player !== 'undefined') {
          Player.refresh()
        } else if (targetTab === 'tab-settings') {
          this.loadDiagnostics()
        }
      })
    })
  },

  initClock() {
    const clockEl = document.getElementById('clock')
    const updateClock = () => {
      const now = new Date()
      const h = String(now.getHours()).padStart(2, '0')
      const m = String(now.getMinutes()).padStart(2, '0')
      if (clockEl) clockEl.textContent = `${h}:${m}`
    }
    updateClock()
    setInterval(updateClock, 1000)
  },

  initSettings() {
    const btnMock = document.getElementById('btn-set-mock')
    const btnReal = document.getElementById('btn-set-real')
    const btnReset = document.getElementById('btn-reset-mock')

    if (btnMock) {
      btnMock.addEventListener('click', () => this.switchMode(true))
    }
    if (btnReal) {
      btnReal.addEventListener('click', () => this.switchMode(false))
    }
    if (btnReset) {
      btnReset.addEventListener('click', async () => {
        await fetch('/api/system/reset-mock', { method: 'POST' })
        this.toast(
          typeof I18n !== 'undefined'
            ? I18n.t('toast_reset_sim')
            : 'Simulation state reset to default'
        )
        this.refreshAll()
      })
    }
  },

  async switchMode(isMock) {
    try {
      const res = await fetch('/api/system/mode', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mock: isMock }),
      })
      const data = await res.json()
      if (data.success) {
        this.isMock = isMock
        const msg = isMock
          ? (typeof I18n !== 'undefined' ? I18n.t('toast_switched_sim') : 'Switched to Simulation Mode')
          : (typeof I18n !== 'undefined' ? I18n.t('toast_switched_real') : 'Switched to Live Host Mode')
        this.toast(msg)
        setTimeout(() => location.reload(), 500)
      }
    } catch (e) {
      this.toast('Failed to change mode')
    }
  },

  updateModeBadge() {
    const badge = document.getElementById('mode-badge')
    if (!badge) return
    const isMock = badge.classList.contains('badge-mock')
    this.isMock = isMock
    if (typeof I18n !== 'undefined') {
      badge.textContent = I18n.t(isMock ? 'mode_mock' : 'mode_real')
    }
  },

  async loadDiagnostics() {
    try {
      const res = await fetch('/api/system/info')
      const data = await res.json()
      if (data.success) {
        const s = data.system
        const setVal = (id, val) => {
          const el = document.getElementById(id)
          if (el) el.textContent = val
        }
        setVal('diag-os', s.os)
        setVal('diag-php', s.php_version)
        setVal('diag-btctl', s.has_bluetoothctl ? 'Installed ✅' : 'Missing ❌')
        setVal('diag-pactl', s.has_pactl ? 'Installed ✅' : 'Missing ❌')
        setVal('diag-playerctl', s.has_playerctl ? 'Installed ✅' : 'Not Found (Fallback to Bluez)')
        setVal('diag-wpctl', s.has_wpctl ? 'Installed ✅' : 'Not Found')
        setVal('diag-dbus', s.has_dbus_socket ? 'Available (/var/run/dbus/system_bus_socket) ✅' : 'Not Found (Check Docker Mount)')
        setVal('diag-bluez-dbus', s.bluez_dbus_connected ? 'Connected (org.bluez OK) ✅' : 'Failed / Unreachable ❌')
        setVal('diag-media-player', s.media_player_path ? `Found (${s.media_player_path}) ✅` : 'Not Detected (Connect phone & open music app)')
        setVal('diag-pulse-server', s.pulse_server)
      }
    } catch (e) {
      console.error('Diagnostics failed', e)
    }
  },

  refreshAll() {
    if (typeof Player !== 'undefined') Player.refresh()
    if (typeof Bluetooth !== 'undefined') Bluetooth.loadStatus()
    this.loadDiagnostics()
    this.updateModeBadge()
  },

  pollFast() {
    if (typeof Player !== 'undefined') Player.refresh()
  },

  initPWA() {
    const btnHeaderInstall = document.getElementById('btn-install-pwa')
    const btnSettingsInstall = document.getElementById('btn-settings-install-pwa')
    const btnClearCache = document.getElementById('btn-clear-cache')
    const swStatusEl = document.getElementById('pwa-sw-status')

    // Service Worker の登録
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker
          .register('/sw.js')
          .then((registration) => {
            console.log('[PWA] ServiceWorker registered with scope:', registration.scope)
            if (swStatusEl) {
              swStatusEl.textContent = typeof I18n !== 'undefined' ? I18n.t('badge_sw_active') : 'Active'
              swStatusEl.className = 'badge badge-active'
            }
          })
          .catch((error) => {
            console.warn('[PWA] ServiceWorker registration failed:', error)
            if (swStatusEl) {
              swStatusEl.textContent = 'Failed'
              swStatusEl.className = 'badge badge-idle'
            }
          })
      })
    } else {
      if (swStatusEl) {
        swStatusEl.textContent = 'Not Supported'
        swStatusEl.className = 'badge badge-idle'
      }
    }

    // PWA インストールプロンプト
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault()
      this.deferredPrompt = e

      if (btnHeaderInstall) btnHeaderInstall.classList.remove('hidden')
      if (btnSettingsInstall) {
        btnSettingsInstall.disabled = false
      }
    })

    const triggerInstall = async () => {
      if (!this.deferredPrompt) {
        this.toast('App is already installed or your browser does not support installation.')
        return
      }
      this.deferredPrompt.prompt()
      const { outcome } = await this.deferredPrompt.userChoice
      console.log(`[PWA] User response to install prompt: ${outcome}`)
      this.deferredPrompt = null
      if (btnHeaderInstall) btnHeaderInstall.classList.add('hidden')
    }

    if (btnHeaderInstall) {
      btnHeaderInstall.addEventListener('click', triggerInstall)
    }
    if (btnSettingsInstall) {
      btnSettingsInstall.addEventListener('click', triggerInstall)
    }

    window.addEventListener('appinstalled', () => {
      this.toast('App was successfully installed!')
      if (btnHeaderInstall) btnHeaderInstall.classList.add('hidden')
      if (btnSettingsInstall) {
        btnSettingsInstall.disabled = true
        btnSettingsInstall.textContent = 'Installed ✅'
      }
    })

    // キャッシュクリア
    if (btnClearCache) {
      btnClearCache.addEventListener('click', async () => {
        if ('caches' in window) {
          const cacheNames = await caches.keys()
          await Promise.all(cacheNames.map((name) => caches.delete(name)))
          this.toast(
            typeof I18n !== 'undefined'
              ? I18n.t('toast_cache_cleared')
              : 'Cache cleared. Reloading app...'
          )
          setTimeout(() => location.reload(true), 800)
        } else {
          location.reload(true)
        }
      })
    }
  },

  toast(message) {
    const toast = document.getElementById('toast')
    if (!toast) return
    toast.textContent = message
    toast.classList.remove('hidden')
    setTimeout(() => {
      toast.classList.add('hidden')
    }, 3000)
  },
}

window.addEventListener('DOMContentLoaded', () => {
  App.init()
})
