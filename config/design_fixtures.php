<?php

return [
  'viewports' => [
    'desktop' => ['width' => 1440, 'height' => 1024],
    'mobile' => ['width' => 390, 'height' => 844],
  ],

  'fixtures' => [
    'editorial-split-hero' => [
      'name' => 'Editorial Split Hero',
      'purpose' => 'Lead with meaningful foreground media and an unequal copy/image relationship without turning the opening band into a panel.',
      'directions' => ['editorial', 'calm-human', 'luxury'],
      'rhythm_role' => 'dominant',
      'framing_strategy' => 'open',
      'tree' => [
        'type' => 'section',
        'settings' => ['spacing' => 'lg'],
        'children' => [[
          'type' => 'container',
          'settings' => ['width' => 'xl'],
          'children' => [[
            'type' => 'grid',
            'settings' => ['columns' => '2', 'ratio' => 'lead-left', 'gap' => '6'],
            'children' => [[
              'type' => 'stack',
              'children' => [['type' => 'content_header'], ['type' => 'cluster', 'children' => [['type' => 'button_link'], ['type' => 'button_link']]]],
            ], [
              'type' => 'image',
              'media_role' => 'foreground',
            ]],
          ]],
        ]],
      ],
      'expected_hooks' => ['[data-wb-public-block-type="grid"]', '.wb-public-grid--lead-left', '[data-wb-public-block-type="content-header"]', '[data-wb-public-block-type="image"]'],
      'forbidden_hooks' => ['[data-wb-public-block-type="hero"].wb-card', '[data-wb-public-block-type="card"]'],
      'avoid' => ['centered copy without a content reason', 'decorative duplicate imagery', 'framing the outer hero merely to hold the split', 'extra card grid directly below'],
    ],
    'full-bleed-photographic-hero' => [
      'name' => 'Full-bleed Photographic Hero',
      'purpose' => 'Create a dominant, unframed opening band when one meaningful image carries the page direction.',
      'directions' => ['editorial', 'calm-human', 'playful', 'luxury'],
      'rhythm_role' => 'dominant',
      'framing_strategy' => 'open',
      'tree' => [
        'type' => 'hero',
        'settings' => ['layout' => 'full-bleed', 'background_overlay' => 'medium'],
        'media_role' => 'background',
        'children' => [['type' => 'button_link']],
      ],
      'expected_hooks' => ['[data-wb-public-block-type="hero"]', '.wb-public-hero--full-bleed', '.wb-public-hero__copy'],
      'forbidden_hooks' => ['.wb-public-hero--full-bleed.wb-card'],
      'avoid' => ['low-resolution imagery', 'decorative imagery without sufficient text contrast', 'placing the hero inside another card surface'],
    ],
    'overlapping-editorial-band' => [
      'name' => 'Overlapping Editorial Band',
      'purpose' => 'Break uniform vertical rhythm by letting one contained content band overlap a dominant predecessor.',
      'directions' => ['editorial', 'calm-human', 'playful', 'luxury'],
      'rhythm_role' => 'dominant-to-structured',
      'framing_strategy' => 'open-with-overlap',
      'tree' => [
        'type' => 'stack',
        'children' => [[
          'type' => 'hero',
          'settings' => ['layout' => 'full-bleed', 'background_overlay' => 'medium'],
          'media_role' => 'background',
        ], [
          'type' => 'section',
          'settings' => ['flow' => 'overlap-previous'],
          'children' => [[
            'type' => 'container',
            'settings' => ['width' => 'lg'],
            'children' => [['type' => 'columns', 'settings' => ['variant' => 'plain']]],
          ]],
        ]],
      ],
      'expected_hooks' => ['.wb-public-hero--full-bleed', '.wb-public-section--overlap-previous'],
      'avoid' => ['overlapping several consecutive sections', 'critical content hidden behind the preceding band', 'retaining overlap on small screens'],
    ],
    'unframed-principles' => [
      'name' => 'Unframed Principles',
      'purpose' => 'Present qualities, benefits, or values without turning every statement into a card.',
      'directions' => ['editorial', 'technical', 'institutional', 'calm-human', 'utilitarian'],
      'rhythm_role' => 'structured',
      'framing_strategy' => 'open',
      'tree' => [
        'type' => 'section',
        'settings' => ['spacing' => 'lg'],
        'children' => [[
          'type' => 'container',
          'settings' => ['width' => 'xl'],
          'children' => [[
            'type' => 'columns',
            'settings' => ['variant' => 'plain'],
            'children' => [['type' => 'column_item'], ['type' => 'column_item'], ['type' => 'column_item']],
          ]],
        ]],
      ],
      'expected_hooks' => ['[data-wb-public-block-type="columns"]', '.wb-icon-card'],
      'forbidden_hooks' => ['[data-wb-public-block-type="columns"] > .wb-grid > .wb-card'],
      'avoid' => ['card framing', 'equal-height surface treatment', 'shadow as item boundary'],
    ],
    'alternating-image-story' => [
      'name' => 'Alternating Image Story',
      'purpose' => 'Create narrative rhythm with alternating foreground images and editable copy stacks.',
      'directions' => ['editorial', 'calm-human', 'playful', 'luxury'],
      'rhythm_role' => 'dominant',
      'framing_strategy' => 'open',
      'tree' => [
        'type' => 'section',
        'children' => [[
          'type' => 'container',
          'settings' => ['width' => 'xl'],
          'children' => [[
            'type' => 'grid',
            'settings' => ['columns' => '2', 'ratio' => 'lead-left', 'gap' => '6', 'alternate_media_text_sections' => true, 'alternate_start' => 'media_left'],
            'children' => [['type' => 'image'], ['type' => 'stack'], ['type' => 'image'], ['type' => 'stack']],
          ]],
        ]],
      ],
      'expected_hooks' => ['[data-wb-public-block-type="grid"]', '.wb-public-grid--lead-left', '[data-wb-public-block-type="image"]', '[data-wb-public-block-type="stack"]'],
      'avoid' => ['background images for semantic content', 'cards around every copy stack', 'identical image crop on every row'],
    ],
    'bounded-entity-cards' => [
      'name' => 'Bounded Entity Cards',
      'purpose' => 'Use cards only for entities with their own boundary or action.',
      'directions' => ['technical', 'institutional', 'playful', 'utilitarian'],
      'rhythm_role' => 'structured',
      'framing_strategy' => 'bounded-entities',
      'requires_card_justification' => true,
      'card_justification' => 'Each repeated item is an independently identifiable entity with its own details or action.',
      'tree' => [
        'type' => 'section',
        'children' => [[
          'type' => 'container',
          'settings' => ['width' => 'xl'],
          'children' => [[
            'type' => 'grid',
            'settings' => ['columns' => '3', 'gap' => '4'],
            'children' => [[
              'type' => 'card',
              'children' => [['type' => 'card_header'], ['type' => 'card_body'], ['type' => 'card_footer']],
            ]],
          ]],
        ]],
      ],
      'expected_hooks' => ['[data-wb-public-block-type="card"]', '[data-wb-public-block-type="card-body"]'],
      'eligible_content' => ['product', 'plugin', 'price-plan', 'download', 'resource', 'service-with-own-action'],
      'avoid' => ['qualities or values', 'short claims without actions', 'cards selected only because there are three items'],
    ],
  ],
];
