<div class="mc-wrap" style="max-width:500px;margin:40px auto;padding:30px;background:#fff;border-radius:6px;box-shadow:0 0 15px rgba(0,0,0,0.08);text-align:center">
    {if $success}
        <div class="alert alert-success" style="padding:20px"><strong>{$message}</strong></div>
        <br><a href="clientarea.php" class="btn btn-primary">Go to Client Area</a>
    {elseif $error}
        <div class="alert alert-danger" style="padding:20px"><strong>{$message}</strong></div>
        <br><a href="clientarea.php" class="btn btn-default">Back to Client Area</a>
    {else}
        <h2 style="margin:0 0 15px;color:#333">Email Verification Required</h2>
        <p style="color:#666">A verification link has been sent to:</p>
        <p style="font-size:20px;font-weight:bold;color:#06c;margin:10px 0 20px">{$email}</p>
        <p style="color:#999;font-size:13px">Check your inbox (and spam folder).</p>

        <form method="post" action="index.php?m=mailcertifyverify&action=resend" style="margin-top:25px">
            {$captcha_html}
            <button type="submit" class="btn btn-primary" style="margin-top:15px;padding:10px 35px">
                Resend Verification Email
            </button>
        </form>

        <p style="margin-top:25px;font-size:12px">
            <a href="clientarea.php?action=details">Update Profile</a> |
            <a href="submitticket.php">Support Ticket</a>
        </p>
    {/if}
</div>
