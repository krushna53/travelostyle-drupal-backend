<?php

namespace Drupal\journey_ie_validation\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueIeTerm constraint.
 */
class UniqueIeTermConstraintValidator extends ConstraintValidator {

  /**
   * Paragraph bundle that holds the actual inclusion/exclusion term refs.
   */
  const TARGET_PARAGRAPH_BUNDLE = 'inclusion_exclusion_item';

  /**
   * Field on that paragraph holding included terms.
   */
  const FIELD_INCLUSIONS = 'field_field_journey_inclusions';

  /**
   * Field on that paragraph holding excluded terms.
   */
  const FIELD_EXCLUSIONS = 'field_exclusion';

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!$entity instanceof ContentEntityInterface || $entity->getEntityTypeId() !== 'node') {
      return;
    }

    if ($entity->bundle() !== 'journey') {
      return;
    }

    $inclusion_ids = [];
    $exclusion_ids = [];

    // Recursively walk every paragraph field on the node to find all
    // inclusion_exclusion_item paragraphs, wherever they are nested.
    $this->collectIeTermIds($entity, $inclusion_ids, $exclusion_ids);

    $duplicate_tids = array_unique(array_intersect($inclusion_ids, $exclusion_ids));

    if (!empty($duplicate_tids)) {
      $labels = [];
      foreach ($duplicate_tids as $tid) {
        $term = Term::load($tid);
        $labels[] = $term ? $term->label() : "TID:$tid";
      }

      $this->context->buildViolation($constraint->message, [
        '%terms' => implode(', ', $labels),
      ])
        ->atPath('field_journey_tabs_section')
        ->addViolation();
    }
  }

  /**
   * Recursively collects inclusion/exclusion term IDs from nested paragraphs.
   *
   * Walks any entity_reference_revisions (paragraph) field found on the
   * given entity, and for every paragraph of bundle
   * `inclusion_exclusion_item` found, gathers term IDs from its inclusion
   * and exclusion fields. Recurses into nested paragraphs as well so it
   * doesn't matter how many levels deep the item is
   * (Journey -> Journey Tabs Section -> Inclusions Exclusions Tab -> Item).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to scan (node or paragraph).
   * @param array $inclusion_ids
   *   Accumulator array of inclusion term IDs (passed by reference).
   * @param array $exclusion_ids
   *   Accumulator array of exclusion term IDs (passed by reference).
   */
  protected function collectIeTermIds(ContentEntityInterface $entity, array &$inclusion_ids, array &$exclusion_ids) {
    // If this entity IS an inclusion_exclusion_item paragraph, harvest it.
    if ($entity instanceof ParagraphInterface && $entity->bundle() === self::TARGET_PARAGRAPH_BUNDLE) {
      if ($entity->hasField(self::FIELD_INCLUSIONS)) {
        foreach ($entity->get(self::FIELD_INCLUSIONS) as $item) {
          if (!empty($item->target_id)) {
            $inclusion_ids[] = (int) $item->target_id;
          }
        }
      }
      if ($entity->hasField(self::FIELD_EXCLUSIONS)) {
        foreach ($entity->get(self::FIELD_EXCLUSIONS) as $item) {
          if (!empty($item->target_id)) {
            $exclusion_ids[] = (int) $item->target_id;
          }
        }
      }
    }

    // Regardless of bundle, keep walking any paragraph reference fields
    // in case items are nested deeper (e.g. inside Journey Tabs Section
    // -> Inclusions Exclusions Tab -> Inclusion Exclusion Item).
    foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($definition->getFieldStorageDefinition()->getSetting('target_type') !== 'paragraph') {
        continue;
      }
      if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($entity->get($field_name)->referencedEntities() as $referenced) {
        if ($referenced instanceof ParagraphInterface) {
          $this->collectIeTermIds($referenced, $inclusion_ids, $exclusion_ids);
        }
      }
    }
  }

}
