import re

path = "public/js/users/pages/profile.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Refactor loadTickets
old_load_tickets = r"""            if \(!append\) \{
                ticketsList\.innerHTML = '';
            \}

            if \(orders\.length === 0 && !append\) \{
                ticketsEmpty\.classList\.remove\('d-none'\);
                if \(loadMoreBtn\) loadMoreBtn\.style\.display = 'none';
            \} else \{
                let filteredOrders = orders;

                // Filter by year if needed
                if \(this\.ticketFilter === 'year'\) \{
                    const currentYear = new Date\(\)\.getFullYear\(\);
                    filteredOrders = orders\.filter\(order => \{
                        const orderYear = new Date\(order\.created_at\)\.getFullYear\(\);
                        return orderYear === currentYear;
                    \}\);
                \}

                filteredOrders\.forEach\(order => \{
                    const card = this\.createTicketCard\(order\);
                    ticketsList\.appendChild\(card\);
                \}\);

                // Show/hide load more button
                if \(loadMoreBtn\) \{
                    if \(currentPage < lastPage\) \{
                        loadMoreBtn\.style\.display = 'inline-block';
                        loadMoreBtn\.disabled = false;
                        loadMoreBtn\.textContent = 'Xem thêm lịch sử';
                    \} else \{
                        loadMoreBtn\.style\.display = 'none';
                    \}
                \}
            \}"""

new_load_tickets = """            if (!append) {
                ticketsList.innerHTML = '';
            }

            if (orders.length === 0 && !append) {
                ticketsEmpty.classList.remove('d-none');
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            } else {
                let filteredOrders = orders;

                // Filter by year if needed
                if (this.ticketFilter === 'year') {
                    const currentYear = new Date().getFullYear();
                    filteredOrders = orders.filter(order => {
                        const orderYear = new Date(order.created_at).getFullYear();
                        return orderYear === currentYear;
                    });
                }

                const html = filteredOrders.map(order => this.createTicketCard(order)).join('');
                if (append) {
                    ticketsList.insertAdjacentHTML('beforeend', html);
                } else {
                    ticketsList.innerHTML = html;
                }
                
                // Attach event listeners for the newly added cards
                const addedCards = append ? Array.from(ticketsList.children).slice(-filteredOrders.length) : Array.from(ticketsList.children);
                addedCards.forEach((card, index) => {
                    const order = filteredOrders[index];
                    if (card.classList.contains('ticket-card')) {
                        card.addEventListener('click', (e) => {
                            if (e.target.closest('.ticket-detail-btn')) return;
                            this.openOrderDetailModal(order);
                        });
                        
                        card.querySelector('.ticket-detail-btn')?.addEventListener('click', (e) => {
                            e.stopPropagation();
                            this.openOrderDetailModal(order);
                        });
                    }
                });

                // Show/hide load more button
                if (loadMoreBtn) {
                    if (currentPage < lastPage) {
                        loadMoreBtn.style.display = 'inline-block';
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = 'Xem thêm lịch sử';
                    } else {
                        loadMoreBtn.style.display = 'none';
                    }
                }
            }"""

content = re.sub(old_load_tickets, new_load_tickets, content)


