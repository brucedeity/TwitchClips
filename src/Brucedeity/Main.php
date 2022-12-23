<?php

require '../../vendor/autoload.php';

use Brucedeity\Twitchclips\TwitchClips;

$twitchClass = new TwitchClips('gordox', 86400);

echo json_encode($twitchClass->downloadMostViewedClip());