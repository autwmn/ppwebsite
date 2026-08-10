<?php
/**
 * Perfect Pointe Dance — placement class / waitlist form handler.
 * Emails submissions to info@perfectpointedance.com.
 * Returns JSON: {"ok":true} on success, {"ok":false,"error":"..."} on failure.
 */

header('Content-Type: application/json; charset=utf-8');

// --- Where submissions go ---
$TO      = 'info@perfectpointedance.com';
// The From address MUST be on a domain that Bluehost's server is authorized to send for
// (SPF/DKIM), otherwise Google/Squarespace rejects it. The domain's real email lives at
// Squarespace, so we send FROM the Bluehost account domain and set Reply-To to the parent.
$FROM    = 'noreply@elz.sqs.mybluehost.me';
$SITE    = 'Perfect Pointe Dance website';

// --- Only accept POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// --- Honeypot: bots fill hidden fields; real people don't. Pretend success. ---
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// --- Helper to clean input ---
function clean($key) {
    $v = isset($_POST[$key]) ? trim($_POST[$key]) : '';
    // Strip newlines from single-line fields to prevent header injection.
    return str_replace(["\r", "\n"], ' ', $v);
}

$parent     = clean('parent_name');
$phone      = clean('phone');
$email      = clean('email');
$dancer     = clean('dancer_name');
$age        = clean('dancer_age');
$experience = clean('experience');
$selected   = clean('selected_class');
$source     = clean('source_page');
$type       = clean('form_type'); // "placement" or "waitlist"
$notes      = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// --- Basic validation ---
if ($parent === '' || $phone === '' || $email === '' || $dancer === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please fill in the required fields.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

// --- Build the message ---
$isWaitlist = ($type === 'waitlist');
$subject = ($isWaitlist ? 'Waitlist Request' : 'Placement Class Request')
         . ' — ' . $dancer . ' (age ' . ($age !== '' ? $age : 'n/a') . ')';

$lines = [];
$lines[] = ($isWaitlist ? 'NEW WAITLIST REQUEST' : 'NEW PLACEMENT CLASS REQUEST');
$lines[] = str_repeat('=', 40);
$lines[] = 'Parent name:     ' . $parent;
$lines[] = 'Phone:           ' . $phone;
$lines[] = 'Email:           ' . $email;
$lines[] = 'Dancer name:     ' . $dancer;
$lines[] = 'Dancer age:      ' . ($age !== '' ? $age : '(not given)');
$lines[] = 'Experience:      ' . ($experience !== '' ? $experience : '(not given)');
if ($selected !== '') {
    $lines[] = 'Class of interest: ' . $selected;
}
$lines[] = '';
$lines[] = 'Notes:';
$lines[] = ($notes !== '' ? $notes : '(none)');
$lines[] = '';
$lines[] = str_repeat('-', 40);
$lines[] = 'Submitted from: ' . ($source !== '' ? $source : 'website');
$lines[] = 'Date: ' . date('l, F j, Y g:i A T');

$body = implode("\n", $lines);

// --- Headers ---
$headers  = 'From: ' . $SITE . ' <' . $FROM . '>' . "\r\n";
$headers .= 'Reply-To: ' . $parent . ' <' . $email . '>' . "\r\n";
$headers .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

// Envelope sender (helps deliverability on Bluehost shared hosting).
$params = '-f ' . $FROM;

$sent = @mail($TO, $subject, $body, $headers, $params);

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Message could not be sent. Please call us.']);
}
