/**
 * Media Player (A2DP Sink / AVRCP) Logic
 */
const Player = {
  isPlaying: false,

  init() {
    const btnPlayPause = document.getElementById('btn-play-pause')
    const btnNext = document.getElementById('btn-next')
    const btnPrev = document.getElementById('btn-prev')
    const volSlider = document.getElementById('player-volume-slider')

    if (btnPlayPause) {
      btnPlayPause.addEventListener('click', () => this.control('play-pause'))
    }
    if (btnNext) {
      btnNext.addEventListener('click', () => this.control('next'))
    }
    if (btnPrev) {
      btnPrev.addEventListener('click', () => this.control('prev'))
    }
    if (volSlider) {
      volSlider.addEventListener('input', (e) => {
        const val = e.target.value
        document.getElementById('player-volume-text').textContent = val + '%'
      })
      volSlider.addEventListener('change', (e) => {
        AudioMixer.setVolume(e.target.value)
      })
    }
  },

  async refresh() {
    try {
      const res = await fetch('/api/media/player')
      const data = await res.json()
      if (data.success && data.player) {
        this.updateUI(data.player)
      }
    } catch (e) {
      console.error('Player status fetch failed', e)
    }
  },

  updateUI(p) {
    document.getElementById('track-title').textContent =
      p.title || 'No Media Playing'
    document.getElementById('track-artist').textContent =
      p.artist || 'Connect Bluetooth Device'
    document.getElementById('track-album').textContent = p.album || ''
    document.getElementById('player-codec').textContent = p.codec || 'A2DP'

    // アプリ名バッジの表示 (Spotify, Apple Music, YouTube 等)
    const appBadge = document.getElementById('player-app-badge')
    if (appBadge) {
      if (p.app_name) {
        appBadge.textContent = p.app_name
        appBadge.style.display = 'inline-block'
      } else {
        appBadge.style.display = 'none'
      }
    }

    // Play/Pause icon & animation
    this.isPlaying = p.status === 'playing'
    const iconPlay = document.getElementById('icon-play')
    const iconPause = document.getElementById('icon-pause')
    const albumCover = document.getElementById('album-cover')

    if (this.isPlaying) {
      iconPlay.classList.add('hidden')
      iconPause.classList.remove('hidden')
      albumCover.classList.add('playing')
      albumCover.classList.remove('paused')
    } else {
      iconPlay.classList.remove('hidden')
      iconPause.classList.add('hidden')
      albumCover.classList.remove('playing')
      albumCover.classList.add('paused')
    }

    if (p.cover) {
      albumCover.style.background = p.cover
    }

    // Timeline & Progress
    const cur = p.position || 0
    const dur = p.duration || 1
    const pct = Math.min(100, Math.max(0, (cur / dur) * 100))

    document.getElementById('progress-fill').style.width = pct + '%'
    document.getElementById('time-current').textContent = this.formatTime(cur)
    document.getElementById('time-duration').textContent = this.formatTime(dur)
  },

  async control(action) {
    try {
      const res = await fetch('/api/media/control', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action }),
      })
      const data = await res.json()
      if (data.success) {
        this.refresh()
      } else {
        const errMsg = data.error || 'Control failed'
        console.warn('AVRCP Error:', data)
        App.toast('AVRCP: ' + errMsg)
      }
    } catch (e) {
      console.error('AVRCP request error:', e)
      App.toast('Failed to send control command')
    }
  },

  formatTime(sec) {
    const m = Math.floor(sec / 60)
    const s = Math.floor(sec % 60)
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  },
}
