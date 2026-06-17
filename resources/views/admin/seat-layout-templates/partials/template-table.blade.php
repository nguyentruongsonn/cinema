<div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead style="border-bottom: 1px solid var(--border-color);">
            <tr>
                <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                <th class="text-secondary fw-semibold border-0">Tên mẫu sơ đồ ghế</th>
                <th class="text-secondary fw-semibold border-0">Ma trận ghế</th>
                <th class="text-secondary fw-semibold border-0">Phân bổ hàng</th>
                <th class="text-secondary fw-semibold border-0">Trạng thái</th>
                <th class="text-center text-secondary fw-semibold border-0">Hoạt động</th>
                <th class="text-center text-secondary fw-semibold border-0" style="width: 140px;">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $index => $template)
                @php
                    $matrixPresets = [
                        '12x12' => ['capacity' => 144, 'rows' => 12],
                        '13x13' => ['capacity' => 169, 'rows' => 13],
                        '14x14' => ['capacity' => 196, 'rows' => 14],
                        '15x15' => ['capacity' => 225, 'rows' => 15],
                    ];
                    $preset = $matrixPresets[$template->seat_matrix] ?? null;
                @endphp
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td class="text-center text-white-50">{{ $templates->firstItem() + $index }}</td>
                    <td class="fw-medium text-white">
                        <div>{{ $template->template_name }}</div>
                        @if($template->description)
                            <div class="small text-white-50">{{ \Illuminate\Support\Str::limit($template->description, 60) }}</div>
                        @endif
                    </td>
                    <td>
                        @if($preset)
                            <div class="d-flex flex-column gap-1">
                                <span class="badge" style="background: rgba(229,9,20,0.15); color:#e50914; font-size:0.78rem; width:fit-content;">
                                    <i class="bi bi-grid-3x3 me-1"></i>{{ $template->seat_matrix }}
                                </span>
                                <span class="small text-white-50">
                                    <i class="bi bi-person-fill me-1" style="color:#60a5fa;"></i>{{ $preset['capacity'] }} chỗ &nbsp;·&nbsp;
                                    <i class="bi bi-list-ol me-1" style="color:#34d399;"></i>{{ $preset['rows'] }} hàng
                                </span>
                            </div>
                        @else
                            <span class="text-white-50 small">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $total = ($template->regular_seat_rows ?? 0) + ($template->vip_seat_rows ?? 0) + ($template->couple_seat_rows ?? 0);
                        @endphp
                        <div class="d-flex flex-wrap gap-1">
                            @if($template->regular_seat_rows > 0)
                                <span class="badge" style="background: rgba(96,165,250,0.15); color:#60a5fa; font-size:0.73rem;">
                                    Thường: {{ $template->regular_seat_rows }}
                                </span>
                            @endif
                            @if($template->vip_seat_rows > 0)
                                <span class="badge" style="background: rgba(245,158,11,0.15); color:#f59e0b; font-size:0.73rem;">
                                    VIP: {{ $template->vip_seat_rows }}
                                </span>
                            @endif
                            @if($template->couple_seat_rows > 0)
                                <span class="badge" style="background: rgba(236,72,153,0.15); color:#ec4899; font-size:0.73rem;">
                                    Đôi: {{ $template->couple_seat_rows }}
                                </span>
                            @endif
                            @if($total === 0)
                                <span class="text-white-50 small">—</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($template->status)
                            <span class="badge bg-success">Đã xuất bản</span>
                        @else
                            <span class="badge bg-secondary">Bản nháp</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-active-btn" type="checkbox" role="switch"
                                data-id="{{ $template->id }}"
                                {{ $template->status ? 'checked' : '' }}
                                style="cursor: pointer;">
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-seat-layout-template"
                                style="color: var(--text-secondary); background: rgba(255,255,255,0.05);"
                                data-bs-toggle="modal"
                                data-bs-target="#seatLayoutTemplateModal"
                                data-id="{{ $template->id }}"
                                data-template-name="{{ $template->template_name }}"
                                data-seat-matrix="{{ $template->seat_matrix }}"
                                data-regular-seats="{{ $template->regular_seat_rows }}"
                                data-vip-seats="{{ $template->vip_seat_rows }}"
                                data-couple-seats="{{ $template->couple_seat_rows }}"
                                data-description="{{ e($template->description ?? '') }}"
                                data-status="{{ $template->status ? '1' : '0' }}"
                                title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="{{ route('admin.seat-layout-templates.destroy', $template) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa mẫu sơ đồ ghế này?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm ms-1" style="color: #ef4444; background: rgba(239,68,68,0.1);" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        {{ $emptyText ?? 'Không tìm thấy mẫu sơ đồ ghế nào.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($templates->hasPages())
    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);">
        {{ $templates->links('pagination::bootstrap-5') }}
    </div>
@endif
