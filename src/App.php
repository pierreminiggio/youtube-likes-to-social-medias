<?php

namespace App;

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

        // if (file_exists($videoFile)) {
        //     echo PHP_EOL . PHP_EOL . 'Removing old video file ...';
        //     unlink($videoFile);
        //     echo ' Removed !';
        // }

        echo PHP_EOL . PHP_EOL . 'Rendering video ...';
        // $renderLog = shell_exec('npm --prefix ' . escapeshellarg($videoFolder) . ' run build');

        // if (! str_contains($renderLog, 'Your video is ready')) {
        //     echo ' Error while rendering !';

        //     return;
        // }

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

        echo PHP_EOL . PHP_EOL . 'Mark as videoed ...';

        // $setAsVideoedCurl = curl_init($apiBaseUrl . '/setAsVideoed.php');

        // $curlOptions[CURLOPT_POST] = 1;
        // $curlOptions[CURLOPT_POSTFIELDS] = json_encode([
        //     'ids' => array_map(fn (array $like): int => (int) $like['id'], $likes)
        // ]);

        // curl_setopt_array($setAsVideoedCurl, $curlOptions);

        // $setAsVideoedCurlResponse = curl_exec($setAsVideoedCurl);

        // if ($setAsVideoedCurlResponse === false) {
        //     echo 'Set as videoable failed : Erreur curl';

        //     return;
        // }

        // $httpCode = curl_getinfo($setAsVideoedCurl)['http_code'];

        // if ($httpCode !== 204) {
        //     echo 'Set as videoable failed : not a 204';

        //     return;
        // }

        echo ' Marked !';
    }
}
