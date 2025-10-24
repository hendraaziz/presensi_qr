<?php
// Example configuration for presensi_wasbang
// Copy this file to config.php and fill in your values

// Google Sheet URLs
define('GOOGLE_SHEET_SESI_URL', 'https://docs.google.com/spreadsheets/d/.../export?format=csv');
define('NIM_GSHEET_URL', 'https://docs.google.com/spreadsheets/d/.../export?format=csv');

// n8n Webhook URL
define('N8N_WEBHOOK_URL', 'https://your-n8n-instance.example.com/webhook/xxx');

// Secret key for hashing (fallback fingerprint)
define('SECRET_KEY', 'change-this-secret');

// Default country code (used for placeholder/display)
// Keep for backward compatibility
define('COUNTRY_CODE', '62');

// Allowed WhatsApp country codes (comma-separated). Example: '62,61,60'
// This supports multiple prefixes and will be used for validation.
define('COUNTRY_CODES', '62,61,60');

// Helper: base URL detection compatible with PHP 5.6
function base_url() {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
  $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
  $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
  return $scheme . '://' . $host . ($dir ? $dir : '');
}