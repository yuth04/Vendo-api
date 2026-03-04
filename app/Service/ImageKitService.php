<?php

namespace App\Services;

use ImageKit\ImageKit;

class ImageKitService
{
    protected $imagekit;

    public function __construct()
    {
        $this->imagekit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );
    }

    public function upload($file, $folder = "/products")
    {
        $result = $this->imagekit->upload([
            'file' => fopen($file->getRealPath(), "r"),
            'fileName' => $file->getClientOriginalName(),
            'folder' => $folder,
        ]);

        if(isset($result->error)) {
            throw new \Exception($result->error->message);
        }

        return $result->result->url;
    }
}