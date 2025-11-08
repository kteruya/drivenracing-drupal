<?php

namespace Drupal\driven_motorcycles\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add/Edit form for Driven Motorcycle.
 */
class DrivenMotorcycleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Keep default generated widgets; fields are configurable from UI.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $entity = $this->getEntity();
    $this->messenger()->addStatus($this->t('Saved motorcycle %label.', ['%label' => $entity->label()]));
    $form_state->setRedirect('entity.driven_motorcycle.collection');
    return $status;
  }

}
