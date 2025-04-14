<?php
// backend/verify_receipt.php

require_once 'db_connect.php';
require_once 'user_service.php';

header('Content-Type: application/json');

// Daten aus der Anfrage holen
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Überprüfen der erforderlichen Parameter
if (!isset($input['receipt_data']) || !isset($input['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'Missing required parameters']);
    exit;
}

$receiptData = $input['receipt_data'];
$userId = $input['user_id'];

try {
    // Apple-Verifizierung des Belegs
    $verificationResult = verifyReceiptWithApple($receiptData);
    
    if (!$verificationResult['status']) {
        echo json_encode(['status' => false, 'message' => 'Receipt verification failed: ' . $verificationResult['message']]);
        exit;
    }
    
    // Transaktion in der Datenbank speichern
    $transactionInfo = $verificationResult['transaction'];
    $result = storeTransaction($userId, $transactionInfo);
    
    if (!$result['status']) {
        echo json_encode(['status' => false, 'message' => 'Failed to store transaction: ' . $result['message']]);
        exit;
    }
    
    // Premium-Status für den Benutzer aktualisieren
    $updateResult = updateUserPremiumStatus($userId, $transactionInfo['product_id']);
    
    if (!$updateResult['status']) {
        echo json_encode(['status' => false, 'message' => 'Failed to update premium status: ' . $updateResult['message']]);
        exit;
    }
    
    // Erfolgreiche Antwort
    echo json_encode(['status' => true, 'message' => 'Receipt verified and premium status updated']);
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Verifiziert den Beleg bei Apple
 *
 * @param string $receiptData Der Base64-kodierte Beleg
 * @return array Ergebnis der Verifizierung
 */
function verifyReceiptWithApple($receiptData) {
    // Bestimme URL basierend auf der Umgebung
    $verifyUrl = APPLE_SANDBOX ? APPLE_VERIFY_RECEIPT_SANDBOX : APPLE_VERIFY_RECEIPT_PRODUCTION;
    
    // Erstelle die Anfrage an Apple
    $requestData = json_encode([
        'receipt-data' => $receiptData,
        'password' => APPLE_SHARED_SECRET, // App-spezifisches Shared Secret aus App Store Connect
        'exclude-old-transactions' => false
    ]);
    
    // Sende die Anfrage an Apple
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['status' => false, 'message' => 'Apple verification server error: HTTP ' . $httpCode];
    }
    
    // Analysiere die Antwort
    $decodedResponse = json_decode($response, true);
    
    if (!$decodedResponse) {
        return ['status' => false, 'message' => 'Invalid response from Apple'];
    }
    
    // Überprüfe den Status
    $status = $decodedResponse['status'] ?? -1;
    
    // Status 21007 bedeutet, dass es sich um einen Sandbox-Beleg handelt, aber wir verwenden die Produktions-URL
    if ($status === 21007 && !APPLE_SANDBOX) {
        // Versuche es erneut mit der Sandbox-URL
        $verifyUrl = APPLE_VERIFY_RECEIPT_SANDBOX;
        
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        $status = $decodedResponse['status'] ?? -1;
    }
    
    if ($status !== 0) {
        $statusMessages = [
            21000 => 'The App Store could not read the JSON object you provided.',
            21002 => 'The data in the receipt-data property was malformed.',
            21003 => 'The receipt could not be authenticated.',
            21004 => 'The shared secret you provided does not match the shared secret on file for your account.',
            21005 => 'The receipt server is not currently available.',
            21006 => 'This receipt is valid but the subscription has expired.',
            21007 => 'This receipt is from the test environment, but it was sent to the production environment for verification.',
            21008 => 'This receipt is from the production environment, but it was sent to the test environment for verification.',
            21010 => 'This receipt could not be authorized.',
            21100 => 'Internal data access error.',
            21199 => 'Unknown error.'
        ];
        
        $errorMessage = $statusMessages[$status] ?? 'Unknown error with status: ' . $status;
        return ['status' => false, 'message' => $errorMessage];
    }
    
    // Extrahiere die relevanten Transaktionsinformationen
    $receiptInfo = $decodedResponse['receipt'] ?? [];
    $latestReceiptInfo = $decodedResponse['latest_receipt_info'] ?? [];
    
    // Bei Auto-Renewable-Abonnements verwenden wir die neuesten Informationen
    if (!empty($latestReceiptInfo) && is_array($latestReceiptInfo)) {
        // Sortiere nach Kaufdatum (absteigend)
        usort($latestReceiptInfo, function ($a, $b) {
            return $b['purchase_date_ms'] - $a['purchase_date_ms'];
        });
        
        $latestTransaction = $latestReceiptInfo[0];
        
        $transactionId = $latestTransaction['transaction_id'] ?? '';
        $productId = $latestTransaction['product_id'] ?? '';
        $purchaseDate = $latestTransaction['purchase_date_ms'] ?? 0;
        $expiresDate = $latestTransaction['expires_date_ms'] ?? 0;
        $isTrialPeriod = ($latestTransaction['is_trial_period'] ?? 'false') === 'true';
        
    } else {
        // Für Nicht-Abonnement-Käufe
        $inApp = $receiptInfo['in_app'] ?? [];
        
        if (empty($inApp) || !is_array($inApp)) {
            return ['status' => false, 'message' => 'No in-app purchase information found in receipt'];
        }
        
        // Sortiere nach Kaufdatum (absteigend)
        usort($inApp, function ($a, $b) {
            return $b['purchase_date_ms'] - $a['purchase_date_ms'];
        });
        
        $latestTransaction = $inApp[0];
        
        $transactionId = $latestTransaction['transaction_id'] ?? '';
        $productId = $latestTransaction['product_id'] ?? '';
        $purchaseDate = $latestTransaction['purchase_date_ms'] ?? 0;
        $expiresDate = 0; // Für Nicht-Abonnements
        $isTrialPeriod = false;
    }
    
    // Prüfe, ob die Transaktion gültig ist
    if (empty($transactionId) || empty($productId)) {
        return ['status' => false, 'message' => 'Invalid transaction information in receipt'];
    }
    
    // Erfolgreiche Verifizierung
    return [
        'status' => true,
        'message' => 'Receipt verified successfully',
        'transaction' => [
            'transaction_id' => $transactionId,
            'product_id' => $productId,
            'purchase_date' => $purchaseDate,
            'expires_date' => $expiresDate,
            'is_trial_period' => $isTrialPeriod,
            'receipt_data' => $receiptData
        ]
    ];
}

/**
 * Speichert die Transaktion in der Datenbank
 *
 * @param string $userId Die Benutzer-ID
 * @param array $transactionInfo Die Transaktionsinformationen
 * @return array Ergebnis des Speichervorgangs
 */
function storeTransaction($userId, $transactionInfo) {
    try {
        $db = getDbConnection();
        
        if (!$db) {
            return ['status' => false, 'message' => 'Database connection failed'];
        }
        
        // Prüfe, ob die Transaktion bereits existiert
        $stmt = $db->prepare("SELECT id FROM payments WHERE transaction_id = ?");
        $stmt->execute([$transactionInfo['transaction_id']]);
        
        if ($stmt->rowCount() > 0) {
            return ['status' => true, 'message' => 'Transaction already processed', 'is_duplicate' => true];
        }
        
        // Transaktion speichern
        $stmt = $db->prepare("
            INSERT INTO payments (
                user_id, 
                transaction_id, 
                product_id, 
                purchase_date, 
                expires_date, 
                is_trial_period, 
                receipt_data, 
                created_at
            ) VALUES (?, ?, ?, FROM_UNIXTIME(?/1000), FROM_UNIXTIME(?/1000), ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $transactionInfo['transaction_id'],
            $transactionInfo['product_id'],
            $transactionInfo['purchase_date'],
            $transactionInfo['expires_date'] > 0 ? $transactionInfo['expires_date'] : null,
            $transactionInfo['is_trial_period'] ? 1 : 0,
            $transactionInfo['receipt_data']
        ]);
        
        return ['status' => true, 'message' => 'Transaction stored successfully', 'payment_id' => $db->lastInsertId()];
        
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return ['status' => false, 'message' => 'Database error occurred'];
    }
}

?>


