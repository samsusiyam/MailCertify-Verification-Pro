<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/lib/Core/Database.php';
require_once __DIR__ . '/lib/Core/Verification.php';
require_once __DIR__ . '/lib/Core/BanManager.php';
require_once __DIR__ . '/lib/Admin/ConfigController.php';
require_once __DIR__ . '/lib/Admin/BanController.php';
require_once __DIR__ . '/lib/Admin/LogController.php';
require_once __DIR__ . '/lib/Client/VerifyController.php';

function mailcertifyverify_config()
{
    $dbVersion = \MailCertify\Core\Database::getDbVersion();
    $latestVersion = '1.0.0';

    return [
        'name' => 'MailCertify Verification Pro',
        'description' => 'Email verification module. Supports All Page & Checkout verification, IP/email banning, reCAPTCHA v3, CloudFlare Turnstile, and auto-termination.',
        'author' => 'MD Samsuzzaman Siyam',
        'language' => 'english',
        'version' => $latestVersion,
        'fields' => [
            'verification_type' => [
                'FriendlyName' => 'Verification Type',
                'Type' => 'dropdown',
                'Options' => 'checkout,allpages',
                'Description' => 'Checkout: verify before placing order. All Pages: restrict all account access until verified.',
                'Default' => 'checkout',
            ],
            'lock_email' => [
                'FriendlyName' => 'Lock Email',
                'Type' => 'yesno',
                'Description' => 'Prevent clients from changing their email address until verified.',
                'Default' => 'on',
            ],
            'auto_terminate_enabled' => [
                'FriendlyName' => 'Enable Auto Terminate',
                'Type' => 'yesno',
                'Description' => 'Auto-terminate/close unverified accounts after X days.',
                'Default' => 'on',
            ],
            'auto_terminate_days' => [
                'FriendlyName' => 'Auto Terminate Days',
                'Type' => 'text',
                'Size' => '5',
                'Description' => 'Days after which unverified accounts are auto-terminated.',
                'Default' => '7',
            ],
            'auto_delete_enabled' => [
                'FriendlyName' => 'Enable Auto Delete',
                'Type' => 'yesno',
                'Description' => 'Permanently delete unverified accounts with no active orders after X days.',
                'Default' => 'off',
            ],
            'auto_delete_days' => [
                'FriendlyName' => 'Auto Delete Days',
                'Type' => 'text',
                'Size' => '5',
                'Description' => 'Days after which unverified accounts are permanently deleted.',
                'Default' => '30',
            ],
            'resend_enabled' => [
                'FriendlyName' => 'Enable Resend Email',
                'Type' => 'yesno',
                'Description' => 'Resend verification email after X days if not verified.',
                'Default' => 'on',
            ],
            'resend_days' => [
                'FriendlyName' => 'Resend Email Days',
                'Type' => 'text',
                'Size' => '5',
                'Description' => 'Days after which verification email is resent.',
                'Default' => '3',
            ],
            'captcha_type' => [
                'FriendlyName' => 'CAPTCHA Type',
                'Type' => 'dropdown',
                'Options' => 'none,recaptcha,turnstile',
                'Description' => 'Select CAPTCHA type for extra security on verification page.',
                'Default' => 'none',
            ],
            'recaptcha_site_key' => [
                'FriendlyName' => 'reCAPTCHA v3 Site Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Google reCAPTCHA v3 site key.',
                'Default' => '',
            ],
            'recaptcha_secret_key' => [
                'FriendlyName' => 'reCAPTCHA v3 Secret Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Google reCAPTCHA v3 secret key.',
                'Default' => '',
            ],
            'turnstile_site_key' => [
                'FriendlyName' => 'CloudFlare Turnstile Site Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'CloudFlare Turnstile site key.',
                'Default' => '',
            ],
            'turnstile_secret_key' => [
                'FriendlyName' => 'CloudFlare Turnstile Secret Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'CloudFlare Turnstile secret key.',
                'Default' => '',
            ],
            'ban_ip_days' => [
                'FriendlyName' => 'Ban IP Duration (Days)',
                'Type' => 'text',
                'Size' => '5',
                'Description' => 'Number of days to ban an IP address.',
                'Default' => '30',
            ],
        ],
    ];
}

function mailcertifyverify_activate()
{
    try {
        \MailCertify\Core\Database::install();
        return [
            'status' => 'success',
            'description' => 'MailCertify Verification Pro module activated successfully.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Failed to activate module: ' . $e->getMessage(),
        ];
    }
}

function mailcertifyverify_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'MailCertify Verification Pro module deactivated successfully.',
    ];
}

function mailcertifyverify_upgrade($vars)
{
    $currentVersion = $vars['version'];
    \MailCertify\Core\Database::runUpgrades($currentVersion);
}

function mailcertifyverify_sidebar($vars)
{
    return \MailCertify\Admin\ConfigController::renderSidebar($vars);
}

function mailcertifyverify_output($vars)
{
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'overview';

    switch ($action) {
        case 'settings':
            \MailCertify\Admin\ConfigController::handleSettings();
            break;
        case 'bans':
            \MailCertify\Admin\BanController::handleBans();
            break;
        case 'logs':
            \MailCertify\Admin\LogController::handleLogs();
            break;
        case 'clients':
            \MailCertify\Admin\ConfigController::handleClients();
            break;
        case 'manual_verify':
            \MailCertify\Admin\ConfigController::handleManualVerify();
            break;
        default:
            \MailCertify\Admin\ConfigController::renderOverview();
            break;
    }
}
