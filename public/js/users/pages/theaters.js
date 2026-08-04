document.addEventListener('DOMContentLoaded', () => {
    // --- Elements ---
    const branchFilter = document.getElementById('branchFilter');
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    
    const theatersSkeleton = document.getElementById('theatersSkeleton');
    const theatersGrid = document.getElementById('theatersGrid');
    const emptyState = document.getElementById('emptyState');
    const paginationContainer = document.getElementById('paginationContainer');
    const dataRegion = new window.DataRegion('#theatersDataRegion');

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
            const result = await window.apiClient.get('/theaters/cities');
            
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
            
            const result = await window.apiClient.get(`/theaters?${params.toString()}`);
            
            if (result.success && result.data && result.data.length > 0) {
                renderGroupedTheaters(result.data);
                renderPagination(result.pagination);
                showGrid();
            } else {
                showEmpty();
            }
        } catch (error) {
            console.error('Error fetching theaters:', error);
            showError();
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

        let imageUrl = getSafeImageUrl(theater.images?.[0]?.url);
        if (!imageUrl) {
            const fallbackImages = [
                'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=600&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=600&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?q=80&w=600&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1505686994434-e3cc5abf1330?q=80&w=600&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1513151233558-d860c5398176?q=80&w=600&auto=format&fit=crop',
            ];
            imageUrl = fallbackImages[theater.id % fallbackImages.length];
        }

        const imgWrapper = document.createElement('div');
        imgWrapper.className = 'theater-img-wrapper';

        const media = document.createElement('div');
        media.className = 'theater-img';
        media.style.backgroundImage = `url(${JSON.stringify(imageUrl)})`;
        imgWrapper.appendChild(media);

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

        imgWrapper.appendChild(badgesContainer);

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

        const contacts = document.createElement('div');
        contacts.className = 'theater-contacts';

        if (theater.phone) {
            const phoneEl = document.createElement('div');
            phoneEl.className = 'theater-contact-item';
            phoneEl.innerHTML = `<i class="bi bi-telephone-fill"></i><span>${theater.phone}</span>`;
            contacts.appendChild(phoneEl);
        }
        if (theater.email) {
            const emailEl = document.createElement('div');
            emailEl.className = 'theater-contact-item';
            emailEl.innerHTML = `<i class="bi bi-envelope-fill"></i><span>${theater.email}</span>`;
            contacts.appendChild(emailEl);
        }

        const actions = document.createElement('div');
        actions.className = 'theater-actions';

        const scheduleLink = document.createElement('a');
        scheduleLink.href = '/movies';
        scheduleLink.className = 'btn-theater';
        scheduleLink.append(document.createTextNode('Đặt vé ngay '));

        const arrowIcon = document.createElement('i');
        arrowIcon.className = 'bi bi-arrow-right';
        scheduleLink.appendChild(arrowIcon);
        actions.appendChild(scheduleLink);

        info.append(name, detail, contacts, actions);
        card.append(imgWrapper, info);
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
        window.CinemaPagination.render({
            container: paginationContainer,
            pagination: paginationData,
            itemLabel: 'rạp',
            onPageChange: (page) => {
                currentPage = page;
                fetchTheaters();
                document.querySelector('.theaters-filters-section').scrollIntoView({ behavior: 'smooth' });
            }
        });
        paginationContainer.classList.toggle('d-none', Number(paginationData.last_page || 1) <= 1);
    }

    // --- UI Helpers ---
    function showSkeleton() {
        dataRegion.show('loading');
        paginationContainer.classList.add('d-none');
    }

    function showGrid() {
        dataRegion.show('ready');
    }

    function showEmpty() {
        dataRegion.show('empty');
        paginationContainer.classList.add('d-none');
    }

    function showError() {
        dataRegion.show('error');
        paginationContainer.classList.add('d-none');
    }
});
