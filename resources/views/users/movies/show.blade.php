@extends('layouts.app')

@section('title', 'Movie Details - Cinema Premium')
@section('meta_description', 'View movie details and book tickets at Cinema Premium.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
<link rel="stylesheet" href="{{ asset('css/users/pages/movie-detail.css') }}">
@endpush

@section('content')
    <section class="movie-detail-hero">
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

        <div id="heroContent" class="d-none"></div>
    </section>

    <section class="content-with-sidebar">
        <div class="container">
            <div class="content-layout">
                <div class="main-content">
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

                    <div id="showtimesSkeleton" class="skeleton-showtimes">
                        <div class="skeleton skeleton-showtime-date"></div>
                        <div class="skeleton skeleton-showtime-grid"></div>
                        <div class="skeleton skeleton-showtime-grid"></div>
                    </div>

                    <div id="showtimesContent" class="d-none">
                    </div>

                    <div id="noShowtimes" class="empty-state d-none">
                        <i class="bi bi-calendar-x"></i>
                        <h3>No Showtimes Available</h3>
                        <p>There are currently no showtimes for this movie. Please check back later.</p>
                    </div>
                </div>

                <aside class="sidebar-content">
                    <div class="trending-section">
                        <div class="trending-header">
                            <i class="bi bi-graph-up-arrow"></i>
                            <h3>Trending Now</h3>
                        </div>

                        <div id="trendingSkeleton" class="trending-skeleton">
                            <div class="skeleton skeleton-trending-item"></div>
                            <div class="skeleton skeleton-trending-item"></div>
                            <div class="skeleton skeleton-trending-item"></div>
                        </div>

                        <div id="trendingContent" class="trending-list d-none">
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <div class="modal fade" id="trailerModal" tabindex="-1" aria-labelledby="trailerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="trailerModalLabel">Watch Trailer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="ratio ratio-16x9">
                        <iframe id="trailerIframe" src="" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script type="module" src="{{ asset('js/users/pages/movie-detail.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush
