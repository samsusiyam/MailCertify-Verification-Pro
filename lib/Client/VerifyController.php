<?php

namespace MailCertify\Client;

use WHMCS\Database\Capsule;
use MailCertify\Core\Verification;
use MailCertify\Core\Database;

class VerifyController
{
    public static function handleVerify()
    {
        $token = isset($_GET['token']) ? $_GET['token'] : '';
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;

        if ($token) {
            $result = Verification::verifyEmail($token);
            if ($result['success']) {
                $_SESSION['email_verified'] = 1;
                return ['success' => true, 'message' => 'Your email has been verified successfully!'];
            }
            return ['success' => false, 'message' => $result['message']];
        }

        if ($clientId) {
            $client = Capsule::table('tblclients')->find($clientId);
            if ($client && $client->email_verified) {
                $_SESSION['email_verified'] = 1;
                return ['success' => true, 'message' => 'Email already verified.', 'redirect' => 'clientarea.php'];
            }
        }

        return ['success' => false, 'message' => 'Invalid verification request.'];
    }

    public static function renderVerifyPage()
    {
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;

        if (!$clientId) {
            return ['redirect' => 'login.php'];
        }

        $client = Capsule::table('tblclients')->find($clientId);
        if (!$client) {
            return ['redirect' => 'login.php'];
        }

        if ($client->email_verified) {
            return ['redirect' => 'clientarea.php'];
        }

        $captchaType = Database::getSetting('captcha_type');
        $recaptchaSite = Database::getSetting('recaptcha_site_key');
        $turnstileSite = Database::getSetting('turnstile_site_key');

        $email = $client->email;
        $resendUrl = 'index.php?m=mailcertifyverify&action=resend';

        $captchaHtml = '';
        if ($captchaType === 'recaptcha' && $recaptchaSite) {
            $captchaHtml = <<<HTML
<script src="https://www.google.com/recaptcha/api.js"></script>
<div class="g-recaptcha" data-sitekey="{$recaptchaSite}"></div>
HTML;
        } elseif ($captchaType === 'turnstile' && $turnstileSite) {
            $captchaHtml = <<<HTML
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<div class="cf-turnstile" data-sitekey="{$turnstileSite}"></div>
HTML;
        }

        $ip = Verification::getClientIP();
        $isBanned = Verification::isIPBanned($ip);
        $banEmail = Verification::isEmailBanned($email);

        if ($isBanned) {
            return [
                'template' => 'banned',
                'vars' => ['type' => 'IP', 'value' => $ip],
            ];
        }

        if ($banEmail) {
            return [
                'template' => 'banned',
                'vars' => ['type' => 'Email', 'value' => $email],
            ];
        }

        return [
            'template' => 'verify',
            'vars' => [
                'email' => $email,
                'resend_url' => $resendUrl,
                'captcha_html' => $captchaHtml,
                'captcha_type' => $captchaType,
            ],
        ];
    }

    public static function handleResend()
    {
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;

        if (!$clientId) {
            return ['success' => false, 'message' => 'Please login first.'];
        }

        $client = Capsule::table('tblclients')->find($clientId);
        if (!$client) {
            return ['success' => false, 'message' => 'Client not found.'];
        }

        if ($client->email_verified) {
            return ['success' => true, 'message' => 'Email already verified.'];
        }

        $captchaType = Database::getSetting('captcha_type');

        if ($captchaType === 'recaptcha') {
            $secretKey = Database::getSetting('recaptcha_secret_key');
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaResponse}");
            $captchaSuccess = json_decode($verify)->success;
            if (!$captchaSuccess) {
                return ['success' => false, 'message' => 'reCAPTCHA verification failed.'];
            }
        } elseif ($captchaType === 'turnstile') {
            $secretKey = Database::getSetting('turnstile_secret_key');
            $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
            $verify = file_get_contents("https://challenges.cloudflare.com/turnstile/v0/siteverify?secret={$secretKey}&response={$turnstileResponse}");
            $captchaSuccess = json_decode($verify)->success;
            if (!$captchaSuccess) {
                return ['success' => false, 'message' => 'Turnstile verification failed.'];
            }
        }

        Verification::resendVerification($clientId, $client->email);
        return ['success' => true, 'message' => 'Verification email has been resent.'];
    }

    public static function checkAccess()
    {
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;

        if (!$clientId) {
            return ['allowed' => true];
        }

        $verificationType = Database::getSetting('verification_type');
        if ($verificationType !== 'allpages') {
            return ['allowed' => true];
        }

        $client = Capsule::table('tblclients')->find($clientId);
        if (!$client || $client->email_verified) {
            return ['allowed' => true];
        }

        $allowedPages = [
            'index.php?m=mailcertifyverify',
            'logout.php',
            'clientarea.php?action=details',
            'submitticket.php',
            'viewticket.php',
            'supporttickets.php',
        ];

        $currentPage = $_SERVER['REQUEST_URI'];
        foreach ($allowedPages as $page) {
            if (strpos($currentPage, $page) !== false) {
                return ['allowed' => true];
            }
        }

        return [
            'allowed' => false,
            'redirect' => 'index.php?m=mailcertifyverify',
            'message' => 'Please verify your email address to access this page.',
        ];
    }
}
