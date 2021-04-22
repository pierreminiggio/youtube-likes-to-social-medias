<?php

namespace App;

class App
{

    public function run(): void
    {
        $config = require
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'config.php'
        ;
        $token = $config['apiToken'];

        echo PHP_EOL . 'Getting likes ...';
        
        $likeCurl = curl_init('https://old.miniggiodev.fr/api/getVideoableLikes.php');

        $authHeader = ['Content-Type: application/json' , 'Authorization: Bearer ' . $token];
        curl_setopt_array($likeCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $authHeader
        ]);

        $likeCurlResponse = curl_exec($likeCurl);

        if ($likeCurlResponse === false) {
            return;
        }

        $likes = json_decode($likeCurlResponse, true);

        if ($likes === null) {
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

        echo PHP_EOL . PHP_EOL . 'Rendering video ...' . PHP_EOL . PHP_EOL;
        $renderLog = shell_exec('npm --prefix ' . escapeshellarg($videoFolder) . ' run build');

        if (! str_contains($renderLog, 'Your video is ready')) {
            echo ' Error while rendering !';

            return;
        }
        echo 'Rendered !';
    }
}
