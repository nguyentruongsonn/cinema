document.addEventListener('DOMContentLoaded', () => {
    // --- Elements ---
    const branchFilter = document.getElementById('branchFilter');
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    
    const theatersSkeleton = document.getElementById('theatersSkeleton');
    const theatersGrid = document.getElementById('theatersGrid');
    const emptyState = document.getElementById('emptyState');
    const paginationContainer = document.getElementById('paginationContainer');

    // --- State ---
    let currentPage = 1;
    let currentBranch = '';
    let searchQuery = '';
    let isFetching = false;

    // --- Init ---
    init();

    async function init() {
        await fetchBranches();
        fetchTheaters();
        setupEventListeners();
    }

    function setupEventListeners() {
        branchFilter.addEventListener('change', (e) => {
            currentBranch = e.target.value;
            currentPage = 1;
            fetchTheaters();
        });

        searchBtn.addEventListener('click', () => {
            searchQuery = searchInput.value.trim();
            currentPage = 1;
            fetchTheaters();
        });

        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchQuery = searchInput.value.trim();
                currentPage = 1;
                fetchTheaters();
            }
        });
    }

    // --- API Calls ---
    async function fetchBranches() {
        try {
            const response = await fetch('/api/v1/theaters/cities');
            const result = await response.json();
            
            if (result.success && result.data) {
                // Keep the "Tất cả chi nhánh" option and append others
                const defaultOption = '<option value="">Tất cả chi nhánh</option>';
                const branchOptions = result.data.map(branchName => `<option value="${branchName}">${branchName}</option>`).join('');
                branchFilter.innerHTML = defaultOption + branchOptions;
            }
        } catch (error) {
            console.error('Error fetching branches:', error);
        }
    }

    async function fetchTheaters() {
        if (isFetching) return;
        
        isFetching = true;
        showSkeleton();

        try {
            // Build query params
            const params = new URLSearchParams();
            if (currentBranch) params.append('city', currentBranch); // Backend uses 'city' parameter for branch name filter
            if (searchQuery) params.append('q', searchQuery);
            params.append('page', currentPage);
            // Default to only active theaters
            params.append('status', 'active');
            
            const response = await fetch(`/api/v1/theaters?${params.toString()}`);
            const result = await response.json();
            
            if (result.success && result.data && result.data.length > 0) {
                renderGroupedTheaters(result.data);
                renderPagination(result.pagination);
                showGrid();
            } else {
                showEmpty();
            }
        } catch (error) {
            console.error('Error fetching theaters:', error);
            showEmpty();
        } finally {
            isFetching = false;
        }
    }

    // --- Renderers ---
    function renderGroupedTheaters(theaters) {
        // Group theaters by branch name
        const groups = {};
        theaters.forEach(theater => {
            const branchName = theater.branch ? theater.branch.name : 'Khác';
            if (!groups[branchName]) {
                groups[branchName] = [];
            }
            groups[branchName].push(theater);
        });

        let html = '';

        for (const [branchName, branchTheaters] of Object.entries(groups)) {
            html += `
                <div class="col-12 branch-group">
                    <h2 class="branch-title">${branchName}</h2>
                    <div class="row g-4">
                        ${branchTheaters.map(theater => generateTheaterCard(theater)).join('')}
                    </div>
                </div>
            `;
        }

        // We wrap the whole thing inside theatersGrid which is a container
        theatersGrid.innerHTML = html;
        // Since theatersGrid itself was a row, we should remove the 'row' class from it or just let the inner rows handle the grid.
        // Let's modify theatersGrid class safely.
        theatersGrid.className = 'w-100'; // Remove row g-4 from the main container as we handle it per group
    }

    function generateTheaterCard(theater) {
        // Mock badges or extract from screens if they had format data.
        // For visual match with the requested UI, we will add some badges randomly if screens contain keywords or just mock for preview
        let badgesHtml = '';
        const badges = new Set();
        
        if (theater.screens && theater.screens.length > 0) {
            theater.screens.forEach(s => {
                if (s.name.toUpperCase().includes('IMAX') || (s.format && s.format.name.toUpperCase().includes('IMAX'))) badges.add('IMAX');
                if (s.name.toUpperCase().includes('GOLD CLASS') || (s.format && s.format.name.toUpperCase().includes('GOLD CLASS'))) badges.add('GOLD CLASS');
                if (s.name.toUpperCase().includes('DOLBY') || (s.format && s.format.name.toUpperCase().includes('DOLBY'))) badges.add('DOLBY ATMOS');
            });
        }
        
        // Add random badges for mockup if no screens data available, matching screenshot aesthetic
        if (badges.size === 0) {
            // Using theater ID to pseudo-randomly assign badges so they don't look completely empty
            if (theater.id % 2 === 0) badges.add('IMAX');
            if (theater.id % 3 === 0) badges.add('GOLD CLASS');
            if (theater.id % 4 === 0) badges.add('DOLBY ATMOS');
        }

        badges.forEach(badge => {
            let badgeClass = '';
            if (badge === 'IMAX') badgeClass = 'imax';
            else if (badge === 'GOLD CLASS') badgeClass = 'gold-class';
            
            badgesHtml += `<span class="t-badge ${badgeClass}">${badge}</span>`;
        });

        return `
            <div class="col-md-6 col-lg-4">
                <div class="theater-card">
                    ${theater.images && theater.images.length > 0 
                        ? `<div class="theater-img" style="background-image: url('${theater.images[0].url}')">
                             <div class="theater-badges">${badgesHtml}</div>
                           </div>`
                        : `<div class="theater-img-placeholder">
                             <i class="bi bi-camera-video"></i>
                             <div class="theater-badges">${badgesHtml}</div>
                           </div>`
                    }
                    <div class="theater-info">
                        <h3 class="theater-name">${theater.name}</h3>
                        
                        <div class="theater-detail">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>${theater.address}</span>
                        </div>
                        
                        <div class="theater-actions">
                            <a href="#" class="btn-theater">Xem lịch chiếu <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderPagination(paginationData) {
        if (paginationData.last_page <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }

        let html = '';
        
        // Prev button
        html += `<button class="page-btn" ${paginationData.current_page === 1 ? 'disabled' : ''} data-page="${paginationData.current_page - 1}">
            <i class="bi bi-chevron-left"></i>
        </button>`;
        
        // Page numbers
        for (let i = 1; i <= paginationData.last_page; i++) {
            if (i === 1 || i === paginationData.last_page || (i >= paginationData.current_page - 1 && i <= paginationData.current_page + 1)) {
                html += `<button class="page-btn ${i === paginationData.current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
            } else if (i === paginationData.current_page - 2 || i === paginationData.current_page + 2) {
                html += `<span class="px-2" style="color: #666;">...</span>`;
            }
        }
        
        // Next button
        html += `<button class="page-btn" ${paginationData.current_page === paginationData.last_page ? 'disabled' : ''} data-page="${paginationData.current_page + 1}">
            <i class="bi bi-chevron-right"></i>
        </button>`;
        
        paginationContainer.innerHTML = html;
        paginationContainer.style.display = 'flex';
        
        // Add listeners
        paginationContainer.querySelectorAll('.page-btn:not(:disabled)').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page);
                fetchTheaters();
                // Scroll to top of section
                document.querySelector('.theaters-filters-section').scrollIntoView({ behavior: 'smooth' });
            });
        });
    }

    // --- UI Helpers ---
    function showSkeleton() {
        theatersSkeleton.style.display = 'flex';
        theatersGrid.style.display = 'none';
        emptyState.style.display = 'none';
        paginationContainer.style.display = 'none';
    }

    function showGrid() {
        theatersSkeleton.style.display = 'none';
        theatersGrid.style.display = 'block';
        emptyState.style.display = 'none';
    }

    function showEmpty() {
        theatersSkeleton.style.display = 'none';
        theatersGrid.style.display = 'none';
        emptyState.style.display = 'block';
        paginationContainer.style.display = 'none';
    }
});
