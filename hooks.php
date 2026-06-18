<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\Authentication\CurrentUser;
use MailCertify\Core\Verification;
use MailCertify\Core\Database;
use MailCertify\Core\BanManager;
use MailCertify\Client\VerifyController;

require_once __DIR__ . '/lib/Core/Database.php';
require_once __DIR__ . '/lib/Core/Verification.php';
require_once __DIR__ . '/lib/Core/BanManager.php';
require_once __DIR__ . '/lib/Client/VerifyController.php';

// --- Client Area Page Hook: Check verification ---
add_hook('ClientAreaPage', 1, function ($vars) {
    $check = VerifyController::checkAccess();
    if (!$check['allowed']) {
        $redirectUrl = $check['redirect'];
        header("Location: {$redirectUrl}");
        exit;
    }
    return $vars;
});

// --- After Login: Check verification ---
add_hook('ClientAreaPage', 2, function ($vars) {
    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($clientId) {
        $verified = Verification::isVerified($clientId);
        $_SESSION['email_verified'] = $verified;
    }
    return $vars;
});

// --- Client Add (Registration) Hook ---
add_hook('ClientAdd', 1, function ($vars) {
    $clientId = $vars['userid'];
    $email = $vars['email'];

    $ip = Verification::getClientIP();

    if (Verification::isIPBanned($ip)) {
        Capsule::table('tblclients')
            ->where('id', $clientId)
            ->delete();
        throw new \Exception('Your IP address has been banned.');
    }

    if (Verification::isEmailBanned($email)) {
        Capsule::table('tblclients')
            ->where('id', $clientId)
            ->delete();
        throw new \Exception('This email address has been banned.');
    }

    Verification::createVerification($clientId, $email);
});

// --- After Registration Redirect ---
add_hook('ClientAreaPageRegister', 1, function ($vars) {
    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($clientId) {
        $verified = Verification::isVerified($clientId);
        if (!$verified) {
            $verificationType = Database::getSetting('verification_type');
            if ($verificationType === 'allpages') {
                header("Location: index.php?m=mailcertifyverify");
                exit;
            }
        }
    }
    return $vars;
});

// --- Shopping Cart Checkout: Checkout Mode ---
add_hook('ShoppingCartCheckoutOutput', 1, function ($vars) {
    $verificationType = Database::getSetting('verification_type');
    if ($verificationType !== 'checkout') {
        return $vars;
    }

    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($clientId) {
        $verified = Verification::isVerified($clientId);
        if (!$verified) {
            $verifyUrl = 'index.php?m=mailcertifyverify';
            header("Location: {$verifyUrl}");
            exit;
        }
    }
    return $vars;
});

// --- Order Creation: Check if verified ---
add_hook('ShoppingCartValidateOrder', 1, function ($vars) {
    $verificationType = Database::getSetting('verification_type');
    if ($verificationType !== 'checkout') {
        return $vars;
    }

    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($clientId) {
        $verified = Verification::isVerified($clientId);
        if (!$verified) {
            return [
                'Please verify your email address before placing an order. <a href="index.php?m=mailcertifyverify">Click here to verify.</a>'
            ];
        }
    }
    return $vars;
});

// --- Accept Order Check (Credit Card payment) ---
add_hook('AcceptOrder', 1, function ($vars) {
    $userId = $vars['userid'];
    $verificationType = Database::getSetting('verification_type');
    if ($verificationType === 'checkout') {
        $verified = Verification::isVerified($userId);
        if (!$verified) {
            return ['error' => 'Email not verified. Client must verify email before ordering.'];
        }
    }
    return $vars;
});

// --- Email Change Hook ---
add_hook('ClientChangeEmail', 1, function ($vars) {
    $clientId = $vars['userid'];
    $newEmail = $vars['newemail'];

    $lockEmail = Database::getSetting('lock_email');
    if ($lockEmail === 'on') {
        $verified = Verification::isVerified($clientId);
        if (!$verified) {
            throw new \Exception('You must verify your current email address before changing it.');
        }
    }

    Verification::createVerification($clientId, $newEmail);
});

// --- Client Area Head Output: Inject CSS ---
add_hook('ClientAreaHeadOutput', 1, function () {
    return <<<CSS
<style>
.mailcertify-verify-container {
    max-width: 600px;
    margin: 50px auto;
    padding: 30px;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}
.mailcertify-verify-container h2 {
    margin-bottom: 20px;
    color: #333;
}
.mailcertify-verify-container .email-display {
    font-size: 18px;
    font-weight: bold;
    color: #0066cc;
    margin: 15px 0;
}
.mailcertify-verify-container .btn-resend {
    margin-top: 20px;
}
.mailcertify-verify-container .alert {
    margin-top: 20px;
}
.mailcertify-banned-container {
    max-width: 600px;
    margin: 50px auto;
    padding: 30px;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}
.mailcertify-banned-container .banned-icon {
    font-size: 64px;
    color: #cc0000;
    margin-bottom: 20px;
}
</style>
CSS;
});

