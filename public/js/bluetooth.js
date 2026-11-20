/**
 * Bluetooth Controller & Power Monitor
 * Manufacturer: エノキ電気 ENOKI ELECTRONICS
 */
const Bluetooth = {
  connectedDevice: null,
  isPowered: true,
  hasController: true,
  isDismissed: false,

  init() {
    this.initUI()
    this.loadStatus()
  },

  initUI() {
    // 1. 「Bluetoothをオンにする」ボタン
    const btnTurnOn = document.getElementById('btn-turn-on-bt')
    if (btnTurnOn) {
      btnTurnOn.addEventListener('click', () => this.turnOn())
    }

    // 2. 「導入手順ガイドを見る」ボタン
    const btnGotoGuide = document.getElementById('btn-goto-guide')
    if (btnGotoGuide) {
      btnGotoGuide.addEventListener('click', () => {
        this.hideOffModal()
        this.switchToTab('tab-guide')
      })
    }

    // 3. 手動有効化コマンド アコーディオンのトグル
    const btnToggleManual = document.getElementById('btn-toggle-bt-manual')
    const manualContent = document.getElementById('bt-manual-content')
    if (btnToggleManual && manualContent) {
      btnToggleManual.addEventListener('click', () => {
        const isHidden = manualContent.classList.toggle('hidden')
        btnToggleManual.classList.toggle('open', !isHidden)
      })
    }

    // 4. CLIコマンドコピーボタン
    const btnCopyCli = document.getElementById('btn-copy-bt-cli')
    const cliCommandEl = document.getElementById('bt-cli-command')
    if (btnCopyCli && cliCommandEl) {
      btnCopyCli.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(cliCommandEl.textContent.trim())
          const orig = btnCopyCli.textContent
          btnCopyCli.textContent = '✅'
          if (typeof App !== 'undefined') {
            App.toast(typeof I18n !== 'undefined' ? I18n.t('bt_off_copied') : 'Copied!')
          }
          setTimeout(() => {
            btnCopyCli.textContent = orig
          }, 2000)
        } catch (e) {
          console.warn('Clipboard write failed', e)
        }
      })
    }

    // 5. 閉じるボタン（一時的にUIを確認）
    const btnDismiss = document.getElementById('btn-dismiss-bt-off')
    if (btnDismiss) {
      btnDismiss.addEventListener('click', () => {
        this.isDismissed = true
        this.hideOffModal()
      })
    }

    // 6. Settingsタブのシミュレーション用 Bluetooth 電源テストボタン
    const btnTestOff = document.getElementById('btn-test-bt-off')
    const btnTestOn = document.getElementById('btn-test-bt-on')
    if (btnTestOff) {
      btnTestOff.addEventListener('click', async () => {
        this.isDismissed = false
        await this.setPower(false)
      })
    }
    if (btnTestOn) {
      btnTestOn.addEventListener('click', async () => {
        await this.setPower(true)
      })
    }

    // 7. ガイド画面内のコードブロックコピーボタン
    document.querySelectorAll('.btn-copy-code').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const code = btn.getAttribute('data-code')
        if (code) {
          try {
            await navigator.clipboard.writeText(code)
            const orig = btn.textContent
            btn.textContent = '✅ コピー完了!'
            if (typeof App !== 'undefined') {
              App.toast('クリップボードにコピーしました')
            }
            setTimeout(() => {
              btn.textContent = orig
            }, 2000)
          } catch (e) {
            console.warn('Copy failed', e)
          }
        }
      })
    })
  },

  async refresh() {
    await this.loadStatus()
  },

  async loadStatus() {
    try {
      const res = await fetch('/api/bluetooth/status')
      const data = await res.json()
      if (data.success && data.bluetooth) {
        const b = data.bluetooth
        const adapter = b.adapter || {}
        
        const wasPowered = this.isPowered
        this.isPowered = (adapter.powered !== false && adapter.powered !== 0)
        this.hasController = (adapter.has_controller !== false)

        const connDev = (b.devices || []).find((d) => d.connected)
        this.connectedDevice = connDev ? connDev.name : null

        const noDevText = (typeof I18n !== 'undefined') ? I18n.t('no_device_connected') : 'No Device'
        const discText = (typeof I18n !== 'undefined') ? I18n.t('disconnected') : 'Disconnected'

        // Top status bar connected device & status update
        const topDevEl = document.getElementById('top-bt-device')
        const topStatEl = document.getElementById('top-bt-stat')
        if (topDevEl) {
          if (!this.isPowered) {
            topDevEl.textContent = 'Bluetooth OFF'
            if (topStatEl) topStatEl.style.color = 'var(--color-red)'
          } else {
            topDevEl.textContent = this.connectedDevice || discText
            if (topStatEl) topStatEl.style.color = ''
          }
        }

        // Player source device update
        const sourceDev = document.getElementById('player-source-dev')
        if (sourceDev) {
          if (!this.isPowered) {
            sourceDev.textContent = 'Bluetooth OFF'
          } else {
            sourceDev.textContent = this.connectedDevice || noDevText
          }
        }

        // Bluetooth電源オフ状態の検知 & 警告モーダル制御
        if (!this.isPowered) {
          // 前回オンから今回オフに切り替わった場合はdismiss状態をリセット
          if (wasPowered) {
            this.isDismissed = false
          }
          if (!this.isDismissed) {
            this.showOffModal(!this.hasController)
          }
        } else {
          // オンになったら自動でモーダルを閉じる
          this.hideOffModal()
          this.isDismissed = false
        }
      }
    } catch (e) {
      console.error('Bluetooth status error', e)
    }
  },

  showOffModal(missingController = false) {
    const modal = document.getElementById('modal-bt-off')
    if (modal) {
      modal.classList.remove('hidden')
    }
    const warnBox = document.getElementById('bt-off-missing-warning')
    if (warnBox) {
      warnBox.classList.toggle('hidden', !missingController)
    }
  },

  hideOffModal() {
    const modal = document.getElementById('modal-bt-off')
    if (modal) {
      modal.classList.add('hidden')
    }
  },

  async turnOn() {
    const btn = document.getElementById('btn-turn-on-bt')
    const btnText = document.getElementById('btn-turn-on-bt-text')
    const origText = btnText ? btnText.textContent : ''

    try {
      if (btn) btn.classList.add('loading')
      if (btnText) {
        btnText.textContent = typeof I18n !== 'undefined' ? I18n.t('bt_off_turning_on') : 'Turning on...'
      }

      const res = await fetch('/api/bluetooth/power', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ power: true }),
      })
      const data = await res.json()

      if (data.success && data.data && data.data.powered) {
        this.isPowered = true
        this.hideOffModal()
        if (typeof App !== 'undefined') {
          App.toast(typeof I18n !== 'undefined' ? I18n.t('toast_bt_on_success') : 'Bluetooth turned on successfully')
        }
        await this.loadStatus()
      } else {
        const errMsg = data.error || (typeof I18n !== 'undefined' ? I18n.t('toast_bt_on_failed') : 'Failed to turn on Bluetooth')
        if (typeof App !== 'undefined') {
          App.toast(errMsg)
        }
        // 手動コマンドアコーディオンを開いて案内
        const manualContent = document.getElementById('bt-manual-content')
        const btnToggleManual = document.getElementById('btn-toggle-bt-manual')
        if (manualContent && btnToggleManual) {
          manualContent.classList.remove('hidden')
          btnToggleManual.classList.add('open')
        }
      }
    } catch (e) {
      if (typeof App !== 'undefined') {
        App.toast('Network error while turning on Bluetooth')
      }
    } finally {
      if (btn) btn.classList.remove('loading')
      if (btnText) btnText.textContent = origText
    }
  },

  async setPower(power) {
    try {
      const res = await fetch('/api/bluetooth/power', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ power }),
      })
      const data = await res.json()
      if (typeof App !== 'undefined') {
        const msg = power ? 'Bluetooth ON' : 'Bluetooth OFF'
        App.toast(`Set: ${msg}`)
      }
      await this.loadStatus()
    } catch (e) {
      console.error('setPower error', e)
    }
  },

  switchToTab(tabId) {
    const navBtn = document.querySelector(`.nav-btn[data-tab="${tabId}"]`)
    if (navBtn) {
      navBtn.click()
    }
  },
}
