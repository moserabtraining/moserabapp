//
//  user_service.php
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//

<?php
// backend/user_service.php

require_once 'db_connect.php';

/**
 * Aktualisiert den Premium-Status eines Benutzers
 *
 * @param string $userId Die Benutzer-ID
 * @param string $productId Die Produkt-ID
 * @return array Ergebnis der Aktualisierung
 */
function updateUserPremiumStatus($userId, $productId) {
    try {
        $db = getDbConnection();
        
        if (!$db) {
            return ['status' => false, 'message' => 'Database connection failed'];
        }
        
        // Bestimme die Premium-Dauer basierend auf der Produkt-ID
        $premiumDuration = PREMIUM_DURATION_DAYS; // Standard: 30 Tage
        
        // Hier könnten weitere Produkt-IDs mit unterschiedlichen Laufzeiten hinzugefügt werden
        if ($productId === PRODUCT_PREMIUM_MONTHLY) {
            $premiumDuration = PREMIUM_DURATION_DAYS; // 30 Tage für monatliches Abo
        }
        
        // Hole aktuellen Premium-Status
        $stmt = $db->prepare("SELECT premium_until FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['status' => false, 'message' => 'User not found'];
        }
        
        // Berechne neues Ablaufdatum
        $currentTime = time();
        $newExpiryTimestamp = $currentTime + ($premiumDuration * 24 * 60 * 60); // Umrechnung in Sekunden
        
        // Falls der Benutzer bereits Premium ist und das aktuelle Ablaufdatum in der Zukunft liegt,
        // verlängere das Abonnement von diesem Datum aus
        if (isset($user['premium_until']) && strtotime($user['premium_until']) > $currentTime) {
        
            //    $newExpiryTimestamp
            
        }
?>
