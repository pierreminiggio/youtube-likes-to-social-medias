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
        
        $likeCurl = curl_init('https://old.miniggiodev.fr/api/getVideoableLikes.php');

        curl_setopt_array($likeCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json' , 'Authorization: Bearer ' . $token]
        ]);

        $likeCurlResponse = curl_exec($likeCurl);

        if ($likeCurlResponse === false) {
            return;
        }

        $likes = json_decode($likeCurlResponse, true);

        if ($likes === null) {
            return;
        }

        $likePopulator = new LikeMediaPopulator();
        $likePopulator->populate($likes);
    }
}
