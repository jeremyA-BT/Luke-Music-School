// Media Player with Audio Visualizer and Video Overlay
class MediaPlayer {
    constructor() {
        // FORCE stop any existing audio manager first
        if (window.globalAudioManager) {
            window.globalAudioManager.stopAudio();
        }
        window.globalAudioManager = this;
        
        this.currentAudio = null;
        this.audioContext = null;
        this.analyser = null;
        this.dataArray = null;
        this.currentPlayer = null;
        this.animationId = null;
        this.isPlaying = false;
        this.oscillators = null;
        this.audioSource = null;
        
        this.initAudioPlayers();
        this.initVideoPlayers();
        
        // Safety: Stop all audio when page becomes hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isPlaying) {
                console.log('Page hidden, stopping audio');
                this.stopAudio();
            }
        });
        
        // Safety: Stop all audio when page unloads
        window.addEventListener('beforeunload', () => {
            this.stopAudio();
        });
    }

    initAudioPlayers() {
        const audioItems = document.querySelectorAll('.audio-item');
        
        audioItems.forEach(item => {
            const playBtn = item.querySelector('.play-btn');
            const progressBar = item.querySelector('.progress');
            const timeDisplay = item.querySelector('.time-display');
            const canvas = item.querySelector('.visualizer-canvas');
            const audioSrc = item.dataset.audio;
            
            if (canvas) {
                canvas.width = canvas.offsetWidth || 300;
                canvas.height = canvas.offsetHeight || 100;
            }
            
            playBtn.addEventListener('click', () => {
                this.toggleAudio(item, audioSrc, canvas);
            });
            
            // Click on progress bar to seek
            const progressContainer = item.querySelector('.progress-bar');
            if (progressContainer) {
                progressContainer.addEventListener('click', (e) => {
                    if (this.currentAudio && this.currentPlayer === item) {
                        const rect = progressContainer.getBoundingClientRect();
                        const clickX = e.clientX - rect.left;
                        const width = rect.width;
                        const seekTime = (clickX / width) * this.currentAudio.duration;
                        this.currentAudio.currentTime = seekTime;
                    }
                });
            }
        });
    }

    initVideoPlayers() {
        this.videoItems = Array.from(document.querySelectorAll('.video-item'));
        this.currentVideoIndex = 0;
        
        // Initialize carousel
        this.initCarousel();
        
        const overlay = document.getElementById('videoOverlay');
        const modalVideo = overlay.querySelector('.modal-video source');
        const closeBtn = overlay.querySelector('.close-btn');
        const prevBtn = overlay.querySelector('.prev-btn');
        const nextBtn = overlay.querySelector('.next-btn');
        const currentCounter = overlay.querySelector('.current-video');
        const totalCounter = overlay.querySelector('.total-videos');
        
        // Set total videos count
        totalCounter.textContent = this.videoItems.length;
        
        // Demo video URLs
        this.demoVideos = [
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4'
        ];
        
        this.videoItems.forEach((item, index) => {
            item.addEventListener('click', () => {
                this.currentVideoIndex = index;
                this.showVideoLoading(item);
                this.openVideoOverlay();
            });
        });
        
        // Navigation buttons
        prevBtn.addEventListener('click', () => {
            this.currentVideoIndex = (this.currentVideoIndex - 1 + this.videoItems.length) % this.videoItems.length;
            this.updateVideoModal();
        });
        
        nextBtn.addEventListener('click', () => {
            this.currentVideoIndex = (this.currentVideoIndex + 1) % this.videoItems.length;
            this.updateVideoModal();
        });
        
        // Close overlay
        closeBtn.addEventListener('click', () => {
            this.closeVideoOverlay();
        });
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.closeVideoOverlay();
            }
        });
    }

    initCarousel() {
        // Set up carousel navigation
        const carouselPrev = document.querySelector('.carousel-nav.prev');
        const carouselNext = document.querySelector('.carousel-nav.next');
        const indicators = document.querySelectorAll('.carousel-indicator');
        
        if (carouselPrev) {
            carouselPrev.addEventListener('click', () => {
                this.currentVideoIndex = (this.currentVideoIndex - 1 + this.videoItems.length) % this.videoItems.length;
                this.updateCarousel('prev');
            });
        }
        
        if (carouselNext) {
            carouselNext.addEventListener('click', () => {
                this.currentVideoIndex = (this.currentVideoIndex + 1) % this.videoItems.length;
                this.updateCarousel('next');
            });
        }
        
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                const direction = index > this.currentVideoIndex ? 'next' : 'prev';
                this.currentVideoIndex = index;
                this.updateCarousel(direction);
            });
        });
        
        // Initialize carousel position
        this.updateCarousel();
    }

    updateCarousel(direction = null) {
        const indicators = document.querySelectorAll('.carousel-indicator');
        
        // Calculate which videos should be visible (always 3: left, center, right)
        const totalVideos = this.videoItems.length;
        const leftIndex = (this.currentVideoIndex - 1 + totalVideos) % totalVideos;
        const centerIndex = this.currentVideoIndex;
        const rightIndex = (this.currentVideoIndex + 1) % totalVideos;
        
        // Clear all classes first
        this.videoItems.forEach((item, index) => {
            item.classList.remove('left', 'center', 'right', 'exiting-left', 'exiting-right', 'entering-left', 'entering-right');
        });
        
        // Set ALL video positions (including hidden ones behind the wheel)
        this.videoItems.forEach((item, index) => {
            if (index === centerIndex) {
                item.classList.add('center');
            } else if (index === leftIndex) {
                item.classList.add('left');
            } else if (index === rightIndex) {
                item.classList.add('right');
            } else {
                // Position other videos behind the wheel based on their relative position
                const relativePosition = (index - centerIndex + totalVideos) % totalVideos;
                if (relativePosition <= totalVideos / 2) {
                    // Videos on the right side of the wheel (behind)
                    item.classList.add('entering-right');
                } else {
                    // Videos on the left side of the wheel (behind)
                    item.classList.add('entering-left');
                }
            }
        });
        
        // Add transition effects based on direction
        if (direction === 'next') {
            // Video exiting to the left (being scrolled away)
            const exitingIndex = (this.currentVideoIndex - 2 + totalVideos) % totalVideos;
            this.videoItems[exitingIndex].classList.remove('entering-left');
            this.videoItems[exitingIndex].classList.add('exiting-left');
        } else if (direction === 'prev') {
            // Video exiting to the right
            const exitingIndex = (this.currentVideoIndex + 2) % totalVideos;
            this.videoItems[exitingIndex].classList.remove('entering-right');
            this.videoItems[exitingIndex].classList.add('exiting-right');
        }
        
        // Update indicators
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === this.currentVideoIndex);
        });
    }

    openVideoOverlay() {
        const overlay = document.getElementById('videoOverlay');
        const modalLoading = overlay.querySelector('.video-modal-loading');
        
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Show modal loading
        modalLoading.classList.add('active');
        
        this.updateVideoModal();
    }

    updateVideoModal() {
        const overlay = document.getElementById('videoOverlay');
        const modalVideo = overlay.querySelector('.modal-video');
        const modalVideoSource = modalVideo.querySelector('source');
        const currentCounter = overlay.querySelector('.current-video');
        const modalLoading = overlay.querySelector('.video-modal-loading');
        
        // Use demo video URLs from the array
        const videoSrc = this.demoVideos[this.currentVideoIndex % this.demoVideos.length];
        
        modalVideoSource.src = videoSrc;
        currentCounter.textContent = this.currentVideoIndex + 1;
        
        // Show loading
        modalLoading.classList.add('active');
        
        // Set up loading events
        const handleLoadStart = () => {
            modalLoading.classList.add('active');
        };
        
        const handleCanPlay = () => {
            modalLoading.classList.remove('active');
            this.hideAllVideoLoading();
            // Autoplay the video
            modalVideo.play().catch(e => console.log('Autoplay prevented:', e));
            modalVideo.removeEventListener('loadstart', handleLoadStart);
            modalVideo.removeEventListener('canplaythrough', handleCanPlay);
            modalVideo.removeEventListener('error', handleError);
        };
        
        const handleError = () => {
            modalLoading.classList.remove('active');
            this.hideAllVideoLoading();
            modalVideo.removeEventListener('loadstart', handleLoadStart);
            modalVideo.removeEventListener('canplaythrough', handleCanPlay);
            modalVideo.removeEventListener('error', handleError);
        };
        
        modalVideo.addEventListener('loadstart', handleLoadStart);
        modalVideo.addEventListener('canplaythrough', handleCanPlay);
        modalVideo.addEventListener('error', handleError);
        
        modalVideo.load();
    }

    closeVideoOverlay() {
        const overlay = document.getElementById('videoOverlay');
        const video = overlay.querySelector('.modal-video');
        
        video.pause();
        video.currentTime = 0;
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    async toggleAudio(playerElement, audioSrc, canvas) {
        console.log('toggleAudio called', { currentPlayer: this.currentPlayer, isPlaying: this.isPlaying, newPlayer: playerElement });
        
        const playBtn = playerElement.querySelector('.play-btn');
        
        // Check if this is the same player that's currently playing BEFORE stopping
        const isSamePlayer = this.currentPlayer === playerElement && this.isPlaying;
        
        console.log('isSamePlayer:', isSamePlayer);
        
        // ALWAYS stop any currently playing audio first
        this.stopAudio();
        
        // If this was the same player that was playing, just stop (toggle off)
        if (isSamePlayer) {
            console.log('Same player clicked, stopping audio');
            return;
        }
        
        console.log('Starting new audio for different player');
        // Start new audio
        this.setupRealAudio(playerElement, audioSrc, canvas);
    }

    pauseAudio() {
        if (this.currentPlayer && this.isPlaying) {
            this.isPlaying = false;
            const playBtn = this.currentPlayer.querySelector('.play-btn');
            const visualizer = this.currentPlayer.querySelector('.visualizer');
            
            playBtn.innerHTML = '<i class="fas fa-play"></i>';
            
            // Pause real audio
            if (this.currentAudio) {
                this.currentAudio.pause();
            }
            
            // Stop oscillators (for demo audio fallback)
            if (this.oscillators) {
                this.oscillators.forEach(osc => {
                    try { osc.stop(); } catch(e) {}
                });
                this.oscillators = null;
            }
            
            // Stop visualization
            if (this.animationId) {
                cancelAnimationFrame(this.animationId);
                this.animationId = null;
            }
            
            // Hide visualizer
            if (visualizer) {
                visualizer.classList.remove('active');
            }
        }
    }

    async setupRealAudio(playerElement, audioSrc, canvas) {
        // FORCE stop everything first as safety measure
        this.stopAudio();
        
        this.currentPlayer = playerElement;
        const playBtn = playerElement.querySelector('.play-btn');
        const progressBar = playerElement.querySelector('.progress');
        const timeDisplay = playerElement.querySelector('.time-display');
        const audioPlayer = playerElement.querySelector('.audio-player');
        
        // Show loading state
        if (audioPlayer) {
            this.showAudioLoading(audioPlayer, playBtn);
        }
        
        try {
            // Create completely fresh audio element
            this.currentAudio = new Audio(audioSrc);
            this.currentAudio.crossOrigin = 'anonymous';
            this.currentAudio.preload = 'auto';
            
            // Set up audio event listeners
            this.currentAudio.addEventListener('loadedmetadata', () => {
                if (this.currentPlayer === playerElement) { // Only update if still current
                    if (audioPlayer) {
                        this.hideAudioLoading(audioPlayer, playBtn);
                    }
                    this.updateTimeDisplay(timeDisplay);
                }
            });
            
            this.currentAudio.addEventListener('canplaythrough', () => {
                if (this.currentPlayer === playerElement && audioPlayer) {
                    this.hideAudioLoading(audioPlayer, playBtn);
                }
            });
            
            this.currentAudio.addEventListener('timeupdate', () => {
                if (this.currentPlayer === playerElement) { // Only update if still current
                    this.updateProgress(progressBar, timeDisplay);
                }
            });
            
            this.currentAudio.addEventListener('ended', () => {
                if (this.currentPlayer === playerElement) { // Only stop if still current
                    this.stopAudio();
                }
            });
            
            this.currentAudio.addEventListener('error', (e) => {
                console.warn('Audio failed to load, falling back to demo audio:', e);
                if (this.currentPlayer === playerElement) { // Only fallback if still current
                    if (audioPlayer) {
                        this.hideAudioLoading(audioPlayer, playBtn);
                    }
                    this.setupDemoAudio(playerElement, canvas);
                }
            });
            
            // Set up audio context for visualization
            await this.setupAudioVisualization(canvas);
            
            // Double check we're still the current player before playing
            if (this.currentPlayer === playerElement) {
                await this.currentAudio.play();
                this.isPlaying = true;
                if (audioPlayer) {
                    this.hideAudioLoading(audioPlayer, playBtn);
                    audioPlayer.classList.add('playing');
                }
                playBtn.innerHTML = '<i class="fas fa-pause"></i>';
            }
            
        } catch (error) {
            console.warn('Real audio failed, falling back to demo audio:', error);
            if (this.currentPlayer === playerElement) {
                if (audioPlayer) {
                    this.hideAudioLoading(audioPlayer, playBtn);
                }
                this.setupDemoAudio(playerElement, canvas);
            }
        }
    }

    setupDemoAudio(playerElement, canvas) {
        // FORCE stop everything first as safety measure
        this.stopAudio();
        
        this.currentPlayer = playerElement;
        this.isPlaying = true;
        const playBtn = playerElement.querySelector('.play-btn');
        const progressBar = playerElement.querySelector('.progress');
        const timeDisplay = playerElement.querySelector('.time-display');
        const audioPlayer = playerElement.querySelector('.audio-player');
        
        // Create a simple demo audio context with oscillator
        this.createDemoAudioWithVisualizer(canvas);
        
        playBtn.innerHTML = '<i class="fas fa-pause"></i>';
        if (audioPlayer) {
            audioPlayer.classList.add('playing');
        }
        
        // Simulate progress for demo
        this.simulateProgress(progressBar, timeDisplay);
    }

    async setupAudioVisualization(canvas) {
        if (!canvas || !this.currentAudio) return;
        
        try {
            // Close existing audio context if it exists
            if (this.audioContext) {
                this.audioContext.close();
                this.audioContext = null;
            }
            
            // Create fresh audio context for visualization
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.analyser = this.audioContext.createAnalyser();
            
            // Create media source from audio element
            this.audioSource = this.audioContext.createMediaElementSource(this.currentAudio);
            this.audioSource.connect(this.analyser);
            this.analyser.connect(this.audioContext.destination);
            
            this.analyser.fftSize = 256;
            const bufferLength = this.analyser.frequencyBinCount;
            this.dataArray = new Uint8Array(bufferLength);
            
            // Show visualizer with animation
            const visualizer = canvas.closest('.visualizer');
            visualizer.classList.add('active');
            
            // Scroll to ensure visualizer is in view
            this.scrollToVisualizer(visualizer);
            
            this.startVisualization(canvas);
            
        } catch (error) {
            console.warn('Audio visualization setup failed:', error);
        }
    }

    updateProgress(progressBar, timeDisplay) {
        if (!this.currentAudio) return;
        
        const progress = (this.currentAudio.currentTime / this.currentAudio.duration) * 100;
        progressBar.style.width = `${progress}%`;
        
        this.updateTimeDisplay(timeDisplay);
    }

    updateTimeDisplay(timeDisplay) {
        if (!this.currentAudio) return;
        
        const current = this.formatTime(this.currentAudio.currentTime || 0);
        const duration = this.formatTime(this.currentAudio.duration || 0);
        timeDisplay.textContent = `${current} / ${duration}`;
    }

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    createDemoAudioWithVisualizer(canvas) {
        if (!canvas) return;
        
        // Create audio context for visualization
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        this.analyser = this.audioContext.createAnalyser();
        
        // Create multiple oscillators for a richer sound
        this.oscillators = [];
        this.gainNodes = [];
        
        // Create a simple chord progression (C major)
        const frequencies = [261.63, 329.63, 392]; // C4, E4, G4
        const masterGain = this.audioContext.createGain();
        masterGain.gain.setValueAtTime(0.05, this.audioContext.currentTime);
        
        frequencies.forEach((freq, index) => {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.type = index === 0 ? 'sine' : (index === 1 ? 'triangle' : 'sawtooth');
            oscillator.frequency.setValueAtTime(freq, this.audioContext.currentTime);
            gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
            
            // Add some variation
            const lfo = this.audioContext.createOscillator();
            const lfoGain = this.audioContext.createGain();
            lfo.frequency.setValueAtTime(0.5 + index * 0.2, this.audioContext.currentTime);
            lfo.type = 'sine';
            lfoGain.gain.setValueAtTime(10, this.audioContext.currentTime);
            
            lfo.connect(lfoGain);
            lfoGain.connect(oscillator.frequency);
            
            oscillator.connect(gainNode);
            gainNode.connect(masterGain);
            
            this.oscillators.push(oscillator);
            this.gainNodes.push(gainNode);
            
            oscillator.start();
            lfo.start();
        });
        
        masterGain.connect(this.analyser);
        this.analyser.connect(this.audioContext.destination);
        
        this.analyser.fftSize = 256;
        const bufferLength = this.analyser.frequencyBinCount;
        this.dataArray = new Uint8Array(bufferLength);
        
        // Show visualizer with animation
        const visualizer = canvas.closest('.visualizer');
        visualizer.classList.add('active');
        
        // Scroll to ensure visualizer is in view
        this.scrollToVisualizer(visualizer);
        
        this.startVisualization(canvas);
    }

    startVisualization(canvas) {
        if (!canvas || !this.analyser) return;
        
        const ctx = canvas.getContext('2d');
        const WIDTH = canvas.width;
        const HEIGHT = canvas.height;
        
        const draw = () => {
            this.animationId = requestAnimationFrame(draw);
            
            this.analyser.getByteFrequencyData(this.dataArray);
            
            ctx.fillStyle = 'rgba(26, 26, 26, 0.3)';
            ctx.fillRect(0, 0, WIDTH, HEIGHT);
            
            const barWidth = (WIDTH / this.dataArray.length) * 2.5;
            let barHeight;
            let x = 0;
            
            for (let i = 0; i < this.dataArray.length; i++) {
                barHeight = (this.dataArray[i] / 255) * HEIGHT * 0.8;
                
                // Create gradient for bars
                const gradient = ctx.createLinearGradient(0, HEIGHT - barHeight, 0, HEIGHT);
                gradient.addColorStop(0, '#d4af37');
                gradient.addColorStop(0.5, '#ff7f39');
                gradient.addColorStop(1, '#3498db');
                
                ctx.fillStyle = gradient;
                ctx.fillRect(x, HEIGHT - barHeight, barWidth, barHeight);
                
                x += barWidth + 1;
            }
        };
        
        draw();
    }

    simulateProgress(progressBar, timeDisplay) {
        let currentTime = 0;
        const duration = 30; // 30 second demo
        
        const updateProgress = () => {
            if (this.currentPlayer && currentTime < duration) {
                currentTime += 0.1;
                const progress = (currentTime / duration) * 100;
                progressBar.style.width = `${progress}%`;
                
                const currentMinutes = Math.floor(currentTime / 60);
                const currentSeconds = Math.floor(currentTime % 60);
                const totalMinutes = Math.floor(duration / 60);
                const totalSeconds = duration % 60;
                
                timeDisplay.textContent = 
                    `${currentMinutes}:${currentSeconds.toString().padStart(2, '0')} / ${totalMinutes}:${totalSeconds.toString().padStart(2, '0')}`;
                
                setTimeout(updateProgress, 100);
            } else if (currentTime >= duration) {
                this.stopAudio();
            }
        };
        
        updateProgress();
    }

    stopAudio() {
        console.log('stopAudio called');
        
        // Reset playing state first
        this.isPlaying = false;
        
        // NUCLEAR OPTION: Stop ALL audio elements on the page
        document.querySelectorAll('audio').forEach(audio => {
            audio.pause();
            audio.currentTime = 0;
            audio.src = '';
        });
        
        // Stop and cleanup our managed audio
        if (this.currentAudio) {
            this.currentAudio.pause();
            this.currentAudio.currentTime = 0;
            this.currentAudio.src = '';
            this.currentAudio = null;
        }
        
        // Stop oscillators (for demo audio fallback)
        if (this.oscillators) {
            this.oscillators.forEach(osc => {
                try { osc.stop(); } catch(e) {}
            });
            this.oscillators = null;
        }
        
        // Close audio context
        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }
        
        // Reset audio source
        this.audioSource = null;
        
        // Cancel animation frame
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        
        // Update UI for current player
        if (this.currentPlayer) {
            const playBtn = this.currentPlayer.querySelector('.play-btn');
            const progressBar = this.currentPlayer.querySelector('.progress');
            const timeDisplay = this.currentPlayer.querySelector('.time-display');
            const visualizer = this.currentPlayer.querySelector('.visualizer');
            
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>';
            if (progressBar) progressBar.style.width = '0%';
            if (timeDisplay) timeDisplay.textContent = '0:00 / 0:00';
            
            // Hide visualizer with animation
            if (visualizer) {
                visualizer.classList.remove('active');
            }
        }
        
        // Reset all UI states for all players (safety cleanup)
        const allPlayers = document.querySelectorAll('.audio-player');
        allPlayers.forEach(player => {
            const playBtn = player.querySelector('.play-btn');
            const progressBar = player.querySelector('.progress');
            const timeDisplay = player.querySelector('.time-display');
            const visualizer = player.querySelector('.visualizer');
            
            player.classList.remove('playing');
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>';
            if (progressBar) progressBar.style.width = '0%';
            if (timeDisplay) timeDisplay.textContent = '0:00 / 0:00';
            if (visualizer) visualizer.classList.remove('active');
        });
        
        // Hide all loading states
        this.hideAllAudioLoading();
        
        this.currentPlayer = null;
        console.log('stopAudio completed');
    }

    showAudioLoading(audioPlayer, playBtn) {
        if (audioPlayer) {
            audioPlayer.classList.add('loading');
        }
        if (playBtn) {
            playBtn.classList.add('loading');
        }
        // Don't change innerHTML, let CSS handle the spinner visibility
    }

    hideAudioLoading(audioPlayer, playBtn) {
        if (audioPlayer) {
            audioPlayer.classList.remove('loading');
        }
        if (playBtn) {
            playBtn.classList.remove('loading');
        }
        // Don't change button text here - let the calling function handle it
    }

    hideAllAudioLoading() {
        const allAudioPlayers = document.querySelectorAll('.audio-player');
        const allPlayBtns = document.querySelectorAll('.play-btn');
        
        allAudioPlayers.forEach(player => {
            player.classList.remove('loading');
        });
        
        allPlayBtns.forEach(btn => {
            btn.classList.remove('loading');
            // Reset button to play state if it's not currently playing
            const parentPlayer = btn.closest('.audio-player');
            if (parentPlayer && !parentPlayer.classList.contains('playing')) {
                btn.innerHTML = '<i class="fas fa-play"></i>';
            }
        });
    }

    showVideoLoading(videoItem) {
        const thumbnail = videoItem.querySelector('.video-thumbnail');
        if (thumbnail) {
            thumbnail.classList.add('loading');
        }
    }

    hideVideoLoading(videoItem) {
        const thumbnail = videoItem.querySelector('.video-thumbnail');
        if (thumbnail) {
            thumbnail.classList.remove('loading');
        }
    }

    hideAllVideoLoading() {
        const allVideoThumbnails = document.querySelectorAll('.video-thumbnail');
        allVideoThumbnails.forEach(thumbnail => {
            thumbnail.classList.remove('loading');
        });
    }

    pauseVisualization() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
    }

    resumeVisualization() {
        if (this.currentPlayer) {
            const canvas = this.currentPlayer.querySelector('.visualizer-canvas');
            this.startVisualization(canvas);
        }
    }

    scrollToVisualizer(visualizer) {
        if (!visualizer) return;
        
        // Get the audio section container for proper scrolling context
        const audioSection = visualizer.closest('.audio-section');
        const audioItems = audioSection?.querySelector('.audio-items');
        
        if (audioItems) {
            // Check if visualizer is already visible
            const visualizerRect = visualizer.getBoundingClientRect();
            const containerRect = audioItems.getBoundingClientRect();
            
            // Add some buffer for "visible enough"
            const buffer = 50; // pixels
            const isVisibleTop = visualizerRect.top >= (containerRect.top - buffer);
            const isVisibleBottom = visualizerRect.bottom <= (containerRect.bottom + buffer);
            const isFullyVisible = isVisibleTop && isVisibleBottom;
            
            // Only scroll if visualizer is not visible enough
            if (!isFullyVisible) {
                const currentScrollTop = audioItems.scrollTop;
                let newScrollTop = currentScrollTop;
                
                // If visualizer top is above the container top, scroll up just enough
                if (visualizerRect.top < containerRect.top) {
                    const diff = containerRect.top - visualizerRect.top;
                    newScrollTop = currentScrollTop - diff - 20; // 20px padding
                }
                // If visualizer bottom is below container bottom, scroll down just enough
                else if (visualizerRect.bottom > containerRect.bottom) {
                    const diff = visualizerRect.bottom - containerRect.bottom;
                    newScrollTop = currentScrollTop + diff + 20; // 20px padding
                }
                
                // Ensure we don't scroll beyond bounds
                newScrollTop = Math.max(0, Math.min(newScrollTop, audioItems.scrollHeight - audioItems.clientHeight));
                
                // Only scroll if there's actually a change
                if (Math.abs(newScrollTop - currentScrollTop) > 5) {
                    audioItems.scrollTo({
                        top: newScrollTop,
                        behavior: 'smooth'
                    });
                }
            }
        }
    }
}

// Initialize media player when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MediaPlayer();
});