/**
 * Bluetooth Status Reader (Top Bar & Player integration)
 * Manufacturer: エノキ電気 ENOKI ELECTRONICS
 */
const Bluetooth = {
  connectedDevice: null,

  init() {
    this.loadStatus()
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
        const connDev = (b.devices || []).find((d) => d.connected)
        this.connectedDevice = connDev ? connDev.name : null

        const noDevText = (typeof I18n !== 'undefined') ? I18n.t('no_device_connected') : 'No Device'
        const discText = (typeof I18n !== 'undefined') ? I18n.t('disconnected') : 'Disconnected'

        // Top status bar connected device update
        const topDevEl = document.getElementById('top-bt-device')
        if (topDevEl) {
          topDevEl.textContent = this.connectedDevice || discText
        }

        // Player source device update
        const sourceDev = document.getElementById('player-source-dev')
        if (sourceDev) {
          sourceDev.textContent = this.connectedDevice || noDevText
        }
      }
    } catch (e) {
      console.error('Bluetooth status error', e)
    }
  },
}