// --- Client Area Output for module ---
add_hook('ClientAreaPrimarySidebar', 1, function ($sidebar) {
    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($clientId) {
        $verified = Verification::isVerified($clientId);
        if (!$verified) {
            $sidebar->addChild('email-verify-notice', [
                'label' => '<span style="color:red">⚠ Email Not Verified</span>',
                'uri' => 'index.php?m=mailcertifyverify',
                'order' => '1',
                'icon' => 'fa-envelope',
            ]);
        }
    }
    return $sidebar;
});

// --- Daily Cron: Auto-terminate, delete, resend ---
add_hook('DailyCronJob', 1, function ($vars) {
    Verification::autoTerminateUnverified();
    Verification::autoDeleteUnverified();
    Verification::resendUnverified();
});

// --- Admin Area Head Output ---
add_hook('AdminAreaHeadOutput', 1, function () {
    // Only load on our module pages
    if (isset($_GET['module']) && $_GET['module'] === 'mailcertifyverify') {
        return <<<CSS
<style>
.mb-3 { margin-bottom: 15px; }
</style>
CSS;
    }
});

// --- Module Client Area Router ---
add_hook('ClientAreaPage', 3, function ($vars) {
    if (isset($_GET['m']) && $_GET['m'] === 'mailcertifyverify') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        if ($action === 'resend') {
            $result = VerifyController::handleResend();
            $_SESSION['mailcertify_message'] = $result['message'];
            $_SESSION['mailcertify_success'] = $result['success'];
            header("Location: index.php?m=mailcertifyverify");
            exit;
        }

        if ($action === 'verify') {
            $result = VerifyController::handleVerify();
            if ($result['success']) {
                $_SESSION['mailcertify_message'] = $result['message'];
                $_SESSION['mailcertify_success'] = true;
                if (isset($result['redirect'])) {
                    header("Location: " . $result['redirect']);
                    exit;
                }
                header("Location: clientarea.php");
                exit;
            } else {
                $_SESSION['mailcertify_message'] = $result['message'];
                $_SESSION['mailcertify_success'] = false;
            }
        }

        $message = $_SESSION['mailcertify_message'] ?? '';
        $success = $_SESSION['mailcertify_success'] ?? false;
        unset($_SESSION['mailcertify_message'], $_SESSION['mailcertify_success']);

        $result = VerifyController::renderVerifyPage();

        if (isset($result['redirect'])) {
            header("Location: " . $result['redirect']);
            exit;
        }

        if (isset($result['template'])) {
            $template = $result['template'];
            $vars = $result['vars'];
        } else {
            $template = 'verify';
            $vars = [];
        }

        $vars['message'] = $message;
        $vars['success'] = $success;

        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
        $vars['client_id'] = $clientId;

        if ($template === 'banned') {
            return renderBannedPage($vars);
        }

        return renderVerifyPageOutput($vars);
    }
    return $vars;
});

function renderVerifyPageOutput($vars)
{
    $email = $vars['email'] ?? '';
    $resendUrl = $vars['resend_url'] ?? '';
    $captchaHtml = $vars['captcha_html'] ?? '';
    $message = $vars['message'] ?? '';
    $success = $vars['success'] ?? false;

    $alertType = $success ? 'alert-success' : 'alert-danger';
    $alertHtml = $message ? "<div class=\"{$alertType}\">{$message}</div>" : '';

    return <<<HTML
<div class="mailcertify-verify-container">
    <h2>Email Verification Required</h2>
    <p>Please verify your email address to continue.</p>
    <p>A verification link has been sent to:</p>
    <div class="email-display">{$email}</div>
    <p>Check your inbox (and spam folder) for the verification email.</p>
    {$alertHtml}
    <form method="post" action="{$resendUrl}" class="btn-resend">
        {$captchaHtml}
        <br><br>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-envelope"></i> Resend Verification Email
        </button>
    </form>
    <p style="margin-top:20px;font-size:12px;color:#888">
        <a href="clientarea.php?action=details">Update your profile</a> |
        <a href="submitticket.php">Open a support ticket</a>
    </p>
</div>
HTML;
}

function renderBannedPage($vars)
{
    $type = $vars['type'] ?? '';
    $value = $vars['value'] ?? '';

    return <<<HTML
<div class="mailcertify-banned-container">
    <div class="banned-icon">
        <i class="fa fa-shield"></i>
    </div>
    <h2>Access Blocked</h2>
    <p>Your {$type} <strong>{$value}</strong> has been blocked.</p>
    <p>If you believe this is an error, please contact support.</p>
    <p>
        <a href="submitticket.php" class="btn btn-primary">
            <i class="fa fa-ticket"></i> Contact Support
        </a>
    </p>
</div>
HTML;
}
