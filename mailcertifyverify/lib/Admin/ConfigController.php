<?php

namespace MailCertify\Admin;

use WHMCS\Database\Capsule;
use MailCertify\Core\Database;
use MailCertify\Core\Verification;
use MailCertify\Core\BanManager;

class ConfigController
{
    public static function renderOverview()
    {
        $verifiedCount = Capsule::table('tblclients')->where('email_verified', 1)->count();
        $unverifiedCount = Capsule::table('tblclients')->where('email_verified', 0)->count();
        $banStats = BanManager::getBanStats();
        $totalBans = array_sum($banStats);
        $logCount = Capsule::table('mod_mailcertify_logs')->count();
        $verificationType = Database::getSetting('verification_type');

        $recentLogs = Capsule::table('mod_mailcertify_logs')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        echo <<<HTML
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-success">
            <div class="panel-heading"><strong>Verified</strong></div>
            <div class="panel-body text-center"><h3>{$verifiedCount}</h3></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-danger">
            <div class="panel-heading"><strong>Unverified</strong></div>
            <div class="panel-body text-center"><h3>{$unverifiedCount}</h3></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-warning">
            <div class="panel-heading"><strong>Total Bans</strong></div>
            <div class="panel-body text-center"><h3>{$totalBans}</h3></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-info">
            <div class="panel-heading"><strong>Log Entries</strong></div>
            <div class="panel-body text-center"><h3>{$logCount}</h3></div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Current Mode: </strong> " . ucfirst($verificationType) . "</div>
    <div class="panel-body">
        <a href="?module=mailcertifyverify&action=settings" class="btn btn-primary">
            <i class="fa fa-cogs"></i> Settings
        </a>
        <a href="?module=mailcertifyverify&action=bans" class="btn btn-danger">
            <i class="fa fa-ban"></i> Ban Management
        </a>
        <a href="?module=mailcertifyverify&action=clients" class="btn btn-info">
            <i class="fa fa-users"></i> Client Management
        </a>
        <a href="?module=mailcertifyverify&action=logs" class="btn btn-default">
            <i class="fa fa-list"></i> View Logs
        </a>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Recent Activity</strong></div>
    <div class="panel-body">
        <table class="table table-striped">
            <thead><tr><th>Client</th><th>Action</th><th>Details</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
HTML;

        foreach ($recentLogs as $log) {
            $clientName = '';
            if ($log->client_id) {
                $client = Capsule::table('tblclients')->find($log->client_id);
                $clientName = $client ? $client->firstname . ' ' . $client->lastname : 'N/A';
            }
            echo "<tr><td>{$clientName} ({$log->email})</td>
                      <td>{$log->action}</td>
                      <td>{$log->details}</td>
                      <td>{$log->ip_address}</td>
                      <td>{$log->created_at}</td></tr>";
        }

        echo <<<HTML
            </tbody>
        </table>
    </div>
</div>
HTML;
    }

    public static function handleSettings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = [
                'verification_type', 'lock_email', 'auto_terminate_enabled',
                'auto_terminate_days', 'auto_delete_enabled', 'auto_delete_days',
                'resend_enabled', 'resend_days', 'captcha_type',
                'recaptcha_site_key', 'recaptcha_secret_key',
                'turnstile_site_key', 'turnstile_secret_key', 'ban_ip_days',
            ];
            foreach ($fields as $field) {
                $value = isset($_POST[$field]) ? $_POST[$field] : '';
                Database::saveSetting($field, $value);
            }
            echo '<div class="alert alert-success">Settings saved successfully.</div>';
        }

        $settings = [];
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', 'mailcertifyverify')
            ->get();
        foreach ($rows as $row) {
            $settings[$row->setting] = $row->value;
        }

        $verificationType = $settings['verification_type'] ?? 'checkout';
        $lockEmail = $settings['lock_email'] ?? 'on';
        $autoTerminate = $settings['auto_terminate_enabled'] ?? 'on';
        $autoTerminateDays = $settings['auto_terminate_days'] ?? '7';
        $autoDelete = $settings['auto_delete_enabled'] ?? 'off';
        $autoDeleteDays = $settings['auto_delete_days'] ?? '30';
        $resendEnabled = $settings['resend_enabled'] ?? 'on';
        $resendDays = $settings['resend_days'] ?? '3';
        $captchaType = $settings['captcha_type'] ?? 'none';
        $recaptchaSite = $settings['recaptcha_site_key'] ?? '';
        $recaptchaSecret = $settings['recaptcha_secret_key'] ?? '';
        $turnstileSite = $settings['turnstile_site_key'] ?? '';
        $turnstileSecret = $settings['turnstile_secret_key'] ?? '';
        $banIpDays = $settings['ban_ip_days'] ?? '30';

        $selChkout = self::selected($verificationType, 'checkout');
        $selAllPg = self::selected($verificationType, 'allpages');
        $chkLock = self::checked($lockEmail, 'on');
        $chkTerm = self::checked($autoTerminate, 'on');
        $chkDel = self::checked($autoDelete, 'on');
        $chkResend = self::checked($resendEnabled, 'on');
        $selNone = self::selected($captchaType, 'none');
        $selRecap = self::selected($captchaType, 'recaptcha');
        $selTurn = self::selected($captchaType, 'turnstile');

        echo <<<HTML
