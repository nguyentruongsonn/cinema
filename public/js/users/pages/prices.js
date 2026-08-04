document.addEventListener('DOMContentLoaded', function() {
    loadPricingData();
});

async function loadPricingData() {
    const tabsContainer = document.getElementById('pricing-tabs-container');
    const tablesContainer = document.getElementById('pricing-tables-container');
    
    try {
        const json = await window.apiClient.get('/prices');
        
        if (!json.success) {
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
            return `<button type="button" role="tab" aria-selected="${isActive}" class="p-tab-btn ${isActive ? 'active' : ''}" data-target="theater-${data.theater.id}">${data.theater.name}</button>`;
        }).join('');

        window.CinemaTabs?.initialize(tabsContainer.parentElement);

        tablesContainer.innerHTML = theatersData.map((data, index) => {
            const isActive = index === 0;
            const theater = data.theater;
            
            let tablesHtml = data.format_tables.map(fTable => {
                const rowsHtml = fTable.rows.map(row => `
                    <tr class="row-light">
                        <td class="col-title">
                            <div class="title pricing-row-title">${row.title}</div>
                            <div class="desc">Ghế Standard</div>
                        </td>
                        <td class="price-col">${Number(row.adult).toLocaleString('vi-VN')}đ</td>
                        <td class="price-col">${Number(row.u22).toLocaleString('vi-VN')}đ</td>
                        <td class="price-col">${Number(row.child).toLocaleString('vi-VN')}đ</td>
                    </tr>
                `).join('');

                const notesText = fTable.seat_notes ? `* ${fTable.seat_notes}` : `* Giá vé cho định dạng này chưa bao gồm phụ thu ghế đặc biệt (nếu có).`;

                return `
                    <h3 class="mt-4 mb-3 pricing-format-title">
                        ĐỊNH DẠNG: ${fTable.format.name}
                    </h3>
                    <div class="pricing-table-scroll">
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
                    </div>
                    <div class="mb-4 pricing-format-note">
                        ${notesText}
                    </div>
                `;
            }).join('');

            return `
                <div class="theater-pricing-wrapper ${isActive ? '' : 'd-none'}" id="theater-${theater.id}">
                    <div class="pricing-card pricing-card--plain">
                        ${tablesHtml}
                        <div class="table-notes mt-4">
                            <h5 class="pricing-note-title">* LƯU Ý KHI ĐẶT VÉ</h5>
                            <p class="mb-0 pricing-note-copy">
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

        tabsContainer.addEventListener('cinema:tab-change', event => {
            document.querySelectorAll('.theater-pricing-wrapper').forEach(wrapper => {
                wrapper.classList.toggle('d-none', wrapper.id !== event.detail.target);
            });
        });
        
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
