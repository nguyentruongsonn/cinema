<?php

/**
 * Script tự động tạo Laravel migrations từ file sql.sql
 * Chạy: php create_migrations_from_sql.php
 */

$sqlFile = __DIR__ . '/sql.sql';
$migrationsDir = __DIR__ . '/database/migrations';

if (!file_exists($sqlFile)) {
    die("File sql.sql không tồn tại!\n");
}

// Xóa migrations cũ
$files = glob($migrationsDir . '/2026_05_29_16*.php');
foreach ($files as $file) {
    unlink($file);
    echo "Deleted old: " . basename($file) . "\n";
}

$sql = file_get_contents($sqlFile);

// Parse CREATE TABLE statements
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`(\w+)`\s*\((.*?)\)\s*ENGINE=/s', $sql, $matches, PREG_SET_ORDER);

if (empty($matches)) {
    die("Không tìm thấy CREATE TABLE statements trong sql.sql\n");
}

$counter = 0;
$baseTime = strtotime('2026-05-29 16:40:00');

foreach ($matches as $match) {
    $tableName = $match[1];
    $tableDefinition = trim($match[2]);

    // Skip Laravel system tables
    if (in_array($tableName, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'])) {
        continue;
    }

    $counter++;
    $migrationTimestamp = date('Y_m_d_His', $baseTime + $counter);
    $fileName = "{$migrationTimestamp}_create_{$tableName}_table.php";
    $filePath = "$migrationsDir/$fileName";

    // Parse column definitions - split by comma at top level (not inside parentheses)
    $columnDefs = parseTopLevelColumns($tableDefinition);

    $migrationContent = generateMigration($tableName, $columnDefs);
    file_put_contents($filePath, $migrationContent);
    echo "Created: $fileName\n";
}

echo "\n✅ Đã tạo $counter migrations từ file sql.sql!\n";

function parseTopLevelColumns($definition) {
    $columns = [];
    $depth = 0;
    $current = '';
    $len = strlen($definition);

    for ($i = 0; $i < $len; $i++) {
        $ch = $definition[$i];

        if ($ch === '(' || $ch === '[') {
            $depth++;
            $current .= $ch;
        } elseif ($ch === ')' || $ch === ']') {
            $depth--;
            $current .= $ch;
        } elseif ($ch === ',' && $depth === 0) {
            $trimmed = trim($current);
            if (!empty($trimmed)) {
                $columns[] = $trimmed;
            }
            $current = '';
        } else {
            $current .= $ch;
        }
    }

    $trimmed = trim($current);
    if (!empty($trimmed)) {
        $columns[] = $trimmed;
    }

    // Filter out indexes and constraints
    $result = [];
    foreach ($columns as $col) {
        $upper = strtoupper($col);
        if (preg_match('/^(UNIQUE|INDEX|KEY|FULLTEXT|SPATIAL|CONSTRAINT|PRIMARY)\s/i', $upper)) {
            continue;
        }
        $result[] = $col;
    }

    return $result;
}

function parseColumn($colDef) {
    $result = [
        'name' => '',
        'type' => '',
        'size' => '',
        'unsigned' => false,
        'nullable' => false,
        'autoIncrement' => false,
        'default' => null,
        'defaultRaw' => false,
        'comment' => '',
        'unique' => false,
        'onUpdate' => ''
    ];

    // Extract column name
    if (!preg_match('/`(\w+)`\s+(.+)/s', $colDef, $m)) return null;

    $result['name'] = $m[1];
    $rest = trim($m[2]);

    // Extract type (with possible size)
    if (preg_match('/^(\w+)(\(([^)]*)\))?\s*(.*)/s', $rest, $m2)) {
        $result['type'] = strtolower($m2[1]);
        $result['size'] = isset($m2[3]) ? trim($m2[3]) : '';
        $rest = trim($m2[4]);
    } else {
        return null;
    }

    // Extract attributes
    $upper = strtoupper($rest);
    $result['unsigned'] = strpos($upper, 'UNSIGNED') !== false;
    $result['autoIncrement'] = strpos($upper, 'AUTO_INCREMENT') !== false;

    // Check NOT NULL vs nullable
    // MySQL default is NULL if not specified and not part of PRIMARY KEY
    if (strpos($upper, 'NOT NULL') !== false) {
        $result['nullable'] = false;
    } elseif (strpos($upper, 'DEFAULT NULL') !== false) {
        $result['nullable'] = true;
    } elseif (strpos($upper, 'NULL') !== false && strpos($upper, 'NOT NULL') === false) {
        // Just "NULL" means nullable
        $result['nullable'] = true;
    }

    // Extract DEFAULT value
    if (preg_match('/DEFAULT\s+(\S+)/i', $rest, $dm)) {
        $default = $dm[1];
        // Remove trailing comma if any
        if (substr($default, -1) === ',') $default = substr($default, 0, -1);

        $upperDefault = strtoupper($default);
        if ($upperDefault === 'NULL') {
            $result['default'] = null;
        } elseif ($upperDefault === 'CURRENT_TIMESTAMP' || $upperDefault === 'CURRENT_TIMESTAMP()') {
            $result['defaultRaw'] = 'CURRENT_TIMESTAMP';
        } else {
            // Remove surrounding quotes
            $unquoted = trim($default, "'\"");
            if (is_numeric($unquoted)) {
                $result['default'] = $unquoted;
            } else {
                $result['default'] = $unquoted;
            }
        }
    }

    // Extract ON UPDATE
    if (preg_match('/ON\s+UPDATE\s+(\S+)/i', $rest, $oum)) {
        $result['onUpdate'] = strtoupper(trim($oum[1], " \t\n\r\0\x0B,"));
    }

    // Extract COMMENT
    if (preg_match("/COMMENT\s+'([^']+)'/i", $rest, $cm)) {
        $result['comment'] = $cm[1];
    }

    // UNIQUE
    $result['unique'] = strpos($upper, 'UNIQUE') !== false;

    return $result;
}

function generateMigration($tableName, $columnDefs) {
    $lines = [];
    $hasTimestamps = false;
    $hasSoftDeletes = false;
    $foreignKeys = [];

    foreach ($columnDefs as $colDef) {
        $col = parseColumn($colDef);
        if (!$col) continue;

        $name = $col['name'];
        $type = $col['type'];
        $size = $col['size'];

        // Skip auto-managed columns
        if ($name === 'created_at' || $name === 'updated_at') {
            $hasTimestamps = true;
            continue;
        }
        if ($name === 'deleted_at') {
            $hasSoftDeletes = true;
            continue;
        }

        // Build the line
        $line = '';

        // id column - always use id() for bigint unsigned
        if ($name === 'id' && $type === 'bigint' && $col['unsigned']) {
            $lines[] = "\$table->id();";
            continue;
        }

        // Determine Laravel column type
        $laravelType = mapType($type);
        $isNumeric = in_array($type, ['bigint', 'int', 'tinyint', 'smallint', 'mediumint']);

        // Build column creation
        if ($type === 'enum' && !empty($size)) {
            $enumValues = explode(',', $size);
            $enumValues = array_map(function($v) { return trim($v, "'"); }, $enumValues);
            $valuesStr = "'" . implode("', '", $enumValues) . "'";
            $line = "\$table->enum('$name', [$valuesStr])";
        } elseif ($type === 'decimal' && !empty($size)) {
            $parts = explode(',', $size);
            $total = trim($parts[0] ?? '8');
            $places = trim($parts[1] ?? '2');
            $line = "\$table->decimal('$name', $total, $places)";
        } else {
            $line = "\$table->$laravelType('$name')";
        }

        // unsigned
        if ($col['unsigned'] && $isNumeric) {
            $line .= "->unsigned()";
        }

        // nullable (only if not NOT NULL and not autoIncrement)
        if ($col['nullable'] && !$col['autoIncrement']) {
            $line .= "->nullable()";
        }

        // unique
        if ($col['unique']) {
            $line .= "->unique()";
        }

        // default value
        if ($col['defaultRaw'] === 'CURRENT_TIMESTAMP') {
            $line .= "->useCurrent()";
        } elseif ($col['default'] !== null) {
            if (is_numeric($col['default'])) {
                $line .= "->default({$col['default']})";
            } else {
                $line .= "->default('{$col['default']}')";
            }
        }

        // comment
        if (!empty($col['comment'])) {
            $line .= "->comment('" . addslashes($col['comment']) . "')";
        }

        // Track foreign keys for later
        if (preg_match('/_id$/', $name) && $name !== 'id' && $isNumeric && $col['unsigned']) {
            $refTable = preg_replace('/_id$/', '', $name);
            // Pluralize if needed (simple heuristic)
            if (!in_array($refTable, ['user', 'format', 'sound', 'screen', 'seat', 'showtime', 'order', 'payment', 'permission', 'role', 'promotion', 'category', 'movie', 'subtitle', 'product', 'branch', 'theater'])) {
                $refTable .= 's';
            } else {
                $refTable .= 's';
            }
            $foreignKeys[] = ['column' => $name, 'table' => $refTable];
            $line .= "->index()";
        }

        $lines[] = "$line;";
    }

    if ($hasTimestamps) {
        $lines[] = "\$table->timestamps();";
    }
    if ($hasSoftDeletes) {
        $lines[] = "\$table->softDeletes();";
    }

    // Indent all lines
    $indentedLines = array_map(function($l) {
        return "            $l";
    }, $lines);

    $columnsCode = implode("\n", $indentedLines) . "\n";

    return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('$tableName', function (Blueprint \$table) {
$columnsCode        });
    }

    public function down(): void
    {
        Schema::dropIfExists('$tableName');
    }
};

PHP;
}

function mapType($mysqlType) {
    $typeMap = [
        'bigint' => 'bigInteger',
        'int' => 'integer',
        'tinyint' => 'tinyInteger',
        'smallint' => 'smallInteger',
        'mediumint' => 'mediumInteger',
        'varchar' => 'string',
        'char' => 'char',
        'text' => 'text',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'date' => 'date',
        'datetime' => 'dateTime',
        'timestamp' => 'timestamp',
        'time' => 'time',
        'year' => 'year',
        'enum' => 'enum',
        'json' => 'json',
        'boolean' => 'boolean',
        'tinytext' => 'text',
        'longvarchar' => 'string',
    ];

    return $typeMap[$mysqlType] ?? 'string';
}
