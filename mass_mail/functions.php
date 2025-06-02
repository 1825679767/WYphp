<?php
// mass_mail/functions.php
declare(strict_types=1);

/**
 * Retrieves the names of all online characters.
 *
 * @param PDO $pdo_C Connection to the Characters database.
 * @return array List of online character names.
 * @throws PDOException If database query fails.
 */
function get_online_character_names(PDO $pdo_C): array
{
    try {
        $stmt = $pdo_C->query("SELECT name FROM characters WHERE online = 1");
        // fetchAll with FETCH_COLUMN fetches only the first column of all rows
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching online character names: " . $e->getMessage());
        throw $e; // Re-throw the exception to be caught by the handler
    }
}

/**
 * Parses a string containing character names (one per line) into an array.
 * Trims whitespace and removes empty lines.
 *
 * @param string $list_string The raw string from the textarea.
 * @return array List of unique character names.
 */
function parse_character_list(string $list_string): array
{
    $lines = preg_split('/\r\n|\r|\n/', $list_string); // Split by various line endings
    $names = [];
    if (is_array($lines)) {
         foreach ($lines as $line) {
            $trimmed_line = trim($line);
            if (!empty($trimmed_line)) {
                $names[] = $trimmed_line;
            }
         }
    }
    return array_unique($names); // Return only unique names
}

?> 