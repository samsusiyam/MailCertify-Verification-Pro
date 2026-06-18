<?php

namespace MailCertify\Core;

use WHMCS\Database\Capsule;
use WHMCS\Mail\Template;

class Verification
{
    public static function generateToken()
    {
        return bin2hex(random_bytes(32));
    }

    public static function createVerification($clientId, $email)
    {
        $token = self::generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $ip = self::getClientIP();

        Capsule::table('mod_mailcertify_verify')->insert([
            'client_id' => $clientId,
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Capsule::table('tblclients')
            ->where('id', $clientId)
            ->update([
                'verify_token' => $token,
                'verify_token_expires' => $expires,
                'email_verified' => 0,
            ]);

        self::sendVerificationEmail($clientId, $email, $token);

        self::logAction($clientId, $email, 'verification_created', "Verification email sent");

        return $token;
    }

    public static function sendVerificationEmail($clientId, $email, $token)
    {
        $verifyLink = \WHMCS\Config\Setting::getValue('SystemURL')
            . '/index.php?m=mailcertifyverify&action=verify&token=' . $token;

        $postData = [
            'id' => $clientId,
            'messagename' => 'MailCertify Verification',
            'mergefields' => [
                'verify_link' => $verifyLink,
                'verify_token' => $token,
                'client_name' => Capsule::table('tblclients')->where('id', $clientId)->value('firstname'),
            ],
        ];

        localAPI('SendEmail', $postData);
    }

    public static function verifyEmail($token)
    {
        $record = Capsule::table('mod_mailcertify_verify')
            ->where('token', $token)
            ->whereNull('verified_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$record) {
            return ['success' => false, 'message' => 'Invalid or expired verification token.'];
        }

        $now = date('Y-m-d H:i:s');

        Capsule::table('mod_mailcertify_verify')
            ->where('id', $record->id)
            ->update(['verified_at' => $now]);

        Capsule::table('tblclients')
            ->where('id', $record->client_id)
            ->update([
                'email_verified' => 1,
                'email_verified_at' => $now,
                'verify_token' => null,
                'verify_token_expires' => null,
            ]);

        self::logAction($record->client_id, $record->email, 'verified', 'Email successfully verified');

        return ['success' => true, 'message' => 'Email verified successfully.', 'client_id' => $record->client_id];
    }

    public static function resendVerification($clientId, $email)
    {
        $existing = Capsule::table('mod_mailcertify_verify')
            ->where('client_id', $clientId)
            ->whereNull('verified_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existing) {
            $token = self::generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

            Capsule::table('mod_mailcertify_verify')
                ->where('id', $existing->id)
                ->update([
                    'token' => $token,
                    'expires_at' => $expires,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

            Capsule::table('tblclients')
                ->where('id', $clientId)
                ->update([
                    'verify_token' => $token,
                    'verify_token_expires' => $expires,
                ]);

            self::sendVerificationEmail($clientId, $email, $token);
            self::logAction($clientId, $email, 'resent', 'Verification email resent');

            return ['success' => true, 'message' => 'Verification email resent.'];
        }

        return self::createVerification($clientId, $email);
    }

    public static function isVerified($clientId)
    {
        return Capsule::table('tblclients')
            ->where('id', $clientId)
            ->value('email_verified');
    }

    public static function getClientIP()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwardedIps[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        return $ip;
    }

    public static function logAction($clientId, $email, $action, $details = null)
    {
        try {
            Capsule::table('mod_mailcertify_logs')->insert([
                'client_id' => $clientId,
                'email' => $email,
                'action' => $action,
                'details' => $details,
                'ip_address' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
        }
    }

    public static function manualVerify($clientId)
    {
        $email = Capsule::table('tblclients')->where('id', $clientId)->value('email');
        $now = date('Y-m-d H:i:s');

        Capsule::table('mod_mailcertify_verify')
            ->where('client_id', $clientId)
            ->whereNull('verified_at')
            ->update(['verified_at' => $now]);

        Capsule::table('tblclients')
            ->where('id', $clientId)
            ->update([
                'email_verified' => 1,
                'email_verified_at' => $now,
                'verify_token' => null,
                'verify_token_expires' => null,
            ]);

        self::logAction($clientId, $email, 'manual_verified', 'Admin manually verified email');
    }

    public static function isEmailBanned($email)
    {
        $domain = substr(strrchr($email, '@'), 1);
        $now = date('Y-m-d H:i:s');

        $emailBanned = Capsule::table('mod_mailcertify_bans')
            ->where('type', 'email')
            ->where('value', $email)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();

        $domainBanned = Capsule::table('mod_mailcertify_bans')
            ->where('type', 'domain')
            ->where('value', $domain)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();

        return $emailBanned || $domainBanned;
    }

    public static function isIPBanned($ip)
    {
        $now = date('Y-m-d H:i:s');
        return Capsule::table('mod_mailcertify_bans')
            ->where('type', 'ip')
            ->where('value', $ip)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();
    }

    public static function autoTerminateUnverified()
    {
        $enabled = Database::getSetting('auto_terminate_enabled');
        if ($enabled !== 'on') {
            return;
        }

        $days = (int) Database::getSetting('auto_terminate_days');
        if ($days <= 0) {
            $days = 7;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $unverified = Capsule::table('tblclients')
            ->where('email_verified', 0)
            ->where('created_at', '<', $cutoff)
            ->where('status', 'Active')
            ->get();

        foreach ($unverified as $client) {
            Capsule::table('tblclients')
                ->where('id', $client->id)
                ->update(['status' => 'Inactive']);

            self::logAction($client->id, $client->email, 'auto_terminated', "Auto-terminated after {$days} days");
        }
    }

    public static function autoDeleteUnverified()
    {
        $enabled = Database::getSetting('auto_delete_enabled');
        if ($enabled !== 'on') {
            return;
        }

        $days = (int) Database::getSetting('auto_delete_days');
        if ($days <= 0) {
            $days = 30;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $unverified = Capsule::table('tblclients')
            ->leftJoin('tblorders', 'tblclients.id', '=', 'tblorders.userid')
            ->where('tblclients.email_verified', 0)
            ->where('tblclients.created_at', '<', $cutoff)
            ->whereNull('tblorders.id')
            ->select('tblclients.id', 'tblclients.email')
            ->get();

        foreach ($unverified as $client) {
            Capsule::table('tblaccounts')
                ->where('userid', $client->id)
                ->delete();

            Capsule::table('tblorders')
                ->where('userid', $client->id)
                ->delete();

            Capsule::table('mod_mailcertify_verify')
                ->where('client_id', $client->id)
                ->delete();

            Capsule::table('tblclients')
                ->where('id', $client->id)
                ->delete();

            self::logAction($client->id, $client->email, 'auto_deleted', "Auto-deleted after {$days} days (no orders)");
        }
    }

    public static function resendUnverified()
    {
        $enabled = Database::getSetting('resend_enabled');
        if ($enabled !== 'on') {
            return;
        }

        $days = (int) Database::getSetting('resend_days');
        if ($days <= 0) {
            $days = 3;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $pending = Capsule::table('mod_mailcertify_verify')
            ->whereNull('verified_at')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($pending as $record) {
            self::resendVerification($record->client_id, $record->email);
        }
    }
}
