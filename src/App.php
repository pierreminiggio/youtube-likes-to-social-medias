<?php

namespace App;

use PierreMiniggio\GoogleTokenRefresher\GoogleClient;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\Video;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPosterFactory;
use PierreMiniggio\HeropostYoutubePosting\YoutubeCategoriesEnum;
use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;

class App
{

    public function run(): void
    {

        $projectFolder =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
        ;
        $config = require
            $projectFolder
            . 'config.php'
        ;
        $token = $config['apiToken'];

        echo PHP_EOL . 'Getting likes ...';
        
        $apiBaseUrl = 'https://old.miniggiodev.fr/api';
        $likeCurl = curl_init($apiBaseUrl . '/getVideoableLikes.php');

        $authHeader = ['Content-Type: application/json' , 'Authorization: Bearer ' . $token];
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $authHeader
        ];
        curl_setopt_array($likeCurl, $curlOptions);

        $likeCurlResponse = curl_exec($likeCurl);

        if ($likeCurlResponse === false) {
            echo 'Erreur curl';

            return;
        }

        $likes = json_decode($likeCurlResponse, true);

        if ($likes === null) {
            echo 'Erreur retour JSON';

            return;
        }

        curl_close($likeCurl);

        echo ' Done !';

        echo PHP_EOL . PHP_EOL . 'Populating likes ...';

        $likePopulator = new LikeMediaPopulator($authHeader);
        $likePopulator->populate($likes);

        echo PHP_EOL . PHP_EOL . 'Populated !';

        echo PHP_EOL . PHP_EOL . 'Saving likes into a JSON file ...';

        file_put_contents(
            __DIR__
                . DIRECTORY_SEPARATOR
                . '..'
                . DIRECTORY_SEPARATOR
                . 'node_modules'
                . DIRECTORY_SEPARATOR
                . '@pierreminiggio'
                . DIRECTORY_SEPARATOR
                . 'youtube-likes-recap-video-maker'
                . DIRECTORY_SEPARATOR
                . 'likes.json'
            ,
            json_encode($likes)
        );

        echo ' Saved !';

        $videoFolder =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'node_modules'
            . DIRECTORY_SEPARATOR
            . '@pierreminiggio'
            . DIRECTORY_SEPARATOR
            . 'youtube-likes-recap-video-maker'
        ;

        $videoFile = $videoFolder . DIRECTORY_SEPARATOR . 'out.mp4';

        if (file_exists($videoFile)) {
            echo PHP_EOL . PHP_EOL . 'Removing old video file ...';
            unlink($videoFile);
            echo ' Removed !';
        }

        echo PHP_EOL . PHP_EOL . 'Rendering video ...';
        $renderLog = shell_exec('npm --prefix ' . escapeshellarg($videoFolder) . ' run build');

        if (! str_contains($renderLog, 'Your video is ready')) {
            echo ' Error while rendering !';

            return;
        }

        echo ' Rendered !';

        echo PHP_EOL . PHP_EOL . 'Making a thumbnail...';

        $thumbnailName = 'minia.png';
        $thumbnailFile = $projectFolder . $thumbnailName;

        $img = imagecreatetruecolor(1280, 720);
        $white = imagecolorallocate($img, 255, 255, 255);
        $txt = 'Les vidéos'
            . PHP_EOL
            . 'que j\'ai regardé'
            . PHP_EOL
            . 'le '
            . date('d/m/Y');
        $font = $projectFolder . 'Roboto-Regular.ttf';
        imagettftext($img, 100, 0, 200, 200, $white, $font, $txt);

        imagepng($img, $thumbnailFile, 9);

        echo ' Done !';

        echo PHP_EOL . PHP_EOL . 'Picking a title...';

        $randomLike = $likes[array_rand($likes)];
        $title = $randomLike['title'] . ' | ' . $randomLike['channel_name'];

        echo ' Picked !';

        echo PHP_EOL . PHP_EOL . 'Building the description...';

        $description = 'Chaque jour je regarde des vidéos sur Youtube, pour découvrir et apprendre des choses, ou bien pour me divertir :P';
        $description .= PHP_EOL . PHP_EOL . 'Sinon je publie aussi des vidéos sur ma chaîne principale : https://ggio.link/youtube';

