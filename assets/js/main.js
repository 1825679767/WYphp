document.addEventListener("DOMContentLoaded", () => {
  // 背景图片懒加载
  function loadBackgroundImage() {
    const img = new Image()
    img.onload = () => {
      document.body.classList.add('bg-loaded')
      console.log('背景图片加载完成')
    }
    img.onerror = () => {
      console.warn('背景图片加载失败')
    }
    img.src = 'assets/images/background.jpg'
  }

  // 延迟加载背景图片（页面加载完成后）
  window.addEventListener('load', () => {
    setTimeout(loadBackgroundImage, 100)
  })

  // 背景音乐控制
  const bgMusic = document.getElementById("bgMusic")
  const musicToggle = document.getElementById("musicToggle")
  const volumeIcon = document.getElementById("volumeIcon")
  const musicPrompt = document.getElementById("musicPrompt")
  let isMusicPlaying = false
  let musicLoaded = false

  // 懒加载音频资源
  function loadAudioIfNeeded() {
    if (!musicLoaded) {
      bgMusic.preload = "auto"
      musicLoaded = true
    }
  }

  // 尝试自动播放音乐（仅在用户交互后）
  function attemptAutoplay() {
    loadAudioIfNeeded()
    
    // 尝试自动播放
    const playPromise = bgMusic.play()

    if (playPromise !== undefined) {
      playPromise
        .then(() => {
          // 自动播放成功
          isMusicPlaying = true
          volumeIcon.className = "fas fa-volume-up"
          console.log("背景音乐播放成功")
        })
        .catch((error) => {
          // 自动播放被阻止
          console.log("背景音乐播放被阻止:", error)
          isMusicPlaying = false
          volumeIcon.className = "fas fa-volume-mute"
        })
    }
  }

  // 显示音乐提示
  function showMusicPrompt() {
    musicPrompt.classList.add("show")
    // 5秒后隐藏提示
    setTimeout(() => {
      musicPrompt.classList.remove("show")
    }, 5000)
  }

  // 首次用户交互时显示提示
  function onFirstUserInteraction() {
    showMusicPrompt()
    // 移除事件监听器，避免重复触发
    document.removeEventListener("click", onFirstUserInteraction)
    document.removeEventListener("keydown", onFirstUserInteraction)
    document.removeEventListener("touchstart", onFirstUserInteraction)
  }

  // 添加首次交互事件监听
  document.addEventListener("click", onFirstUserInteraction)
  document.addEventListener("keydown", onFirstUserInteraction)
  document.addEventListener("touchstart", onFirstUserInteraction)

  // 音乐控制函数
  function toggleMusic() {
    loadAudioIfNeeded()
    
    if (isMusicPlaying) {
      bgMusic.pause()
      volumeIcon.className = "fas fa-volume-mute"
      isMusicPlaying = false
    } else {
      // 尝试播放音乐
      const playPromise = bgMusic.play()

      // 处理可能的播放错误
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            // 播放成功
            volumeIcon.className = "fas fa-volume-up"
            isMusicPlaying = true
            // 隐藏提示
            musicPrompt.classList.remove("show")
          })
          .catch((error) => {
            // 播放被阻止
            console.log("音乐播放被阻止: ", error)
            volumeIcon.className = "fas fa-volume-mute"
            isMusicPlaying = false
          })
      }
    }
  }

  // 点击音乐按钮时切换音乐状态
  musicToggle.addEventListener("click", toggleMusic)

  // 优化菜单按钮交互
  const menuButtons = document.querySelectorAll(".menu-btn")
  let audioCache = new Map() // 缓存音频对象

  // 预加载音效（可选，仅在用户首次交互后）
  function preloadSounds() {
    const sounds = ['hover.mp3', 'click.mp3']
    sounds.forEach(sound => {
      const audio = new Audio(`assets/audio/${sound}`)
      audio.preload = "auto"
      audio.volume = 0.2
      audioCache.set(sound, audio)
    })
  }

  let soundsPreloaded = false

  menuButtons.forEach((button) => {
    button.addEventListener("mouseenter", () => {
      // 首次悬停时预加载音效
      if (!soundsPreloaded) {
        preloadSounds()
        soundsPreloaded = true
      }
      
      // 播放悬停音效（降低音量）
      playSound("hover.mp3", 0.1)
    })

    button.addEventListener("click", () => {
      // 播放点击音效
      playSound("click.mp3", 0.2)
    })
  })

  // 优化的播放音效函数
  function playSound(soundFile, volume = 0.2) {
    try {
      // 尝试使用缓存的音频对象
      let audio = audioCache.get(soundFile)
      
      if (!audio) {
        // 创建新的音频对象
        audio = new Audio(`assets/audio/${soundFile}`)
        audio.volume = volume
        audioCache.set(soundFile, audio)
      }

      // 重置播放位置
      audio.currentTime = 0
      
      // 尝试播放音效
      const playPromise = audio.play()

      if (playPromise !== undefined) {
        playPromise.catch((error) => {
          // 静默处理音效播放错误
          console.log("音效播放被阻止: ", error)
        })
      }
    } catch (error) {
      console.log("音效播放错误: ", error)
    }
  }

  // 页面加载完成标记
  const consoleContainer = document.querySelector(".console-container")
  if (consoleContainer) {
    consoleContainer.classList.add("loaded")
  }
  
  // 性能优化：减少重绘和回流
  window.addEventListener('resize', debounce(() => {
    // 处理窗口大小变化
    console.log('窗口大小已改变')
  }, 250))
  
  // 防抖函数
  function debounce(func, wait) {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  }

  // 性能监控（开发环境）
  if (window.performance && window.performance.timing) {
    window.addEventListener('load', () => {
      setTimeout(() => {
        const perfData = window.performance.timing
        const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart
        const domReadyTime = perfData.domContentLoadedEventEnd - perfData.navigationStart
        
        console.log(`页面总加载时间: ${pageLoadTime}ms`)
        console.log(`DOM就绪时间: ${domReadyTime}ms`)
      }, 0)
    })
  }
})
