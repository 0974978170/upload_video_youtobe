<?php

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ . '"');
}

require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');
ini_set('max_execution_time', 10800);
$data = '';

$keys = json_decode(@file_get_contents('keys.json'), true);

$OAUTH2_CLIENT_ID = $keys['oauth2ClientId'];
$OAUTH2_CLIENT_SECRET = $keys['oauth2ClientSecret'];
$ACCESS_TOKEN = $keys['access_token'];
$REFRESH_TOKEN = $keys['refresh_token'];
$created = $keys['created'];
$expires_in = $keys['expires_in'];
$tokenExpiryTime = $created + $expires_in - 600;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (is_array($data) || is_object($data)) {
            $modifiedPath = str_replace("\\\\", "\\", $data['file_path']);
            if (file_exists($modifiedPath)) {
                session_start();
                if (time() > $tokenExpiryTime) {
                    $client = new Google_Client();
                    $client->setClientId($OAUTH2_CLIENT_ID);
                    $client->setClientSecret($OAUTH2_CLIENT_SECRET);
                    $client->setAccessToken($ACCESS_TOKEN);
                    $client->setAccessType('offline');
                    $client->fetchAccessTokenWithRefreshToken($REFRESH_TOKEN);
                    $tokens = $client->getAccessToken();
                    $keys['access_token'] = $tokens['access_token'];
                    $keys['refresh_token'] = $tokens['refresh_token'];
                    $keys['expires_in'] = $tokens['expires_in'];
                    $keys['created'] = $tokens['created'];
                    $new_json_data = json_encode($keys);
                    file_put_contents('keys.json', $new_json_data);
                }
                $new_key = json_decode(@file_get_contents('keys.json'), true);
                $client = new Google_Client();
                $client->setClientId($new_key['oauth2ClientId']);
                $client->setClientSecret($new_key['oauth2ClientSecret']);
                $client->setAccessToken($new_key['access_token']);
                $client->setScopes(array(
                    'https://www.googleapis.com/auth/youtube.upload',
                    'https://www.googleapis.com/auth/youtube'
                ));

                $youtube = new Google_Service_YouTube($client);

                if ($client->getAccessToken()) {
                    $htmlBody = '';
                    try {
                        $videoPath = $modifiedPath;

                        $snippet = new Google_Service_YouTube_VideoSnippet();
                        $snippet->setTitle($data['title']);
                        $snippet->setDescription($data['description']);
//                        $snippet->setTags(array("tag1", "tag2"));

                        $snippet->setCategoryId("17");

                        // Set the video's status to "public". Valid statuses are "public",
                        // "private" and "unlisted".
                        $status = new Google_Service_YouTube_VideoStatus();
                        $status->privacyStatus = "public";

                        // Associate the snippet and status objects with a new video resource.
                        $video = new Google_Service_YouTube_Video();
                        $video->setSnippet($snippet);
                        $video->setStatus($status);

                        // Specify the size of each chunk of data, in bytes. Set a higher value for
                        // reliable connection as fewer chunks lead to faster uploads. Set a lower
                        // value for better recovery on less reliable connections.
                        $chunkSizeBytes = 1 * 1024 * 1024;

                        // Setting the defer flag to true tells the client to return a request which can be called
                        // with ->execute(); instead of making the API call immediately.
                        $client->setDefer(true);

                        // Create a request for the API's videos.insert method to create and upload the video.
                        $insertRequest = $youtube->videos->insert("status,snippet", $video);

                        // Create a MediaFileUpload object for resumable uploads.
                        $media = new Google_Http_MediaFileUpload(
                            $client,
                            $insertRequest,
                            'video/*',
                            null,
                            true,
                            $chunkSizeBytes
                        );
                        $media->setFileSize(filesize($videoPath));


                        // Read the media file and upload it chunk by chunk.
                        $status = false;
                        $handle = fopen($videoPath, "rb");
                        while (!$status && !feof($handle)) {
                            $chunk = fread($handle, $chunkSizeBytes);
                            $status = $media->nextChunk($chunk);
                        }

                        fclose($handle);

                        // If you want to make other calls after the file upload, set setDefer back to false
                        $client->setDefer(false);


                        $htmlBody .= "<h3>Video Uploaded</h3><ul>";
                        $htmlBody .= sprintf('<li>%s (%s)</li>',
                            $status['snippet']['title'],
                            $status['id']);

                        $htmlBody .= '</ul>';

                    } catch (Google_Service_Exception $e) {
                        $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
                            htmlspecialchars($e->getMessage()));
                    } catch (Google_Exception $e) {
                        $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
                            htmlspecialchars($e->getMessage()));
                    }
                }

            } else {
                echo "File does not exist.";
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Decoded JSON is not an array or object'
            ]);
        }
    } else {
        $jsonError = json_last_error();
        $jsonErrorMsg = json_last_error_msg();

        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON',
            'error_code' => $jsonError,
            'error_message' => $jsonErrorMsg
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST requests are allowed'
    ]);
}
?>

<!doctype html>
<html>
<head>
    <title>Video Uploaded</title>
</head>
<body>
<?= $htmlBody ?>
</body>
</html>