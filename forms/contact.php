<?php
/**
 * Contact Form Handler - Rabbit Fresh / Neer Investment
 * Sends form submissions directly to email
 */

// ============================================
// CONFIGURATION
// ============================================
$recipient_email = "investmentneer26@gmail.com";  // Where inquiries go
$site_name       = "Rabbit Fresh (Neer Investment)";
$admin_email     = "investmentneer26@gmail.com";  // From address (use same domain if possible)
$max_message_len = 5000;

// ============================================
// CORS & SECURITY HEADERS
// ============================================
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ============================================
// HONEYPOT / BOT PROTECTION
// (Optional: add a hidden field named "website" in the HTML form)
// ============================================
if (!empty($_POST['website'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Spam detected']);
    exit;
}

// Rate limiting (simple — prevents same IP spamming)
session_start();
$now = time();
$cooldown = 60; // seconds between submissions
if (isset($_SESSION['last_submit']) && ($now - $_SESSION['last_submit']) < $cooldown) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Please wait a moment before submitting again.']);
    exit;
}

// ============================================
// SANITIZE & VALIDATE INPUT
// ============================================
function clean($value) {
    return htmlspecialchars(trim(strip_tags($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function isValidPhone($phone) {
    // Accepts +255..., 0..., digits, spaces, dashes, parentheses
    return preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $phone);
}

$inquiryType = clean($_POST['inquiryType'] ?? '');
$name        = clean($_POST['name'] ?? '');
$company     = clean($_POST['company'] ?? '');
$email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone       = clean($_POST['phone'] ?? '');
$location    = clean($_POST['location'] ?? '');
$message     = clean($_POST['message'] ?? '');

// Validation
$errors = [];

$allowedTypes = [
    'Wholesale Order', 'Retail Partnership', 'Distributor Partnership',
    'Supermarket Supply', 'Institutional Supply', 'Individual Customer', 'Other Inquiry'
];

if (empty($inquiryType) || !in_array($inquiryType, $allowedTypes)) {
    $errors[] = 'Please select a valid inquiry type.';
}
if (empty($name)) {
    $errors[] = 'Full name is required.';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (empty($phone) || !isValidPhone($phone)) {
    $errors[] = 'A valid phone number is required.';
}
if (empty($message)) {
    $errors[] = 'Message is required.';
}
if (strlen($message) > $max_message_len) {
    $errors[] = 'Message is too long (max ' . $max_message_len . ' characters).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ============================================
// BUILD EMAIL
// ============================================
$subject = "New Inquiry: {$inquiryType} - {$name}";

// Plain text version
$plainBody = "You have received a new inquiry from the Rabbit Fresh website.\n\n";
$plainBody .= "=====================================\n";
$plainBody .= "Inquiry Type : {$inquiryType}\n";
$plainBody .= "Full Name    : {$name}\n";
$plainBody .= "Company      : " . ($company ?: 'N/A') . "\n";
$plainBody .= "Email        : {$email}\n";
$plainBody .= "Phone        : {$phone}\n";
$plainBody .= "Location     : " . ($location ?: 'N/A') . "\n";
$plainBody .= "=====================================\n\n";
$plainBody .= "Message:\n{$message}\n\n";
$plainBody .= "-------------------------------------\n";
$plainBody .= "Sent from " . $site_name . " website\n";
$plainBody .= "Date: " . date('Y-m-d H:i:s T') . "\n";
$plainBody .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

// HTML version
$htmlBody = "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color: #333; line-height: 1.6; }
    .wrap { max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px; }
    .card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    .header { background: #0d6efd; color: #fff; padding: 20px; }
    .header h2 { margin: 0; font-size: 20px; }
    .content { padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 14px; }
    td.label { font-weight: bold; color: #1a1a2e; width: 35%; }
    .msg-box { background: #f1f5ff; border-left: 4px solid #0d6efd; padding: 15px; border-radius: 6px; white-space: pre-wrap; font-size: 14px; }
    .footer { text-align: center; font-size: 12px; color: #888; padding: 15px; }
  </style>
</head>
<body>
  <div class='wrap'>
    <div class='card'>
      <div class='header'>
        <h2>New Inquiry — Rabbit Fresh</h2>
      </div>
      <div class='content'>
        <table>
          <tr><td class='label'>Inquiry Type</td><td>{$inquiryType}</td></tr>
          <tr><td class='label'>Full Name</td><td>{$name}</td></tr>
          <tr><td class='label'>Company</td><td>" . ($company ?: 'N/A') . "</td></tr>
          <tr><td class='label'>Email</td><td><a href='mailto:{$email}'>{$email}</a></td></tr>
          <tr><td class='label'>Phone</td><td>{$phone}</td></tr>
          <tr><td class='label'>Location</td><td>" . ($location ?: 'N/A') . "</td></tr>
        </table>
        <h3 style='font-size:15px; margin:0 0 10px; color:#1a1a2e;'>Message</h3>
        <div class='msg-box'>{$message}</div>
      </div>
      <div class='footer'>
        Sent from {$site_name} website &middot; " . date('d M Y, H:i') . "
      </div>
    </div>
  </div>
</body>
</html>
";

// ============================================
// HEADERS (anti-spoofing)
// ============================================
$boundary = '----=_Part_' . md5(uniqid((string)mt_rand(), true));

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "From: {$site_name} <{$admin_email}>\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// Multipart (plain + HTML)
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

$body  = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $plainBody . "\r\n\r\n";

$body .= "--{$boundary}\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $htmlBody . "\r\n\r\n";

$body .= "--{$boundary}--";

// ============================================
// SEND EMAIL
// ============================================
$sent = @mail($recipient_email, $subject, $body, $headers);

if (!$sent) {
    // Log the failure for debugging
    error_log("Contact form mail failed at " . date('c'));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Sorry, the message could not be sent right now. Please call us on +255 796 617 333.'
    ]);
    exit;
}

// ============================================
// OPTIONAL: SEND AUTO-REPLY TO CUSTOMER
// ============================================
$autoSubject = "We received your message — {$site_name}";
$autoBody    = "Hello {$name},\n\n";
$autoBody   .= "Thank you for contacting {$site_name}. We have received your inquiry ({$inquiryType}) ";
$autoBody   .= "and our team will get back to you within 24 hours.\n\n";
$autoBody   .= "For urgent matters, call us on +255 796 617 333.\n\n";
$autoBody   .= "Best regards,\n{$site_name}\nSungwi Street, Temeke, Dar es Salaam, Tanzania";

$autoHeaders  = "MIME-Version: 1.0\r\n";
$autoHeaders .= "From: {$site_name} <{$admin_email}>\r\n";
$autoHeaders .= "Reply-To: {$recipient_email}\r\n";
$autoHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($email, $autoSubject, $autoBody, $autoHeaders);

// ============================================
// SUCCESS RESPONSE
// ============================================
$_SESSION['last_submit'] = $now;
echo json_encode([
    'success' => true,
    'message' => 'Your message has been sent. Thank you! Our team will contact you shortly.'
]);