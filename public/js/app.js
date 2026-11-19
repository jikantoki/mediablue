/**
 * mediablue Application Core
 */
const App = {
  pollingInterval: null,
  deferredPrompt: null,

  init() {
    this.initPWA()
    this.initTabs()
    this.initClock()
    this.initSettings()

    // 各機能モジュール初期化
    Player.init()
    Phone.init()
    Bluetooth.init()
    AudioMixer.init()

    // 最初のデータロード
    this.refreshAll()

    // 1秒ごとに自動更新（再生位置、アクティブメディアプレイヤーの追従、通話ステータス）
    this.pollingInterval = setInterval(() => {
      this.pollFast()
    }, 1000)

    // 10秒ごとの低頻度更新（デバイス一覧など）
    setInterval(() => {
      Bluetooth.loadDevices()
    }, 10000)
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

        // タブ切り替え時に即座にそのタブのデータをリフレッシュ
        if (targetTab === 'tab-player') Player.refresh()
        else if (targetTab === 'tab-phone') Phone.refresh()
        else if (targetTab === 'tab-bluetooth') Bluetooth.refresh()
        else if (targetTab === 'tab-audio') AudioMixer.refresh()
        else if (targetTab === 'tab-settings') this.loadDiagnostics()
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
        this.toast('Simulation state reset to default')
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
        this.toast(
          isMock ? 'Switched to Simulation Mode' : 'Switched to Live Host Mode',
        )
        setTimeout(() => location.reload(), 500)
      }
    } catch (e) {
      this.toast('Failed to change mode')
    }
  },

  async loadDiagnostics() {
    try {
      const res = await fetch('/api/system/info')
      const data = await res.json()
      if (data.success) {
        const s = data.system
        document.getElementById('diag-os').textContent = s.os
        document.getElementById('diag-php').textContent = s.php_version
        document.getElementById('diag-btctl').textContent = s.has_bluetoothctl
          ? 'Installed ✅'
          : 'Missing ❌'
        document.getElementById('diag-pactl').textContent = s.has_pactl
          ? 'Installed ✅'
          : 'Missing ❌'
        document.getElementById('diag-playerctl').textContent = s.has_playerctl
          ? 'Installed ✅'
          : 'Not Found (Fallback to Bluez)'
        document.getElementById('diag-wpctl').textContent = s.has_wpctl
          ? 'Installed ✅'
          : 'Not Found'
        document.getElementById('diag-dbus').textContent = s.has_dbus_socket
          ? 'Available (/var/run/dbus/system_bus_socket) ✅'
          : 'Not Found (Check Docker Mount)'
        document.getElementById('diag-bluez-dbus').textContent = s.bluez_dbus_connected
          ? 'Connected (org.bluez OK) ✅'
          : 'Failed / Unreachable ❌'
        document.getElementById('diag-media-player').textContent = s.media_player_path
          ? `Found (${s.media_player_path}) ✅`
          : 'Not Detected (Connect phone & open music app)'
        document.getElementById('diag-pulse-server').textContent =
          s.pulse_server
      }
    } catch (e) {
      console.error('Diagnostics failed', e)
    }
  },

  refreshAll() {
    Player.refresh()
    Phone.refresh()
    Bluetooth.refresh()
    AudioMixer.refresh()
    this.loadDiagnostics()
  },

  pollFast() {
    Player.refresh()
    Phone.refresh()
  },

  initPWA() {
    const btnHeaderInstall = document.getElementById('btn-install-pwa')
    const btnSettingsInstall = document.getElementById(
      'btn-settings-install-pwa',
    )
    const btnClearCache = document.getElementById('btn-clear-cache')
    const swStatusEl = document.getElementById('pwa-sw-status')

    // Service Worker の登録
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker
          .register('/sw.js')
          .then((registration) => {
            console.log(
              '[PWA] ServiceWorker registered with scope:',
              registration.scope,
            )
            if (swStatusEl) {
              swStatusEl.textContent = 'Active (Ready)'
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

    // PWA インストールプロンプト (beforeinstallprompt) のハンドリング
    window.addEventListener('beforeinstallprompt', (e) => {
      // 自動ポップアップを防ぎ、カスタムボタンをトリガーにする
      e.preventDefault()
      this.deferredPrompt = e

      if (btnHeaderInstall) btnHeaderInstall.classList.remove('hidden')
      if (btnSettingsInstall) {
        btnSettingsInstall.disabled = false
        btnSettingsInstall.textContent = '📲 Install App'
      }
    })

    const triggerInstall = async () => {
      if (!this.deferredPrompt) {
        this.toast(
          'App is already installed or your browser does not support installation.',
        )
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

    // インストール完了イベント
    window.addEventListener('appinstalled', () => {
      this.toast('mediablue was successfully installed!')
      if (btnHeaderInstall) btnHeaderInstall.classList.add('hidden')
      if (btnSettingsInstall) {
        btnSettingsInstall.disabled = true
        btnSettingsInstall.textContent = 'Installed ✅'
      }
    })

    // キャッシュクリアボタン
    if (btnClearCache) {
      btnClearCache.addEventListener('click', async () => {
        if ('caches' in window) {
          const cacheNames = await caches.keys()
          await Promise.all(cacheNames.map((name) => caches.delete(name)))
          this.toast('Cache cleared. Reloading app...')
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
