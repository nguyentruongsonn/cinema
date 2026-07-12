import sys

path = "public/js/users/pages/booking.js"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# The methods are currently at the bottom, after the class end `}` and before `document.addEventListener`
wrong_code = """}

// Initialize when DOM is ready

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

document.addEventListener('DOMContentLoaded', () => {"""

correct_code = """
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
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {"""

if wrong_code in content:
    content = content.replace(wrong_code, correct_code)
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    print("Fixed syntax error in booking.js successfully!")
else:
    print("Could not find the exact wrong code block.")
