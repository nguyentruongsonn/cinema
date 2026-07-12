@extends('layouts.app')

@section('title', 'Movies - Cinema Premium')
@section('meta_description', 'Browse all movies currently showing and coming soon at Cinema Premium.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
<link rel="stylesheet" href="{{ asset('css/users/pages/movies.css') }}">
@endpush

@section('content')
    {{-- Movies Header --}}
    <section class="movies-header">
        <div class="container">
            <h1 class="page-title">Movies</h1>
        </div>
    </section>

    {{-- Main Movies Content --}}
    <div class="movies-content-wrapper">
        <div class="container">
            
            {{-- Section 1: Suất chiếu đặc biệt (Special Screenings) --}}
            <section class="movie-section special-screenings-section mb-5 d-none" id="specialSection">
                <h2 class="section-title"><i class="bi bi-star-fill text-danger me-2"></i> Suất chiếu đặc biệt</h2>
                <div class="horizontal-scroll-container">
                    <div id="specialMoviesGrid" class="special-movies-grid">
                        {{-- Injected via JS --}}
                    </div>
                </div>
            </section>

            {{-- Section 2: Phim đang chiếu (Now Showing) --}}
            <section class="movie-section now-showing-section mb-5">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h2 class="section-title mb-0">Phim đang chiếu</h2>
                    <a href="#" class="view-all-link">View All <i class="bi bi-arrow-right"></i></a>
                </div>
                
                {{-- Skeleton --}}
                <div id="nowShowingSkeleton" class="horizontal-scroll-container">
                    <div class="movies-grid-horizontal">
                        @for ($i = 0; $i < 5; $i++)
                        <div class="skeleton-movie-card">
                            <div class="skeleton skeleton-poster"></div>
                        </div>
                        @endfor
                    </div>
                </div>

                {{-- Actual Content --}}
                <div class="horizontal-scroll-container d-none" id="nowShowingContainer">
                    <div id="nowShowingGrid" class="movies-grid-horizontal">
                        {{-- Injected via JS --}}
                    </div>
                </div>
            </section>

            {{-- Section 3: Phim sắp chiếu (Coming Soon) --}}
            <section class="movie-section coming-soon-section mb-5">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h2 class="section-title mb-0">Phim sắp chiếu</h2>
                    <div class="scroll-controls">
                        <button class="btn-scroll" id="scrollPrev"><i class="bi bi-chevron-left"></i></button>
                        <button class="btn-scroll" id="scrollNext"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>

                {{-- Skeleton --}}
                <div id="comingSoonSkeleton" class="horizontal-scroll-container">
                    <div class="movies-grid-horizontal coming-soon-grid">
                        @for ($i = 0; $i < 3; $i++)
                        <div class="skeleton-movie-card-wide">
                            <div class="skeleton skeleton-poster-wide"></div>
                        </div>
                        @endfor
                    </div>
                </div>

                {{-- Actual Content --}}
                <div class="horizontal-scroll-container d-none" id="comingSoonContainer">
                    <div id="comingSoonGrid" class="movies-grid-horizontal coming-soon-grid">
                        {{-- Injected via JS --}}
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/users/pages/movies.js') }}"></script>
@endpush
