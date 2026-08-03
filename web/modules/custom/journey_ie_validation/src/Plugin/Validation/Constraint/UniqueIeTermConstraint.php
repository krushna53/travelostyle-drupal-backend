<?php

namespace Drupal\journey_ie_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the same Inclusion/Exclusion term isn't used in both lists.
 *
 * @Constraint(
 *   id = "UniqueIeTerm",
 *   label = @Translation("Unique Inclusion/Exclusion term per Journey", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class UniqueIeTermConstraint extends Constraint {

  public $message = 'The following item(s) cannot appear in both Inclusions and Exclusions: %terms';

}
