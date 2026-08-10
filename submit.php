<?php
/**
 * Perfect Pointe — placement class form handler.
 * Emails submissions directly to info@perfectpointedance.com.
 * No third-party service. Returns JSON for the site's AJAX flow.
 */

header('Content-Type: application/json; charset=utf-8');

$RECIPIENT = 'info@perfectpointedance.com';
// Envelope/From must be an address on this domain for good deliverability.
$FROM      = 'Perfect Pointe Website <info@perfectpointedance.com>';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function clean($v) {
    return trim(is_string($v) ? $v : '');
}

// Strip CR/LF so values used in headers can't inject extra headers.
function header_safe($v) {
    return str_replace(["\r", "\n"], ' ', clean($v));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed.', 405);
}

// Honeypot — bots fill this hidden field. Pretend success, send nothing.
if (clean($_POST['_honey'] ?? '') !== '') {
    echo json_encode(['success' => true]);
    exit;
}

// Fields we care about, in the order they should appear in the email.
$fields = [
    'parent_name' => 'Parent Name',
    'phone'       => 'Phone',
    'email'       => 'Email',
    'dancer_name' => "Dancer's Name",
    'dancer_age'  => "Dancer's Age",
    'experience'  => 'Prior Dance Experience',
    'notes'       => 'Anything we should know?',
    'source_page' => 'Submitted From',
];

$required = ['parent_name', 'phone', 'email', 'dancer_name'];
foreach ($required as $r) {
    if (clean($_POST[$r] ?? '') === '') {
        fail('Please fill in all required fields.', 422);
    }
}

$submitterEmail = clean($_POST['email'] ?? '');
if (!filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
    fail('Please enter a valid email address.', 422);
}

// Build a readable plain-text body.
$lines = [];
foreach ($fields as $name => $label) {
    $val = clean($_POST[$name] ?? '');
    if ($val !== '') {
        $lines[] = $label . ': ' . $val;
    }
}
$body  = "New placement class request from the website:\n\n";
$body .= implode("\n", $lines);
$body .= "\n\n— Sent automatically from perfectpointedance.com";

$subject = header_safe($_POST['_subject'] ?? 'New Placement Class Request — Perfect Pointe');

$headers  = 'From: ' . $FROM . "\r\n";
$headers .= 'Reply-To: ' . header_safe($submitterEmail) . "\r\n";
$headers .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";
$headers .= 'MIME-Version: 1.0' . "\r\n";

$sent = @mail($RECIPIENT, $subject, $body, $headers, '-f info@perfectpointedance.com');

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    fail('The message could not be sent. Please call (626) 404-2219 or email info@perfectpointedance.com.', 500);
}
