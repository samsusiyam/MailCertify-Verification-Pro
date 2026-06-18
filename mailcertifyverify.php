<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

require_once __DIR__ . '/lib/Core/Database.php';
require_once __DIR__ . '/lib/Core/Verification.php';
require_once __DIR__ . '/lib/Core/BanManager.php';
require_once __DIR__ . '/lib/Client/VerifyController.php';
require_once __DIR__ . '/lib/Admin/ConfigController.php';
require_once __DIR__ . '/lib/Admin/BanController.php';
require_once __DIR__ . '/lib/Admin/LogController.php';

use WHMCS\Database\Capsule;

function mailcertifyverify_config()
{
    return [
        'name' => 'MailCertify Verification Pro',
        'description' => 'Email verification system for WHMCS with reCAPTCHA/Turnstile support, banning, and auto-termination.',
        'version' => '1.0.0',
        'author' => 'MD Samsuzzaman Siyam',
        'fields' => [],
    ];
}

function mailcertifyverify_activate()
{
    \MailCertify\Core\Database::install();
    return [
        'status' => 'success',
        'description' => 'MailCertify Verification Pro activated. Configure under Addons > MailCertify Verification.',
    ];
}

function mailcertifyverify_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'MailCertify Verification Pro deactivated. Database tables preserved.',
    ];
}

function mailcertifyverify_output($vars)
{
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    if ($action === 'clients') {
        \MailCertify\Admin\ConfigController::handleClients();
    } elseif ($action === 'manualverify' || $action === 'manual_verify') {
        \MailCertify\Admin\ConfigController::handleManualVerify();
    } elseif ($action === 'bans') {
        \MailCertify\Admin\BanController::handleBans();
    } elseif ($action === 'logs') {
        \MailCertify\Admin\LogController::handleLogs();
    } elseif ($action === 'settings') {
        \MailCertify\Admin\ConfigController::handleSettings();
    } else {
        \MailCertify\Admin\ConfigController::renderOverview();
    }
}

function mailcertifyverify_clientarea($vars)
{
    $_SESSION['mc_on_verify'] = true;

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    if ($action === 'verify' && isset($_GET['token'])) {
        $result = \MailCertify\Client\VerifyController::handleVerify();
        $_SESSION['mc_msg'] = $result['message'];
        $_SESSION['mc_success'] = $result['success'];
        header("Location: index.php?m=mailcertifyverify");
        exit;
    }

    if ($action === 'resend') {
        $result = \MailCertify\Client\VerifyController::handleResend();
        $_SESSION['mc_msg'] = $result['message'];
        $_SESSION['mc_success'] = $result['success'];
        header("Location: index.php?m=mailcertifyverify");
        exit;
    }

    $msg = $_SESSION['mc_msg'] ?? '';
    $success = $_SESSION['mc_success'] ?? false;
    unset($_SESSION['mc_msg'], $_SESSION['mc_success']);

    $pageData = \MailCertify\Client\VerifyController::renderVerifyPage();

    if (isset($pageData['redirect'])) {
        if ($msg && $pageData['redirect'] === 'clientarea.php') {
            $pageData = ['vars' => []];
        } else {
            header("Location: " . $pageData['redirect']);
            exit;
        }
    }

    if (isset($pageData['template']) && $pageData['template'] === 'banned') {
        return [
            'banned' => true,
            'ban_type' => $pageData['vars']['type'] ?? '',
            'ban_value' => $pageData['vars']['value'] ?? '',
        ];
    }

    return [
        'captcha_html' => $pageData['vars']['captcha_html'] ?? '',
        'email' => $pageData['vars']['email'] ?? '',
        'message' => $msg,
        'success' => $success,
        'error' => !$success && $msg ? true : false,
    ];
}
