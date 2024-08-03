<?php

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/vendor/autoload.php';
session_start();

$keys = json_decode(@file_get_contents('keys.json'), true);

$OAUTH2_CLIENT_ID = $keys['oauth2ClientId'];
$OAUTH2_CLIENT_SECRET = $keys['oauth2ClientSecret'];

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$client->setAccessType('offline');
$redirect = filter_var($keys['redirect_uri']);
$client->setRedirectUri($redirect);

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-' . $client->prepareScopes();
if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION[$tokenSessionKey])) {
  $client->setAccessToken($_SESSION[$tokenSessionKey]);
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
  $htmlBody = '';
  try{
    $tokens = $client->getAccessToken();
    if (isset($tokens['access_token'])) {
        $keys['access_token'] = $tokens['access_token'];
        $keys['refresh_token'] = $tokens['refresh_token'];
        $keys['expires_in'] = $tokens['expires_in'];
        $keys['created'] = $tokens['created'];
        $new_json_data = json_encode($keys);
        file_put_contents('keys.json', $new_json_data);
        $htmlBody .= "<h3>Get Access Token Succsess</h3><ul>";
    } else {
        $htmlBody .= "<p>Access Token không có sẵn trong phản hồi.</p>";
    }
  } catch (Google_Service_Exception $e) {
    $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  }
} elseif (empty($OAUTH2_CLIENT_ID)) {
  $htmlBody = <<<END
  <h3>Client Credentials Required</h3>
  <p>
    You need to set <code>\$OAUTH2_CLIENT_ID</code> and
    <code>\$OAUTH2_CLIENT_ID</code> before proceeding.
  <p>
END;
} else {
  // If the user hasn't authorized the app, initiate the OAuth flow
  $state = mt_rand();
  $client->setState($state);
  $_SESSION['state'] = $state;

  $authUrl = $client->createAuthUrl();
  $htmlBody = <<<END
  <h3>Authorization Required</h3>
  <p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;
}
?>

<!doctype html>
<html>
<head>
<title>Generate Access Token</title>
</head>
<body>
  <?=$htmlBody?>
</body>
</html>