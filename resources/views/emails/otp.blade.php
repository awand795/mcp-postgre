<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OTP Reset Password</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 20px; color: #333;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: #f53003; margin: 0;">darkotech AI</h1>
        </div>
        <p>Halo,</p>
        <p>Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.</p>
        <p>Berikut adalah kode OTP untuk melanjutkan proses reset password Anda:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span style="display: inline-block; padding: 15px 30px; background-color: #f53003; color: #ffffff; font-size: 24px; font-weight: bold; border-radius: 8px; letter-spacing: 5px;">
                {{ $otp }}
            </span>
        </div>
        
        <p>Kode OTP ini hanya berlaku selama 15 menit.</p>
        <p>Jika Anda tidak meminta reset password, Anda dapat mengabaikan email ini.</p>
        <br>
        <p>Salam,</p>
        <p><strong>Tim darkotech AI</strong></p>
    </div>
</body>
</html>
