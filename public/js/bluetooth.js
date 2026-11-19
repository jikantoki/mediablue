/**
 * Bluetooth Management (BlueZ & Adapter) Logic
 */
const Bluetooth = {
  isScanning: false,

  init() {
    const btnScan = document.getElementById('btn-scan')
    const switchPower = document.getElementById('switch-bt-power')
    const switchDisc = document.getElementById('switch-bt-discoverable')

    if (btnScan) {
      btnScan.addEventListener('click', () => this.toggleScan())
    }

    if (switchPower) {
      switchPower.addEventListener('change', (e) => {
        this.setPower(e.target.checked)
      })
    }

    if (switchDisc) {
      switchDisc.addEventListener('change', (e) => {
        this.setDiscoverable(e.target.checked)
      })
    }
  },

  async refresh() {
    await this.loadStatus()
    await this.loadDevices()
  },

  async loadStatus() {
    try {
      const res = await fetch('/api/bluetooth/status')
      const data = await res.json()
      if (data.success && data.bluetooth) {
        const b = data.bluetooth
        const ad = b.adapter
        document.getElementById('adapter-name').textContent = `${ad.name}`
        document.getElementById('adapter-mac').textContent = ad.mac
        document.getElementById('switch-bt-power').checked = ad.powered
        document.getElementById('switch-bt-discoverable').checked =
          ad.discoverable

        // Top status bar connected device update
        const connDev = b.devices.find((d) => d.connected)
        const topDevEl = document.getElementById('top-bt-device')
        if (topDevEl) {
          topDevEl.textContent = connDev ? connDev.name : 'Disconnected'
        }
        const sourceDev = document.getElementById('player-source-dev')
        if (sourceDev) {
          sourceDev.textContent = connDev ? connDev.name : 'No Device'
        }
      }
    } catch (e) {
      console.error('Bluetooth status error', e)
    }
  },

  async loadDevices() {
    try {
      const res = await fetch('/api/bluetooth/devices')
      const data = await res.json()
      if (data.success && data.devices) {
        this.renderDevices(data.devices)
      }
    } catch (e) {
      console.error('Bluetooth devices error', e)
    }
  },

  renderDevices(devices) {
    const pairedContainer = document.getElementById('paired-devices-list')
    const availableContainer = document.getElementById('available-devices-list')

    pairedContainer.innerHTML = ''
    availableContainer.innerHTML = ''

    const paired = devices.filter((d) => d.paired)
    const available = devices.filter((d) => !d.paired)

    if (paired.length === 0) {
      pairedContainer.innerHTML =
        '<p class="subtext">No paired devices. Scan and pair below.</p>'
    } else {
      paired.forEach((d) => {
        pairedContainer.appendChild(this.createDeviceCard(d, true))
      })
    }

    if (available.length === 0) {
      availableContainer.innerHTML =
        '<p class="subtext">No nearby devices found. Tap "Scan for Devices" above.</p>'
    } else {
      available.forEach((d) => {
        availableContainer.appendChild(this.createDeviceCard(d, false))
      })
    }
  },

  createDeviceCard(dev, isPaired) {
    const card = document.createElement('div')
    card.className = `device-item ${dev.connected ? 'connected' : ''}`

    // Profiles badges
    const profilesHtml = (dev.profiles || [])
      .map(
        (p) =>
          `<span class="badge ${p.includes('A2DP') ? 'badge-profile' : 'badge-codec'}">${p}</span>`,
      )
      .join('')

    // Battery / Signal
    const battHtml =
      dev.battery !== null && dev.battery !== undefined
        ? `<span class="subtext">🔋 ${dev.battery}%</span>`
        : ''

    const iconSvg =
      dev.icon === 'headphones'
        ? `<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>`
        : `<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>`

    card.innerHTML = `
            <div class="dev-info-left">
                <div class="dev-icon">${iconSvg}</div>
                <div>
                    <div class="dev-name">${dev.name || 'Unknown'} ${battHtml}</div>
                    <div class="dev-mac">${dev.mac}</div>
                    <div class="dev-profiles">${profilesHtml}</div>
                </div>
            </div>
            <div class="dev-actions">
                ${
                  isPaired
                    ? `
                    ${
                      dev.connected
                        ? `<button class="btn-sm btn-disconnect" onclick="Bluetooth.disconnect('${dev.mac}')">Disconnect</button>`
                        : `<button class="btn-sm btn-connect" onclick="Bluetooth.connect('${dev.mac}')">Connect</button>`
                    }
                    <button class="btn-sm btn-delete" onclick="Bluetooth.remove('${dev.mac}')" title="Unpair & Remove">Unpair</button>
                `
                    : `
                    <button class="btn-sm btn-connect" onclick="Bluetooth.pair('${dev.mac}')">Pair & Trust</button>
                `
                }
            </div>
        `
    return card
  },

  async toggleScan() {
    const btnText = document.getElementById('scan-btn-text')
    this.isScanning = !this.isScanning

    if (this.isScanning) {
      btnText.textContent = 'Scanning... (Listening)'
      await fetch('/api/bluetooth/scan', { method: 'POST' })
      App.toast('Started Bluetooth Discovery')
      setTimeout(async () => {
        await this.loadDevices()
        btnText.textContent = 'Scan for Devices'
        this.isScanning = false
      }, 5000)
    } else {
      btnText.textContent = 'Scan for Devices'
      await fetch('/api/bluetooth/scan/stop', { method: 'POST' })
    }
  },

  async pair(mac) {
    App.toast('Pairing with ' + mac + '...')
    try {
      const res = await fetch('/api/bluetooth/pair', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mac }),
      })
      const data = await res.json()
      if (data.success) {
        App.toast('Pairing successful')
        this.refresh()
      } else {
        App.toast('Pairing failed: ' + (data.error || ''))
      }
    } catch (e) {
      App.toast('Pair request failed')
    }
  },

  async connect(mac) {
    App.toast('Connecting to ' + mac + ' (A2DP / HFP)...')
    try {
      const res = await fetch('/api/bluetooth/connect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mac }),
      })
      const data = await res.json()
      if (data.success) {
        App.toast('Connected to ' + mac)
        this.refresh()
        Player.refresh()
        Phone.refresh()
      } else {
        App.toast('Connect failed: ' + (data.error || ''))
      }
    } catch (e) {
      App.toast('Connect request failed')
    }
  },

  async disconnect(mac) {
    try {
      await fetch('/api/bluetooth/disconnect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mac }),
      })
      App.toast('Disconnected from ' + mac)
      this.refresh()
    } catch (e) {
      App.toast('Disconnect failed')
    }
  },

  async remove(mac) {
    if (!confirm('Unpair and forget this device?')) return
    try {
      await fetch('/api/bluetooth/remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mac }),
      })
      App.toast('Device removed')
      this.refresh()
    } catch (e) {
      App.toast('Remove failed')
    }
  },

  async setPower(power) {
    await fetch('/api/bluetooth/power', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ power }),
    })
    App.toast('Bluetooth power ' + (power ? 'ON' : 'OFF'))
  },

  async setDiscoverable(discoverable) {
    await fetch('/api/bluetooth/discoverable', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ discoverable }),
    })
    App.toast('Bluetooth Discoverable ' + (discoverable ? 'ON' : 'OFF'))
  },
}
