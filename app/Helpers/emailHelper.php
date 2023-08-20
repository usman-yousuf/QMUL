<?php

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

function sendEmail($fromAddress = '', $toAddress = '', $bccAddress = '', $mailSubject = '', $mailBody = '')
{
    if ($fromAddress == '' || $toAddress == '' || $mailSubject == '' || $mailBody == '') {
        throw new Exception("Not sending email - required parameters missing");
    }

    $email = new TestEmail($mailSubject, $mailBody);

    try {
        Mail::to($toAddress)
            ->bcc($bccAddress)
            ->send($email);

        return true;
    } catch (\Exception $e) {
        // Log the error or return false
        // logger($e->getMessage());
        return false;
    }
}
