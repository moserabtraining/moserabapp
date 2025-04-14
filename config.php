<?php
// backend/config.php

// Datenbank-Konfiguration
define('DB_HOST', 'db-mysql-fra1-86243-do-user-7250725-0.a.db.ondigitalocean.com');
define('DB_NAME', 'engine');
define('DB_USER', 'moserab');
define('DB_PASS', 'qzso8ml78yfch16w');

// Apple-Konfiguration
define('APPLE_SANDBOX', false); // true für Testumgebung, false für Produktion
define('APPLE_VERIFY_RECEIPT_SANDBOX', 'https://sandbox.itunes.apple.com/verifyReceipt');
define('APPLE_VERIFY_RECEIPT_PRODUCTION', 'https://buy.itunes.apple.com/verifyReceipt');
define('APPLE_SHARED_SECRET', 'your_shared_secret_from_appstoreconnect');

// Produkt-IDs
define('PRODUCT_PREMIUM_MONTHLY', 'moserab09');

// Premium-Abonnement-Dauer in Tagen
define('PREMIUM_DURATION_DAYS', 30);

?>

<?php
