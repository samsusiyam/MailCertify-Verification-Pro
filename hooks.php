<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use MailCertify\Core\Verification;
use MailCertify\Core\Database;
use MailCertify\Client\VerifyController;

require_once __DIR__ . '/lib/Core/Database.php';
require_once __DIR__ . '/lib/Core/Verification.php';
require_once __DIR__ . '/lib/Core/BanManager.php';
require_once __DIR__ . '/lib/Client/VerifyController.php';

add_hook('ClientAdd', 1, function ($vars) {
    $clientId = $vars['userid'];
    $email = $vars['email'];
    $ip = Verification::getClientIP();

    if (Verification::isIPBanned($ip)) {
        Capsule::table('tblclients')->where('id', $clientId)->delete();
        throw new \Exception('Your IP address has been banned.');
    }
    if (Verification::isEmailBanned($email)) {
        Capsule::table('tblclients')->where('id', $clientId)->delete();
        throw new \Exception('This email address has been banned.');
    }

    Verification::createVerification($clientId, $email);
});

add_hook('ShoppingCartCheckoutOutput', 1, function ($vars) {
    $type = Database::getSetting('verification_type');
    if ($type !== 'checkout') return $vars;

    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId && !Verification::isVerified($clientId)) {
        header("Location: index.php?m=mailcertifyverify");
        exit;
    }
    return $vars;
});

add_hook('ShoppingCartValidateOrder', 1, function ($vars) {
    $type = Database::getSetting('verification_type');
    if ($type !== 'checkout') return $vars;

    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId && !Verification::isVerified($clientId)) {
        return ['Please verify your email address before placing an order. <a href="index.php?m=mailcertifyverify">Click here to verify.</a>'];
    }
    return $vars;
});

add_hook('ClientChangeEmail', 1, function ($vars) {
    $clientId = $vars['userid'];
    if (Database::getSetting('lock_email') === 'on' && !Verification::isVerified($clientId)) {
        throw new \Exception('You must verify your current email address before changing it.');
    }
    Verification::createVerification($clientId, $vars['newemail']);
});

add_hook('ClientAreaHeadOutput', 1, function () {
    $clientId = (int)($_SESSION['uid'] ?? 0);
    if (!$clientId) return '';

    $type = Database::getSetting('verification_type');
    if ($type !== 'allpages') return '';

    if (Verification::isVerified($clientId)) return '';

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $allowed = ['logout.php', 'submitticket.php', 'viewticket.php', 'supporttickets.php', 'clientarea.php?action=details'];
    foreach ($allowed as $page) {
        if (strpos($uri, $page) !== false) return '';
    }

    $verifyUrl = 'index.php?m=mailcertifyverify';
    return <<<HTML
<script>
if (window.location.href.indexOf('m=mailcertifyverify') === -1) {
    window.location.href = '{$verifyUrl}';
}
</script>
HTML;
});

add_hook('ClientAreaPrimarySidebar', 1, function ($sidebar) {
    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId && !Verification::isVerified($clientId)) {
        $sidebar->addChild('email-verify-notice', [
            'label' => '<span style="color:red">⚠ Email Not Verified</span>',
            'uri' => 'index.php?m=mailcertifyverify',
            'order' => '1',
            'icon' => 'fa-envelope',
        ]);
    }
    return $sidebar;
});

add_hook('DailyCronJob', 1, function ($vars) {
    Verification::autoTerminateUnverified();
    Verification::autoDeleteUnverified();
    Verification::resendUnverified();
});

add_hook('AdminAreaHeadOutput', 1, function () {
    if (isset($_GET['module']) && $_GET['module'] === 'mailcertifyverify') {
        return '<style>.mb-3 { margin-bottom:15px; }</style>';
    }
});
