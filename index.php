<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dhama Podcast</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <div class="logo">
                    <span class="logo-icon">
                        <img src="assets/img/shwe-da-gone.jpg" alt="Dhama Podcast" 
                        style="width: 40px; height: 40px;
                        border-radius: 50%;">
                    </span>
                    <span class="logo-text">Dhama Podcast</span>
                </div>
                <div class="nav-links">
                    <a href="#home" class="nav-link active" data-section="home">မူလစာမျက်နှာ</a>
                    <a href="#player" class="nav-link" data-section="player">ဖွင့်ရန်</a>
                    <a href="#artists" class="nav-link" data-section="artists">ဆရာတော်ကြီးများ</a>
                    <a href="#songs" class="nav-link" data-section="songs">တရားများ</a>
                </div>
                <div class="nav-search">
                    <input type="text" id="search-input" placeholder="ရှာဖွေမည်..." class="search-input">
                    <button class="search-btn" id="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Home Section -->
        <section id="home" class="section active">
            <div class="container">
                <!-- Hero Section -->
                <div class="hero">
                    <h1 class="hero-title">Welcome to Dhama Podcast</h1>
                    <p class="hero-subtitle">ကိုယ်စိတ်နှစ်ဖြာ ရွှင်လန်းချမ်းမြေ့ကပါစေ</p>
                </div>

                <!-- Featured Songs -->
                <div class="section-header">
                    <h2 class="section-title">အကြိုက်ဆုံး</h2>
                    <p class="section-subtitle">အကြိုက်ဆုံး တရားများ</p>
                </div>
                <div class="songs-grid" id="featured-songs">
                    <div class="loading">အကြိုက်ဆုံး တရားများ ဖွင့်နေသည်...</div>
                </div>

                <!-- Recent Songs -->
                <div class="section-header">
                    <h2 class="section-title">နောက်ဆုံး</h2>
                    <p class="section-subtitle">မကြာသေးမီ ထည့်သွင်းထားသော တရားများ</p>
                </div>
                <div class="songs-grid" id="latest-songs">
                    <div class="loading">နောက်ဆုံး တရားများ ဖွင့်နေသည်...</div>
                </div>
            </div>
        </section>

        <!-- Artists Section -->
        <section id="artists" class="section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">ဆရာတော်ကြီးများ</h2>
                    <p class="section-subtitle">ဗုဒံ ဓမ္မံ သံဃံ </p>
                </div>
                <div class="artists-grid" id="artists-container">
                    <div class="loading">ရှာဖွေနေသည်..</div>
                </div>
            </div>
        </section>

        <!-- Songs Section -->
        <section id="songs" class="section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">တရားများ</h2>
                    <p class="section-subtitle">ဗုဒံ ဓမ္မံ သံဃံ </p>
                </div>
                <div class="songs-grid" id="all-songs">
                    <div class="loading">ရှာဖွေနေသည်..</div>
                </div>
                <div class="load-more-container" id="load-more-container" style="display: none;">
                    <button class="btn-load-more" id="load-more-btn">ပိုမို ဖွင့်ရန်</button>
                </div>
            </div>
        </section>

        <!-- Player Page Section -->
        <section id="player" class="section">
            <div class="container">
                <div class="player-page-layout">
                    <!-- Left Side: Artist List -->
                    <div class="player-page-left">
                        <div class="player-page-card">
                            <div class="player-page-header">
                                <h3 class="player-page-subtitle">ဆရာတော်ကြီးများ</h3>
                                <button class="player-page-toggle-btn" id="artist-card-toggle" title="Collapse/Expand">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                            <div class="player-page-artists collapsed" id="player-page-artists">
                                <div class="loading">ရှာဖွေနေသည်..</div>
                            </div>
                            <div class="player-page-artists-pagination" id="player-page-artists-pagination" style="display: none;">
                                <button class="player-page-pagination-btn" id="artist-prev-btn" disabled>
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span class="player-page-pagination-info" id="artist-pagination-info">1 / 1</span>
                                <button class="player-page-pagination-btn" id="artist-next-btn" disabled>
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Player and Playlist -->
                    <div class="player-page-right">
                        <!-- Player Card -->
                        <div class="player-page-card">
                            <div class="player-page-header">
                                <h2 class="player-page-title">ဖွင့်ရန်</h2>
                            </div>
                            <div class="player-page-player">
                                <div class="player-page-cover" id="player-page-cover">
                                    <span class="cover-placeholder">🎵</span>
                                </div>
                                <div class="player-page-info">
                                    <div class="player-page-song-title" id="player-page-title">တရားရွေးချယ်ထားခြင်းမရှိပါ</div>
                                    <div class="player-page-song-artist" id="player-page-artist">—</div>
                                    <button class="player-page-download-btn" id="player-page-download-btn" title="Download" style="display: none;">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                                <div class="player-page-controls">
                                    <button class="player-page-btn" id="player-page-prev-btn">⏮</button>
                                    <button class="player-page-btn player-page-btn-play" id="player-page-play-pause-btn">▶</button>
                                    <button class="player-page-btn" id="player-page-next-btn">⏭</button>
                                </div>
                                <div class="player-page-progress">
                                    <div class="player-page-progress-bar">
                                        <div class="player-page-progress-fill" id="player-page-progress-fill"></div>
                                        <input type="range" class="player-page-progress-slider" id="player-page-progress-slider" min="0" max="100" value="0">
                                    </div>
                                    <div class="player-page-progress-time">
                                        <span id="player-page-current-time">0:00</span>
                                        <span id="player-page-total-time">0:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Playlist -->
                        <div class="player-page-card">
                            <div class="player-page-header">
                                <h3 class="player-page-subtitle">Playlist</h3>
                            </div>
                            <div class="playlist-container" id="playlist-container">
                                <div class="playlist-empty">Playlist ထဲမှာ တရားမရှိသေးပါ</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Audio Player -->
    <div class="audio-player" id="audio-player">
        <div class="player-content">
            <div class="player-info">
                <div class="player-cover" id="player-cover">
                    <span class="cover-placeholder">🎵</span>
                </div>
                <div class="player-details">
                    <div class="player-title" id="player-title">No selected</div>
                    <div class="player-artist" id="player-artist">—</div>
                </div>
            </div>
            <div class="player-controls">
                <button class="player-btn" id="prev-btn">⏮</button>
                <button class="player-btn player-btn-play" id="play-pause-btn">▶</button>
                <button class="player-btn" id="next-btn">⏭</button>
                <button class="player-download-btn" id="player-download-btn" title="Download" style="display: none;">
                    <i class="fas fa-download"></i>
                </button>
            </div>
            <div class="player-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                    <input type="range" class="progress-slider" id="progress-slider" min="0" max="100" value="0">
                </div>
                <div class="progress-time">
                    <span id="current-time">0:00</span>
                    <span id="total-time">0:00</span>
                </div>
            </div>
            <audio id="audio-element" preload="metadata"></audio>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
</body>
</html>
