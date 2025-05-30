<?php

/**
 * @property-read int $id
 * @property-read ?string $document
 * @property-read ?string $langcode
 * @property-read ?string $anchor
 * @property-read string $subject
 * @property-read string $rule
 */
class RandomRule
{
    private array $table_row = [];

    // Because these are static class variables we can't use _() to translate
    // them. But they're only used in the Admin interface so that's probably
    // OK anyway. If, in the future, we want to translate them we can move
    // them to a class function (get_document_values()) instead.
    public static $document_values = [
        "proofreading_guidelines.php" => "Proofreading Guidelines",
        "formatting_guidelines.php" => "Formatting Guidelines",
    ];

    public function __construct(?int $id = null)
    {
        if ($id !== null) {
            $this->load($id);
        }
    }

    public function __get(string $name)
    {
        return $this->table_row[$name];
    }

    public function __isset(string $name)
    {
        return isset($this->table_row[$name]);
    }

    /**
     * Load a specific rule into this object given its ID
     */
    public function load(int $id): void
    {
        $sql = sprintf(
            "
            SELECT *
            FROM rules
            WHERE id = %d
            ",
            $id
        );

        $result = DPDatabase::query($sql);
        $this->table_row = mysqli_fetch_assoc($result);
    }

    /**
     * Get a specific RandomRule given its document/anchor/langcode
     */
    public static function load_from_anchor(string $document, string $anchor, string $langcode = 'en'): ?RandomRule
    {
        $sql = sprintf(
            "
            SELECT id
            FROM rules
            WHERE
                document = '%s'
                AND anchor = '%s'
                AND langcode = '%s'
            ",
            DPDatabase::escape($document),
            DPDatabase::escape($anchor),
            DPDatabase::escape($langcode)
        );

        $result = DPDatabase::query($sql);
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if (!$row) {
            return null;
        }
        return new RandomRule($row["id"]);
    }

    /**
     * Get a RandomRule object for a given document-langcode pair.
     */
    public static function get_random(string $document, string $langcode = 'en'): ?RandomRule
    {
        $sql = sprintf(
            "
            SELECT id
            FROM rules
            WHERE
                document = '%s'
                AND langcode = '%s'
            ORDER BY RAND(NOW())
            LIMIT 1
            ",
            DPDatabase::escape($document),
            DPDatabase::escape($langcode)
        );
        $result = DPDatabase::query($sql);
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if (!$row) {
            return null;
        }
        return new RandomRule($row["id"]);
    }

