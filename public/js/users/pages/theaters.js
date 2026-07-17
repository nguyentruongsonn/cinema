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
                const options = [];
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Tất cả chi nhánh';
                options.push(defaultOption);

                result.data.forEach(branchName => {
                    const option = document.createElement('option');
                    option.value = String(branchName ?? '');
                    option.textContent = String(branchName ?? '');
                    options.push(option);
                });

                branchFilter.replaceChildren(...options);
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
        const groups = new Map();

        theaters.forEach(theater => {
            const branchName = theater.branch ? theater.branch.name : 'Khác';
            const safeBranchName = String(branchName ?? 'Khác');

            if (!groups.has(safeBranchName)) {
                groups.set(safeBranchName, []);
            }

            groups.get(safeBranchName).push(theater);
        });

        const fragment = document.createDocumentFragment();

        groups.forEach((branchTheaters, branchName) => {
            const group = document.createElement('div');
            group.className = 'col-12 branch-group';

            const title = document.createElement('h2');
            title.className = 'branch-title';
            title.textContent = branchName;

            const row = document.createElement('div');
            row.className = 'row g-4';
            branchTheaters.forEach(theater => row.appendChild(generateTheaterCard(theater)));

            group.append(title, row);
            fragment.appendChild(group);
        });

        theatersGrid.replaceChildren(fragment);
        theatersGrid.className = 'w-100';
    }

    function generateTheaterCard(theater) {
        const column = document.createElement('div');
        column.className = 'col-md-6 col-lg-4';

        const card = document.createElement('div');
        card.className = 'theater-card';

        const imageUrl = getSafeImageUrl(theater.images?.[0]?.url);
        const media = document.createElement('div');
        media.className = imageUrl ? 'theater-img' : 'theater-img-placeholder';

        if (imageUrl) {
            media.style.backgroundImage = `url(${JSON.stringify(imageUrl)})`;
        } else {
            const placeholderIcon = document.createElement('i');
            placeholderIcon.className = 'bi bi-camera-video';
            media.appendChild(placeholderIcon);
        }

        const badgesContainer = document.createElement('div');
        badgesContainer.className = 'theater-badges';

        getTheaterBadges(theater).forEach(badge => {
            const badgeElement = document.createElement('span');
            badgeElement.className = 't-badge';
            badgeElement.textContent = badge;

            if (badge === 'IMAX') {
                badgeElement.classList.add('imax');
            } else if (badge === 'GOLD CLASS') {
                badgeElement.classList.add('gold-class');
            }

            badgesContainer.appendChild(badgeElement);
        });

        media.appendChild(badgesContainer);

        const info = document.createElement('div');
        info.className = 'theater-info';

        const name = document.createElement('h3');
        name.className = 'theater-name';
        name.textContent = String(theater.name ?? '');

        const detail = document.createElement('div');
        detail.className = 'theater-detail';

        const locationIcon = document.createElement('i');
        locationIcon.className = 'bi bi-geo-alt-fill';

        const address = document.createElement('span');
        address.textContent = String(theater.address ?? '');
        detail.append(locationIcon, address);

        const actions = document.createElement('div');
        actions.className = 'theater-actions';

        const scheduleLink = document.createElement('a');
        scheduleLink.href = '#';
        scheduleLink.className = 'btn-theater';
        scheduleLink.append(document.createTextNode('Xem lịch chiếu '));

        const arrowIcon = document.createElement('i');
        arrowIcon.className = 'bi bi-arrow-right';
        scheduleLink.appendChild(arrowIcon);
        actions.appendChild(scheduleLink);

        info.append(name, detail, actions);
        card.append(media, info);
        column.appendChild(card);

        return column;
    }

    function getTheaterBadges(theater) {
        const badges = new Set();
        const screens = Array.isArray(theater.screens) ? theater.screens : [];

        screens.forEach(screen => {
            const screenName = String(screen?.name ?? '').toUpperCase();
            const formatName = String(screen?.format?.name ?? '').toUpperCase();
            const capability = `${screenName} ${formatName}`;

            if (capability.includes('IMAX')) {
                badges.add('IMAX');
            }
            if (capability.includes('GOLD CLASS')) {
                badges.add('GOLD CLASS');
            }
            if (capability.includes('DOLBY')) {
                badges.add('DOLBY ATMOS');
            }
        });

        return badges;
    }

    function getSafeImageUrl(value) {
        if (!value) {
            return '';
        }

        const urlValue = String(value).trim();

        if (urlValue.startsWith('/') && !urlValue.startsWith('//')) {
            return urlValue;
        }

        try {
            const parsedUrl = new URL(urlValue, window.location.origin);

            if (parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:') {
                return parsedUrl.href;
            }
        } catch (error) {
            return '';
        }

        return '';
    }

    function renderPagination(paginationData) {
        if (paginationData.last_page <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }

        const controls = [];

        controls.push(createPaginationButton(
            paginationData.current_page - 1,
            'bi bi-chevron-left',
            paginationData.current_page === 1
        ));
        
        for (let i = 1; i <= paginationData.last_page; i++) {
            if (i === 1 || i === paginationData.last_page || (i >= paginationData.current_page - 1 && i <= paginationData.current_page + 1)) {
                const pageButton = createPaginationButton(i, '', false, String(i));
                pageButton.classList.toggle('active', i === paginationData.current_page);
                controls.push(pageButton);
            } else if (i === paginationData.current_page - 2 || i === paginationData.current_page + 2) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'px-2';
                ellipsis.style.color = '#666';
                ellipsis.textContent = '...';
                controls.push(ellipsis);
            }
        }
        
        controls.push(createPaginationButton(
            paginationData.current_page + 1,
            'bi bi-chevron-right',
            paginationData.current_page === paginationData.last_page
        ));
        
        paginationContainer.replaceChildren(...controls);
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

    function createPaginationButton(page, iconClass, disabled, label = '') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-btn';
        button.disabled = disabled;
        button.dataset.page = String(page);

        if (label) {
            button.textContent = label;
        } else {
            const icon = document.createElement('i');
            icon.className = iconClass;
            button.appendChild(icon);
        }

        return button;
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