# Refactor createTicketCard and renderTicketStatus
old_create_ticket_card = r"""    createTicketCard\(order\) \{
        const template = document\.getElementById\('ticketCardTemplate'\);
        if \(!template\) return document\.createElement\('div'\);

        const card = template\.content\.cloneNode\(true\);
        const cardEl = card\.querySelector\('\.ticket-card'\);

        // Status class
        const status = order\.status \|\| 'pending';
        if \(cardEl\) cardEl\.dataset\.status = status;

        // Make whole card clickable
        if \(cardEl\) \{
            cardEl\.style\.cursor = 'pointer';
            cardEl\.addEventListener\('click', \(e\) => \{
                if \(e\.target\.closest\('\.ticket-detail-btn'\)\) return;
                this\.openOrderDetailModal\(order\);
            \}\);
        \}

        // Poster \+ cancelled overlay
        const poster = card\.querySelector\('\.ticket-poster'\);
        const overlay = card\.querySelector\('\.ticket-cancelled-overlay'\);
        if \(poster\) \{
            poster\.src = order\.poster_url \|\| order\.showtime\?\.movie\?\.poster_url \|\| '/images/default-poster\.jpg';
            poster\.alt = order\.movie_title \|\| order\.showtime\?\.movie\?\.title \|\| 'Poster';
            poster\.onerror = \(\) => \{ poster\.src = '/images/default-poster\.jpg'; \};
        \}
        if \(overlay\) \{
            overlay\.style\.display = status === 'cancelled' \? 'flex' : 'none';
        \}

        // Format badges \(3D, IMAX, etc\.\)
        const formatsContainer = card\.querySelector\('\.ticket-formats'\);
        if \(formatsContainer\) \{
            const addBadge = \(text\) => \{
                if \(!text\) return;
                const b = document\.createElement\('span'\);
                b\.className = 'ticket-format-badge';
                b\.textContent = text;
                formatsContainer\.appendChild\(b\);
            \};
            addBadge\(order\.showtime\?\.format\?\.name\);
            addBadge\(order\.showtime\?\.sound\?\.name\);
            addBadge\(order\.showtime\?\.subtitle\?\.name\);
        \}

        // Title
        const title = card\.querySelector\('\.ticket-title'\);
        if \(title\) title\.textContent = order\.movie_title \|\| order\.showtime\?\.movie\?\.title \|\| 'N/A';

        // ID
        const ticketId = card\.querySelector\('\.ticket-id'\);
        if \(ticketId\) ticketId\.textContent = `ID: #CP-\$\{String\(order\.id\)\.padStart\(5, '0'\)\}`;

        // Showtime
        const showtime = card\.querySelector\('\.ticket-showtime'\);
        if \(showtime\) \{
            const rawDate = order\.show_date \|\| order\.showtime\?\.scheduled_at;
            if \(rawDate\) \{
                const d = new Date\(rawDate\);
                showtime\.textContent = `\$\{d\.toLocaleDateString\('vi-VN'\)\} - \$\{d\.toLocaleTimeString\('vi-VN', \{ hour: '2-digit', minute: '2-digit' \}\)\}`;
            \} else \{
                showtime\.textContent = 'N/A';
            \}
        \}

        // Theater
        const theater = card\.querySelector\('\.ticket-theater'\);
        if \(theater\) \{
            const branch = order\.branch_name \|\| order\.showtime\?\.screen\?\.theater\?\.branch\?\.name \|\| '';
            const screen = order\.screen_name \|\| order\.showtime\?\.screen\?\.name \|\| '';
            theater\.textContent = branch && screen \? `\$\{branch\} - \$\{screen\}` : \(branch \|\| screen \|\| 'N/A'\);
        \}

        // Seats
        const seats = card\.querySelector\('\.ticket-seats'\);
        if \(seats && order\.order_items\) \{
            const names = order\.order_items\.map\(i => i\.metadata\?\.seat_label \|\| i\.seat\?\.seat_number\)\.filter\(Boolean\)\.join\(', '\);
            seats\.textContent = names \|\| 'N/A';
        \}

        // Status badge
        const statusEl = card\.querySelector\('\.ticket-status'\);
        if \(statusEl\) this\.renderTicketStatus\(statusEl, status\);

        // Detail button
        const detailBtn = card\.querySelector\('\.ticket-detail-btn'\);
        if \(detailBtn\) \{
            detailBtn\.addEventListener\('click', \(\) => this\.openOrderDetailModal\(order\)\);
        \}

        return card;
    \}

    renderTicketStatus\(container, status\) \{
        if \(!container\) return;
        container\.innerHTML = '';

        const config = \{
            completed: \{ dot: '#22c55e', label: 'CONFIRMED', color: '#22c55e' \},
            confirmed: \{ dot: '#22c55e', label: 'CONFIRMED', color: '#22c55e' \},
            pending:   \{ dot: '#f59e0b', label: 'PENDING',   color: '#f59e0b' \},
            cancelled: \{ dot: '#ed0712', label: 'ĐÃ HỦY',    color: '#ed0712' \},
        \}\[status\] \|\| \{ dot: '#6b7280', label: String\(status \|\| 'Không rõ'\)\.toUpperCase\(\), color: '#6b7280' \};

        container\.innerHTML = `
            <div style="display:inline-flex; align-items:center; gap:6px; background:\$\{config\.color\}15; padding:6px 12px; border-radius:20px;">
                <div style="width:8px; height:8px; border-radius:50%; background:\$\{config\.dot\};"></div>
                <span style="color:\$\{config\.color\}; font-size:12px; font-weight:700; letter-spacing:0\.5px;">\$\{config\.label\}</span>
            </div>
        `;
    \}"""

