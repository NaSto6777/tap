<?php
/**
 * Simple CSRF helper for form protection.
 *
 * Usage:
 *   CsrfHelper::generateToken(); // once per request (or before rendering forms)
 *   echo CsrfHelper::getTokenField(); // inside <form>
 *   if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? '')) { ... } // on POST
 */
class CsrfHelper
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Ensure a CSRF token exists in the current session and return it.
     */
    public static function generateToken(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Return the current token (generating one if needed).
     */
    public static function getToken(): string
    {
        return self::generateToken();
    }

    /**
     * Return a hidden input field containing the CSRF token.
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $escaped . '">';
    }

    /**
     * Validate a submitted token against the session token.
     */
    public static function validateToken(?string $token): bool
    {
        self::ensureSession();
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}

