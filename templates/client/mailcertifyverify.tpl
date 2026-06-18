<div class="mailcertify-verify-container">
    {if $success}
        <div class="alert alert-success">{$message}</div>
        <p><a href="clientarea.php" class="btn btn-primary">Go to Client Area</a></p>
    {elseif $error}
        <div class="alert alert-danger">{$error}</div>
        <p><a href="clientarea.php" class="btn btn-default">Back to Client Area</a></p>
    {elseif $banned}
        <div class="mailcertify-banned-container">
            <div class="banned-icon"><i class="fa fa-shield"></i></div>
            <h2>{$LANG.mailcertify_access_blocked|default:'Access Blocked'}</h2>
            <p>Your {$ban_type} <strong>{$ban_value}</strong> has been blocked.</p>
            <p>If you believe this is an error, please contact support.</p>
            <p><a href="submitticket.php" class="btn btn-primary"><i class="fa fa-ticket"></i> Contact Support</a></p>
        </div>
    {else}
        <h2>Email Verification Required</h2>
        <p>Please verify your email address to continue.</p>
        <p>A verification link has been sent to:</p>
        <div class="email-display">{$email}</div>
        <p>Check your inbox (and spam folder) for the verification email.</p>
        {if $message}
            <div class="alert {if $success}alert-success{else}alert-danger{/if}">{$message}</div>
        {/if}
        <form method="post" action="index.php?m=mailcertifyverify&action=resend" class="btn-resend">
            {$captcha_html}
            <br><br>
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-envelope"></i> Resend Verification Email
            </button>
        </form>
        <p style="margin-top:20px;font-size:12px;color:#888">
            <a href="clientarea.php?action=details">Update your profile</a> |
            <a href="submitticket.php">Open a support ticket</a>
        </p>
    {/if}
</div>