new_create_ticket_card = """    createTicketCard(order) {
        const status = order.status || 'pending';
        const posterSrc = order.poster_url || order.showtime?.movie?.poster_url || '/images/default-poster.jpg';
        const posterAlt = order.movie_title || order.showtime?.movie?.title || 'Poster';
        
        let badgesHtml = '';
        if (order.showtime?.format?.name) badgesHtml += `<span class="ticket-format-badge">${order.showtime.format.name}</span>`;
        if (order.showtime?.sound?.name) badgesHtml += `<span class="ticket-format-badge">${order.showtime.sound.name}</span>`;
        if (order.showtime?.subtitle?.name) badgesHtml += `<span class="ticket-format-badge">${order.showtime.subtitle.name}</span>`;

        const title = order.movie_title || order.showtime?.movie?.title || 'N/A';
        const ticketId = `ID: #CP-${String(order.id).padStart(5, '0')}`;
        
        const rawDate = order.show_date || order.showtime?.scheduled_at;
        const showtimeText = rawDate 
            ? `${new Date(rawDate).toLocaleDateString('vi-VN')} - ${new Date(rawDate).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`
            : 'N/A';

        const branch = order.branch_name || order.showtime?.screen?.theater?.branch?.name || '';
        const screen = order.screen_name || order.showtime?.screen?.name || '';
        const theaterText = branch && screen ? `${branch} - ${screen}` : (branch || screen || 'N/A');

        let seatsText = 'N/A';
        if (order.order_items) {
            const names = order.order_items.map(i => i.metadata?.seat_label || i.seat?.seat_number).filter(Boolean).join(', ');
            if (names) seatsText = names;
        }

        const statusHtml = this.getTicketStatusHTML(status);

        return `
        <div class="ticket-card" data-status="${status}" style="cursor: pointer;">
            <!-- Poster -->
            <div class="ticket-poster-wrap">
                <img class="ticket-poster" src="${posterSrc}" alt="${posterAlt}" onerror="this.src='/images/default-poster.jpg'">
                <div class="ticket-cancelled-overlay" style="display: ${status === 'cancelled' ? 'flex' : 'none'};">CANCELLED</div>
            </div>

            <!-- Content -->
            <div class="ticket-body">
                <!-- Top row: title + formats + ID -->
                <div class="ticket-top-row">
                    <div class="ticket-title-row">
                        <h3 class="ticket-title">${title}</h3>
                        <div class="ticket-formats">${badgesHtml}</div>
                    </div>
                    <span class="ticket-id">${ticketId}</span>
                </div>

                <!-- Info grid: 2 columns -->
                <div class="ticket-info-grid">
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">NGÀY CHIẾU</span>
                        <span class="ticket-showtime ticket-info-value">${showtimeText}</span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">RẠP CHIẾU</span>
                        <span class="ticket-theater ticket-info-value">${theaterText}</span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">GHẾ</span>
                        <span class="ticket-seats ticket-info-value">${seatsText}</span>
                    </div>
                </div>

                <!-- Bottom row: status + detail button -->
                <div class="ticket-bottom-row">
                    <span class="ticket-status">${statusHtml}</span>
                    <button type="button" class="ticket-detail-btn">Xem chi tiết</button>
                </div>
            </div>
        </div>
        `;
    }

    getTicketStatusHTML(status) {
        const config = {
            completed: { dot: '#22c55e', label: 'CONFIRMED', color: '#22c55e' },
            confirmed: { dot: '#22c55e', label: 'CONFIRMED', color: '#22c55e' },
            pending:   { dot: '#f59e0b', label: 'PENDING',   color: '#f59e0b' },
            cancelled: { dot: '#ed0712', label: 'ĐÃ HỦY',    color: '#ed0712' },
        }[status] || { dot: '#6b7280', label: String(status || 'Không rõ').toUpperCase(), color: '#6b7280' };

        return `
            <div style="display:inline-flex; align-items:center; gap:6px; background:${config.color}15; padding:6px 12px; border-radius:20px;">
                <div style="width:8px; height:8px; border-radius:50%; background:${config.dot};"></div>
                <span style="color:${config.color}; font-size:12px; font-weight:700; letter-spacing:0.5px;">${config.label}</span>
            </div>
        `;
    }"""

content = re.sub(old_create_ticket_card, new_create_ticket_card, content)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Refactored tickets logic successfully!")
