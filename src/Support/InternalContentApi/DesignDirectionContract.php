<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

class DesignDirectionContract
{
  public function section(): array
  {
    return [
      'status' => 'required_before_page_planning',
      'persistence' => 'planning contract; implement through native block settings, public theme tokens, and stable site CSS',
      'dimensions' => [
        'character' => ['editorial', 'technical', 'institutional', 'calm-human', 'playful', 'luxury', 'utilitarian'],
        'density' => ['compact', 'balanced', 'relaxed'],
        'typography' => ['restrained', 'expressive', 'technical', 'warm'],
        'geometry' => ['ordered', 'editorial', 'asymmetric'],
        'imagery' => ['minimal', 'supporting', 'dominant'],
        'corners' => ['square', 'restrained', 'soft'],
        'contrast' => ['soft', 'balanced', 'high'],
        'framing' => ['open', 'restrained', 'framed', 'dense-ui'],
      ],
      'required_output' => [
        'direction' => 'Select exactly one value for every dimension before choosing blocks.',
        'rationale' => 'Explain how the selected direction fits the site, audience, and content.',
        'composition_map' => 'Map each major page band to its width, visual weight, imagery role, and native block tree.',
        'card_justification' => 'For every card collection, identify the independent boundary or action that makes cards appropriate.',
        'framed_surface_audit' => 'For every non-entity framed surface, explain the semantic independence or interaction boundary. Visual separation alone is not sufficient.',
      ],
      'composition_policy' => [
        'columns_default' => 'plain',
        'cards' => 'opt_in',
        'card_eligible_content' => ['product', 'plugin', 'price-plan', 'download', 'resource', 'service-with-own-action', 'form'],
        'card_ineligible_by_default' => ['quality', 'principle', 'benefit', 'value', 'process-summary', 'short-marketing-claim'],
        'three_items_is_not_card_justification' => true,
        'section_organization_is_not_framing_justification' => true,
        'framing_rule' => 'Do not use a card or bordered panel merely to organize a section. Framing must communicate semantic independence, interaction, or entity boundaries.',
        'unframed_by_default' => ['hero-outer-container', 'feature-or-principle-group', 'section-wrapper', 'navigation-or-footer-column', 'cta-band', 'decorative-stat', 'editorial-split'],
        'preferred_separation_tools' => ['whitespace', 'typography-hierarchy', 'asymmetric-layout', 'divider', 'background-tone-change', 'wide-or-full-width-section', 'alignment', 'offset-or-overlap', 'restrained-rule'],
        'repeat_adjacent_composition' => false,
        'vary_section_weight' => true,
      ],
      'rhythm_roles' => [
        'quiet' => 'Narrow or regular-width copy with minimal framing.',
        'structured' => 'Repeated content using plain Columns, Grid, Split, or a justified bounded-entity Card collection.',
        'dominant' => 'Wide/full-width imagery, Slider, background Section, or split Hero.',
        'conversion' => 'One focused CTA, open by default; use a framed promo only when the offer or interaction is independently bounded.',
      ],
      'capability_gaps' => [],
    ];
  }
}
