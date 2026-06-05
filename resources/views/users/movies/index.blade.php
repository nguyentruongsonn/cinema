@extends('layouts.app')

@section('title', 'Movies - Cinema Premium')
@section('meta_description', 'Browse all movies currently showing and coming soon at Cinema Premium.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/skeleton.css') }}">
<link rel="stylesheet" href="{{ asset('css/movies.css') }}">
@endpush

@section('content')
    {{-- Movies Header --}}
    <section class="movies-header">
        <div class="container">
            <h1 class="page-title">Movies</h1>
            <p class="page-subtitle">Discover the latest films showing at Cinema Premium</p>
        </div>
    </section>

    {{-- Filters Section --}}
    <section class="movies-filters-section">
        <div class="container">
            {{-- Skeleton --}}
            <div id="filtersSkeleton" class="skeleton skeleton-filters"></div>

            {{-- Actual Filters (hidden until loaded) --}}
            <div id="filtersContent" class="filters-content d-none">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="statusFilter" class="filter-label">Status</label>
                        <select id="statusFilter" class="filter-select">
                            <option value="active">All Active</option>
                            <option value="now_showing">Now Showing</option>
                            <option value="upcoming">Coming Soon</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="categoryFilter" class="filter-label">Category</label>
                        <select id="categoryFilter" class="filter-select">
                            <option value="">All Categories</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="sortFilter" class="filter-label">Sort By</label>
                        <select id="sortFilter" class="filter-select">
                            <option value="release_date-desc">Latest Release</option>
                            <option value="release_date-asc">Oldest Release</option>
                            <option value="title-asc">Title A-Z</option>
                            <option value="title-desc">Title Z-A</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <div class="search-box">
                            <input type="text" id="searchInput" class="search-input" placeholder="Search movies...">
                            <button type="button" id="searchBtn" class="search-btn">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Movies Grid Section --}}
    <section class="movies-listing-section">
        <div class="container">
            {{-- Skeleton Grid --}}
            <div id="moviesSkeleton" class="skeleton-grid">
                @for ($i = 0; $i < 12; $i++)
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- Actual Movies Grid (hidden until loaded) --}}
            <div id="moviesGrid" class="movies-grid d-none"></div>

            {{-- Empty State --}}
            <div id="emptyState" class="empty-state d-none">
                <i class="bi bi-film"></i>
                <h3>No movies found</h3>
                <p>Try adjusting your filters or search query</p>
            </div>

            {{-- Pagination --}}
            <div id="paginationContainer" class="pagination-container d-none"></div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/movies.js') }}"></script>
@endpush