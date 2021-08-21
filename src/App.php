<?php

namespace App;

use Dailymotion;
use DateTime;
use Exception;
use PierreMiniggio\DailymotionFileUploader\FileUploader;
use PierreMiniggio\DailymotionTokenProvider\AccessTokenProvider;
use PierreMiniggio\DailymotionUploadUrlMaker\UploadUrlMaker;
use PierreMiniggio\GithubActionRemotionRenderer\GithubActionRemotionRenderer;
use PierreMiniggio\GoogleTokenRefresher\GoogleClient;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\Video;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPosterFactory;
use PierreMiniggio\HeropostYoutubePosting\YoutubeCategoriesEnum;
use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;

class App
{

    public function run(): void
    {

        $uploadDestination = UploadDestination::DAILYMOTION;

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

        $yesterdayDate = (new DateTime('-1 day'));
        $yesterday = $yesterdayDate->format('d/m/Y');

        echo PHP_EOL . 'Getting ' . $yesterday . '\'s likes ...';
        
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

        echo PHP_EOL . PHP_EOL . 'Rendering video ...';
        
        $rendererProjects = $config['rendererProjects'];
        $rendererProject = $rendererProjects[array_rand($rendererProjects)];

        $renderer = new GithubActionRemotionRenderer();
        $runnerAndDownloader = $renderer->getRunnerAndDownloader();
        $runnerAndDownloader->sleepTimeBetweenRunCreationChecks = 30;
        $runnerAndDownloader->numberOfRunCreationChecksBeforeAssumingItsNotCreated = 20;

        try {
            $videoFile = $renderer->render(
                $rendererProject['token'],
                $rendererProject['account'],
                $rendererProject['project'],
                1800,
                0,
                [
                    'likes' => json_encode($likes)
                ]
            );
        } catch (Exception $e) {
            echo PHP_EOL . 'Error while rendering : ' . $e->getMessage();
            var_dump($e->getTrace());
        }

        $oldMiniggiodevLikesUrl = 'https://likes.ggio.fr?date=' . $yesterdayDate->format('Y-m-d');

        if (isset($videoFile)) {
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
                . $yesterday
            ;
            $font = $projectFolder . 'Roboto-Regular.ttf';
            imagettftext($img, 100, 0, 200, 200, $white, $font, $txt);

            imagepng($img, $thumbnailFile, 9);

            echo ' Done !';

            echo PHP_EOL . PHP_EOL . 'Picking a title...';

            if ($likes) {
                $randomLike = $likes[array_rand($likes)];
                $channelName = $randomLike['channel_name'];

                $iWatched = 'J\'ai regardé "';
                $pipe = ' | ';
                $andMoreVideos = '" et d\'autres vidéos';

                $youtubeMaxTitleLength = 100;

                $everyThingButVideoTitleLength =
                    strlen($iWatched)
                    + strlen($pipe)
                    + strlen($channelName)
                    + strlen($andMoreVideos)
                ;

                $selectedVideoTitle = $randomLike['title'];
                $selectedVideoTitleLength = strlen($selectedVideoTitle);

                $difference = $everyThingButVideoTitleLength + $selectedVideoTitleLength - $youtubeMaxTitleLength;

                if ($difference > 0) {
                    $selectedVideoTitle = substr($selectedVideoTitle, 0, -$difference);
                }

                $title = $iWatched . $selectedVideoTitle . $pipe . $channelName . $andMoreVideos;
            } else {
                $title = 'J\'ai regardé rien du tout mdr';
            }

            echo ' Picked !';
            echo PHP_EOL . 'Title : ' . $title;

            echo PHP_EOL . PHP_EOL . 'Building the description...';

            $descriptionMakeSize = UploadDestination::DAILYMOTION ? 3000 : null;

            $description = 'Chaque jour' . (
                $likes
                    ? ''
                    : ' (mais pas aujourd\'hui faut croire...'
            ) . ' je regarde des vidéos sur Youtube, pour découvrir et apprendre des choses, ou bien pour me divertir :P';
            $description .= PHP_EOL . PHP_EOL . 'Sinon je publie aussi des vidéos sur ma chaîne principale : https://ggio.link/youtube';

            $descriptionSuffix = PHP_EOL . PHP_EOL . 'Plus d\'infos ici : ' . $oldMiniggiodevLikesUrl;

            foreach ($likes as &$like) {
                $thisLikeDescription = PHP_EOL . PHP_EOL;
                $thisLikeDescription .= $like['title'] . ' | ' . $like['channel_name'] . ' :';
                $thisLikeDescription .= PHP_EOL . 'https://youtube.com/watch?v=' . $like['youtube_id'];
                
                $willDescriptionBeTooLong = $descriptionMakeSize !== null && strlen(
                    $description . $thisLikeDescription . $descriptionSuffix
                ) > $descriptionMakeSize
                ;

                if ($willDescriptionBeTooLong) {
                    break;
                }

                $description .= $thisLikeDescription;
            }

            $description .= $descriptionSuffix;

            echo ' Built !';

            echo PHP_EOL . PHP_EOL . 'Uploading to ';

            if ($uploadDestination === UploadDestination::YOUTUBE) {
                echo 'Youtube ...';

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
                $videoLink = 'https://youtu.be/' . $youtubeVideoId;
            } else {
                echo 'Dailymotion ...';

                $dmConfig = $config['dailymotion'];
                $dmClientId = $dmConfig['apiKey'];
                $dmClientSecret = $dmConfig['apiSecret'];
                $dmUsername = $dmConfig['username'];
                $dmPassword = $dmConfig['password'];

                $dmAPI = new Dailymotion();
                $dmAPI->setGrantType(
                    Dailymotion::GRANT_TYPE_PASSWORD,
                    $dmClientId,
                    $dmClientSecret,
                    [
                        'manage_videos'
                    ],
                    [
                        'username' => $dmUsername,
                        'password' => $dmPassword
                    ]
                );

                $tokenProvider = new AccessTokenProvider();
                $token = $tokenProvider->login($dmClientId, $dmClientSecret, $dmUsername, $dmPassword);
                if ($token === null) {
                    echo ' Login failed !';
                    die;
                }

                $dmUrlMaker = new UploadUrlMaker();
                $dmUploadUrl = $dmUrlMaker->create($token);
                if ($dmUploadUrl === null) {
                    echo ' Upload URL not created !';
                    die;
                }

                $dmFileUploader = new FileUploader();
                $dmVideoUrl = $dmFileUploader->upload($dmUploadUrl, $videoFile);
                if ($dmVideoUrl === null) {
                    echo ' Video URL not created ! Upload failed ?';
                    die;
                }

                $videoCreator = new DailymotionVideoCreator($dmAPI);

                try {
                    $dmVideoId = $videoCreator->create($dmVideoUrl, $title, $description);
                } catch (Exception $e) {
                    echo ' Error while creating video: ' . $e->getMessage();
                    echo PHP_EOL . 'Trace: ' . json_encode($e->getTrace());
                    die;
                }

                $videoLink = 'https://dai.ly/' . $dmVideoId;
            }
            echo ' Uploaded !';
        } else {
            echo 'We won\'t upload a video since it likely failed, we\'ll only Tweet text';
        }

        echo PHP_EOL . PHP_EOL . 'Tweeting ...';

        $tweetStart = 'J\'ai 👍 les videos de ';
        $tweetEnd = PHP_EOL . ($videoLink ?? $oldMiniggiodevLikesUrl);

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
