@extends('layouts.admin')

@section('title', 'Cập nhật chi nhánh')
@section('header_title', 'Cập nhật chi nhánh')
@section('header_subtitle', 'Chỉnh sửa thông tin của chi nhánh rạp chiếu.')

@section('content')

<div class="row">
    <div class="col-xl-8 col-lg-10">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title"><i class="bi bi-pencil-square me-2"></i>Thông tin chi nhánh: {{ $branch->name }}</h3>
            </div>
            
            <form action="{{ route('admin.branches.update', $branch) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="mb-3">
                    <label for="name" class="form-label text-secondary">Tên chi nhánh <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $branch->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="code" class="form-label text-secondary">Mã chi nhánh <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $branch->code) }}" required>
                    <div class="form-text text-secondary opacity-75">Mã chi nhánh dùng để định danh, ví dụ: HN-01, HCM-02. Cần là duy nhất.</div>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label text-secondary">Mô tả chi tiết</label>
                    <textarea class="form-control bg-dark text-white border-secondary @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $branch->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $branch->is_active) ? 'checked' : '' }} style="cursor: pointer;">
                        <label class="form-check-label text-white" for="is_active" style="cursor: pointer;">Cho phép hoạt động</label>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4 pt-3" style="border-top: 1px solid var(--border-color);">
                    <a href="{{ route('admin.branches.index') }}" class="btn text-white" style="background: rgba(255,255,255,0.1);">Hủy bỏ</a>
                    <button type="submit" class="btn-primary-custom border-0">Cập nhật chi nhánh</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/stats.css') }}?v={{ time() }}">
@endpush
