@extends('layouts.admin')

@section('title', 'Quản lý chi nhánh')
@section('header_title', 'Quản lý chi nhánh')
@section('header_subtitle', 'Xem và quản lý danh sách các chi nhánh rạp chiếu.')

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
        <h5 class="mb-0 text-white fw-bold me-4"><i class="bi bi-buildings me-2"></i>Danh sách chi nhánh</h5>
        
        <form action="{{ route('admin.branches.index') }}" method="GET" class="d-flex flex-grow-1 align-items-center gap-3">
            <div class="filter-group flex-grow-1" style="max-width: 400px;">
                <label for="search" class="filter-label" style="display:none;">Tìm kiếm</label>
                <div class="input-group">
                    <input type="text" name="search" class="filter-input" placeholder="Tên hoặc mã chi nhánh..." value="{{ request('search') }}" style="border-radius: 6px 0 0 6px;">
                    <button class="btn btn-outline-secondary border-0" style="background: rgba(255,255,255,0.05); border-radius: 0 6px 6px 0;" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>

        <a href="{{ route('admin.branches.create') }}" class="btn-primary-custom ms-auto" style="text-decoration:none;">
            <i class="bi bi-plus-lg"></i> Tạo chi nhánh
        </a>
    </div>
</div>

{{-- ── Dòng 3: Table ───────────────────────────────────────────────── --}}
<div class="chart-card">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
            <thead style="border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 60px;">STT</th>
                    <th class="text-secondary fw-semibold border-0">Tên chi nhánh</th>
                    <th class="text-secondary fw-semibold border-0">Mã chi nhánh</th>
                    <th class="text-center text-secondary fw-semibold border-0">Hoạt động</th>
                    <th class="text-secondary fw-semibold border-0">Ngày tạo</th>
                    <th class="text-secondary fw-semibold border-0">Ngày cập nhật</th>
                    <th class="text-center text-secondary fw-semibold border-0" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($branches as $index => $branch)
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td class="text-center text-white-50">{{ $branches->firstItem() + $index }}</td>
                    <td class="fw-medium text-white">{{ $branch->name }}</td>
                    <td><span class="badge" style="background: rgba(255,255,255,0.1); color: #fff;">{{ $branch->code }}</span></td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-active-btn" type="checkbox" role="switch" 
                                data-id="{{ $branch->id }}" 
                                {{ $branch->is_active ? 'checked' : '' }}
                                style="cursor: pointer;">
                        </div>
                    </td>
                    <td class="text-light small">{{ $branch->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-light small">{{ $branch->updated_at->format('d/m/Y H:i') }}</td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm" style="color: var(--text-secondary); background: rgba(255,255,255,0.05);" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.branches.destroy', $branch) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa chi nhánh này?');">
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
                        Không tìm thấy chi nhánh nào.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($branches->hasPages())
    <div class="d-flex justify-content-end mt-4 pt-3" style="border-top: 1px solid var(--border-color);">
        {{ $branches->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">

@endpush

@push('scripts')
<script src="{{ asset('js/pages/admin/branches.js') }}?v={{ time() }}" defer></script>
@endpush
