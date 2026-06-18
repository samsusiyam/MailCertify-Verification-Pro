<?php

namespace MailCertify\Admin;

use MailCertify\Core\BanManager;

class BanController
{
    public static function handleBans()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['ban_action'] ?? '';

            if ($action === 'add_ip') {
                $ip = trim($_POST['ip_address'] ?? '');
                $reason = trim($_POST['reason'] ?? '');
                if ($ip) {
                    BanManager::banIP($ip, $reason);
                    echo '<div class="alert alert-success">IP banned successfully.</div>';
                }
            } elseif ($action === 'add_email') {
                $email = trim($_POST['email'] ?? '');
                $reason = trim($_POST['reason'] ?? '');
                if ($email) {
                    BanManager::banEmail($email, $reason);
                    echo '<div class="alert alert-success">Email banned successfully.</div>';
                }
            } elseif ($action === 'add_domain') {
                $domain = trim($_POST['domain'] ?? '');
                $reason = trim($_POST['reason'] ?? '');
                if ($domain) {
                    BanManager::banDomain($domain, $reason);
                    echo '<div class="alert alert-success">Domain banned successfully.</div>';
                }
            } elseif ($action === 'unban') {
                $id = (int) ($_POST['ban_id'] ?? 0);
                if ($id) {
                    BanManager::unban($id);
                    echo '<div class="alert alert-success">Ban removed successfully.</div>';
                }
            }
        }

        $type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';
        $page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
        $result = BanManager::getBans($type, $page);
        $stats = BanManager::getBanStats();

        echo <<<HTML
<div class="row">
    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-heading"><strong>IP Bans: {$stats['ip']}</strong></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-warning">
            <div class="panel-heading"><strong>Email Bans: {$stats['email']}</strong></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-danger">
            <div class="panel-heading"><strong>Domain Bans: {$stats['domain']}</strong></div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Add Ban</strong></div>
    <div class="panel-body">
        <form method="post" class="form-inline" style="margin-bottom:10px">
            <input type="hidden" name="ban_action" value="add_ip">
            <div class="input-group">
                <input type="text" name="ip_address" class="form-control" placeholder="IP Address" required>
                <input type="text" name="reason" class="form-control" placeholder="Reason">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-danger"><i class="fa fa-ban"></i> Ban IP</button>
                </span>
            </div>
        </form>
        <form method="post" class="form-inline" style="margin-bottom:10px">
            <input type="hidden" name="ban_action" value="add_email">
            <div class="input-group">
                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                <input type="text" name="reason" class="form-control" placeholder="Reason">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-warning"><i class="fa fa-ban"></i> Ban Email</button>
                </span>
            </div>
        </form>
        <form method="post" class="form-inline">
            <input type="hidden" name="ban_action" value="add_domain">
            <div class="input-group">
                <input type="text" name="domain" class="form-control" placeholder="Domain (e.g. example.com)" required>
                <input type="text" name="reason" class="form-control" placeholder="Reason">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-default"><i class="fa fa-ban"></i> Ban Domain</button>
                </span>
            </div>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Ban List</strong>
        <div class="pull-right">
            <a href="?module=mailcertifyverify&action=bans&filter_type=all" class="btn btn-xs btn-default">All</a>
            <a href="?module=mailcertifyverify&action=bans&filter_type=ip" class="btn btn-xs btn-info">IP</a>
            <a href="?module=mailcertifyverify&action=bans&filter_type=email" class="btn btn-xs btn-warning">Email</a>
            <a href="?module=mailcertifyverify&action=bans&filter_type=domain" class="btn btn-xs btn-danger">Domain</a>
        </div>
    </div>
    <div class="panel-body">
        <table class="table table-striped">
            <thead><tr><th>Type</th><th>Value</th><th>Reason</th><th>Expires</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
HTML;

        $bans = $result['bans'];
        if (count($bans) > 0) {
            foreach ($bans as $ban) {
                $badgeClass = 'default';
                if ($ban->type === 'ip') $badgeClass = 'info';
                elseif ($ban->type === 'email') $badgeClass = 'warning';
                elseif ($ban->type === 'domain') $badgeClass = 'danger';

                echo '<tr>
                    <td><span class="label label-' . $badgeClass . '">' . $ban->type . '</span></td>
                    <td>' . $ban->value . '</td>
                    <td>' . $ban->reason . '</td>
                    <td>' . ($ban->expires_at ?? 'Never') . '</td>
                    <td>' . $ban->created_at . '</td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="ban_action" value="unban">
                            <input type="hidden" name="ban_id" value="' . $ban->id . '">
                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm(\'Remove this ban?\')">Remove</button>
                        </form>
                    </td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="6">No bans found.</td></tr>';
        }

        echo '</tbody></table>';

        if ($result['pages'] > 1) {
            echo '<nav><ul class="pagination">';
            for ($i = 1; $i <= $result['pages']; $i++) {
                $active = $i === $result['currentPage'] ? ' class="active"' : '';
                echo '<li' . $active . '><a href="?module=mailcertifyverify&action=bans&filter_type=' . $type . '&p=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
        }

        echo '</div></div>';
    }
}
