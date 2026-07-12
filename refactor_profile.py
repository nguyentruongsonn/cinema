import re

path = "resources/views/users/profile/index.blade.php"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace profile-skeleton inline styles
replacements = [
    (r'<div class="profile-skeleton rounded-circle" style="width: 56px; height: 56px; flex-shrink: 0;"></div>', r'<div class="skeleton skel-avatar rounded-circle"></div>'),
    (r'<div class="profile-skeleton rounded mb-2" style="width: 80%; height: 16px;"></div>', r'<div class="skeleton w-75 skel-subtitle mb-2 rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 50%; height: 14px;"></div>', r'<div class="skeleton w-50 skel-label rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 100%; height: 48px;"></div>', r'<div class="skeleton skel-input rounded"></div>'),
    (r'<div class="profile-skeleton" style="width: 100px; height: 100px; border-radius: 50%; border: 3px solid rgba\(255,255,255,0.05\);"></div>', r'<div class="skeleton skel-cover-avatar"></div>'),
    (r'<div class="profile-skeleton rounded mb-3" style="width: 250px; height: 32px;"></div>', r'<div class="skeleton w-50 skel-title mb-3 rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 150px; height: 16px;"></div>', r'<div class="skeleton w-25 skel-subtitle rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 200px; height: 50px;"></div>', r'<div class="skeleton skel-card-stats rounded"></div>'),
    (r'<div class="profile-skeleton rounded-circle" style="width: 20px; height: 20px;"></div>', r'<div class="skeleton skel-icon rounded-circle"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 180px; height: 24px;"></div>', r'<div class="skeleton w-50 skel-title rounded"></div>'),
    (r'<div class="profile-skeleton rounded mb-2" style="width: 80px; height: 14px;"></div>', r'<div class="skeleton w-25 skel-label mb-2 rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 100%; height: 100px;"></div>', r'<div class="skeleton skel-textarea rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 140px; height: 48px;"></div>', r'<div class="skeleton skel-btn rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 150px; height: 24px;"></div>', r'<div class="skeleton w-50 skel-title rounded"></div>'),
    (r'<div class="profile-skeleton rounded mb-2" style="width: 120px; height: 14px;"></div>', r'<div class="skeleton w-25 skel-label mb-2 rounded"></div>'),
    (r'<div class="profile-skeleton rounded mt-4" style="width: 100%; height: 48px;"></div>', r'<div class="skeleton skel-input mt-4 rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 100px; height: 24px;"></div>', r'<div class="skeleton w-25 skel-title rounded"></div>'),
    (r'<div class="profile-skeleton rounded mb-2" style="width: 100%; height: 14px;"></div>', r'<div class="skeleton w-100 skel-label mb-2 rounded"></div>'),
    (r'<div class="profile-skeleton rounded mb-4" style="width: 80%; height: 14px;"></div>', r'<div class="skeleton w-75 skel-label mb-4 rounded"></div>'),
    (r'<div class="profile-skeleton rounded" style="width: 100%; height: 40px;"></div>', r'<div class="skeleton skel-box rounded"></div>')
]

for old, new_html in replacements:
    content = re.sub(old, new_html, content)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Refactored profile index blade styles!")
