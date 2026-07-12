import os
import sys

path = "public/js/admin/pages/dashboard.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace Top Movies spinner with skeleton
old_spinner = "els.topMoviesContainer.innerHTML = `<div class=\"col-12 text-center py-5\"><div class=\"spinner-border text-danger\"></div></div>`;"
new_skeleton_movies = """        let skeletonHtml = '';
        for(let i=0; i<6; i++) {
            skeletonHtml += `
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="movie-card admin-skeleton" style="background: var(--admin-surface); border-color: transparent;"></div>
            </div>`;
        }
        els.topMoviesContainer.innerHTML = skeletonHtml;"""

if old_spinner in content:
    content = content.replace(old_spinner, new_skeleton_movies)
    print("Replaced spinner with skeleton for top movies.")
else:
    print("Could not find top movies spinner.")


# Replace fetchStats to add skeleton to stats
# In fetchStats(target = 'all'):
# We will inject a function call `showStatsSkeleton()` before `authManager.fetchAPI`.
# And we need to define `showStatsSkeleton`.

skeleton_func = """    function showStatsSkeleton() {
        const skeletonHtml = '<div class="admin-skeleton admin-skeleton-text" style="width: 60%; height: 28px; margin-bottom: 0;"></div>';
        const trendSkeletonHtml = '<div class="admin-skeleton admin-skeleton-text" style="width: 40px; margin-bottom: 0;"></div>';
        
        els.statRevenue.innerHTML = skeletonHtml;
        els.statRevenueTrend.innerHTML = trendSkeletonHtml;
        
        els.statTickets.innerHTML = skeletonHtml;
        els.statTicketsTrend.innerHTML = trendSkeletonHtml;
        
        els.statNewUsers.innerHTML = skeletonHtml;
        els.statUsersTrend.innerHTML = trendSkeletonHtml;
        
        els.statRetention.innerHTML = skeletonHtml;
    }
"""

# Insert `showStatsSkeleton` before `function fetchStats`
if "function fetchStats" in content and skeleton_func not in content:
    content = content.replace("async function fetchStats", skeleton_func + "\n    async function fetchStats")
    
    # Inject call to showStatsSkeleton() inside fetchStats()
    # Before `const url =`
    fetch_target = "const url = `${API_ENDPOINTS.stats}?range=${state.revenueFilter}`;"
    if fetch_target in content:
        content = content.replace(fetch_target, "if (target === 'all' || target === 'cards') showStatsSkeleton();\n            " + fetch_target)
        print("Injected showStatsSkeleton into fetchStats.")
    else:
        print("Could not find fetchStats url line.")

# Auto-update after 30s is already in init():
# setInterval(() => { fetchStats('all'); fetchTopMovies(); }, 30000);

with open(path, "w", encoding="utf-8") as f:
    f.write(content)
print("Done.")
