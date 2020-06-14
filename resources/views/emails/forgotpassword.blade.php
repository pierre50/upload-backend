<!DOCTYPE html>
<html>
<head>
    <title>Your new password</title>
</head>
 
<body>
<p>
    Hello {{ $user->name }},<br>
    It seem's you ask for a new password ?
</p>
<h2>Here is your new password :</h2>
<h5>{{ $newpassword }}</h5>
<p>
    Don't forgot to change your password after login !<br>
    See you at Supfile,
</p>
<p>
    Supfile team.
</p>
</body>
</html>