<form method="post" class="form-horizontal">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>MailCertify Verification Pro - Settings</strong></div>
        <div class="panel-body">

            <div class="form-group">
                <label class="col-sm-3 control-label">Verification Type</label>
                <div class="col-sm-6">
                    <select name="verification_type" class="form-control">
                        <option value="checkout" {$selChkout}>Checkout (Verify before order)</option>
                        <option value="allpages" {$selAllPg}>All Pages (Restrict all access)</option>
                    </select>
                    <p class="help-block">Checkout: verify before placing order. All Pages: restrict all account access until verified.</p>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Lock Email</label>
                <div class="col-sm-6">
                    <input type="hidden" name="lock_email" value="off">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="lock_email" value="on" {$chkLock}> Prevent email changes until verified
                    </label>
                </div>
            </div>

            <hr>
            <h4>Auto Management</h4>

            <div class="form-group">
                <label class="col-sm-3 control-label">Auto Terminate</label>
                <div class="col-sm-6">
                    <input type="hidden" name="auto_terminate_enabled" value="off">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="auto_terminate_enabled" value="on" {$chkTerm}> Enable auto-terminate
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Terminate After (Days)</label>
                <div class="col-sm-2">
                    <input type="number" name="auto_terminate_days" value="{$autoTerminateDays}" class="form-control" min="1">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Auto Delete</label>
                <div class="col-sm-6">
                    <input type="hidden" name="auto_delete_enabled" value="off">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="auto_delete_enabled" value="on" {$chkDel}> Auto-delete unverified accounts (no orders)
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Delete After (Days)</label>
                <div class="col-sm-2">
                    <input type="number" name="auto_delete_days" value="{$autoDeleteDays}" class="form-control" min="1">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Resend Email</label>
                <div class="col-sm-6">
                    <input type="hidden" name="resend_enabled" value="off">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="resend_enabled" value="on" {$chkResend}> Auto-resend verification email
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Resend After (Days)</label>
                <div class="col-sm-2">
                    <input type="number" name="resend_days" value="{$resendDays}" class="form-control" min="1">
                </div>
            </div>

            <hr>
            <h4>CAPTCHA Security</h4>

            <div class="form-group">
                <label class="col-sm-3 control-label">CAPTCHA Type</label>
                <div class="col-sm-6">
                    <select name="captcha_type" class="form-control">
                        <option value="none" {$selNone}>None</option>
                        <option value="recaptcha" {$selRecap}>Google reCAPTCHA v3</option>
                        <option value="turnstile" {$selTurn}>CloudFlare Turnstile</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">reCAPTCHA Site Key</label>
                <div class="col-sm-6">
                    <input type="text" name="recaptcha_site_key" value="{$recaptchaSite}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">reCAPTCHA Secret Key</label>
                <div class="col-sm-6">
                    <input type="password" name="recaptcha_secret_key" value="{$recaptchaSecret}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Turnstile Site Key</label>
                <div class="col-sm-6">
                    <input type="text" name="turnstile_site_key" value="{$turnstileSite}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Turnstile Secret Key</label>
                <div class="col-sm-6">
                    <input type="password" name="turnstile_secret_key" value="{$turnstileSecret}" class="form-control">
                </div>
            </div>

            <hr>
            <h4>Ban Settings</h4>

            <div class="form-group">
                <label class="col-sm-3 control-label">Ban IP Duration (Days)</label>
                <div class="col-sm-2">
                    <input type="number" name="ban_ip_days" value="{$banIpDays}" class="form-control" min="1">
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
HTML;
    }

    public static function handleClients()
    {
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $query = Capsule::table('tblclients');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('created_at', 'desc')
            ->select('id', 'firstname', 'lastname', 'email', 'email_verified', 'email_verified_at', 'status', 'created_at')
            ->paginate(20);

        echo <<<HTML
<div class="panel panel-default">
    <div class="panel-heading"><strong>Client Management</strong></div>
    <div class="panel-body">
        <form method="get" class="form-inline mb-3">
            <input type="hidden" name="module" value="mailcertifyverify">
            <input type="hidden" name="action" value="clients">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search clients..." value="{$search}">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                </span>
            </div>
        </form>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th>
                    <th>Verified</th><th>Verified At</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
HTML;

        if (count($clients) > 0) {
            foreach ($clients as $client) {
                $verifiedBadge = $client->email_verified
                    ? '<span class="label label-success">Yes</span>'
                    : '<span class="label label-danger">No</span>';
                $verifyBtn = '';
                if (!$client->email_verified) {
                    $verifyBtn = '<a href="?module=mailcertifyverify&action=manual_verify&client_id=' . $client->id . '" class="btn btn-xs btn-success" onclick="return confirm(\'Mark this client as verified?\')">Manual Verify</a>';
                }
                echo "<tr>
                    <td>{$client->id}</td>
                    <td>{$client->firstname} {$client->lastname}</td>
                    <td>{$client->email}</td>
                    <td>{$verifiedBadge}</td>
                    <td>{$client->email_verified_at}</td>
                    <td>{$client->status}</td>
                    <td>{$verifyBtn}</td>
                </tr>";
            }
        } else {
            echo '<tr><td colspan="7">No clients found.</td></tr>';
        }

        echo '</tbody></table></div></div>';
    }

    public static function handleManualVerify()
    {
        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
        if ($clientId) {
            \MailCertify\Core\Verification::manualVerify($clientId);
            echo '<div class="alert alert-success">Client #' . $clientId . ' marked as verified.</div>';
        }
        self::handleClients();
    }

    public static function renderSidebar($vars)
    {
        return [];
    }

    private static function selected($current, $value)
    {
        return $current === $value ? 'selected' : '';
    }

    private static function checked($current, $value)
    {
        return $current === $value ? 'checked' : '';
    }
}
