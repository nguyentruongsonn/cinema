<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 5: Data Model Integrity & Database Constraints
     * 
     * This migration adds critical unique constraints and check constraints
     * to enforce business invariants at the database level.
     */
    public function up(): void
    {
        // Preflight duplicate detection
        $this->detectDuplicates();

        // Add unique constraints
        $this->addUniqueConstraints();

        // Add check constraints (MySQL 8.0.16+)
        if ($this->supportsCheckConstraints()) {
            $this->addCheckConstraints();
        }

        // Add missing indexes for performance
        $this->addPerformanceIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraints first (if they exist)
        if ($this->supportsCheckConstraints()) {
            $this->dropCheckConstraints();
        }

        // Drop unique constraints
        $this->dropUniqueConstraints();

        // Drop performance indexes
        $this->dropPerformanceIndexes();
    }

    /**
     * Detect duplicate data before adding unique constraints.
     */
    private function detectDuplicates(): void
    {
        // Check for duplicate promotion codes
        $promotionDeletedAtClause = $this->columnExists('promotions', 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
        $duplicatePromotions = DB::select("
            SELECT code, COUNT(*) as count
            FROM promotions
            {$promotionDeletedAtClause}
            GROUP BY code
            HAVING count > 1
        ");

        if (!empty($duplicatePromotions)) {
            $codes = collect($duplicatePromotions)->pluck('code')->implode(', ');
            throw new \RuntimeException(
                "Duplicate promotion codes found: {$codes}. " .
                "Please resolve duplicates before applying unique constraint."
            );
        }

        // Check for duplicate ticket codes
        $duplicateTicketCodes = DB::select("
            SELECT ticket_code, COUNT(*) as count
            FROM tickets
            GROUP BY ticket_code
            HAVING count > 1
        ");

        if (!empty($duplicateTicketCodes)) {
            $codes = collect($duplicateTicketCodes)->pluck('ticket_code')->implode(', ');
            throw new \RuntimeException(
                "Duplicate ticket codes found: {$codes}. " .
                "Please resolve duplicates before applying unique constraint."
            );
        }

        // Check for duplicate ticket QR codes
        $duplicateQRCodes = DB::select("
            SELECT qr_code, COUNT(*) as count
            FROM tickets
            WHERE qr_code IS NOT NULL
            GROUP BY qr_code
            HAVING count > 1
        ");

        if (!empty($duplicateQRCodes)) {
            throw new \RuntimeException(
                "Duplicate ticket QR codes found. " .
                "Please regenerate QR codes before applying unique constraint."
            );
        }

        // Check for duplicate movie slugs
        $movieDeletedAtClause = $this->columnExists('movies', 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
        $duplicateSlugs = DB::select("
            SELECT slug, COUNT(*) as count
            FROM movies
            {$movieDeletedAtClause}
            GROUP BY slug
            HAVING count > 1
        ");

        if (!empty($duplicateSlugs)) {
            $slugs = collect($duplicateSlugs)->pluck('slug')->implode(', ');
            throw new \RuntimeException(
                "Duplicate movie slugs found: {$slugs}. " .
                "Please resolve slug conflicts before applying unique constraint."
            );
        }

        // Check for negative prices/stock
        $invalidProducts = DB::select("
            SELECT id, name, price, stock
            FROM products
            WHERE price < 0 OR stock < 0
        ");

        if (!empty($invalidProducts)) {
            throw new \RuntimeException(
                "Invalid product price or stock found. " .
                "Please fix negative values before applying check constraints."
            );
        }

        $invalidCombos = DB::select("
            SELECT id, name, price
            FROM combos
            WHERE price < 0
        ");

        if (!empty($invalidCombos)) {
            throw new \RuntimeException(
                "Invalid combo price found. " .
                "Please fix negative values before applying check constraints."
            );
        }
    }

    /**
     * Add unique constraints to enforce business rules.
     */
    private function addUniqueConstraints(): void
    {
        // Promotions: unique code
        if (!$this->indexExists('promotions', 'promotions_code_unique')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->unique('code', 'promotions_code_unique');
            });
        }

        // Tickets: unique ticket_code
        if (!$this->indexExists('tickets', 'tickets_ticket_code_unique')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unique('ticket_code', 'tickets_ticket_code_unique');
            });
        }

        // Tickets: unique qr_code (nullable unique)
        if (!$this->indexExists('tickets', 'tickets_qr_code_unique')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unique('qr_code', 'tickets_qr_code_unique');
            });
        }

        // Movies: unique slug for active movies where the database supports partial indexes.
        // MySQL does not support partial indexes; active-slug enforcement remains in service/model validation there.
        if ($this->databaseDriver() === 'sqlite' && $this->columnExists('movies', 'deleted_at')) {
            DB::statement('
                CREATE UNIQUE INDEX movies_slug_unique 
                ON movies(slug) 
                WHERE deleted_at IS NULL
            ');
        } elseif (!$this->columnExists('movies', 'deleted_at')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->unique('slug', 'movies_slug_unique');
            });
        }

        // Showtimes: prevent exact duplicate screenings
        if (!$this->indexExists('showtimes', 'showtimes_screen_time_unique')) {
            Schema::table('showtimes', function (Blueprint $table) {
                $table->unique(['screen_id', 'scheduled_at'], 'showtimes_screen_time_unique');
            });
        }
    }

    /**
     * Add check constraints for data integrity (MySQL 8.0.16+).
     */
    private function addCheckConstraints(): void
    {
        // Products: price and stock must be non-negative
        DB::statement('
            ALTER TABLE products
            ADD CONSTRAINT products_price_nonnegative CHECK (price >= 0)
        ');

        DB::statement('
            ALTER TABLE products
            ADD CONSTRAINT products_stock_nonnegative CHECK (stock >= 0)
        ');

        // Combos: price must be non-negative
        DB::statement('
            ALTER TABLE combos
            ADD CONSTRAINT combos_price_nonnegative CHECK (price >= 0)
        ');

        // ComboItems: quantity must be positive
        DB::statement('
            ALTER TABLE combo_items
            ADD CONSTRAINT combo_items_quantity_positive CHECK (quantity > 0)
        ');

        // Screens: capacity must be positive
        DB::statement('
            ALTER TABLE screens
            ADD CONSTRAINT screens_capacity_positive CHECK (capacity > 0)
        ');

        // OrderItems: quantity and prices must be positive
        DB::statement('
            ALTER TABLE order_items
            ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)
        ');

        DB::statement('
            ALTER TABLE order_items
            ADD CONSTRAINT order_items_price_nonnegative CHECK (unit_price >= 0)
        ');

        DB::statement('
            ALTER TABLE order_items
            ADD CONSTRAINT order_items_total_nonnegative CHECK (total_price >= 0)
        ');

        // Formats: surcharge must be non-negative
        DB::statement('
            ALTER TABLE formats
            ADD CONSTRAINT formats_surcharge_nonnegative CHECK (surcharge >= 0)
        ');

        // SeatTypes: surcharge must be non-negative
        DB::statement('
            ALTER TABLE seat_types
            ADD CONSTRAINT seat_types_surcharge_nonnegative CHECK (surcharge >= 0)
        ');
    }

    /**
     * Add performance indexes for common query patterns.
     */
    private function addPerformanceIndexes(): void
    {
        // Promotions: index on active status and dates for eligibility queries
        Schema::table('promotions', function (Blueprint $table) {
            if (!$this->indexExists('promotions', 'promotions_active_dates_idx')) {
                $table->index(['status', 'start_date', 'end_date'], 'promotions_active_dates_idx');
            }
        });

        // Movies: index on status and release_date for catalog queries
        Schema::table('movies', function (Blueprint $table) {
            if (!$this->indexExists('movies', 'movies_status_release_idx')) {
                $table->index(['status', 'release_date'], 'movies_status_release_idx');
            }
        });

        // Combos: index on status for active catalog
        Schema::table('combos', function (Blueprint $table) {
            if (!$this->indexExists('combos', 'combos_status_idx')) {
                $table->index('status', 'combos_status_idx');
            }
        });
    }

    /**
     * Drop check constraints.
     */
    private function dropCheckConstraints(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_price_nonnegative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_stock_nonnegative');
        DB::statement('ALTER TABLE combos DROP CONSTRAINT IF EXISTS combos_price_nonnegative');
        DB::statement('ALTER TABLE combo_items DROP CONSTRAINT IF EXISTS combo_items_quantity_positive');
        DB::statement('ALTER TABLE screens DROP CONSTRAINT IF EXISTS screens_capacity_positive');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_quantity_positive');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_price_nonnegative');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_total_nonnegative');
        DB::statement('ALTER TABLE formats DROP CONSTRAINT IF EXISTS formats_surcharge_nonnegative');
        DB::statement('ALTER TABLE seat_types DROP CONSTRAINT IF EXISTS seat_types_surcharge_nonnegative');
    }

    /**
     * Drop unique constraints.
     */
    private function dropUniqueConstraints(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropUnique('promotions_code_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_ticket_code_unique');
        });

        if ($this->indexExists('tickets', 'tickets_qr_code_unique')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropUnique('tickets_qr_code_unique');
            });
        }

        if ($this->indexExists('movies', 'movies_slug_unique')) {
            if ($this->databaseDriver() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS movies_slug_unique');
            } else {
                Schema::table('movies', function (Blueprint $table) {
                    $table->dropUnique('movies_slug_unique');
                });
            }
        }

        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropUnique('showtimes_screen_time_unique');
        });
    }

    /**
     * Drop performance indexes.
     */
    private function dropPerformanceIndexes(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if ($this->indexExists('promotions', 'promotions_active_dates_idx')) {
                $table->dropIndex('promotions_active_dates_idx');
            }
        });

        Schema::table('movies', function (Blueprint $table) {
            if ($this->indexExists('movies', 'movies_status_release_idx')) {
                $table->dropIndex('movies_status_release_idx');
            }
        });

        Schema::table('combos', function (Blueprint $table) {
            if ($this->indexExists('combos', 'combos_status_idx')) {
                $table->dropIndex('combos_status_idx');
            }
        });
    }

    /**
     * Check if MySQL supports CHECK constraints (8.0.16+).
     */
    private function supportsCheckConstraints(): bool
    {
        if ($this->databaseDriver() !== 'mysql') {
            return false;
        }

        $version = DB::selectOne('SELECT VERSION() as version')->version;
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);

        if (count($matches) >= 4) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            $patch = (int) $matches[3];

            return ($major > 8) || ($major === 8 && $minor > 0) || ($major === 8 && $minor === 0 && $patch >= 16);
        }

        return false;
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        if ($this->databaseDriver() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$table})");

            return collect($indexes)->contains(fn ($existingIndex): bool => $existingIndex->name === $index);
        }

        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

        return !empty($indexes);
    }

    /**
     * Check whether a table column exists.
     */
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * Get current database driver name.
     */
    private function databaseDriver(): string
    {
        return DB::getDriverName();
    }
};