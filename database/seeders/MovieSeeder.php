<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        
        // Get categories
        $action = Category::where('slug', 'action')->first();
        $adventure = Category::where('slug', 'adventure')->first();
        $sciFi = Category::where('slug', 'sci-fi')->first();
        $thriller = Category::where('slug', 'thriller')->first();
        $drama = Category::where('slug', 'drama')->first();
        $crime = Category::where('slug', 'crime')->first();
        
        // Movies currently showing (release_date in past, end_date in future or null)
        $nowShowingMovies = [
            [
                'title' => 'Avengers: Endgame',
                'slug' => Str::slug('Avengers: Endgame'),
                'original_title' => 'Avengers: Endgame',
                'description' => 'After the devastating events of Avengers: Infinity War, the universe is in ruins. With the help of remaining allies, the Avengers assemble once more in order to reverse Thanos\'s actions and restore balance to the universe.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=TcMBFSGVi1c',
                'duration' => 181,
                'release_date' => $now->copy()->subDays(20)->format('Y-m-d'),
                'end_date' => $now->copy()->addDays(40)->format('Y-m-d'),
                'age_rating' => 'T13',
                'surcharge' => 0,
                'director' => 'Anthony Russo, Joe Russo',
                'cast' => 'Robert Downey Jr., Chris Evans, Mark Ruffalo, Chris Hemsworth, Scarlett Johansson',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 1,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/7RyHsO4yDXtBv1zUU3mTpHeQ0d5.jpg',
                    'https://image.tmdb.org/t/p/original/kKTPv9LKKs5L3oO1y5FNObxAPWI.jpg'
                ]),
            ],
            [
                'title' => 'The Dark Knight',
                'slug' => Str::slug('The Dark Knight'),
                'original_title' => 'The Dark Knight',
                'description' => 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the greatest psychological and physical tests of his ability to fight injustice.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=EXeTwQWrcwY',
                'duration' => 152,
                'release_date' => $now->copy()->subDays(15)->format('Y-m-d'),
                'end_date' => null, // No end date - showing indefinitely
                'age_rating' => 'T16',
                'surcharge' => 10000,
                'director' => 'Christopher Nolan',
                'cast' => 'Christian Bale, Heath Ledger, Aaron Eckhart, Michael Caine, Maggie Gyllenhaal',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 1,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/hkBaDkMWbLaf8B1lsWsKX7Ew3Xq.jpg'
                ]),
            ],
            [
                'title' => 'Inception',
                'slug' => Str::slug('Inception'),
                'original_title' => 'Inception',
                'description' => 'A thief who steals corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=YoHD9XEInc0',
                'duration' => 148,
                'release_date' => $now->copy()->subDays(10)->format('Y-m-d'),
                'end_date' => $now->copy()->addDays(30)->format('Y-m-d'),
                'age_rating' => 'T13',
                'surcharge' => 5000,
                'director' => 'Christopher Nolan',
                'cast' => 'Leonardo DiCaprio, Joseph Gordon-Levitt, Ellen Page, Tom Hardy, Ken Watanabe',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 0,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/s3TBrRGB1iav7gFOCNx3H31MoES.jpg'
                ]),
            ],
            [
                'title' => 'Interstellar',
                'slug' => Str::slug('Interstellar'),
                'original_title' => 'Interstellar',
                'description' => 'A team of explorers travel through a wormhole in space in an attempt to ensure humanity\'s survival.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=zSWdZVtXT7E',
                'duration' => 169,
                'release_date' => $now->copy()->subDays(5)->format('Y-m-d'),
                'end_date' => $now->copy()->addDays(45)->format('Y-m-d'),
                'age_rating' => 'T13',
                'surcharge' => 15000,
                'director' => 'Christopher Nolan',
                'cast' => 'Matthew McConaughey, Anne Hathaway, Jessica Chastain, Bill Irwin, Ellen Burstyn',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 1,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/xu9zaAevzQ5nnrsXN6JcahLnG4i.jpg'
                ]),
            ],
        ];

        // Upcoming movies (release_date in future)
        $upcomingMovies = [
            [
                'title' => 'Dune: Part Three',
                'slug' => Str::slug('Dune: Part Three'),
                'original_title' => 'Dune: Part Three',
                'description' => 'The epic conclusion to the Dune saga. Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=U2Qp5pL3ovA',
                'duration' => 165,
                'release_date' => $now->copy()->addDays(5)->format('Y-m-d'),
                'end_date' => null,
                'age_rating' => 'T13',
                'surcharge' => 20000,
                'director' => 'Denis Villeneuve',
                'cast' => 'Timothée Chalamet, Zendaya, Rebecca Ferguson, Josh Brolin, Austin Butler',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 1,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/fRtaDgOnUB6kjWqQdKrtLx0xhV.jpg'
                ]),
            ],
            [
                'title' => 'Avatar 3',
                'slug' => Str::slug('Avatar 3'),
                'original_title' => 'Avatar: The Seed Bearer',
                'description' => 'The third installment in the Avatar franchise, exploring new worlds and cultures of Pandora.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=d9MyW72ELq0',
                'duration' => 190,
                'release_date' => $now->copy()->addDays(15)->format('Y-m-d'),
                'end_date' => null,
                'age_rating' => 'T13',
                'surcharge' => 25000,
                'director' => 'James Cameron',
                'cast' => 'Sam Worthington, Zoe Saldana, Sigourney Weaver, Stephen Lang, Kate Winslet',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 1,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/s16H6tpK2utvwDtzZ8Qy4qm5Emw.jpg'
                ]),
            ],
            [
                'title' => 'The Matrix Resurrections 2',
                'slug' => Str::slug('The Matrix Resurrections 2'),
                'original_title' => 'The Matrix Resurrections 2',
                'description' => 'Neo and Trinity continue their journey through the Matrix in this anticipated sequel.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=nNpvWBuTfrc',
                'duration' => 155,
                'release_date' => $now->copy()->addDays(30)->format('Y-m-d'),
                'end_date' => null,
                'age_rating' => 'T16',
                'surcharge' => 10000,
                'director' => 'Lana Wachowski',
                'cast' => 'Keanu Reeves, Carrie-Anne Moss, Yahya Abdul-Mateen II, Jessica Henwick',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 0,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/icmmSD4vTTDKOq2vvdulafOGw93.jpg'
                ]),
            ],
        ];

        // Ended movies (for testing - both dates in past)
        $endedMovies = [
            [
                'title' => 'The Shawshank Redemption',
                'slug' => Str::slug('The Shawshank Redemption'),
                'original_title' => 'The Shawshank Redemption',
                'description' => 'Two imprisoned men bond over a number of years, finding solace and eventual redemption through acts of common decency.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=6hB3S9bIaco',
                'duration' => 142,
                'release_date' => $now->copy()->subDays(90)->format('Y-m-d'),
                'end_date' => $now->copy()->subDays(10)->format('Y-m-d'),
                'age_rating' => 'T16',
                'surcharge' => 0,
                'director' => 'Frank Darabont',
                'cast' => 'Tim Robbins, Morgan Freeman, Bob Gunton, William Sadler, Clancy Brown',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 0,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/kXfqcdQKsToO0OUXHcrrNCHDBzO.jpg'
                ]),
            ],
            [
                'title' => 'Pulp Fiction',
                'slug' => Str::slug('Pulp Fiction'),
                'original_title' => 'Pulp Fiction',
                'description' => 'The lives of two mob hitmen, a boxer, a gangster and his wife intertwine in four tales of violence and redemption.',
                'poster_url' => null,
                'poster_path' => null,
                'banner_path' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=s7EdQ4FqbhY',
                'duration' => 154,
                'release_date' => $now->copy()->subDays(60)->format('Y-m-d'),
                'end_date' => $now->copy()->subDays(5)->format('Y-m-d'),
                'age_rating' => 'T18',
                'surcharge' => 0,
                'director' => 'Quentin Tarantino',
                'cast' => 'John Travolta, Uma Thurman, Samuel L. Jackson, Bruce Willis, Ving Rhames',
                'status' => 1,
                'is_hidden' => 0,
                'is_hot' => 0,
                'backdrops' => json_encode([
                    'https://image.tmdb.org/t/p/original/4cDFJr4HnXN5AdPw4AKrmLlMWdO.jpg'
                ]),
            ],
        ];

        $allMovies = array_merge($nowShowingMovies, $upcomingMovies, $endedMovies);

        foreach ($allMovies as $movie) {
            Movie::updateOrCreate(
                ['slug' => $movie['slug']],
                $movie
            );
        }

        // Attach categories to movies
        $movieCategories = [
            'avengers-endgame' => [$action, $adventure, $sciFi],
            'the-dark-knight' => [$action, $crime, $thriller],
            'inception' => [$action, $sciFi, $thriller],
            'interstellar' => [$adventure, $drama, $sciFi],
            'dune-part-three' => [$adventure, $drama, $sciFi],
            'avatar-3' => [$action, $adventure, $sciFi],
            'the-matrix-resurrections-2' => [$action, $sciFi],
            'the-shawshank-redemption' => [$drama, $crime],
            'pulp-fiction' => [$crime, $thriller, $drama],
        ];

        foreach ($movieCategories as $slug => $categories) {
            $movie = Movie::where('slug', $slug)->first();
            if ($movie && !empty($categories)) {
                $categoryIds = array_filter(array_map(fn($cat) => $cat?->id, $categories));
                if (!empty($categoryIds)) {
                    $movie->categories()->sync($categoryIds);
                }
            }
        }

        $this->command->info('Movies seeded successfully!');
        $this->command->info('- Now Showing: ' . count($nowShowingMovies) . ' movies');
        $this->command->info('- Upcoming: ' . count($upcomingMovies) . ' movies');
        $this->command->info('- Ended: ' . count($endedMovies) . ' movies (for testing)');
        $this->command->info('- Categories attached to all movies');
    }
}
