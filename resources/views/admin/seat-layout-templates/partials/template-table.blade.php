<div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead style="border-bottom: 1px solid var(--border-color);">
            <tr>
                <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                <th class="text-secondary fw-semibold border-0">Tên mẫu sơ đồ ghế</th>
                <th class="text-secondary fw-semibold border-0">Ma trận</th>
                <th class="text-secondary fw-semibold border-0">Trạng thái</th>
                <th class="text-center text-secondary fw-semibold border-0">Hoạt động</th>
                <th class="text-center text-secondary fw-semibold border-0" style="width: 140px;">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $index => $template)
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td class="text-center text-white-50">{{ $templates->firstItem() + $index }}</td>
                    <td class="fw-medium text-white">
                        <div>{{ $template->template_name }}</div>
                        @if($template->description)
                            <div class="small text-white-50">{{ \Illuminate\Support\Str::limit($template->description, 60) }}</div>
                        @endif
                    </td>
                    <td class="text-light small">{{ $template->seat_matrix ?: '—' }}</td>
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
                    <td colspan="6" class="text-center py-5 text-muted">
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
