document.addEventListener('DOMContentLoaded', function() {
    loadPricingData();
});

async function loadPricingData() {
    const tabsContainer = document.getElementById('pricing-tabs-container');
    const tablesContainer = document.getElementById('pricing-tables-container');
    
    try {
        const response = await fetch('/api/v1/prices');
        const json = await response.json();
        
        if (!response.ok || !json.success) {
            throw new Error(json.message || 'Failed to fetch prices');
        }

        const theatersData = json.data.theaters;
        
        tabsContainer.innerHTML = '';
        tablesContainer.innerHTML = '';
        
        if (!theatersData || theatersData.length === 0) {
            document.getElementById('pricingTabsSkeleton')?.classList.add('d-none');
            document.getElementById('pricingTableSkeleton')?.classList.add('d-none');
            tabsContainer.classList.remove('d-none');
            tablesContainer.classList.remove('d-none');
            
            tablesContainer.innerHTML = '<div class="text-center py-5 text-white">Không có dữ liệu bảng giá.</div>';
            return;
        }

        tabsContainer.innerHTML = theatersData.map((data, index) => {
            const isActive = index === 0;
            return `<button class="p-tab-btn ${isActive ? 'active' : ''}" data-target="theater-${data.theater.id}">${data.theater.name}</button>`;
        }).join('');

        // Add event listeners for tabs
        tabsContainer.querySelectorAll('.p-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                tabsContainer.querySelectorAll('.p-tab-btn').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const targetId = this.getAttribute('data-target');
                document.querySelectorAll('.theater-pricing-wrapper').forEach(w => {
                    w.style.display = (w.id === targetId) ? 'block' : 'none';
                });
            });
        });

        tablesContainer.innerHTML = theatersData.map((data, index) => {
            const isActive = index === 0;
            const theater = data.theater;
            
            let tablesHtml = data.format_tables.map(fTable => {
                const rowsHtml = fTable.rows.map(row => `
                    <tr class="row-light">
                        <td class="col-title">
                            <div class="title" style="font-size: 1.2rem;">${row.title}</div>
                            <div class="desc">Ghế Standard</div>
                        </td>
                        <td class="price-col">${Number(row.adult).toLocaleString('vi-VN')}đ</td>
                        <td class="price-col">${Number(row.u22).toLocaleString('vi-VN')}đ</td>
                        <td class="price-col">${Number(row.child).toLocaleString('vi-VN')}đ</td>
                    </tr>
                `).join('');

                const notesText = fTable.seat_notes ? `* ${fTable.seat_notes}` : `* Giá vé cho định dạng này chưa bao gồm phụ thu ghế đặc biệt (nếu có).`;

                return `
                    <h3 class="mt-4 mb-3" style="color: var(--cinema-danger); font-weight: 800; text-transform: uppercase;">
                        ĐỊNH DẠNG: ${fTable.format.name}
                    </h3>
                    <table class="galaxy-table mb-2">
                        <thead>
                            <tr>
                                <th class="col-room">THỜI GIAN / LOẠI GHẾ</th>
                                <th>NGƯỜI LỚN</th>
                                <th>U22 - HỌC SINH<br>& SINH VIÊN</th>
                                <th>TRẺ EM &<br>NGƯỜI CAO TUỔI</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <div class="mb-4" style="color: #a1a1aa; font-size: 0.85rem; font-style: italic;">
                        ${notesText}
                    </div>
                `;
            }).join('');

            return `
                <div class="theater-pricing-wrapper" id="theater-${theater.id}" style="display: ${isActive ? 'block' : 'none'};">
                    <div class="pricing-card" style="background: transparent; border: none; box-shadow: none;">
                        ${tablesHtml}
                        <div class="table-notes mt-4">
                            <h5 style="color: var(--cinema-danger); font-weight: 800; font-size: 1.1rem; margin-bottom: 12px;">* LƯU Ý KHI ĐẶT VÉ</h5>
                            <p class="mb-0" style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem; line-height: 1.6;">
                                - <strong>Hệ thống đặt vé online mặc định tính giá vé Người lớn.</strong><br>
                                - Khách hàng thuộc đối tượng U22, Học sinh - Sinh viên, Trẻ em, Người cao tuổi vui lòng <strong>xuất trình giấy tờ tùy thân / thẻ HSSV</strong> tại quầy vé. Nhân viên rạp sẽ áp dụng giảm giá trực tiếp trên hệ thống tại quầy.<br>
                                - Vé Trẻ em áp dụng cho trẻ em cao từ 0.7m đến 1.3m. Vé Người cao tuổi áp dụng cho khách hàng từ 55 tuổi trở lên.<br>
                                - Thành viên được tích điểm thưởng theo các cấp độ.
                            </p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Hide skeletons and show content
        document.getElementById('pricingTabsSkeleton')?.classList.add('d-none');
        document.getElementById('pricingTableSkeleton')?.classList.add('d-none');
        tabsContainer.classList.remove('d-none');
        tablesContainer.classList.remove('d-none');
        
    } catch (error) {
        console.error('Error fetching prices:', error);
        
        document.getElementById('pricingTabsSkeleton')?.classList.add('d-none');
        document.getElementById('pricingTableSkeleton')?.classList.add('d-none');
        tabsContainer.classList.remove('d-none');
        tablesContainer.classList.remove('d-none');
        
        tabsContainer.innerHTML = '';
        tablesContainer.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle text-danger fs-1"></i>
                <div class="mt-3 text-white">Đã xảy ra lỗi khi tải dữ liệu. Vui lòng thử lại sau.</div>
            </div>
        `;
    }
}
