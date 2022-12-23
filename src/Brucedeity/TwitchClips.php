<?php
namespace Brucedeity\Twitchclips;

// Require the Env class
require_once __DIR__ . '/Env.php';


use Exception;
use DateTime;

class TwitchClips
{
    // Read the client ID and client secret from the environment file
    private $clientId;
    private $clientSecret;

    public function __construct($channelNames)
    {
        // Initialize the Env class and read the .env file
        Env::init(dirname(__DIR__, 2) . '/.env');

        $this->channelNames = $channelNames;
    
        // Read the client ID and client secret from the environment file
        $this->clientId = Env::get('CLIENT_ID');
        $this->clientSecret = Env::get('CLIENT_SECRET');
        $this->clipCount = Env::get('CLIP_COUNT');

        // Get the OAuth token
        $this->oauthToken = $this->getOauthToken();
    }

    /**
     * Gets the most recent clips for a specified Twitch channel.
     *
     * @param string $broadcasterId The ID of the channel's broadcaster.
     * @return array The clip data.
     * @throws Exception If the clip data could not be obtained.
     */
    private function getClips($broadcasterId)
    {
        // Set the age of the clips to be at least 24 hours old
        $age = time() - 24*60*60;

        // Convert the age to the correct format
        $date = new DateTime("@$age");
        $timestamp = $date->format('Y-m-d\TH:i:s\Z');

        // Set the API endpoint URL
        $endpointUrl = "https://api.twitch.tv/helix/clips?broadcaster_id=$broadcasterId&first=$this->clipCount&started_at=$timestamp";

        // Send a GET request to the API endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Client-ID: {$this->clientId}", "Authorization: Bearer $this->oauthToken"]);
        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check for cURL errors
        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        // Decode the response
        $data = json_decode($response, true);

        // Check for API errors
        if ($httpStatus !== 200) {
            // Check if the response contains the error key
            if (isset($data['error'])) {
                // Print the error message
                echo "Twitch API error: {$data['error']}\n";
            } else {
                echo "API error: HTTP status $httpStatus\n";
            }
            throw new Exception('API error: Could not get clip data');
        }

        // Check if the response contains the data key
        if (!isset($data['data'])) {
            throw new Exception('API error: No data in response');
        }

        // Return the clip data
        return $data['data'];
    }

    /**
     * Sanitizes a file name by replacing any invalid characters with an underscore (_).
     *
     * @param string $filename The file name to sanitize.
     * @return string The sanitized file name.
     */
    private function sanitizeFilename($filename)
    {
        // Replace any invalid characters with an underscore
        $filename = preg_replace('/[^a-zA-Z0-9_\.]/', '_', $filename);

        // Trim any leading or trailing underscores
        $filename = trim($filename, '_');

        return $filename;
    }

    public function downloadClips()
    {
        // Set the base directory
        $baseDir = dirname(__DIR__, 2);
    
        // Iterate over the channel names
        foreach ($this->channelNames as $channelName) {
            try {
                // Get the broadcaster ID for the channel
                $broadcasterId = $this->getBroadcasterId($channelName, $this->oauthToken);
    
                // Get the clips for the channel
                $clips = $this->getClips($broadcasterId);
    
                // Create the target directory for the channel if it does not exist
                $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'clips' . DIRECTORY_SEPARATOR . $channelName;
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }
    
                // Iterate over the clips
                foreach ($clips as $clip) {
                    // Sanitize the file name
                    $filename = $this->sanitizeFilename($clip['title']);
    
                    // Construct the file path of the clip
                    $filePath = $targetDir . DIRECTORY_SEPARATOR . $filename . '.mp4';
    
                    // Download the clip to the file path
                    file_put_contents($filePath, file_get_contents($clip['url']));
    
                    // Print a message indicating that the clip was downloaded
                    echo "Downloaded clip {$clip['title']} to $filePath\n";
                }
            } catch (Exception $e) {
                // Print an error message if an exception was thrown
                echo "Error: {$e->getMessage()}\n";
            }
        }
    }
    
    
    /**
     * Gets the broadcaster ID for a specified Twitch channel.
     *
     * @param string $channelName The name of the Twitch channel.
     * @param string $oauthToken The OAuth token for the user.
     * @return string The broadcaster ID.
     * @throws Exception If the broadcaster ID could not be obtained.
     */
    private function getBroadcasterId($channelName, $oauthToken)
    {
        // Set the API endpoint URL
        $endpointUrl = "https://api.twitch.tv/helix/users?login={$channelName}";

        // Send a GET request to the API endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Client-ID: {$this->clientId}", "Authorization: Bearer $oauthToken"]);
        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check for cURL errors
        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        // Decode the response
        $data = json_decode($response, true);

        // Check for API errors
        if ($httpStatus !== 200) {
            throw new Exception("API error: HTTP status $httpStatus");
        }

        // Check if the response contains the data key
        if (!isset($data['data'])) {
            throw new Exception('API error: No data in response');
        }

        // Get the first user in the response
        $user = $data['data'][0];

        // Return the broadcaster ID
        return $user['id'];
    }

    /**
     * Parses the thumbnail URL of a Twitch clip into the URL of the video.
     *
     * @param string $thumbnailUrl The thumbnail URL of the clip.
     * @return string The URL of the video.
     */
    public function parseVideoUrl($thumbnailUrl) {
        // Remove the -preview-480x272.jpg part from the thumbnail URL
        $thumbnailUrl = str_replace('-preview-480x272.jpg', '', $thumbnailUrl);
    
        // Extract the clip ID from the thumbnail URL and construct the video URL
        $videoUrl = preg_replace('/.*?clip-([^-]+).*/', '$1', $thumbnailUrl).'.mp4';
    
        return $videoUrl;
    }

    /**
     * Obtains an OAuth token for the user.
     *
     * @return string The OAuth token.
     * @throws Exception If the OAuth token could not be obtained.
     */
    private function getOauthToken()
    {
        // Set the API endpoint URL
        $endpointUrl = 'https://id.twitch.tv/oauth2/token';

        // Set the POST data
        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ];

        // Send a POST request to the API endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check for cURL errors
        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        // Decode the response
        $data = json_decode($response, true);

        // Check for API errors
        if ($httpStatus !== 200) {
            throw new Exception("API error: HTTP status $httpStatus");
        }

        // Check if the response contains the access_token key
        if (!isset($data['access_token'])) {
            throw new Exception('API error: No access token in response');
        }

        // Return the OAuth token
        return $data['access_token'];
    }
}

$twitchClass = new TwitchClips(['gordox'], 86400);

echo json_encode($twitchClass->downloadClips());