<?php
namespace Brucedeity\Twitchclips;

use Exception;

class TwitchClips
{
    // Replace YOUR_CLIENT_ID and YOUR_CLIENT_SECRET with your actual Twitch API client ID and client secret
    private $clientId = '1lzi1u67kidturtq65mn3yz4ijsxxv';
    private $clientSecret = 'ln35os3a2z6pjiy2ng9zglw4gn1rr2';

    public function __construct($channelNames, $maxAge = 86400, $clipCount = 1)
    {
        $this->channelNames = $channelNames;
        $this->maxAge = $maxAge;
        $this->clipCount = $clipCount;

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

        // Set the API endpoint URL
        $endpointUrl = "https://api.twitch.tv/helix/clips?broadcaster_id=$broadcasterId&first={$this->clipCount}&started_at=".date('Y-m-d\TH:i:s', $age);

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

        print_r($data);

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


    public function downloadClips()
    {
        // Set the target directory
        $targetDir = "../../clips";

        // Iterate over the channel names
        foreach ($this->channelNames as $channelName) {
            try {
                // Get the broadcaster ID for the channel
                $broadcasterId = $this->getBroadcasterId($channelName, $this->oauthToken);

                // Get the clips for the channel
                $clips = $this->getClips($broadcasterId);

                // Iterate over the clips
                foreach ($clips as $clip) {
                    // Get the video URL of the clip
                    $videoUrl = $this->parseVideoUrl($clip['thumbnail_url']);

                    // Construct the file path of the clip
                    $filePath = $targetDir . '/' . $clip['id'] . '.mp4';

                    // Download the clip to the file path
                    file_put_contents($filePath, file_get_contents($videoUrl));

                    // Print a message indicating that the clip was downloaded
                    echo "Downloaded clip {$clip['id']} to $filePath\n";
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