    /**
     * Return an array of RandomRule objects for a given document/langcode pair
     *
     * @return RandomRule[]
     */
    public static function get_rules(string $document, string $langcode = 'en'): array
    {
        $sql = sprintf(
            "
            SELECT id
            FROM rules
            WHERE
                document = '%s'
                AND langcode = '%s'
            ORDER BY id
            ",
            DPDatabase::escape($document),
            DPDatabase::escape($langcode)
        );
        $result = DPDatabase::query($sql);

        $rules = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rules[] = new RandomRule($row["id"]);
        }
        mysqli_free_result($result);
        return $rules;
    }

    /**
     * Return an associative array containing metadata about the rules in
     * the table.
     *
     * Useful primarily for the administrative interface.
     *
     * @return array{"document": string, "langcode": string, "count": int}[]
     */
    public static function get_summary(): array
    {
        $sql = "
            SELECT count(*) AS count, document, langcode
            FROM rules
            GROUP BY document, langcode
            ORDER BY langcode, document
        ";
        $result = DPDatabase::query($sql);

        $summary = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $summary[] = [
                'document' => $row['document'],
                'langcode' => $row['langcode'],
                'count' => $row['count'],
            ];
        }
        mysqli_free_result($result);
        return $summary;
    }

    /**
     * Delete rules in the database matching a specific document/langcode pair
     */
    public static function delete_rules(string $document, string $langcode): void
    {
        $sql = sprintf(
            "
            DELETE
            FROM rules
            WHERE
                document = '%s'
                AND langcode = '%s'
            ",
            DPDatabase::escape($document),
            DPDatabase::escape($langcode)
        );
        DPDatabase::query($sql);
    }

    /**
     * Reload rules by parsing a URL and reloading the rules found in it
     *
     * This function supports the HTML rendered from the in-code documents in faq/
     * as well as the wiki-generated HTML for the guidelines at pgdp.net.
     */
    public static function reload_rules(string $url, string $document, string $langcode): void
    {
        // delete all existing random rules for this ($document, $langcode)
        RandomRule::delete_rules($document, $langcode);

        $fhandle = fopen($url, "rt");

        $anchor = $subject = $rule = "";
        $in_rule = false;
        while ($line = fgets($fhandle)) {
            // wiki syntax
            if (preg_match('/^<div id="(.*)"><\/div>$/', $line, $matches)) {
                if ($in_rule) {
                    RandomRule::_add_rule($document, $langcode, $url, $anchor, $subject, $rule);
                    $anchor = $subject = $rule = "";
                }
                $anchor = $matches[1];
                $in_rule = true;
                continue;
            }
            // code syntax
            if (preg_match('/<h3><a name="(.*)">(.*)<\/a><\/h3>/', $line, $matches)) {
                if ($in_rule) {
                    RandomRule::_add_rule($document, $langcode, $url, $anchor, $subject, $rule);
                    $anchor = $subject = $rule = "";
                }
                $anchor = $matches[1];
                $subject = $matches[2];
                $in_rule = true;
                continue;
            }
            // wiki syntax
            elseif (preg_match('/^<h3>.*>(.*)<.*<\/h3>/', $line, $matches)) {
                $subject = $matches[1];
                continue;
            }
            // wiki and code syntax
            if (preg_match('/Back to top/', $line) || preg_match('/END RR/', $line)) {
                RandomRule::_add_rule($document, $langcode, $url, $anchor, $subject, $rule);
                $anchor = $subject = $rule = "";
                $in_rule = false;
                continue;
            }
            if ($in_rule) {
                $rule .= $line;
                continue;
            }
        }
        if ($in_rule) {
            RandomRule::_add_rule($document, $langcode, $url, $anchor, $subject, $rule);
        }
        fclose($fhandle);
    }

    /**
     * Add a rule to the database
     */
    private static function _add_rule(string $document, string $langcode, string $url, string $anchor, string $subject, string $rule): void
    {
        // skip "empty" rules, likely caused by parser errors
        if (!$anchor || !$subject || !$rule) {
            return;
        }

        // update rule URLs
        $rule = preg_replace_callback(
            '/<a href="([^"]*)"[^>]*/',
            function ($match) use ($url) {
                if (str_starts_with($match[1], '#')) {
                    return '<a href="' . $url . $match[1] . '"';
                } elseif (str_starts_with($match[1], '/')) {
                    $url_parts = parse_url($url);
                    return '<a href="' . $url_parts["scheme"] . "://" . $url_parts["host"] . $match[1] . '"';
                } elseif (!str_starts_with($match[1], 'http')) {
                    $base_url = dirname($url) . '/';
                    return '<a href="' . $base_url . $match[1] . '"';
                } else {
                    return $match[0];
                }
            },
            $rule
        );

        // prevent anchor and subject from being too big for their fields
        $anchor = substr($anchor, 0, 255);
        $subject = substr($subject, 0, 255);

        $sql = sprintf(
            "
            INSERT INTO rules
            SET
                document = '%s',
                langcode = '%s',
                anchor = '%s',
                subject = '%s',
                rule = '%s'
            ",
            DPDatabase::escape($document),
            DPDatabase::escape($langcode),
            DPDatabase::escape($anchor),
            DPDatabase::escape($subject),
            DPDatabase::escape($rule)
        );
        DPDatabase::query($sql);
    }
}
