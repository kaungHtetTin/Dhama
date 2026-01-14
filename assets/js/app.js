/**
 * Dhama Podcast - Client Side JavaScript
 * Handles API integration, navigation, and audio player
 */

// Detect base path - handle both root and subdirectory
const getBasePath = () => {
    const path = window.location.pathname;
    // Remove index.php if present
    let basePath = path.replace(/\/index\.php$/, '');
    // Ensure it ends with /
    if (!basePath.endsWith('/')) {
        basePath += '/';
    }
    // If basePath is just '/', check if we're in a subdirectory
    if (basePath === '/') {
        const host = window.location.host;
        const pathname = window.location.pathname;
        // Check if pathname contains 'dhama'
        if (pathname.includes('/dhama')) {
            return '/dhama/';
        }
    }
    return "http://localhost"+basePath;
};

const API_BASE = getBasePath() + 'api/';
console.log('API Base URL:', API_BASE);
console.log('Current pathname:', window.location.pathname);

// State management
let currentSongs = [];
let currentSongIndex = -1;
let isPlaying = false;
let allArtists = [];
let allSongs = [];
let currentOffset = 0;
const SONGS_PER_PAGE = 20;
let playlist = []; // Playlist for player page
let playerPageArtists = []; // Artists for player page
let playerPageArtistsPage = 1;
let playerPageArtistsPerPage = 10;
let playerPageArtistsTotal = 0;

// DOM Elements
const audioElement = document.getElementById('audio-element');
const audioPlayer = document.getElementById('audio-player');
const playPauseBtn = document.getElementById('play-pause-btn');
const prevBtn = document.getElementById('prev-btn');
const nextBtn = document.getElementById('next-btn');
const progressSlider = document.getElementById('progress-slider');
const progressFill = document.getElementById('progress-fill');
const currentTimeEl = document.getElementById('current-time');
const totalTimeEl = document.getElementById('total-time');
const playerTitle = document.getElementById('player-title');
const playerArtist = document.getElementById('player-artist');
const playerCover = document.getElementById('player-cover');
const searchInput = document.getElementById('search-input');
const searchBtn = document.getElementById('search-btn');

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initAudioPlayer();
    initSearch();
    loadHomePage();
});

// ============================================
// Navigation
// ============================================

function initNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const section = link.getAttribute('data-section');
            showSection(section);
            
            // Update active nav link
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        });
    });
}

function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show/hide bottom audio player based on section
    if (sectionName === 'player') {
        // Completely hide bottom player on player page
        audioPlayer.classList.remove('active');
        audioPlayer.style.display = 'none';
    } else {
        // Show bottom player on other pages if a song is playing
        audioPlayer.style.display = 'block';
        if (typeof playlist !== 'undefined' && playlist && playlist.length > 0 && currentSongIndex >= 0) {
            audioPlayer.classList.add('active');
        } else {
            audioPlayer.classList.remove('active');
        }
    }
    
    // Show selected section
    const section = document.getElementById(sectionName);
    if (section) {
        section.classList.add('active');
        
        // Load section data
        switch(sectionName) {
            case 'home':
                loadHomePage();
                break;
            case 'player':
                loadPlayerPage();
                break;
            case 'artists':
                loadArtists();
                break;
            case 'songs':
                loadAllSongs();
                break;
        }
    }
}

// ============================================
// API Functions
// ============================================

