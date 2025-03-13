<?php
/**
 * M-Pesa API integration
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Get M-Pesa access token
 */
function getMpesaAccessToken() {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET)]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $result = json_decode($result);
    
    if($status === 200) {
        return $result->access_token;
    }
    
    return null;
}

/**
 * Initiate STK Push (prompt user for payment)
 */
function initiateMpesaPayment($phone, $amount, $reference, $description) {
    $access_token = getMpesaAccessToken();
    
    if(!$access_token) {
        return [
            'success' => false,
            'message' => 'Failed to get access token'
        ];
    }
    
    // Format phone number (should be 254XXXXXXXXX)
    $phone = formatMpesaPhoneNumber($phone);
    
    // Generate timestamp
    $timestamp = date('YmdHis');
    
    // Generate password
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $curl_post_data = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => $description
    ];
    
    $data_string = json_encode($curl_post_data);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($curl);
    $result = json_decode($response);
    
    if(isset($result->ResponseCode) && $result->ResponseCode == '0') {
        // Store checkout request ID for later verification
        $checkout_id = $result->CheckoutRequestID;
        
        // Store in database for verification
        $conn = getDbConnection();
        $stmt = $conn->prepare("INSERT INTO mpesa_transactions (checkout_request_id, phone, amount, reference, description, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssdss", $checkout_id, $phone, $amount, $reference, $description);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Payment initiated. Please enter your M-Pesa PIN.',
            'checkout_id' => $checkout_id
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to initiate payment. Please try again.'
    ];
}

/**
 * Check payment status
 */
function checkMpesaPaymentStatus($checkout_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT status, mpesa_receipt FROM mpesa_transactions WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkout_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1) {
        $transaction = $result->fetch_assoc();
        
        return [
            'success' => true,
            'status' => $transaction['status'],
            'receipt' => $transaction['mpesa_receipt']
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Transaction not found'
    ];
}

/**
 * Format phone number for M-Pesa
 * Convert formats like 07XXXXXXXX or +254XXXXXXXX to 254XXXXXXXX
 */
function formatMpesaPhoneNumber($phone) {
    // Remove any spaces, hyphens, or parentheses
    $phone = preg_replace('/\s+|-|\(|\)/', '', $phone);
    
    // If number starts with 0, replace with 254
    if(substr($phone, 0, 1) == '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    // If number starts with +, remove the +
    if(substr($phone, 0, 1) == '+') {
        $phone = substr($phone, 1);
    }
    
    return $phone;
}

/**
 * Process M-Pesa callback
 * This function is called by the M-Pesa API when a payment is complete
 */
function processMpesaCallback($callbackData) {
    // Parse callback data
    $callbackData = json_decode($callbackData);
    
    if(!isset($callbackData->Body->stkCallback->CheckoutRequestID)) {
        return false;
    }
    
    $checkout_id = $callbackData->Body->stkCallback->CheckoutRequestID;
    $result_code = $callbackData->Body->stkCallback->ResultCode;
    
    $conn = getDbConnection();
    
    if($result_code == 0) {
        // Payment successful
        $mpesa_receipt = $callbackData->Body->stkCallback->CallbackMetadata->Item[1]->Value;
        
        // Update transaction status
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'completed', mpesa_receipt = ? WHERE checkout_request_id = ?");
        $stmt->bind_param("ss", $mpesa_receipt, $checkout_id);
        $stmt->execute();
        
        // Get transaction details
        $stmt = $conn->prepare("SELECT reference, amount FROM mpesa_transactions WHERE checkout_request_id = ?");
        $stmt->bind_param("s", $checkout_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        // Process payment based on reference type
        // Example: if reference is 'bid_1', it's a bid payment for bid ID 1
        $reference_parts = explode('_', $transaction['reference']);
        $reference_type = $reference_parts[0];
        $reference_id = $reference_parts[1];
        
        switch($reference_type) {
            case 'bid':
                // Process bid payment (hide or feature)
                processBidPayment($reference_id, $transaction['amount'], $mpesa_receipt);
                break;
            case 'skill':
                // Process skill verification payment
                processSkillPayment($reference_id, $mpesa_receipt);
                break;
            // Add other payment types as needed
        }
        
        return true;
    } else {
        // Payment failed
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'failed' WHERE checkout_request_id = ?");
        $stmt->bind_param("s", $checkout_id);
        $stmt->execute();
        
        return false;
    }
}

/**
 * Process bid payment
 */
function processBidPayment($bid_id, $amount, $mpesa_receipt) {
    $conn = getDbConnection();
    
    // Get bid details
    $stmt = $conn->prepare("SELECT job_id, user_id, is_hidden, is_featured FROM bids WHERE id = ?");
    $stmt->bind_param("i", $bid_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1) {
        $bid = $result->fetch_assoc();
        
        // Determine if payment is for hiding or featuring
        $is_hidden = $bid['is_hidden'];
        $is_featured = $bid['is_featured'];
        
        // Update bid with payment info
        $stmt = $conn->prepare("UPDATE bids SET hide_fee = ?, feature_fee = ? WHERE id = ?");
        
        // If bid was already hidden, payment is for featuring, or vice versa
        if($is_hidden) {
            $hide_fee = 0;
            $feature_fee = $amount;
        } else {
            $hide_fee = $amount;
            $feature_fee = 0;
        }
        
        $stmt->bind_param("ddi", $hide_fee, $feature_fee, $bid_id);
        $stmt->execute();
        
        // Record payment in payments table
        $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, payment_type, reference_id, status, transaction_id, mpesa_receipt) 
                               VALUES (?, ?, 'bid_fee', ?, 'completed', ?, ?)");
        $stmt->bind_param("idsss", $bid['user_id'], $amount, $bid_id, $bid_id, $mpesa_receipt);
        $stmt->execute();
        
        // Create notification
        create_notification(
            $bid['user_id'],
            'Bid payment successful',
            'Your payment of $' . $amount . ' for your bid on job #' . $bid['job_id'] . ' has been processed successfully.',
            '/jobs/view.php?id=' . $bid['job_id']
        );
    }
}

/**
 * Process skill verification payment
 */
function processSkillPayment($user_skill_id, $mpesa_receipt) {
    $conn = getDbConnection();
    
    // Get user skill details
    $stmt = $conn->prepare("SELECT user_id, skill_id, verification_fee FROM user_skills WHERE id = ?");
    $stmt->bind_param("i", $user_skill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1) {
        $user_skill = $result->fetch_assoc();
        
        // Mark skill as verification fee paid
        $stmt = $conn->prepare("UPDATE user_skills SET fee_paid = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_skill_id);
        $stmt->execute();
        
        // Record payment in payments table
        $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, payment_type, reference_id, status, transaction_id, mpesa_receipt) 
                               VALUES (?, ?, 'skill_verification', ?, 'completed', ?, ?)");
        $stmt->bind_param("idsss", $user_skill['user_id'], $user_skill['verification_fee'], $user_skill_id, $user_skill_id, $mpesa_receipt);
        $stmt->execute();
        
        // Get skill name
        $stmt = $conn->prepare("SELECT name FROM skills WHERE id = ?");
        $stmt->bind_param("i", $user_skill['skill_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $skill = $result->fetch_assoc();
        
        // Create notification
        create_notification(
            $user_skill['user_id'],
            'Skill verification payment successful',
            'Your payment for verifying the ' . $skill['name'] . ' skill has been processed. Please complete the assessment to verify your skill.',
            '/skills/assessment.php?skill_id=' . $user_skill['skill_id']
        );
    }
}