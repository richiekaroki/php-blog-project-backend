<?php
// subscribe.php - Newsletter sign-up for the server-rendered pages.
// Called via POST from the newsletter form; validates the email, applies
// the same spam guards as the API (honeypot + rate limit), then redirects
// back to the page the visitor came from.

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Connection;
use App\Middleware\CSRF;
use App\Middleware\RateLimit;

$pdo = Connection::getInstance();
CSRF::init();

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$honeypot = trim((string)($_POST['website'] ?? ''));
$back = trim((string)($_POST['back'] ?? 'index.php'));

// Honeypot: a real human never fills this hidden field; bots do.
if ($honeypot !== '') {
    header("Location: " . $back);
    exit;
}

if (!CSRF::verify()) {
    header("Location: " . $back . (str_contains($back, '?') ? '&' : '?') . 'subscribe_error=1');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . $back . (str_contains($back, '?') ? '&' : '?') . 'subscribe_error=1');
    exit;
}

// Spam guard: max 5 subscription attempts per IP per 15 minutes.
$rateLimit = new RateLimit($pdo);
$ip = RateLimit::clientIp();
if ($rateLimit->isBlocked('newsletter', $ip, 5, 900)) {
    header("Location: " . $back . (str_contains($back, '?') ? '&' : '?') . 'subscribe_error=1');
    exit;
}
$rateLimit->hit('newsletter', $ip, 900);

$result = \App\Models\Subscriber::subscribe($email);
if ($result === null) {
    header("Location: " . $back . (str_contains($back, '?') ? '&' : '?') . 'subscribe_error=1');
    exit;
}

header("Location: " . $back . (str_contains($back, '?') ? '&' : '?') . 'subscribed=1');
exit;