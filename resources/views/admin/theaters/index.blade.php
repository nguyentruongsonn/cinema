@extends('layouts.admin')

@section('title', 'Quản lý rạp chiếu')
@section('header_title', 'Quản lý rạp chiếu')
@section('header_subtitle', 'Xem và quản lý danh sách các rạp chiếu phim.')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- ── Dòng 1 + Dòng 2: Header & Filter Bar ────────────────────────── --}}
<div class="filter-bar mb-4">
    <div class="filter-bar-inner align-items-center w-100">
        <h5 class="mb-0 text-white fw-bold me-4"><i class="bi bi-camera-reels me-2"></i>Danh sách rạp chiếu</h5>
        
        <form action="{{ route('admin.theaters.index') }}" method="GET" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="filter-group flex-grow-1" style="max-width: 400px;">
                <label for="search" class="filter-label" style="display:none;">Tìm kiếm</label>
                <div class="input-group">
                    <input type="text" name="search" class="filter-input" placeholder="Tìm rạp chiếu..." value="{{ request('search') }}" style="border-radius: 6px 0 0 6px;">
                    <button class="btn btn-outline-secondary border-0" style="background: rgba(255,255,255,0.05); border-radius: 0 6px 6px 0;" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>

        <button type="button" class="btn-primary-custom ms-auto border-0" id="btnCreateTheater">
            <i class="bi bi-plus-lg"></i> Thêm rạp chiếu
        </button>
    </div>
</div>

{{-- ── Dòng 3: Table ───────────────────────────────────────────────── --}}
<div class="chart-card">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
            <thead style="border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                    <th class="text-secondary fw-semibold border-0">Tên rạp</th>
                    <th class="text-secondary fw-semibold border-0">Chi nhánh</th>
                    <th class="text-secondary fw-semibold border-0">Địa chỉ</th>
                    <th class="text-center text-secondary fw-semibold border-0">Hoạt động</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($theaters as $index => $theater)
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td class="text-center text-white-50">{{ $theaters->firstItem() + $index }}</td>
                    <td class="fw-medium text-white">{{ $theater->name }}</td>
                    <td>
                        @if($theater->branch)
                            <span class="badge" style="background: rgba(229,9,20,0.1); color: #e50914;">{{ $theater->branch->name }}</span>
                        @else
                            <span class="text-white-50 small">- Không có -</span>
                        @endif
                    </td>
                    <td class="text-light small">{{ $theater->address }}</td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-active-btn" type="checkbox" role="switch" 
                                data-id="{{ $theater->id }}" 
                                {{ $theater->status ? 'checked' : '' }}
                                style="cursor: pointer;">
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-edit-theater" 
                                style="color: var(--text-secondary); background: rgba(255,255,255,0.05);" 
                                data-id="{{ $theater->id }}"
                                data-name="{{ $theater->name }}"
                                data-branch-id="{{ $theater->branch_id }}"
                                data-address="{{ $theater->address }}"
                                data-phone="{{ $theater->phone }}"
                                data-email="{{ $theater->email }}"
                                data-description="{{ e($theater->description ?? '') }}"
                                data-status="{{ $theater->status ? '1' : '0' }}"
                                title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="{{ route('admin.theaters.destroy', $theater) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa rạp chiếu này?');">
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
                        Không tìm thấy rạp chiếu nào.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($theaters->hasPages())
    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);">
        {{ $theaters->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- ── Modal: Thêm / Sửa Rạp Chiếu ────────────────────────────────── --}}
<div class="modal fade" id="theaterModal" tabindex="-1" aria-labelledby="theaterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="theaterModalLabel">Thêm rạp chiếu mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="theaterForm" action="{{ route('admin.theaters.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="{{ old('_method', 'POST') }}">
                <input type="hidden" name="theater_id" id="theaterIdInput" value="{{ old('theater_id') }}">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="theaterName" class="form-label text-secondary">Tên rạp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary @error('name') is-invalid @enderror" id="theaterName" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="theaterBranch" class="form-label text-secondary">Chi nhánh <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-white border-secondary @error('branch_id') is-invalid @enderror" id="theaterBranch" name="branch_id" required>
                                <option value="">-- Chọn chi nhánh --</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="theaterAddress" class="form-label text-secondary">Địa chỉ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary @error('address') is-invalid @enderror" id="theaterAddress" name="address" value="{{ old('address') }}" required>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="theaterDescription" class="form-label text-secondary">Mô tả</label>
                        <textarea class="form-control bg-dark text-white border-secondary @error('description') is-invalid @enderror" id="theaterDescription" name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="theaterStatus" name="status" value="1" {{ old('status', true) ? 'checked' : '' }} style="cursor: pointer;">
                            <label class="form-check-label text-white" for="theaterStatus" style="cursor: pointer;">Cho phép hoạt động</label>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="theaterPhone" class="form-label text-secondary">Số điện thoại</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary @error('phone') is-invalid @enderror" id="theaterPhone" name="phone" value="{{ old('phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="theaterEmail" class="form-label text-secondary">Email</label>
                            <input type="email" class="form-control bg-dark text-white border-secondary @error('email') is-invalid @enderror" id="theaterEmail" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white" style="background: rgba(255,255,255,0.1);" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu rạp chiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/theaters.js') }}?v={{ time() }}" defer></script>
@endpush
