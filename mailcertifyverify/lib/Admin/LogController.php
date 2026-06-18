<?php

namespace MailCertify\Admin;

use WHMCS\Database\Capsule;

class LogController
{
    public static function handleLogs()
    {
        $page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
        $perPage = 30;
        $actionFilter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';

        $query = Capsule::table('mod_mailcertify_logs');

        if ($actionFilter) {
            $query->where('action', $actionFilter);
        }

        $total = $query->count();
        $logs = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $actions = Capsule::table('mod_mailcertify_logs')
            ->select('action')
            ->distinct()
            ->pluck('action');

        echo <<<HTML
<div class="panel panel-default">
    <div class="panel-heading"><strong>Activity Logs</strong></div>
    <div class="panel-body">
        <form method="get" class="form-inline">
            <input type="hidden" name="module" value="mailcertifyverify">
            <input type="hidden" name="action" value="logs">
            <div class="form-group">
                <select name="action_filter" class="form-control">
                    <option value="">All Actions</option>
HTML;
        foreach ($actions as $act) {
            $sel = $actionFilter === $act ? 'selected' : '';
            echo "<option value=\"{$act}\" {$sel}>{$act}</option>";
        }
        echo <<<HTML
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
        <br>
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Client</th><th>Email</th><th>Action</th><th>Details</th><th>IP</th><th>User Agent</th><th>Date</th></tr></thead>
            <tbody>
HTML;

        if (count($logs) > 0) {
            foreach ($logs as $log) {
                $clientName = '';
                if ($log->client_id) {
                    $client = Capsule::table('tblclients')->find($log->client_id);
                    $clientName = $client ? $client->firstname . ' ' . $client->lastname : 'Deleted';
                }
                echo '<tr>
                    <td>' . $log->id . '</td>
                    <td>' . $clientName . '</td>
                    <td>' . $log->email . '</td>
                    <td><span class="label label-info">' . $log->action . '</span></td>
                    <td>' . $log->details . '</td>
                    <td>' . $log->ip_address . '</td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . $log->user_agent . '</td>
                    <td>' . $log->created_at . '</td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="8">No logs found.</td></tr>';
        }

        echo '</tbody></table>';

        $pages = ceil($total / $perPage);
        if ($pages > 1) {
            echo '<nav><ul class="pagination">';
            for ($i = 1; $i <= $pages; $i++) {
                $active = $i === $page ? ' class="active"' : '';
                $filterParam = $actionFilter ? '&action_filter=' . $actionFilter : '';
                echo '<li' . $active . '><a href="?module=mailcertifyverify&action=logs' . $filterParam . '&p=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
        }

        echo <<<HTML
        <p>Total: {$total} entries</p>
    </div>
</div>
HTML;
    }
}
