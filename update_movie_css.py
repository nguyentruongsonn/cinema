import os

css_path = 'public/css/admin/dashboard-redesign.css'
css_to_add = """
/* ── TOP MOVIES CARDS ────────────────────────────────────────────────── */
.movie-card {
    border-radius: var(--admin-radius-xl) !important;
    overflow: hidden;
    position: relative;
    aspect-ratio: 2/3;
    background-size: cover;
    background-position: center;
    border: 1px solid var(--admin-border-light);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: var(--admin-shadow-sm);
    cursor: pointer;
}
.movie-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0) 30%, rgba(0,0,0,0.6) 60%, rgba(15,15,17,0.95) 100%);
    transition: opacity 0.3s;
    pointer-events: none;
}
.movie-card:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 20px 25px -5px rgba(229, 9, 20, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
    border-color: rgba(229, 9, 20, 0.4);
    z-index: 2;
}
.movie-card:hover::after {
    opacity: 0.85;
}
.movie-tag {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0, 0, 0, 0.4) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1) !important;
    color: #fff;
    padding: 6px 14px;
    border-radius: var(--admin-radius-full);
    font-weight: 600;
    font-size: 0.8rem;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    letter-spacing: 0.02em;
}
.movie-tag.gradient-red {
    background: linear-gradient(135deg, rgba(229, 9, 20, 0.8), rgba(184, 7, 15, 0.9)) !important;
    border: 1px solid rgba(255, 100, 100, 0.3) !important;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
}
.movie-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 24px 16px 16px;
    z-index: 10;
    transform: translateY(0);
    transition: transform 0.3s;
}
.movie-card-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: #fff;
    margin-bottom: 6px;
    text-shadow: 0 2px 6px rgba(0,0,0,0.8);
    line-height: 1.3;
}
.movie-meta {
    font-size: 0.85rem;
    color: var(--admin-text-secondary);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    opacity: 0.9;
}
.movie-meta i {
    color: var(--admin-primary);
}
"""

with open(css_path, 'a', encoding='utf-8') as f:
    f.write(css_to_add)
print("CSS added successfully")