        foreach ($likes as &$like) {
            $description .= PHP_EOL . PHP_EOL;
            $description .= $like['title'] . ' | ' . $like['channel_name'] . ' :';
            $description .= PHP_EOL . 'https://youtube.com/watch?v=' . $like['youtube_id'];
        }

        echo ' Built !';

        echo PHP_EOL . PHP_EOL . 'Uploading to Youtube ...';

        $videoPoster = (new VideoPosterFactory())->make(new Logger());
        $youtubeVideoId = $videoPoster->post(
            $config['heropostLogin'],
            $config['heropostPassword'],
            $config['channelId'],
            new Video(
                new YoutubeVideo(
                    $title,
                    $description,
                    YoutubeCategoriesEnum::EDUCATION
                ),
                [],
                false,
                $videoFile,
                $thumbnailFile
            ),
            new GoogleClient(
                $config['googleClientId'],
                $config['googleClientSecret'],
                $config['googleRefreshToken']
            )
        );

        echo ' Uploaded !';

        echo PHP_EOL . PHP_EOL . 'Tweeting ...';
        
        $tweetStart = 'J\'ai 👍 les videos de ';
        $tweetEnd = ' :' . PHP_EOL . 'https://youtu.be/' . $youtubeVideoId;

        $twitterHandles = [];
        $tweet = '';
        foreach ($likes as &$like) {
            $twitterCurl = curl_init('https://twitter-handle-finder-api.miniggiodev.fr/' . $like['channel_id']);
            curl_setopt($twitterCurl, CURLOPT_RETURNTRANSFER, true);
            $twitterCurlResponse = curl_exec($twitterCurl);
            curl_close($twitterCurl);

            if (empty($twitterCurlResponse)) {
                continue;
            }

            $jsonTwitterCurlResponse = json_decode($twitterCurlResponse, true);

            if ($jsonTwitterCurlResponse === null || empty($jsonTwitterCurlResponse['twitter_handle'])) {
                continue;
            }

            $twitterHandle = $jsonTwitterCurlResponse['twitter_handle'];

            if (in_array($twitterHandle, $twitterHandles)) {
                continue;
            }

            $twitterHandles[] = $twitterHandle;

            $maybeNextTweet =
                $tweetStart
                . implode(
                    ' ',
                    array_map(fn (string $handle): string => '@' . $handle, $twitterHandles)
                )
                . $tweetEnd;

            if (strlen($maybeNextTweet) >= 280) {
                break;
            }

            $tweet = $maybeNextTweet;
        }

        if (! $twitterHandles) {
            $tweet = $tweetStart . 'plusieurs Youtubeurs' . $tweetEnd;
        }

        $postTweetCurl = curl_init('https://old.miniggiodev.fr/test/twitter/api/index.php');
        $PostTweetCurlOptions = $curlOptions;
        $PostTweetCurlOptions[CURLOPT_POST] = 1;
        $PostTweetCurlOptions[CURLOPT_POSTFIELDS] = $tweet;
        curl_setopt_array($postTweetCurl, $PostTweetCurlOptions);
        curl_exec($postTweetCurl);
        curl_close($postTweetCurl);

        echo ' Tweeted !';

        echo PHP_EOL . PHP_EOL . 'Mark as videoed ...';

        $setAsVideoedCurl = curl_init($apiBaseUrl . '/setAsVideoed.php');

        $videoableCurlOptions = $curlOptions;
        $videoableCurlOptions[CURLOPT_POST] = 1;
        $videoableCurlOptions[CURLOPT_POSTFIELDS] = json_encode([
            'ids' => array_map(fn (array $like): int => (int) $like['id'], $likes)
        ]);

        curl_setopt_array($setAsVideoedCurl, $videoableCurlOptions);

        $setAsVideoedCurlResponse = curl_exec($setAsVideoedCurl);

        if ($setAsVideoedCurlResponse === false) {
            echo 'Set as videoable failed : Erreur curl';

            return;
        }

        $httpCode = curl_getinfo($setAsVideoedCurl)['http_code'];

        if ($httpCode !== 204) {
            echo 'Set as videoable failed : not a 204';

            return;
        }

        echo ' Marked !';
    }
}
