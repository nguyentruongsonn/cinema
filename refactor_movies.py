import sys

path = "public/js/admin/pages/dashboard.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

old_movies_block = """        let html = '';
        movies.forEach(movie => {
            const poster = movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
            const revenue = formatCurrency(movie.revenue);
            const tickets = new Intl.NumberFormat('vi-VN').format(movie.tickets_sold);
            
            html += `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card" style="background-image: url('${escapeHtml(poster)}')">
                    <div class="movie-tag gradient-red">${revenue}</div>
                    <div class="movie-info">
                        <div class="movie-card-title text-truncate" title="${escapeHtml(movie.title)}">${escapeHtml(movie.title)}</div>
                        <div class="movie-meta"><i class="bi bi-ticket-perforated-fill"></i> ${tickets} vé bán ra</div>
                    </div>
                </div>
            </div>`;
        });
        
        els.topMoviesContainer.innerHTML = html;"""

new_movies_block = """        els.topMoviesContainer.innerHTML = movies.map(movie => {
            const poster = movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
            const revenue = formatCurrency(movie.revenue);
            const tickets = new Intl.NumberFormat('vi-VN').format(movie.tickets_sold);
            
            return `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card" style="background-image: url('${escapeHtml(poster)}')">
                    <div class="movie-tag gradient-red">${revenue}</div>
                    <div class="movie-info">
                        <div class="movie-card-title text-truncate" title="${escapeHtml(movie.title)}">${escapeHtml(movie.title)}</div>
                        <div class="movie-meta"><i class="bi bi-ticket-perforated-fill"></i> ${tickets} vé bán ra</div>
                    </div>
                </div>
            </div>`;
        }).join('');"""

if old_movies_block in content:
    content = content.replace(old_movies_block, new_movies_block)
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    print("Successfully refactored top movies rendering.")
else:
    print("Could not find the movies block.")
