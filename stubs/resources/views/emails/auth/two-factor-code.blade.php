@component('mail::message')
# Two-Factor Authentication Code

Hello {{ $name }},

Your two-factor authentication code is:

@component('mail::panel')
<div style="font-size: 24px; text-align: center; font-weight: bold;">{{ $code }}</div>
@endcomponent

This code will expire in 10 minutes.

If you did not request this code, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
