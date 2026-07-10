<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp
    ) {}

    public function build()
    {
        return $this->subject('رمز استعادة كلمة المرور')
            ->view('emails.reset-password-otp')
            ->with([
                'otp' => $this->otp,
            ]);
    }
}
