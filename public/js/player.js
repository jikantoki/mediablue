/**
 * Media Player (A2DP Sink / AVRCP) Logic
 * Manufacturer: エノキ電気 ENOKI ELECTRONICS
 */
const Player = {
  isPlaying: false,
  lastPlayer: null,

  init() {
    const btnPlayPause = document.getElementById('btn-play-pause')
    const btnNext = document.getElementById('btn-next')
    const btnPrev = document.getElementById('btn-prev')
    const btnShuffle = document.getElementById('btn-shuffle')
    const btnRepeat = document.getElementById('btn-repeat')

    if (btnPlayPause) {
      btnPlayPause.addEventListener('click', () => this.control('play-pause'))
    }
    if (btnNext) {
      btnNext.addEventListener('click', () => this.control('next'))
    }
    if (btnPrev) {
      btnPrev.addEventListener('click', () => this.control('prev'))
    }
    if (btnShuffle) {
      btnShuffle.addEventListener('click', () => this.control('shuffle'))
    }
    if (btnRepeat) {
      btnRepeat.addEventListener('click', () => this.control('repeat'))
    }
  },

  async refresh() {
    try {
      const res = await fetch('/api/media/player')
      const data = await res.json()
      if (data.success && data.player) {
        this.lastPlayer = data.player
        this.updateUI(data.player)
      }
    } catch (e) {
      console.error('Player status fetch failed', e)
    }
  },

  updateIdleTexts() {
    if (!this.lastPlayer || !this.lastPlayer.title || this.lastPlayer.title === 'No Media Playing' || this.lastPlayer.title === '再生中のメディアはありません') {
      const titleEl = document.getElementById('track-title')
      const artistEl = document.getElementById('track-artist')
      if (titleEl) {
        titleEl.textContent = (typeof I18n !== 'undefined') ? I18n.t('no_media_playing') : 'No Media Playing'
      }
      if (artistEl) {
        artistEl.textContent = (typeof I18n !== 'undefined') ? I18n.t('connect_bt_prompt') : 'Connect Bluetooth Device'
      }
    }
  },

  updateUI(p) {
    const defaultTitle = (typeof I18n !== 'undefined') ? I18n.t('no_media_playing') : 'No Media Playing'
    const defaultArtist = (typeof I18n !== 'undefined') ? I18n.t('connect_bt_prompt') : 'Connect Bluetooth Device'

    const titleEl = document.getElementById('track-title')
    const artistEl = document.getElementById('track-artist')
    const albumEl = document.getElementById('track-album')
    const codecEl = document.getElementById('player-codec')

    if (titleEl) {
      titleEl.textContent = (p.title && p.title !== 'No Media Playing') ? p.title : defaultTitle
    }
    if (artistEl) {
      artistEl.textContent = (p.artist && p.artist !== 'Connect Bluetooth Device') ? p.artist : defaultArtist
    }
    if (albumEl) {
      albumEl.textContent = p.album || ''
    }
    if (codecEl) {
      codecEl.textContent = p.codec || 'A2DP'
    }

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
      if (iconPlay) iconPlay.classList.add('hidden')
      if (iconPause) iconPause.classList.remove('hidden')
      if (albumCover) {
        albumCover.classList.add('playing')
        albumCover.classList.remove('paused')
      }
    } else {
      if (iconPlay) iconPlay.classList.remove('hidden')
      if (iconPause) iconPause.classList.add('hidden')
      if (albumCover) {
        albumCover.classList.remove('playing')
        albumCover.classList.add('paused')
      }
    }

    if (p.cover && albumCover) {
      albumCover.style.background = p.cover
    }

    // Timeline & Progress
    const cur = p.position || 0
    const dur = p.duration || 1
    const pct = Math.min(100, Math.max(0, (cur / dur) * 100))

    const fillEl = document.getElementById('progress-fill')
    const curEl = document.getElementById('time-current')
    const durEl = document.getElementById('time-duration')

    if (fillEl) fillEl.style.width = pct + '%'
    if (curEl) curEl.textContent = this.formatTime(cur)
    if (durEl) durEl.textContent = this.formatTime(dur)
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
        const errMsg = data.error || (typeof I18n !== 'undefined' ? I18n.t('toast_cmd_failed') : 'Control failed')
        console.warn('AVRCP Error:', data)
        if (typeof App !== 'undefined' && App.toast) {
          App.toast('AVRCP: ' + errMsg)
        }
      }
    } catch (e) {
      console.error('AVRCP request error:', e)
      if (typeof App !== 'undefined' && App.toast) {
        App.toast(typeof I18n !== 'undefined' ? I18n.t('toast_cmd_failed') : 'Failed to send control command')
      }
    }
  },

  formatTime(sec) {
    const m = Math.floor(sec / 60)
    const s = Math.floor(sec % 60)
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  },
}
