<?php

declare(strict_types=1);

return [

    // A public PNG path, passed to asset(). Null prints the site name. Not SVG: Outlook 2016 and older cannot show it.
    'logo' => null,

    'logo_width' => 120,

    // Hex literals, as in Tone. The card itself stays white.
    'colors' => [
        'page'    => '#f5f6f8',
        'rule'    => '#e5e7eb',
        'heading' => '#111827',
        'text'    => '#374151',
        'muted'   => '#69717f',
        'link'    => '#2563eb',
    ],

];
