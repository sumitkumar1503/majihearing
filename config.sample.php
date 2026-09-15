<?php
/**
 * Copy this file to `config.php` and fill in your real values.
 * `config.php` is git-ignored so your secrets never get committed.
 *
 * These are the SAME values you use on Vercel / .env.local today.
 */
return [
    // Google service account (from the JSON key / your current env vars)
    'google_service_account_email' => 'your-service-account@your-project.iam.gserviceaccount.com',

    // Paste the private key exactly. Keep the real newlines OR use \n escapes.
    'google_private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBg...\n-----END PRIVATE KEY-----\n",

    // The four spreadsheet IDs (same as GOOGLE_SPREADSHEET_*_ID)
    'spreadsheets' => [
        'master'     => 'MASTER_SPREADSHEET_ID',
        'operations' => 'OPERATIONS_SPREADSHEET_ID',
        'sales'      => 'SALES_SPREADSHEET_ID',
        'hr'         => 'HR_SPREADSHEET_ID',
    ],

    // Public URL of the site (used for absolute links)
    'app_url' => 'https://majihearing.eu',

    // MySQL (primary datastore). Google Sheets becomes a synced mirror/backup.
    'mysql' => [
        'host' => 'localhost',
        'name' => 'YOUR_DB_NAME',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASSWORD',
        'port' => 3306,
    ],

    // One-time token to run DB setup / initial import via ?page=db-setup&token=...
    'setup_token' => 'change-me',
];
