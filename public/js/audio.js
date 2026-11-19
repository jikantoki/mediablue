/**
 * Audio Mixer & Routing (Audioctl / pactl / PipeWire) Logic
 */
const AudioMixer = {
  masterVolume: 75,
  isMuted: false,

  init() {
    const masterSlider = document.getElementById('mixer-master-slider')
    const btnMute = document.getElementById('btn-master-mute')

    if (masterSlider) {
      masterSlider.addEventListener('input', (e) => {
        const val = e.target.value
        document.getElementById('mixer-master-val').textContent = val + '%'
      })
      masterSlider.addEventListener('change', (e) => {
        this.setVolume(e.target.value)
      })
    }

    if (btnMute) {
      btnMute.addEventListener('click', () => {
        this.toggleMute()
      })
    }
  },

  async refresh() {
    try {
      const res = await fetch('/api/audio/status')
      const data = await res.json()
      if (data.success && data.audio) {
        this.updateUI(data.audio)
      }
    } catch (e) {
      console.error('Audio status error', e)
    }
  },

  updateUI(a) {
    this.masterVolume = a.master_volume
    this.isMuted = a.muted

    // Top Status Bar
    const topVolText = document.getElementById('top-vol-text')
    const topVolIcon = document.getElementById('top-vol-icon')
    if (topVolText) {
      topVolText.textContent = this.isMuted ? 'Muted' : this.masterVolume + '%'
    }

    // Mixer UI
    const masterSlider = document.getElementById('mixer-master-slider')
    const masterVal = document.getElementById('mixer-master-val')
    const btnMute = document.getElementById('btn-master-mute')

    if (masterSlider) masterSlider.value = this.masterVolume
    if (masterVal) masterVal.textContent = this.masterVolume + '%'

    // Also update player volume slider to match
    const playerSlider = document.getElementById('player-volume-slider')
    const playerVal = document.getElementById('player-volume-text')
    if (playerSlider) playerSlider.value = this.masterVolume
    if (playerVal) playerVal.textContent = this.masterVolume + '%'

    if (btnMute) {
      btnMute.textContent = this.isMuted ? 'Unmute' : 'Mute'
      btnMute.className = `btn-mute ${this.isMuted ? 'muted' : ''}`
    }

    // Output Sinks List
    const sinksContainer = document.getElementById('sinks-list')
    if (sinksContainer) {
      sinksContainer.innerHTML = ''
      ;(a.sinks || []).forEach((sink) => {
        const card = document.createElement('div')
        card.className = `sink-card ${sink.is_default ? 'active' : ''}`
        card.innerHTML = `
                    <div class="sink-header">
                        <span>${sink.description || sink.name}</span>
                        ${sink.is_default ? '<span class="badge badge-active">Default Output</span>' : ''}
                    </div>
                    <div class="subtext">Sink ID: #${sink.id} | Vol: ${sink.volume}%</div>
                `
        card.addEventListener('click', () => {
          this.setDefaultSink(sink.name)
        })
        sinksContainer.appendChild(card)
      })
    }

    // Input Sources List
    const sourcesContainer = document.getElementById('sources-list')
    if (sourcesContainer) {
      sourcesContainer.innerHTML = ''
      ;(a.sources || []).forEach((src) => {
        const card = document.createElement('div')
        card.className = 'sink-card'
        card.innerHTML = `
                    <div class="sink-header">
                        <span>${src.description || src.name}</span>
                        <span class="badge badge-profile">Input Mic</span>
                    </div>
                    <div class="subtext">Source ID: #${src.id} | Gain: ${src.volume}%</div>
                `
        sourcesContainer.appendChild(card)
      })
    }
  },

  async setVolume(volume) {
    try {
      await fetch('/api/audio/volume', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ volume: parseInt(volume, 10) }),
      })
      this.refresh()
    } catch (e) {
      App.toast('Failed to set volume')
    }
  },

  async toggleMute() {
    try {
      await fetch('/api/audio/mute', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mute: !this.isMuted }),
      })
      this.refresh()
    } catch (e) {
      App.toast('Failed to toggle mute')
    }
  },

  async setDefaultSink(sinkName) {
    try {
      await fetch('/api/audio/sink', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sink: sinkName }),
      })
      App.toast('Default audio output changed')
      this.refresh()
    } catch (e) {
      App.toast('Failed to switch audio output')
    }
  },
}
