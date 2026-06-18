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

add_hook('ClientAreaPage', 1, function ($vars) {
    $clientId = (int)($_SESSION['uid'] ?? 0);
    if (!$clientId) return $vars;

    $type = Database::getSetting('verification_type');
    if ($type !== 'allpages') return $vars;

    if (Verification::isVerified($clientId)) return $vars;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $allowed = ['logout.php', 'submitticket.php', 'viewticket.php', 'supporttickets.php', 'clientarea.php?action=details'];
    foreach ($allowed as $page) {
        if (strpos($uri, $page) !== false) return $vars;
    }

    if (isset($_GET['m']) && $_GET['m'] === 'mailcertifyverify') return $vars;

    $_SESSION['mailcertify_force_verify'] = true;
    return $vars;
});

add_hook('ClientAreaHeadOutput', 1, function () {
    if (empty($_SESSION['mailcertify_force_verify'])) return '';
    unset($_SESSION['mailcertify_force_verify']);

    $clientId = (int)($_SESSION['uid'] ?? 0);
    if (!$clientId || Verification::isVerified($clientId)) return '';

    $verifyUrl = 'index.php?m=mailcertifyverify';
    return <<<HTML
<style>
html, body { height: 100%; margin: 0; overflow: hidden; }
#mailcertify-overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: #f5f5f5; z-index: 999999;
    display: flex; align-items: center; justify-content: center;
}
#mailcertify-overlay .mc-box {
    background: #fff; padding: 50px; border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center;
    max-width: 500px;
}
#mailcertify-overlay h2 { margin-bottom: 15px; color: #333; }
#mailcertify-overlay p { color: #666; margin-bottom: 20px; }
#mailcertify-overlay .mc-btn {
    display: inline-block; padding: 12px 30px;
    background: #0066cc; color: #fff; text-decoration: none;
    border-radius: 4px; font-size: 16px;
}
#mailcertify-overlay .mc-btn:hover { background: #0052a3; }
</style>
<div id="mailcertify-overlay">
    <div class="mc-box">
        <h2>Email Verification Required</h2>
        <p>You must verify your email address before accessing this area.</p>
        <p>A verification link has been sent to your email inbox.</p>
        <a href="{$verifyUrl}" class="mc-btn">Verify Email Now</a>
    </div>
</div>
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
