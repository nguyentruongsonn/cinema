@extends('layouts.app')

@section('title', 'Movie Details - Cinema Premium')
@section('meta_description', 'View movie details and book tickets at Cinema Premium.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
<link rel="stylesheet" href="{{ asset('css/users/pages/movie-detail.css') }}">
@endpush

@section('content')
    {{-- Movie Hero Section (Full Width) --}}
    <section class="movie-detail-hero">
        {{-- Skeleton --}}
        <div id="heroSkeleton" class="skeleton-movie-hero">
            <div class="container">
                <div class="skeleton-hero-layout">
                    <div class="skeleton skeleton-hero-poster"></div>
                    <div class="skeleton-hero-info">
                        <div class="skeleton skeleton-hero-title"></div>
                        <div class="skeleton skeleton-hero-meta"></div>
                        <div class="skeleton skeleton-hero-text"></div>
                        <div class="skeleton skeleton-hero-text"></div>
                        <div class="skeleton skeleton-hero-button"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actual Hero Content (hidden until loaded) --}}
        <div id="heroContent" class="d-none"></div>
    </section>

    {{-- Main Content Section with Sidebar (Centered Container) --}}
    <section class="content-with-sidebar">
        <div class="container">
            <div class="content-layout">
                {{-- Main Content Column --}}
                <div class="main-content">
                    {{-- Section Header with Theater Filter --}}
                    <div class="cinema-section-header">
                        <h2 class="cinema-section-title">Select Showtime</h2>

                        <div id="theaterFilterContainer" class="cinema-theater-filter-container d-none">
                            <label class="cinema-filter-label">
                                <i class="bi bi-geo-alt"></i>
                                Select Cinema
                            </label>
                            <select id="theaterFilter" class="form-select cinema-theater-filter">
                                <option value="">All Cinemas</option>
                            </select>
                        </div>
                    </div>

                    {{-- Skeleton Loading --}}
                    <div id="showtimesSkeleton" class="skeleton-showtimes">
                        <div class="skeleton skeleton-showtime-date"></div>
                        <div class="skeleton skeleton-showtime-grid"></div>
                        <div class="skeleton skeleton-showtime-grid"></div>
                    </div>

                    {{-- Actual Showtimes Content (hidden until loaded) --}}
                    <div id="showtimesContent" class="d-none">
                        {{-- Date tabs will be inserted here by JS --}}
                        {{-- Format groups will be inserted here by JS --}}
                    </div>

                    {{-- Empty State --}}
                    <div id="noShowtimes" class="empty-state d-none">
                        <i class="bi bi-calendar-x"></i>
                        <h3>No Showtimes Available</h3>
                        <p>There are currently no showtimes for this movie. Please check back later.</p>
                    </div>
                </div>

                {{-- Sidebar Column --}}
                <aside class="sidebar-content">
                    {{-- Trending Movies --}}
                    <div class="trending-section">
                        <div class="trending-header">
                            <i class="bi bi-graph-up-arrow"></i>
                            <h3>Trending Now</h3>
                        </div>

                        {{-- Skeleton Loading --}}
                        <div id="trendingSkeleton" class="trending-skeleton">
                            <div class="skeleton skeleton-trending-item"></div>
                            <div class="skeleton skeleton-trending-item"></div>
                            <div class="skeleton skeleton-trending-item"></div>
                        </div>

                        {{-- Trending Movies List --}}
                        <div id="trendingContent" class="trending-list d-none">
                            {{-- Will be populated by JS --}}
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/users/pages/movie-detail.js') }}"></script>
@endpush
