/**
 * @file
 * Two behaviors for the Inclusion / Exclusion taxonomy select fields:
 *
 * 1. Same-row disable: within one inclusion_exclusion_item paragraph row,
 *    selecting a value in one field disables its sibling. Pairing is done
 *    by substituting the field name inside the exact form element `name`
 *    attribute (which already encodes the correct paragraph delta), so it
 *    works for rows present on load and rows added later via the
 *    Paragraphs "Add another item" AJAX button.
 *
 * 2. Cross-row duplicate check: the same taxonomy term must not be picked
 *    as Inclusion in one row and Exclusion in another. This is checked on
 *    every change across all rows and shows an inline error immediately,
 *    ahead of the server-side validation that blocks the actual save.
 */
(function ($, Drupal, once) {
  'use strict';

  const FIELD_INCLUSION = 'field_inclusion';
  const FIELD_EXCLUSION = 'field_exclusion';
  const ERROR_CLASS = 'travelostyle-inline-error';

  function isEmptyValue(value) {
    return !value || value === '_none';
  }

  function siblingSelector(name, ownField, otherField) {
    return 'select[name="' + name.replace(ownField, otherField) + '"]';
  }

  function toggleSiblingDisabled($el, ownField, otherField) {
    const name = $el.attr('name');
    if (!name) {
      return;
    }
    const $other = $el.closest('form').find(siblingSelector(name, ownField, otherField));
    if ($other.length) {
      $other.prop('disabled', !isEmptyValue($el.val()));
    }
  }

  function showInlineError($el, message) {
    $el.addClass('error').attr('aria-invalid', 'true');
    let $msg = $el.next('.' + ERROR_CLASS);
    if (!$msg.length) {
      $msg = $('<div></div>')
        .addClass(ERROR_CLASS)
        .attr('role', 'alert')
        .css({ color: '#e00', fontSize: '0.85em', marginTop: '4px' })
        .insertAfter($el);
    }
    $msg.text(message);
  }

  function clearInlineError($el) {
    $el.removeClass('error').removeAttr('aria-invalid');
    $el.next('.' + ERROR_CLASS).remove();
  }

  function revalidateDuplicates($form) {
    const $inclusionSelects = $form.find('select[name*="[' + FIELD_INCLUSION + ']"]');
    const $exclusionSelects = $form.find('select[name*="[' + FIELD_EXCLUSION + ']"]');

    const inclusionValues = {};
    $inclusionSelects.each(function () {
      const val = $(this).val();
      if (!isEmptyValue(val)) {
        inclusionValues[val] = true;
      }
    });

    const exclusionValues = {};
    $exclusionSelects.each(function () {
      const val = $(this).val();
      if (!isEmptyValue(val)) {
        exclusionValues[val] = true;
      }
    });

    $inclusionSelects.each(function () {
      const $el = $(this);
      const val = $el.val();
      if (!isEmptyValue(val) && exclusionValues[val]) {
        showInlineError($el, Drupal.t('This item is already selected as Exclusion elsewhere — it cannot also be used as Inclusion.'));
      }
      else {
        clearInlineError($el);
      }
    });

    $exclusionSelects.each(function () {
      const $el = $(this);
      const val = $el.val();
      if (!isEmptyValue(val) && inclusionValues[val]) {
        showInlineError($el, Drupal.t('This item is already selected as Inclusion elsewhere — it cannot also be used as Exclusion.'));
      }
      else {
        clearInlineError($el);
      }
    });
  }

  Drupal.behaviors.travelostyleIncludeExcludeToggle = {
    attach: function (context) {
      const inclusionEls = once('travelostyle-inclusion-toggle', 'select[name*="[' + FIELD_INCLUSION + ']"]', context);
      const exclusionEls = once('travelostyle-exclusion-toggle', 'select[name*="[' + FIELD_EXCLUSION + ']"]', context);
      const $all = $(inclusionEls).add(exclusionEls);

      if (!$all.length) {
        return;
      }

      inclusionEls.forEach(function (el) {
        toggleSiblingDisabled($(el), FIELD_INCLUSION, FIELD_EXCLUSION);
      });
      exclusionEls.forEach(function (el) {
        toggleSiblingDisabled($(el), FIELD_EXCLUSION, FIELD_INCLUSION);
      });

      const $form = $all.closest('form').first();

      $all.on('change.travelostyleIncludeExcludeToggle', function () {
        const $el = $(this);
        const ownField = $el.attr('name').indexOf(FIELD_INCLUSION) !== -1 ? FIELD_INCLUSION : FIELD_EXCLUSION;
        const otherField = ownField === FIELD_INCLUSION ? FIELD_EXCLUSION : FIELD_INCLUSION;
        toggleSiblingDisabled($el, ownField, otherField);
        revalidateDuplicates($form);
      });

      revalidateDuplicates($form);
    }
  };
})(jQuery, Drupal, once);
