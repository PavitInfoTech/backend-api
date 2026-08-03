<html>

<body>
    <p>Hello {{ $name }},</p>
    <p>Thank you for subscribing to our newsletter.</p>
    <p>Your subscription request was received. Please click the link below to complete your subscription:</p>
    <p><a href="{{ $verifyUrl }}">Verify my subscription</a></p>
    <p>If you did not subscribe, you can safely ignore this email.</p>
</body>

</html>
