<?php

// app/Support/PageMeta.php

namespace App\Support;

class PageMeta
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $image = null,
        public ?string $url = null,
        public string $type = 'website',
        public ?array $jsonLd = null,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            // A page with no picture of its own shares the latest issue's cover.
            'image' => $this->image ?? SiteMeta::defaultImage(),
            'url' => $this->url ?? url()->current(),
            'type' => $this->type,
            'jsonLd' => $this->jsonLd,
        ];
    }
}
