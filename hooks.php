<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

use WHMCS\Database\Capsule;
use MailCertify\Core\Verification;
use MailCertify\Core\Database;

require_once __DIR__ . '/lib/Core/Database.php';
require_once __DIR__ . '/lib/Core/Verification.php';
require_once __DIR__ . '/lib/Core/BanManager.php';

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

add_hook('ClientLogin', 1, function ($vars) {
    $userId = $vars['userid'] ?? 0;
    if (!$userId) return;

    $type = Database::getSetting('verification_type');
    if ($type !== 'allpages') return;
    if (Verification::isVerified($userId)) return;

    redir('index.php', 'm=mailcertifyverify');
    exit;
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

add_hook('ClientAreaPage', 1, function ($vars) {
    $onVerify = $_SESSION['mc_on_verify'] ?? false;
    if ($onVerify) {
        unset($_SESSION['mc_on_verify']);
        return $vars;
    }

    $clientId = (int)($_SESSION['uid'] ?? 0);
    if (!$clientId) return $vars;

    $type = Database::getSetting('verification_type');
    if ($type !== 'allpages') return $vars;
    if (Verification::isVerified($clientId)) return $vars;

    redir('index.php', 'm=mailcertifyverify');
    exit;
});

add_hook('AdminAreaHeadOutput', 1, function () {
    if (isset($_GET['module']) && $_GET['module'] === 'mailcertifyverify') {
        return '<style>.mb-3 { margin-bottom:15px; }</style>';
    }
});
