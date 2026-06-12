@extends('layouts.app')

@section('title', 'Cinema Premium - Đặt vé xem phim')
@section('meta_description', 'Cinema premium - đặt vé xem phim trực tuyến với trải nghiệm tối giản, hiện đại và nhanh chóng.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/skeleton.css') }}">
<link rel="stylesheet" href="{{ asset('css/home.css') }}">
@endpush

@section('content')
    {{-- Hero Section with Featured Movie --}}
    <section class="hero-section" aria-labelledby="heroTitle">
        {{-- Skeleton Loading --}}
        <div id="heroSkeleton" class="skeleton-hero">
            <div class="skeleton-hero-content">
                <div class="skeleton-hero-copy">
                    <div class="skeleton skeleton-badge"></div>
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text-short"></div>
                    <div class="d-flex gap-3 mt-4">
                        <div class="skeleton skeleton-button"></div>
                        <div class="skeleton skeleton-button"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actual Hero Content (hidden until loaded) --}}
        <div id="heroContent" class="hero-content d-none"></div>

        {{-- Quick Booking Widget --}}
        <div class="container">
            <div class="quick-booking-widget">
                {{-- Skeleton --}}
                <div id="bookingSkeleton" class="skeleton skeleton-booking"></div>

                {{-- Actual Booking Form (hidden until loaded) --}}
                <form id="bookingForm" class="booking-form d-none" action="/showtimes" method="GET">
                    <div class="booking-controls">
                        <div class="booking-control">
                            <label for="movie" class="booking-label">SELECT MOVIE</label>
                            <select id="movie" name="movie" class="booking-select" required>
                                <option value="">Loading movies...</option>
                            </select>
                        </div>

                        <div class="booking-control">
                            <label for="date" class="booking-label">DATE</label>
                            <select id="date" name="date" class="booking-select" required>
                                <option value="">Loading dates...</option>
                            </select>
                        </div>

                        <div class="booking-control">
                            <label for="cinema" class="booking-label">CINEMA</label>
                            <select id="cinema" name="cinema" class="booking-select" required>
                                <option value="">Loading cinemas...</option>
                            </select>
                        </div>

                        <div class="booking-control-btn">
                            <button type="submit" class="btn-find-seats">
                                <i class="bi bi-search"></i>
                                Find Seats
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    {{-- Now Showing Section --}}
    <section id="movies" class="movies-section" aria-labelledby="moviesTitle">
        <div class="container">
            <div class="section-header">
                <h2 id="moviesTitle" class="section-title">Now Showing</h2>
                <a href="/movies" class="view-all-link">
                    View All <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>

            {{-- Skeleton Grid --}}
            <div id="moviesSkeleton" class="skeleton-grid">
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
            </div>

            {{-- Actual Movies Grid (hidden until loaded) --}}
            <div id="moviesGrid" class="movies-grid d-none"></div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/home.js') }}"></script>
@endpush
