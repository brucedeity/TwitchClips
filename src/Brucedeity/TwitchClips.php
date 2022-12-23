<?php
namespace Brucedeity\Twitchclips;

date_default_timezone_set('America/Sao_Paulo');

// Require the Env class
require_once __DIR__ . '/Env.php';


use Exception;
use DateTime;
use DateInterval;

class TwitchClips
{
    // Read the client ID and client secret from the environment file
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        // Initialize the Env class and read the .env file
        Env::init(dirname(__DIR__, 2) . '/.env');

        $this->channelNames = $channelNames = explode(',', Env::get('CHANNEL_NAMES'));
    
        // Read the client ID and client secret from the environment file
        $this->clientId = Env::get('CLIENT_ID');
        $this->clientSecret = Env::get('CLIENT_SECRET');

        // Get the OAuth token
        $this->oauthToken = $this->getOauthToken();
    }

    public function welcome()
    {
        // Get the start and end dates
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        $numClips = Env::get('NUM_CLIPS');

        // Calculate the real number of clips
        $realNumClips = $numClips * count($this->channelNames);

        // Output a welcome message
        echo "Welcome to the Twitch Clips downloader!\n";
        echo "Start date: $startDate\n";
        echo "End date: $endDate\n";
        echo "Number of clips per channel: {$numClips}\n";
        echo "Channels: " . implode(', ', $this->channelNames) . "\n";
        echo "Total number of clips: $realNumClips\n";
    }

    private function getStartDate()
    {
        // Get the number of days to subtract from the current date
        $subDays = Env::get('SUB_DAYS');

        // Create a DateTime object for the current date
        $date = new DateTime();

        // Subtract the number of days from the current date
        $date->sub(new DateInterval("P{$subDays}D"));

        // Return the date in the required format
        return $date->format('Y-m-d\TH:i:s\Z');
    }

    private function getEndDate()
    {
        // Create a DateTime object for the current date
        $date = new DateTime();

        // Return the date in the required format
        return $date->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Gets the most recent clips for a specified Twitch channel.
     *
     * @param string $broadcasterId The ID of the channel's broadcaster.
     * @return array The clip data.
     * @throws Exception If the clip data could not be obtained.
     */
    public function getClips($broadcasterId)
    {
        // Set the base URL for the Twitch API
        $baseUrl = 'https://api.twitch.tv/helix';

        // Set the number of clips to retrieve
        $numClips = Env::get('NUM_CLIPS');

        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        
         // Set the URL for the API request
        $endpointUrl = "$baseUrl/clips?broadcaster_id=$broadcasterId&first=$numClips&started_at=$startDate&ended_at=$endDate";

        // Set up the cURL request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpointUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Client-ID: {$this->clientId}",
                "Authorization: Bearer $this->oauthToken"
            ]
        ]);

        // Send the request and get the response
        $response = curl_exec($ch);

        // Check if the request was successful
        if ($response === false) {
            // Throw an exception if the request failed
            throw new Exception(curl_error($ch));
        }

        // Decode the response
        $responseData = json_decode($response, true);

        // Check if the response contains an error message
        if (isset($responseData['error'])) {
            // Throw an exception if the response contains an error message
            throw new Exception($responseData['error']);
        }

        // Return the clips
        return $responseData['data'];
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
        // Output the welcome message
        $this->welcome();

        // Set the base directory
        $baseDir = dirname(__DIR__, 2);
    
        // Iterate over the channel names
        foreach ($this->channelNames as $channelName) {
            try {
                // Get the broadcaster ID for the channel
                $broadcasterId = $this->getBroadcasterId($channelName);

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
    
                    // Get the video URL of the clip
                    $videoUrl = $this->parseVideoUrl($clip['thumbnail_url']);
    
                    try {
                        // Download the clip to the file path
                        file_put_contents($filePath, file_get_contents($videoUrl));
    
                        // Print a message indicating that the clip was downloaded
                        echo "Downloaded a {$channelName}'s clip: {$clip['title']} to $filePath\n";
                    } catch (Exception $e) {
                        // Print an error message if an exception was thrown while downloading the clip
                        echo "Error downloading clip {$clip['title']}: {$e->getMessage()}\n";
                    }
                }
            } catch (Exception $e) {
                // Print an error message if an exception was thrown while getting the clips
                echo "Error getting clips for channel {$channelName}: {$e->getMessage()}\n";
            }
        }
    }
    
    
    
    /**
     * Gets the broadcaster ID for a specified Twitch channel.
     *
     * @param string $channelName The name of the Twitch channel.
     * @return string The broadcaster ID.
     * @throws Exception If the broadcaster ID could not be obtained.
     */
    private function getBroadcasterId($channelName)
    {
        // Set the API endpoint URL
        $endpointUrl = "https://api.twitch.tv/helix/users?login=$channelName";

        // Send a GET request to the API endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Client-ID: {$this->clientId}", "Authorization: Bearer {$this->oauthToken}"]);
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

$twitchClass = new TwitchClips;

echo json_encode($twitchClass->downloadClips());