async function fetchAPI(endpoint, params = {}) {
    try {
        // Build query string from params
        const queryString = new URLSearchParams(params).toString();
        const url = `${API_BASE}${endpoint}${queryString ? '?' + queryString : ''}`;
        console.log('Fetching:', url); // Debug log
        const response = await fetch(url);
        if (!response.ok) {
            console.error(`API Error: ${response.status} - ${response.statusText}`, url);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API Error:', error, 'Endpoint:', endpoint);
        // Show user-friendly error message
        if (error.message.includes('404')) {
            console.error('API endpoint not found. Please check if the API is accessible at:', API_BASE + endpoint);
        }
        return null;
    }
}

// Store songs for each section
let featuredSongsList = [];
let latestSongsList = [];

async function loadHomePage() {
    // Load featured songs
    const featured = await fetchAPI('featured.php', { limit: 8 });
    if (featured && featured.songs) {
        featuredSongsList = featured.songs;
        displaySongs(featured.songs, 'featured-songs');
    } else {
        document.getElementById('featured-songs').innerHTML = '<div class="loading" style="color: #ff6b6b;">အကြိုက်ဆုံး တရားများ ဖွင့်၍မရပါ။ API ကို စစ်ဆေးပါ။</div>';
    }
    
    // Load latest songs
    const latest = await fetchAPI('songs.php', { limit: 8, offset: 0 });
    if (latest && latest.songs) {
        latestSongsList = latest.songs;
        displaySongs(latest.songs, 'latest-songs');
    } else {
        document.getElementById('latest-songs').innerHTML = '<div class="loading" style="color: #ff6b6b;">နောက်ဆုံး တရားများ ဖွင့်၍မရပါ။ API ကို စစ်ဆေးပါ။</div>';
    }
}

async function loadArtists() {
    const container = document.getElementById('artists-container');
    container.innerHTML = '<div class="loading">ရှာဖွေနေသည်..</div>';
    
    const data = await fetchAPI('artists.php');
    if (data && data.artists) {
        allArtists = data.artists;
        displayArtists(data.artists);
    } else {
        container.innerHTML = '<div class="loading">ရှာမတွေ့ပါ</div>';
    }
}

async function loadPlayerPage(resetPage = true) {
    // Initialize player page controls if not already done
    if (!playerPagePlayPauseBtn) {
        initPlayerPageControls();
    } else {
        // Re-initialize to ensure toggle button works
        initPlayerPageControls();
    }
    
    // Reset to first page if needed
    if (resetPage) {
        playerPageArtistsPage = 1;
    }
    
    // Load artists for right side with pagination
    const container = document.getElementById('player-page-artists');
    if (container) {
        container.innerHTML = '<div class="loading">ရှာဖွေနေသည်..</div>';
        
        const offset = (playerPageArtistsPage - 1) * playerPageArtistsPerPage;
        const data = await fetchAPI('artists.php', { 
            limit: playerPageArtistsPerPage, 
            offset: offset 
        });
        
        if (data && data.artists) {
            playerPageArtists = data.artists;
            playerPageArtistsTotal = data.total || data.artists.length;
            displayPlayerPageArtists(data.artists);
            updatePlayerPageArtistsPagination();
        } else {
            container.innerHTML = '<div class="loading">ရှာမတွေ့ပါ</div>';
        }
    }
    
    // Update player page player if a song is playing
    updatePlayerPagePlayer();
    
    // Update playlist
    updatePlaylist();
}

function displayPlayerPageArtists(artists) {
    const container = document.getElementById('player-page-artists');
    
    if (artists.length === 0) {
        container.innerHTML = '<div class="loading">ရှာမတွေ့ပါ</div>';
        return;
    }
    
    container.innerHTML = artists.map(artist => `
        <div class="player-page-artist-item" onclick="loadArtistSongsToPlaylist(${artist.id})">
            <div class="player-page-artist-image">
                <img src="${artist.image_url || 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><rect fill=%22%23333%22 width=%2260%22 height=%2260%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>👤</text></svg>'}" 
                     alt="${artist.name}"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><rect fill=%22%23333%22 width=%2260%22 height=%2260%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>👤</text></svg>'">
            </div>
            <div class="player-page-artist-info">
                <div class="player-page-artist-name">${escapeHtml(artist.name)}</div>
                <div class="player-page-artist-songs">${artist.songs_count || 0} တရား</div>
            </div>
        </div>
    `).join('');
}

function updatePlayerPageArtistsPagination() {
    const prevBtn = document.getElementById('artist-prev-btn');
    const nextBtn = document.getElementById('artist-next-btn');
    const infoEl = document.getElementById('artist-pagination-info');
    const paginationContainer = document.getElementById('player-page-artists-pagination');
    
    if (!prevBtn || !nextBtn || !infoEl || !paginationContainer) return;
    
    const totalPages = Math.ceil(playerPageArtistsTotal / playerPageArtistsPerPage);
    
    // Show pagination if there are multiple pages
    if (totalPages > 1) {
        paginationContainer.style.display = 'flex';
    } else {
        paginationContainer.style.display = 'none';
    }
    
    // Update info
    infoEl.textContent = `${playerPageArtistsPage} / ${totalPages}`;
    
    // Update button states
    prevBtn.disabled = playerPageArtistsPage <= 1;
    nextBtn.disabled = playerPageArtistsPage >= totalPages;
}

function loadPlayerPageArtistsPrev() {
    if (playerPageArtistsPage > 1) {
        playerPageArtistsPage--;
        loadPlayerPage(false);
    }
}

function loadPlayerPageArtistsNext() {
    const totalPages = Math.ceil(playerPageArtistsTotal / playerPageArtistsPerPage);
    if (playerPageArtistsPage < totalPages) {
        playerPageArtistsPage++;
        loadPlayerPage(false);
    }
}

async function loadArtistSongsToPlaylist(artistId) {
    // Switch to player page
    showSection('player');
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector('[data-section="player"]').classList.add('active');
    
    const data = await fetchAPI('songs.php', { artist_id: artistId, limit: 100 });
    
    if (data && data.songs && data.songs.length > 0) {
        playlist = data.songs;
        currentSongs = data.songs;
        currentSongIndex = 0;
        updatePlaylist();
        playSongFromPlaylist(0);
    }
}

function updatePlaylist() {
    const container = document.getElementById('playlist-container');
    if (!container) return;
    
    if (!playlist || playlist.length === 0) {
        container.innerHTML = '<div class="playlist-empty">Playlist ထဲမှာ တရားမရှိသေးပါ</div>';
        return;
    }
    
    container.innerHTML = playlist.map((song, index) => `
        <div class="playlist-item ${Number(index) === Number(currentSongIndex) ? 'active' : ''}" onclick="playSongFromPlaylist(${index})">
            <div class="playlist-item-cover">
                <img src="${song.cover_image_url || song.artist_image || 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22><rect fill=%22%23333%22 width=%2250%22 height=%2250%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>🎵</text></svg>'}" 
                     alt="${song.title}"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22><rect fill=%22%23333%22 width=%2250%22 height=%2250%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>🎵</text></svg>'">
            </div>
            <div class="playlist-item-info">
                <div class="playlist-item-title">${escapeHtml(song.title)}</div>
                <div class="playlist-item-artist">${escapeHtml(song.artist_name || 'Unknown Artist')}</div>
            </div>
            <div class="playlist-item-actions">
                <button class="playlist-item-btn" onclick="event.stopPropagation(); downloadSong('${escapeHtml(song.audio_url)}', '${escapeHtml(song.title)}', '${escapeHtml(song.artist_name || 'Unknown Artist')}')" title="Download">
                    <i class="fas fa-download"></i>
                </button>
                <button class="playlist-item-btn" onclick="event.stopPropagation(); removeFromPlaylist(${index})" title="Remove">×</button>
            </div>
        </div>
    `).join('');
}

function playSongFromPlaylist(index) {
    if (!playlist || index < 0 || index >= playlist.length) return;
    
    currentSongIndex = index;
    const song = playlist[index];
    
    // Update playlist to show active indicator
    updatePlaylist();
    
    // Update both players
    updatePlayerPagePlayer();
    updateMainPlayer();
    
    // Play the song
    if (!song.audio_url) {
        alert('Audio file not available');
        return;
    }
    
    audioElement.src = song.audio_url;
    audioElement.load();
    audioElement.play().catch(err => {
        console.error('Error playing audio:', err);
    });
    
    // Show bottom player only if not on player page
    const currentSection = document.querySelector('.section.active');
    if (currentSection && currentSection.id !== 'player') {
        audioPlayer.style.display = 'block';
        audioPlayer.classList.add('active');
    } else {
        audioPlayer.style.display = 'none';
        audioPlayer.classList.remove('active');
    }
    
    // Increment play count
    fetchAPI('songs.php', { id: song.id });
}

function updatePlayerPagePlayer() {
    if (currentSongIndex >= 0 && playlist && playlist[currentSongIndex]) {
        const song = playlist[currentSongIndex];
        
        const titleEl = document.getElementById('player-page-title');
        const artistEl = document.getElementById('player-page-artist');
        const downloadBtn = document.getElementById('player-page-download-btn');
        
        if (titleEl) titleEl.textContent = song.title || 'တရားရွေးချယ်ထားခြင်းမရှိပါ';
        if (artistEl) artistEl.textContent = song.artist_name || '—';
        
        // Update download button
        if (downloadBtn) {
            if (song.audio_url) {
                downloadBtn.style.display = 'inline-flex';
                downloadBtn.onclick = (e) => {
                    e.stopPropagation();
                    downloadSong(song.audio_url, song.title, song.artist_name || 'Unknown Artist');
                };
            } else {
                downloadBtn.style.display = 'none';
            }
        }
        
        const coverImg = song.cover_image_url || song.artist_image;
        const coverEl = document.getElementById('player-page-cover');
        if (coverEl) {
            if (coverImg) {
                coverEl.innerHTML = `<img src="${coverImg}" alt="${song.title}" onerror="this.parentElement.innerHTML='<span class=\\'cover-placeholder\\'>🎵</span>'">`;
            } else {
                coverEl.innerHTML = '<span class="cover-placeholder">🎵</span>';
            }
        }
    } else {
        // Hide download button if no song is playing
        const downloadBtn = document.getElementById('player-page-download-btn');
        if (downloadBtn) {
            downloadBtn.style.display = 'none';
        }
    }
}

function updateMainPlayer() {
    if (currentSongIndex >= 0 && playlist && playlist[currentSongIndex]) {
        const song = playlist[currentSongIndex];
        
        playerTitle.textContent = song.title || 'No selected';
        playerArtist.textContent = song.artist_name || '—';
        
        // Update download button in static player
        const staticDownloadBtn = document.getElementById('player-download-btn');
        if (staticDownloadBtn) {
            if (song.audio_url) {
                staticDownloadBtn.style.display = 'flex';
                staticDownloadBtn.onclick = (e) => {
                    e.stopPropagation();
                    downloadSong(song.audio_url, song.title, song.artist_name || 'Unknown Artist');
                };
            } else {
                staticDownloadBtn.style.display = 'none';
            }
        }
        
        const coverImg = song.cover_image_url || song.artist_image;
        if (coverImg) {
            playerCover.innerHTML = `<img src="${coverImg}" alt="${song.title}" onerror="this.parentElement.innerHTML='<span class=\\'cover-placeholder\\'>🎵</span>'">`;
        } else {
            playerCover.innerHTML = '<span class="cover-placeholder">🎵</span>';
        }
    }
}

function removeFromPlaylist(index) {
    if (index === currentSongIndex) {
        // If removing current song, stop and move to next
        if (playlist.length > 1) {
            playlist.splice(index, 1);
            if (currentSongIndex >= playlist.length) {
                currentSongIndex = playlist.length - 1;
            }
            if (currentSongIndex >= 0) {
                playSongFromPlaylist(currentSongIndex);
            } else {
                audioElement.pause();
                audioElement.src = '';
                updatePlaylist();
                updatePlayerPagePlayer();
            }
        } else {
            playlist = [];
            currentSongIndex = -1;
            audioElement.pause();
            audioElement.src = '';
            updatePlaylist();
            updatePlayerPagePlayer();
        }
    } else {
        playlist.splice(index, 1);
        if (index < currentSongIndex) {
            currentSongIndex--;
        }
        updatePlaylist();
    }
}

function displayArtists(artists) {
    const container = document.getElementById('artists-container');
    
    if (artists.length === 0) {
        container.innerHTML = '<div class="loading">No artists found</div>';
        return;
    }
    
    container.innerHTML = artists.map(artist => `
        <div class="artist-card" onclick="filterByArtist(${artist.id})">
            <img src="${artist.image_url || 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22><rect fill=%22%23333%22 width=%22120%22 height=%22120%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>👤</text></svg>'}" 
                 alt="${artist.name}" 
                 class="artist-image"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22><rect fill=%22%23333%22 width=%22120%22 height=%22120%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>👤</text></svg>'">
            <div class="artist-name">${escapeHtml(artist.name)}</div>
            <div class="artist-songs">${artist.songs_count || 0} songs</div>
        </div>
    `).join('');
}

async function loadAllSongs(reset = true) {
    const container = document.getElementById('all-songs');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (reset) {
        currentOffset = 0;
        allSongs = [];
        container.innerHTML = '<div class="loading">ရှာဖွေနေသည်..</div>';
        if (loadMoreContainer) {
            loadMoreContainer.style.display = 'none';
        }
    }
    
    const data = await fetchAPI('songs.php', { limit: SONGS_PER_PAGE, offset: currentOffset });
    
    if (data && data.songs && data.songs.length > 0) {
        if (reset) {
            allSongs = data.songs;
            displaySongs(data.songs, 'all-songs');
        } else {
            // Append new songs to existing list
            allSongs = [...allSongs, ...data.songs];
            displaySongs(allSongs, 'all-songs');
        }
        
        // Show/hide load more button based on whether there are more songs
        if (loadMoreContainer) {
            if (data.total && data.total > allSongs.length) {
                loadMoreContainer.style.display = 'block';
            } else {
                loadMoreContainer.style.display = 'none';
            }
        }
    } else {
        if (reset) {
            container.innerHTML = '<div class="loading">ရှာမတွေ့ပါ</div>';
        } else {
            // If loading more fails or no more songs, hide the button
            if (loadMoreContainer) {
                loadMoreContainer.style.display = 'none';
            }
        }
    }
}

function displaySongs(songs, containerId) {
    const container = document.getElementById(containerId);
    
    if (songs.length === 0) {
        container.innerHTML = '<div class="loading">No songs found</div>';
        return;
    }
    
    container.innerHTML = songs.map((song, index) => `
        <div class="song-card">
            <div class="song-cover" onclick="playSong(${index}, '${containerId}')">
                <img src="${song.cover_image_url || song.artist_image || 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><rect fill=%22%23333%22 width=%22200%22 height=%22200%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>🎵</text></svg>'}" 
                     alt="${song.title}"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22><rect fill=%22%23333%22 width=%22200%22 height=%22200%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22>🎵</text></svg>'">
                <div class="song-play-overlay">
                    <div class="play-icon">▶</div>
                </div>
            </div>
            <div class="song-info">
                <div class="song-title">${escapeHtml(song.title)}</div>
                <div class="song-artist">${escapeHtml(song.artist_name || 'Unknown Artist')}</div>
                <div class="song-meta">
                    <span>${formatDuration(song.duration || 0)}</span>
                    <span>${formatNumber(song.play_count || 0)} plays</span>
                </div>
                <div class="song-actions">
                    <button class="song-download-btn" onclick="event.stopPropagation(); downloadSong('${escapeHtml(song.audio_url)}', '${escapeHtml(song.title)}', '${escapeHtml(song.artist_name || 'Unknown Artist')}')" title="Download">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

async function filterByArtist(artistId) {
    showSection('songs');
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector('[data-section="songs"]').classList.add('active');
    
    const container = document.getElementById('all-songs');
    container.innerHTML = '<div class="loading">ရှာဖွေနေသည်..</div>';
    
    const data = await fetchAPI('songs.php', { artist_id: artistId, limit: 100 });
    
    if (data && data.songs) {
        allSongs = data.songs;
        currentSongs = data.songs;
        displaySongs(data.songs, 'all-songs');
        document.getElementById('load-more-container').style.display = 'none';
    } else {
        container.innerHTML = '<div class="loading">ရှာမတွေ့ပါ</div>';
    }
}

// ============================================
// Audio Player
// ============================================

// Player page control elements (will be initialized)
let playerPagePlayPauseBtn = null;
let playerPagePrevBtn = null;
let playerPageNextBtn = null;

function initAudioPlayer() {
    // Main player controls
    playPauseBtn.addEventListener('click', togglePlayPause);
    prevBtn.addEventListener('click', playPrevious);
    nextBtn.addEventListener('click', playNext);
    progressSlider.addEventListener('input', seek);
    
    // Initialize player page controls
    initPlayerPageControls();
    
    // Audio events
    audioElement.addEventListener('loadedmetadata', () => {
        updateTotalTime();
        updatePlayerPageTotalTime();
    });
    audioElement.addEventListener('timeupdate', () => {
        updateProgress();
        updatePlayerPageProgress();
    });
    audioElement.addEventListener('ended', playNext);
    audioElement.addEventListener('play', () => {
        isPlaying = true;
        playPauseBtn.textContent = '⏸';
        if (playerPagePlayPauseBtn) playerPagePlayPauseBtn.textContent = '⏸';
        // Update playlist to ensure active indicator is shown
        updatePlaylist();
    });
    audioElement.addEventListener('pause', () => {
        isPlaying = false;
        playPauseBtn.textContent = '▶';
        if (playerPagePlayPauseBtn) playerPagePlayPauseBtn.textContent = '▶';
    });
    
    // Update progress every second
    setInterval(() => {
        updateProgress();
        updatePlayerPageProgress();
    }, 1000);
}

function initPlayerPageControls() {
    playerPagePlayPauseBtn = document.getElementById('player-page-play-pause-btn');
    playerPagePrevBtn = document.getElementById('player-page-prev-btn');
    playerPageNextBtn = document.getElementById('player-page-next-btn');
    const playerPageProgressSlider = document.getElementById('player-page-progress-slider');
    
    if (playerPagePlayPauseBtn) {
        playerPagePlayPauseBtn.addEventListener('click', togglePlayPause);
    }
    if (playerPagePrevBtn) {
        playerPagePrevBtn.addEventListener('click', playPrevious);
    }
    if (playerPageNextBtn) {
        playerPageNextBtn.addEventListener('click', playNext);
    }
    if (playerPageProgressSlider) {
        playerPageProgressSlider.addEventListener('input', (e) => {
            const seekTime = (e.target.value / 100) * audioElement.duration;
            audioElement.currentTime = seekTime;
        });
    }
    
    // Artist card collapse/expand toggle
    const artistCardToggle = document.getElementById('artist-card-toggle');
    const playerPageArtists = document.getElementById('player-page-artists');
    
    if (artistCardToggle && playerPageArtists) {
        // Set initial collapsed state
        playerPageArtists.classList.add('collapsed');
        artistCardToggle.classList.add('collapsed');
        
        // Remove any existing listeners by replacing the button
        const parent = artistCardToggle.parentNode;
        const newToggle = artistCardToggle.cloneNode(true);
        parent.replaceChild(newToggle, artistCardToggle);
        
        // Add click event listener
        newToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const artistsEl = document.getElementById('player-page-artists');
            if (artistsEl) {
                artistsEl.classList.toggle('collapsed');
                this.classList.toggle('collapsed');
            }
        });
    }
    
    // Pagination buttons
    const artistPrevBtn = document.getElementById('artist-prev-btn');
    const artistNextBtn = document.getElementById('artist-next-btn');
    
    if (artistPrevBtn) {
        artistPrevBtn.addEventListener('click', loadPlayerPageArtistsPrev);
    }
    if (artistNextBtn) {
        artistNextBtn.addEventListener('click', loadPlayerPageArtistsNext);
    }
}

async function playSong(index, containerId) {
    let songs = [];
    
    if (containerId === 'all-songs') {
        songs = allSongs;
    } else if (containerId === 'featured-songs') {
        songs = featuredSongsList;
    } else if (containerId === 'latest-songs') {
        songs = latestSongsList;
    }
    
    if (songs.length === 0) return;
    
    const song = songs[index];
    
    if (!song.audio_url) {
        alert('Audio file not available');
        return;
    }
    
    // Switch to player page
    showSection('player');
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector('[data-section="player"]').classList.add('active');
    
    // Load related songs (songs from the same artist) into playlist
    if (song.artist_id) {
        const relatedData = await fetchAPI('songs.php', { artist_id: song.artist_id, limit: 100 });
        if (relatedData && relatedData.songs && relatedData.songs.length > 0) {
            playlist = relatedData.songs;
            // Find the current song index in the playlist
            currentSongIndex = playlist.findIndex(s => s.id === song.id);
            if (currentSongIndex === -1) {
                currentSongIndex = 0;
            }
            updatePlaylist();
        } else {
            // If no related songs, just add the current song
            playlist = [song];
            currentSongIndex = 0;
            updatePlaylist();
        }
    } else {
        // If no artist_id, just add the current song
        playlist = [song];
        currentSongIndex = 0;
        updatePlaylist();
    }
    
    currentSongs = playlist;
    
    // Update both players
    updateMainPlayer();
    updatePlayerPagePlayer();
    
    // Load and play audio
    audioElement.src = song.audio_url;
    audioElement.load();
    audioElement.play().catch(err => {
        console.error('Error playing audio:', err);
        alert('Error playing audio. Please check if the file exists.');
    });
    
    // Show bottom player only if not on player page
    const currentSection = document.querySelector('.section.active');
    if (currentSection && currentSection.id !== 'player') {
        audioPlayer.style.display = 'block';
        audioPlayer.classList.add('active');
    } else {
        audioPlayer.style.display = 'none';
        audioPlayer.classList.remove('active');
    }
    
    // Increment play count via API
    fetchAPI('songs.php', { id: song.id });
}

function togglePlayPause() {
    if (audioElement.paused) {
        audioElement.play();
    } else {
        audioElement.pause();
    }
}

function playPrevious() {
    if (playlist && playlist.length > 0) {
        // Use playlist if available
        currentSongIndex = (currentSongIndex - 1 + playlist.length) % playlist.length;
        playSongFromPlaylist(currentSongIndex);
    } else if (currentSongs.length > 0) {
        // Fallback to current songs
        currentSongIndex = (currentSongIndex - 1 + currentSongs.length) % currentSongs.length;
        const song = currentSongs[currentSongIndex];
        playSong(currentSongIndex, 'current');
    }
}

function playNext() {
    if (playlist && playlist.length > 0) {
        // Use playlist if available
        currentSongIndex = (currentSongIndex + 1) % playlist.length;
        playSongFromPlaylist(currentSongIndex);
    } else if (currentSongs.length > 0) {
        // Fallback to current songs
        currentSongIndex = (currentSongIndex + 1) % currentSongs.length;
        const song = currentSongs[currentSongIndex];
        playSong(currentSongIndex, 'current');
    }
}

function seek() {
    const seekTime = (progressSlider.value / 100) * audioElement.duration;
    audioElement.currentTime = seekTime;
}

function updateProgress() {
    if (audioElement.duration) {
        const progress = (audioElement.currentTime / audioElement.duration) * 100;
        progressFill.style.width = progress + '%';
        progressSlider.value = progress;
        currentTimeEl.textContent = formatTime(audioElement.currentTime);
    }
}

function updatePlayerPageProgress() {
    const playerPageProgressFill = document.getElementById('player-page-progress-fill');
    const playerPageProgressSlider = document.getElementById('player-page-progress-slider');
    const playerPageCurrentTime = document.getElementById('player-page-current-time');
    
    if (audioElement.duration && playerPageProgressFill && playerPageProgressSlider && playerPageCurrentTime) {
        const progress = (audioElement.currentTime / audioElement.duration) * 100;
        playerPageProgressFill.style.width = progress + '%';
        playerPageProgressSlider.value = progress;
        playerPageCurrentTime.textContent = formatTime(audioElement.currentTime);
    }
}

function updateTotalTime() {
    if (audioElement.duration) {
        totalTimeEl.textContent = formatTime(audioElement.duration);
    }
}

function updatePlayerPageTotalTime() {
    const playerPageTotalTime = document.getElementById('player-page-total-time');
    if (audioElement.duration && playerPageTotalTime) {
        playerPageTotalTime.textContent = formatTime(audioElement.duration);
    }
}

// ============================================
// Search
// ============================================

function initSearch() {
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
}

async function performSearch() {
    const query = searchInput.value.trim();
    
    if (!query) {
        loadAllSongs(true);
        return;
    }
    
    showSection('songs');
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector('[data-section="songs"]').classList.add('active');
    
    const container = document.getElementById('all-songs');
    container.innerHTML = '<div class="loading">ရှာဖွေနေသည်..</div>';
    
    const data = await fetchAPI('songs.php', { search: query, limit: 100 });
    
    if (data && data.songs) {
        allSongs = data.songs;
        currentSongs = data.songs;
        displaySongs(data.songs, 'all-songs');
        document.getElementById('load-more-container').style.display = 'none';
    } else {
        container.innerHTML = '<div class="loading">ရှာမတွေ့ပါ</div>';
    }
}

// Load more songs - initialize button click handler
function initLoadMoreButton() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            currentOffset += SONGS_PER_PAGE;
            loadAllSongs(false);
        });
    }
}

// Initialize load more button when DOM is ready
initLoadMoreButton();

// ============================================
// Utility Functions
// ============================================

function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function formatDuration(seconds) {
    return formatTime(seconds);
}

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// Download Function
// ============================================

function downloadSong(audioUrl, title, artist) {
    if (!audioUrl) {
        alert('Audio file not available for download');
        return;
    }
    
    try {
        // Create a temporary anchor element
        const link = document.createElement('a');
        link.href = audioUrl;
        
        // Get file extension from URL
        const urlParts = audioUrl.split('.');
        const extension = urlParts[urlParts.length - 1].split('?')[0]; // Remove query params
        
        // Create a safe filename
        const safeTitle = (title || 'song').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const safeArtist = (artist || 'unknown').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const filename = `${safeArtist}_${safeTitle}.${extension}`;
        
        link.download = filename;
        link.target = '_blank';
        
        // Append to body, click, and remove
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('Download started:', filename);
    } catch (error) {
        console.error('Download error:', error);
        // Fallback: open in new tab
        window.open(audioUrl, '_blank');
    }
}

// Make functions globally available
window.playSong = playSong;
window.filterByArtist = filterByArtist;
window.downloadSong = downloadSong;
window.loadArtistSongsToPlaylist = loadArtistSongsToPlaylist;
window.playSongFromPlaylist = playSongFromPlaylist;
window.removeFromPlaylist = removeFromPlaylist;
window.loadPlayerPageArtistsPrev = loadPlayerPageArtistsPrev;
window.loadPlayerPageArtistsNext = loadPlayerPageArtistsNext;