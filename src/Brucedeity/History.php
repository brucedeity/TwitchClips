<?php
namespace Brucedeity\Twitchclips;

class History {
    /**
     * Stores the name of the clip, the date, and the name of the streamer in the history.txt file.
     *
     * @param string $clipName The name of the clip.
     * @param string $streamerName The name of the streamer.
     */
    public function storeClip($clipName, $streamerName) {
        // Get the current date and time
        $date = date('Y-m-d H:i:s');

        // Open the history.txt file in append mode
        $historyFile = fopen('../history.txt', 'a');

        // Write the clip name, date, and streamer name to the history.txt file
        fwrite($historyFile, "$clipName,$date,$streamerName\n");

        // Close the history.txt file
        fclose($historyFile);
    }

    /**
     * Checks if a given clip has already been downloaded by checking the history.txt file.
     *
     * @param string $clipName The name of the clip.
     * @return bool True if the clip has already been downloaded, false otherwise.
     */
    public function isClipDownloaded($clipName) {
        // Open the history.txt file in read mode
        $historyFile = fopen('../history.txt', 'r');

        // Read the contents of the history.txt file
        $historyData = fread($historyFile, filesize('../history.txt'));

        // Close the history.txt file
        fclose($historyFile);

        // Split the history data into lines
        $historyLines = explode("\n", $historyData);

        // Check if the clip name appears in any of the lines of the history data
        foreach ($historyLines as $historyLine) {
            // Split the history line into fields
            $historyFields = explode(',', $historyLine);

            // Check if the clip name is the first field in the history line
            if ($historyFields[0] == $clipName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the last clipped video of a streamer and the date from the history.txt file.
     *
     * @param string $streamerName The name of the streamer.
     * @return array An array with the last clipped video and the date.
     */
    public function getLastClippedVideo($streamerName) {
        // Open the history.txt file in read mode
        $historyFile = fopen('../history.txt', 'r');

        // Read the contents of the history.txt file
        $historyData = fread($historyFile, filesize('../history.txt'));

        // Close the history.txt file
        fclose($historyFile);

        // Split the history data into lines
        $historyLines = explode("\n", $historyData);

        // Find the last clipped video of the streamer
        $lastClippedVideo = null;
        $lastClippedVideoDate = null;
        foreach ($historyLines as $historyLine) {
            // Split the history line into fields
            $historyFields = explode(',', $historyLine);

            // Check if the streamer name is the third field in the history line
            if ($historyFields[2] == $streamerName) {
                $lastClippedVideo = $historyFields[0];
                $lastClippedVideoDate = $historyFields[1];
            }
        }

        // Return the last clipped video and the date
        return [
            'video' => $lastClippedVideo,
            'date' => $lastClippedVideoDate,
        ];
    }
}