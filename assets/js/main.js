document.addEventListener("DOMContentLoaded", () => {
  // 背景音乐控制
  const bgMusic = document.getElementById("bgMusic")
  const musicToggle = document.getElementById("musicToggle")
  const volumeIcon = document.getElementById("volumeIcon")
  const musicPrompt = document.getElementById("musicPrompt")
  let isMusicPlaying = false

  // 尝试自动播放音乐
  function attemptAutoplay() {
    // 尝试自动播放
    const playPromise = bgMusic.play()

    if (playPromise !== undefined) {
      playPromise
        .then(() => {
          // 自动播放成功
          isMusicPlaying = true
          volumeIcon.className = "fas fa-volume-up"
          console.log("背景音乐自动播放成功")
        })
        .catch((error) => {
          // 自动播放被阻止
          console.log("背景音乐自动播放被阻止:", error)
          // 显示提示
          musicPrompt.classList.add("show")

          // 5秒后隐藏提示
          setTimeout(() => {
            musicPrompt.classList.remove("show")
          }, 5000)
        })
    }
  }

  // 页面加载后尝试自动播放
  attemptAutoplay()

  // 用户与页面交互后再次尝试播放
  function onUserInteraction() {
    if (!isMusicPlaying) {
      attemptAutoplay()
    }
    // 移除事件监听器，避免重复触发
    document.removeEventListener("click", onUserInteraction)
    document.removeEventListener("keydown", onUserInteraction)
    document.removeEventListener("touchstart", onUserInteraction)
  }

  // 添加用户交互事件监听
  document.addEventListener("click", onUserInteraction)
  document.addEventListener("keydown", onUserInteraction)
  document.addEventListener("touchstart", onUserInteraction)

  // 音乐控制函数
  function toggleMusic() {
    if (isMusicPlaying) {
      bgMusic.pause()
      volumeIcon.className = "fas fa-volume-mute"
    } else {
      // 尝试播放音乐
      const playPromise = bgMusic.play()

      // 处理可能的播放错误
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            // 播放成功
            volumeIcon.className = "fas fa-volume-up"
            // 隐藏提示
            musicPrompt.classList.remove("show")
          })
          .catch((error) => {
            // 播放被阻止
            console.log("音乐播放被阻止: ", error)
            volumeIcon.className = "fas fa-volume-mute"
            isMusicPlaying = false
            return
          })
      }
      volumeIcon.className = "fas fa-volume-up"
    }
    isMusicPlaying = !isMusicPlaying
  }

  // 点击音乐按钮时切换音乐状态
  musicToggle.addEventListener("click", toggleMusic)

  // 添加菜单按钮的悬停音效
  const menuButtons = document.querySelectorAll(".menu-btn")

  menuButtons.forEach((button) => {
    button.addEventListener("mouseenter", () => {
      // 播放悬停音效
      playSound("hover.mp3", 0.2)
    })

    button.addEventListener("click", () => {
      // 播放点击音效
      playSound("click.mp3", 0.3)
    })
  })

  // 播放音效的函数
  function playSound(soundFile, volume = 0.3) {
    // 创建一个新的音频对象
    const audio = new Audio(`assets/audio/${soundFile}`)
    audio.volume = volume

    // 尝试播放音效
    const playPromise = audio.play()

    // 处理可能的播放错误
    if (playPromise !== undefined) {
      playPromise.catch((error) => {
        console.log("音效播放被阻止: ", error)
      })
    }
  }

  // 添加简单的加载动画
  const consoleContainer = document.querySelector(".console-container")
  consoleContainer.classList.add("loaded")
})
