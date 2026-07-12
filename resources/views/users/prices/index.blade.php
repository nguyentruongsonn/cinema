@extends('layouts.app')

@section('title', 'Bảng giá vé - Cinema')
@section('meta_description', 'Xem bảng giá vé chi tiết cho từng rạp chiếu phim và định dạng phim.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users/pages/prices.css') }}">
@endpush

@section('content')
    <!-- Header Section -->
    <section class="pricing-header">
        <div class="container">
            <div class="pricing-badge">Bảng giá vé</div>
            <h1 class="pricing-title">Trải nghiệm điện ảnh đỉnh cao</h1>
            <p class="pricing-desc">
                Giá vé có thể thay đổi tùy theo từng rạp, định dạng phim và thời điểm xem phim. 
                Vui lòng chọn rạp bên dưới để xem bảng giá chi tiết.
            </p>
        </div>
    </section>

    <!-- Tabs Section -->
    <section class="pricing-tabs-container">
        <div class="container">
            <!-- Tabs Skeleton -->
            <div class="pricing-tabs" id="pricingTabsSkeleton">
                @for($i = 0; $i < 4; $i++)
                    <div class="skeleton skeleton-button"></div>
                @endfor
            </div>
            
            <!-- Tabs Content -->
            <div class="pricing-tabs d-none" id="pricing-tabs-container">
                <!-- Dynamically loaded tabs -->
            </div>
        </div>
    </section>

    <!-- Pricing Table Section -->
    <section class="pricing-table-section">
        <div class="container">
            <!-- Table Skeleton -->
            <div id="pricingTableSkeleton">
                <div class="theater-pricing-wrapper" style="display: block;">
                    <div class="pricing-card">
                        <div class="skeleton skeleton-title"></div>
                        
                        <table class="galaxy-table mb-2">
                            <thead>
                                <tr>
                                    <th class="col-room"><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                    <th><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                    <th><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                    <th><div class="skeleton skeleton-text-short mx-auto"></div></th>
                                </tr>
                            </thead>
                            <tbody>
                                @for($j = 0; $j < 3; $j++)
                                <tr class="row-light">
                                    <td class="col-title">
                                        <div class="skeleton skeleton-text"></div>
                                        <div class="skeleton skeleton-text-short"></div>
                                    </td>
                                    <td class="price-col"><div class="skeleton skeleton-text mx-auto"></div></td>
                                    <td class="price-col"><div class="skeleton skeleton-text mx-auto"></div></td>
                                    <td class="price-col"><div class="skeleton skeleton-text mx-auto"></div></td>
                                </tr>
                                @endfor
                            </tbody>
                        </table>
                        
                        <div class="skeleton skeleton-text skeleton-text-lg"></div>

                        <div class="table-notes mt-4">
                            <div class="skeleton skeleton-title"></div>
                            <div class="skeleton skeleton-text"></div>
                            <div class="skeleton skeleton-text skeleton-text-short"></div>
                            <div class="skeleton skeleton-text skeleton-text-xs"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Content -->
            <div id="pricing-tables-container" class="d-none">
                <!-- Dynamically loaded pricing tables -->
            </div>
        </div>
    </section>

@endsection

@push('scripts')
<script type="module" src="{{ asset('js/users/pages/prices.js') }}"></script>
@endpush
