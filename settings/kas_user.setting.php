<?php

/**
 * Remembered choices from the "Print business cards" form. They are saved
 * each time cards are printed and used as defaults (and by the single-card
 * link on the contact summary).
 */
$defs = [
  'kas_user_layout' => ['String', 'a4', 'Card layout (a4 or single)'],
  'kas_user_logo_source' => ['String', 'domain', 'Where the logo comes from (domain, employer or none)'],
  'kas_user_qr_target' => ['String', '', 'What the QR code opens (profile:<id>, afform:<name>, url or none)'],
  'kas_user_qr_url' => ['String', '', 'Custom URL for the QR code'],
  'kas_user_qr_caption' => ['String', '', 'Text under the QR code'],
  'kas_user_website' => ['String', '', 'Website shown when a contact has none'],
  'kas_user_show_address' => ['Boolean', TRUE, 'Show the postal address'],
  'kas_user_accent' => ['String', '#1f4e79', 'Accent colour'],
];

$settings = [];
foreach ($defs as $name => [$type, $default, $title]) {
  $settings[$name] = [
    'name' => $name,
    'type' => $type,
    'default' => $default,
    'html_type' => $type === 'Boolean' ? 'checkbox' : 'text',
    'title' => $title,
    'description' => $title,
    'is_domain' => 1,
    'is_contact' => 0,
  ];
}
return $settings;
