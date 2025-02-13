<?php declare(strict_types=1);

namespace App\Model;

readonly class Download
{
    public function __construct(
        public string $audioPath,
        public ?string $imgPath,
    ) {}

    public function __destruct()
    {
        if (isset($this->imgPath)) {
            unlink($this->imgPath);
        }
    }
}
