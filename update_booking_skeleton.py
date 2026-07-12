import re

# Update booking/index.blade.php
blade_path = "resources/views/users/booking/index.blade.php"
with open(blade_path, "r", encoding="utf-8") as f:
    blade_content = f.read()

blade_old = r"""                        <!-- Skeleton Loading -->
                        <div class="seat-map-skeleton">
                            <div class="skeleton-rows">
                                <div class="skeleton-row" v-for="i in 10">
                                    <div class="skeleton-seat" v-for="j in 15"></div>
                                </div>
                            </div>
                        </div>"""

blade_new = """                        <!-- Skeleton Loading -->
                        <div class="seat-map-skeleton w-100" id="seatMapSkeleton"></div>"""

blade_content = re.sub(blade_old, blade_new, blade_content)

blade_food_old = r"""                        <div class="text-center py-5">
                            <div class="spinner-border text-danger" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Đang tải combo...</p>
                        </div>"""
blade_food_new = """                        <!-- Skeleton will be rendered by JS -->"""
blade_content = blade_content.replace(blade_food_old, blade_food_new)

with open(blade_path, "w", encoding="utf-8") as f:
    f.write(blade_content)


# Update booking.js
js_path = "public/js/users/pages/booking.js"
with open(js_path, "r", encoding="utf-8") as f:
    js_content = f.read()

# Replace this.seatMapSkeleton = document.querySelector('.seat-map-skeleton');
# with this.seatMapSkeleton = document.getElementById('seatMapSkeleton');
js_content = js_content.replace("document.querySelector('.seat-map-skeleton')", "document.getElementById('seatMapSkeleton')")


# Find init() function to append skeleton init
init_match = re.search(r"    init\(\) \{\n", js_content)
if init_match:
    pos = init_match.end()
    insertion = """        if (this.seatMapSkeleton) {
            this.seatMapSkeleton.innerHTML = this.createSeatMapSkeleton();
            this.seatMapSkeleton.classList.remove('d-none');
        }
"""
    js_content = js_content[:pos] + insertion + js_content[pos:]

# Find loadProducts() to insert food skeleton
load_products_match = re.search(r"    async loadProducts\(\) \{\n        if \(!this\.productsContainer\) return;\n", js_content)
if load_products_match:
    pos = load_products_match.end()
    insertion = """        this.productsContainer.innerHTML = this.createFoodSkeleton();
"""
    js_content = js_content[:pos] + insertion + js_content[pos:]

# Add createSeatMapSkeleton and createFoodSkeleton at the end of class BookingManager
# Let's just insert it before the closing bracket of the class
end_pos = js_content.rfind("}")
if end_pos != -1:
    skeletons = """
    createSeatMapSkeleton() {
        const rows = 10;
        const cols = 15;
        let html = '';
        for (let i = 0; i < rows; i++) {
            html += `<div class="skel-seat-row">`;
            for (let j = 0; j < cols; j++) {
                html += `<div class="skel-seat profile-skeleton"></div>`;
            }
            html += `</div>`;
        }
        return html;
    }

    createFoodSkeleton() {
        let html = '';
        for (let i = 0; i < 4; i++) {
            html += `
                <div class="skel-food-card">
                    <div class="skel-food-img profile-skeleton"></div>
                    <div class="skel-food-info">
                        <div class="skel-food-title profile-skeleton"></div>
                        <div class="skel-food-desc profile-skeleton"></div>
                        <div class="skel-food-price profile-skeleton"></div>
                    </div>
                </div>
            `;
        }
        return html;
    }
"""
    # The last `}` is the class `BookingManager { ... }` closing bracket
    # Wait, the last `}` might be something else. Let's find `document.addEventListener` 
    # to find the end of the class.
    # We will search for `    }` at the end of the class.
    # Actually, we can use regex to find the end of class BookingManager
    # Let's insert before `document.addEventListener('DOMContentLoaded', () => {`
    event_pos = js_content.find("document.addEventListener('DOMContentLoaded'")
    if event_pos != -1:
        # The class ends right before this
        js_content = js_content[:event_pos] + skeletons + "\n" + js_content[event_pos:]


with open(js_path, "w", encoding="utf-8") as f:
    f.write(js_content)

print("Updated booking skeleton successfully!")
