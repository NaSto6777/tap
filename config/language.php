<?php
class Language {
    private static $currentLang = 'en';
    private static $translations = [];
    private static $loaded = false;

    public static function init($lang = null) {
        if ($lang === null) {
            // Get from session or settings
            if (isset($_SESSION['admin_language'])) {
                $lang = $_SESSION['admin_language'];
            } else {
                // Try to get from database
                try {
                    require_once __DIR__ . '/database.php';
                    $database = new Database();
                    $conn = $database->getConnection();
                    require_once __DIR__ . '/settings.php';
                    $settings = new Settings($conn);
                    $lang = $settings->getSetting('admin_language', 'en');
                } catch (Exception $e) {
                    $lang = 'en';
                }
            }
        }
        
        self::$currentLang = $lang;
        self::loadTranslations();
    }

    private static function loadTranslations() {
        if (self::$loaded) {
            return;
        }
        
        $langFile = __DIR__ . '/languages/' . self::$currentLang . '.php';
        if (file_exists($langFile)) {
            self::$translations = require $langFile;
        } else {
            // Fallback to English
            $enFile = __DIR__ . '/languages/en.php';
            if (file_exists($enFile)) {
                self::$translations = require $enFile;
            }
        }
        
        self::$loaded = true;
    }

    public static function setLanguage($lang) {
        self::$currentLang = $lang;
        self::$loaded = false;
        self::loadTranslations();
        
        // Save to session
        $_SESSION['admin_language'] = $lang;
        
        // Save to database
        try {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $conn = $database->getConnection();
            require_once __DIR__ . '/settings.php';
            $settings = new Settings($conn);
            $settings->setSetting('admin_language', $lang);
        } catch (Exception $e) {
            // Ignore errors
        }
    }

    public static function getCurrentLanguage() {
        return self::$currentLang;
    }

    public static function t($key, $default = null) {
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }
        
        // If not found, return the key or default
        return $default !== null ? $default : $key;
    }

    public static function isRTL() {
        return self::$currentLang === 'ar';
    }
}

// Initialize language on include
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
Language::init();

