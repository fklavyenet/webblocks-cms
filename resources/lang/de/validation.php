<?php

return [
  'search' => [
    'string' => 'Geben Sie eine gültige Suchanfrage als Text ein.',
    'max' => 'Die Suchanfrage darf höchstens :count Zeichen enthalten.',
    'terms' => 'Die Suchanfrage darf höchstens :count Wörter enthalten.',
  ],
  'ip_or_cidr' => 'Geben Sie eine gültige IP-Adresse oder ein CIDR-Netzwerk ein.',
  'contact_form' => [
    'name_required' => 'Geben Sie Ihren Namen ein.',
    'email_required' => 'Geben Sie Ihre E-Mail-Adresse ein.',
    'email_valid' => 'Geben Sie eine gueltige E-Mail-Adresse ein.',
    'consent_required' => 'Bestätigen Sie den Datenschutzhinweis, um fortzufahren.',
    'consent_unavailable' => 'Dieses Formular ist vorübergehend nicht verfügbar, da der erforderliche Einwilligungstext fehlt. Bitte versuchen Sie es später erneut.',
    'message_required' => 'Geben Sie eine Nachricht ein.',
  ],
  'comments' => [
    'body_required' => 'Geben Sie einen Kommentar ein.',
    'body_min' => 'Geben Sie mindestens :min Zeichen ein.',
    'body_max' => 'Kommentare duerfen nicht laenger als :max Zeichen sein.',
  ],
  'rating' => [
    'required' => 'Waehlen Sie eine Bewertung.',
    'integer' => 'Waehlen Sie eine gueltige Bewertung.',
    'min' => 'Waehlen Sie mindestens :min Stern.',
    'max' => 'Waehlen Sie hoechstens :max Sterne.',
  ],
];
