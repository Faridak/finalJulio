<?php
/**
 * Key/value site settings (marketing-controlled front-end config):
 * jumbotron, seasonal theme, promo popup, tracking tags.
 */
class SiteSettings
{
    private static $cache = null;

    public static function all(PDO $pdo)
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($pdo->query("SELECT setting_key, setting_value FROM site_settings") as $r) {
                self::$cache[$r['setting_key']] = $r['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(PDO $pdo, $key, $default = null)
    {
        $all = self::all($pdo);
        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function set(PDO $pdo, $key, $value, $userId = null)
    {
        // NB: the live site_settings table predates the marketing module and
        // has no updated_by column — key/value only.
        $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$key, $value]);
        self::$cache[$key] = $value;
    }

    /** Seasonal theme presets the marketing team can switch between. */
    public static function themes()
    {
        return [
            'default'  => ['label' => 'Default (blue)',            'from' => '#1e3a8a', 'to' => '#1d4ed8', 'accent' => '#f97316'],
            'xmas'     => ['label' => '🎄 Christmas (red)',         'from' => '#7f1d1d', 'to' => '#b91c1c', 'accent' => '#16a34a'],
            'stpatrick'=> ['label' => '🍀 St. Patrick (green)',     'from' => '#14532d', 'to' => '#16a34a', 'accent' => '#f59e0b'],
            'muertos'  => ['label' => '💀 Día de Muertos',          'from' => '#3b0764', 'to' => '#7e22ce', 'accent' => '#fb923c'],
            'summer'   => ['label' => '☀️ Summer (teal)',           'from' => '#0f766e', 'to' => '#14b8a6', 'accent' => '#f43f5e'],
        ];
    }
}
