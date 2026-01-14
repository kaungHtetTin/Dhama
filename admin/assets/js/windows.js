// Windows Desktop Management System

let windows = {};
let zIndexCounter = 100;
let activeWindow = null;
let draggedWindow = null;
let dragOffset = { x: 0, y: 0 };

// Initialize desktop
document.addEventListener('DOMContentLoaded', function() {
    initializeDesktop();
    updateClock();
    setInterval(updateClock, 1000);
    
    // Open dashboard window by default
    const dashboardWindow = document.getElementById('window-dashboard');
    if (dashboardWindow) {
        bringToFront('window-dashboard');
        updateTaskbar();
    }
});

function initializeDesktop() {
    // Handle desktop icon clicks
    document.querySelectorAll('.desktop-icon').forEach(icon => {
        icon.addEventListener('dblclick', function() {
            const windowId = this.dataset.window;
            openWindow(windowId);
        });
        
        icon.addEventListener('click', function() {
            document.querySelectorAll('.desktop-icon').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Handle start button
    const startButton = document.getElementById('start-button');
    const startMenu = document.getElementById('start-menu');
    
    if (startButton && startMenu) {
        startButton.addEventListener('click', function(e) {
            e.stopPropagation();
            const isActive = startMenu.classList.toggle('active');
            startButton.classList.toggle('active', isActive);
        });
        
        document.addEventListener('click', function(e) {
            if (!startMenu.contains(e.target) && !startButton.contains(e.target)) {
                startMenu.classList.remove('active');
                startButton.classList.remove('active');
            }
        });
    }
    
    // Handle start menu items
    document.querySelectorAll('.start-menu-item').forEach(item => {
        item.addEventListener('click', function() {
            const windowId = this.dataset.window;
            if (windowId) {
                openWindow(windowId);
                startMenu.classList.remove('active');
                startButton.classList.remove('active');
            }
        });
    });
}

function openWindow(windowId) {
    const window = document.getElementById(windowId);
    if (!window) return;
    
    // If window is minimized, restore it
    if (window.classList.contains('minimized')) {
        window.classList.remove('minimized');
    }
    
    // Bring to front
    bringToFront(windowId);
    
    // Update taskbar
    updateTaskbar();
}

function closeWindow(windowId) {
    const window = document.getElementById(windowId);
    if (window) {
        window.style.display = 'none';
        window.classList.add('minimized');
        updateTaskbar();
        
        if (activeWindow === windowId) {
            activeWindow = null;
        }
    }
}

function minimizeWindow(windowId) {
    const window = document.getElementById(windowId);
    if (window) {
        window.classList.add('minimized');
        updateTaskbar();
    }
}

function maximizeWindow(windowId) {
    const window = document.getElementById(windowId);
    if (window) {
        window.classList.toggle('maximized');
        updateTaskbar();
    }
}

function bringToFront(windowId) {
    const window = document.getElementById(windowId);
    if (window) {
        zIndexCounter++;
        window.style.zIndex = zIndexCounter;
        window.classList.add('active');
        
        // Remove active from other windows
        document.querySelectorAll('.window').forEach(w => {
            if (w.id !== windowId) {
                w.classList.remove('active');
            }
        });
        
        activeWindow = windowId;
        updateTaskbar();
    }
}

function updateTaskbar() {
    const taskbarWindows = document.getElementById('taskbar-windows');
    if (!taskbarWindows) return;
    
    taskbarWindows.innerHTML = '';
    
    document.querySelectorAll('.window').forEach(window => {
        if (!window.classList.contains('minimized')) {
            const title = window.querySelector('.window-title').textContent;
            const windowId = window.id;
            const isActive = window.classList.contains('active');
            
            const taskbarBtn = document.createElement('div');
            taskbarBtn.className = 'taskbar-window' + (isActive ? ' active' : '');
            taskbarBtn.textContent = title;
            taskbarBtn.addEventListener('click', function() {
                if (window.classList.contains('minimized')) {
                    window.classList.remove('minimized');
                }
                bringToFront(windowId);
            });
            
            taskbarWindows.appendChild(taskbarBtn);
        }
    });
}

// Window dragging
document.addEventListener('mousedown', function(e) {
    const titlebar = e.target.closest('.window-titlebar');
    if (titlebar) {
        const window = titlebar.closest('.window');
        if (window) {
            draggedWindow = window;
            const rect = window.getBoundingClientRect();
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            bringToFront(window.id);
            e.preventDefault();
        }
    }
});

document.addEventListener('mousemove', function(e) {
    if (draggedWindow && !draggedWindow.classList.contains('maximized')) {
        const x = e.clientX - dragOffset.x;
        const y = e.clientY - dragOffset.y;
        
        // Keep window within bounds
        const maxX = window.innerWidth - 100;
        const maxY = window.innerHeight - 140;
        
        draggedWindow.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
        draggedWindow.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
    }
});

document.addEventListener('mouseup', function() {
    draggedWindow = null;
});

// Window controls
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('window-btn')) {
        const btn = e.target;
        const window = btn.closest('.window');
        if (!window) return;
        
        const windowId = window.id;
        
        if (btn.classList.contains('close')) {
            closeWindow(windowId);
        } else if (btn.classList.contains('minimize')) {
            minimizeWindow(windowId);
        } else if (btn.classList.contains('maximize')) {
            maximizeWindow(windowId);
        }
    }
});

// Update clock
function updateClock() {
    const timeElement = document.getElementById('taskbar-time');
    if (timeElement) {
        const now = new Date();
        const time = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
        timeElement.textContent = time;
    }
}

// Make windows draggable on titlebar click
document.querySelectorAll('.window-titlebar').forEach(titlebar => {
    titlebar.addEventListener('mousedown', function(e) {
        const window = this.closest('.window');
        if (window && !window.classList.contains('maximized')) {
            bringToFront(window.id);
        }
    });
});
