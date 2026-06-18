<?php

namespace MailCertify\Core;

use WHMCS\Database\Capsule;

class Database
{
    public static function install()
    {
        $schema = self::getSchema();
        foreach ($schema as $tableSql) {
            Capsule::statement($tableSql);
        }

        $defaults = self::getDefaults();
        foreach ($defaults as $setting => $value) {
            Capsule::table('tbladdonmodules')
                ->where('module', 'mailcertifyverify')
                ->where('setting', $setting)
                ->delete();
            Capsule::table('tbladdonmodules')
                ->insert([
                    'module' => 'mailcertifyverify',
                    'setting' => $setting,
                    'value' => $value,
                ]);
        }

        Capsule::schema()->table('tblclients', function ($table) {
            if (!Capsule::schema()->hasColumn('tblclients', 'email_verified')) {
                $table->tinyInteger('email_verified')->default(0)->after('email');
            }
            if (!Capsule::schema()->hasColumn('tblclients', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email_verified');
            }
            if (!Capsule::schema()->hasColumn('tblclients', 'verify_token')) {
                $table->string('verify_token', 100)->nullable()->after('email_verified_at');
            }
            if (!Capsule::schema()->hasColumn('tblclients', 'verify_token_expires')) {
                $table->timestamp('verify_token_expires')->nullable()->after('verify_token');
            }
            if (!Capsule::schema()->hasColumn('tblclients', 'verification_type')) {
                $table->string('verification_type', 20)->default('email')->after('verify_token_expires');
            }
        });

        Capsule::schema()->table('tblusers', function ($table) {
            if (!Capsule::schema()->hasColumn('tblusers', 'email_verified')) {
                $table->tinyInteger('email_verified')->default(0)->after('email');
            }
        });

        self::createEmailTemplate();
    }

    private static function createEmailTemplate()
    {
        $exists = Capsule::table('tblemailtemplates')
            ->where('name', 'MailCertify Verification')
            ->exists();

        if (!$exists) {
            Capsule::table('tblemailtemplates')->insert([
                'name' => 'MailCertify Verification',
                'subject' => 'Verify Your Email Address',
                'type' => 'product',
                'plaintext' => 0,
                'language' => '',
                'custom' => 0,
                'message' => '<p>Dear {$client_name},</p>
<p>Thank you for registering. Please verify your email address by clicking the link below:</p>
<p><a href="{$verify_link}">{$verify_link}</a></p>
<p>Your verification token: {$verify_token}</p>
<p>This link will expire in 48 hours.</p>
<p>If you did not create an account, please ignore this email.</p>
<p>Regards,<br>{$company_name}</p>',
                'attachments' => '',
                'disabled' => 0,
            ]);
        }
    }

    public static function getDbVersion()
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'mailcertifyverify')
                ->where('setting', 'db_version')
                ->value('value');
            return $val ?: '0.0.0';
        } catch (\Exception $e) {
            return '0.0.0';
        }
    }

    public static function runUpgrades($currentVersion)
    {
    }

    private static function getSchema()
    {
        return [
            "CREATE TABLE IF NOT EXISTS mod_mailcertify_verify (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(100) NOT NULL,
                expires_at DATETIME NOT NULL,
                verified_at DATETIME NULL,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_client (client_id),
                INDEX idx_token (token),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS mod_mailcertify_bans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('ip','email','domain') NOT NULL,
                value VARCHAR(255) NOT NULL,
                reason VARCHAR(500) NULL,
                banned_by INT NULL,
                expires_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type (type),
                INDEX idx_value (value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS mod_mailcertify_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NULL,
                email VARCHAR(255) NULL,
                action VARCHAR(50) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }

    private static function getDefaults()
    {
        return [
            'db_version' => '1.0.0',
            'verification_type' => 'allpages',
            'lock_email' => 'on',
            'auto_terminate_enabled' => 'on',
            'auto_terminate_days' => '7',
            'auto_delete_enabled' => 'off',
            'auto_delete_days' => '30',
            'resend_enabled' => 'on',
            'resend_days' => '3',
            'captcha_type' => 'none',
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'turnstile_site_key' => '',
            'turnstile_secret_key' => '',
            'ban_ip_days' => '30',
        ];
    }

    public static function getSetting($key)
    {
        try {
            return Capsule::table('tbladdonmodules')
                ->where('module', 'mailcertifyverify')
                ->where('setting', $key)
                ->value('value');
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function saveSetting($key, $value)
    {
        Capsule::table('tbladdonmodules')
            ->updateOrInsert(
                ['module' => 'mailcertifyverify', 'setting' => $key],
                ['value' => $value]
            );
    }
}
