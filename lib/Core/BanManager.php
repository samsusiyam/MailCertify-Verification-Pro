<?php

namespace MailCertify\Core;

use WHMCS\Database\Capsule;

class BanManager
{
    public static function banIP($ip, $reason = '', $adminId = null)
    {
        $days = (int) Database::getSetting('ban_ip_days');
        if ($days <= 0) {
            $days = 30;
        }

        $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        $existing = Capsule::table('mod_mailcertify_bans')
            ->where('type', 'ip')
            ->where('value', $ip)
            ->first();

        if ($existing) {
            Capsule::table('mod_mailcertify_bans')
                ->where('id', $existing->id)
                ->update([
                    'expires_at' => $expires,
                    'reason' => $reason,
                    'banned_by' => $adminId,
                ]);
        } else {
            Capsule::table('mod_mailcertify_bans')->insert([
                'type' => 'ip',
                'value' => $ip,
                'reason' => $reason,
                'banned_by' => $adminId,
                'expires_at' => $expires,
            ]);
        }
    }

    public static function banEmail($email, $reason = '', $adminId = null)
    {
        $existing = Capsule::table('mod_mailcertify_bans')
            ->where('type', 'email')
            ->where('value', $email)
            ->first();

        if ($existing) {
            Capsule::table('mod_mailcertify_bans')
                ->where('id', $existing->id)
                ->update([
                    'reason' => $reason,
                    'banned_by' => $adminId,
                ]);
        } else {
            Capsule::table('mod_mailcertify_bans')->insert([
                'type' => 'email',
                'value' => $email,
                'reason' => $reason,
                'banned_by' => $adminId,
            ]);
        }
    }

    public static function banDomain($domain, $reason = '', $adminId = null)
    {
        $existing = Capsule::table('mod_mailcertify_bans')
            ->where('type', 'domain')
            ->where('value', $domain)
            ->first();

        if ($existing) {
            Capsule::table('mod_mailcertify_bans')
                ->where('id', $existing->id)
                ->update([
                    'reason' => $reason,
                    'banned_by' => $adminId,
                ]);
        } else {
            Capsule::table('mod_mailcertify_bans')->insert([
                'type' => 'domain',
                'value' => $domain,
                'reason' => $reason,
                'banned_by' => $adminId,
            ]);
        }
    }

    public static function unban($id)
    {
        Capsule::table('mod_mailcertify_bans')->where('id', $id)->delete();
    }

    public static function getBans($type = null, $page = 1, $perPage = 20)
    {
        $query = Capsule::table('mod_mailcertify_bans');

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $total = $query->count();
        $bans = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'bans' => $bans,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'currentPage' => $page,
        ];
    }

    public static function getBanStats()
    {
        return [
            'ip' => Capsule::table('mod_mailcertify_bans')->where('type', 'ip')->count(),
            'email' => Capsule::table('mod_mailcertify_bans')->where('type', 'email')->count(),
            'domain' => Capsule::table('mod_mailcertify_bans')->where('type', 'domain')->count(),
        ];
    }
}
