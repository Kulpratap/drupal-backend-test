<?php

/**
 * @file contains the StudentSignupForm class.
 */

namespace Drupal\custom_login\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;

/**
 * Provides the student signup form.
 */
class StudentSignupForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a StudentSignupForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   */
  public function __construct(MessengerInterface $messenger, MailManagerInterface $mailManager) {
    $this->messenger = $messenger;
    $this->mailManager = $mailManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'student_signup_form';
  }

  /**
   * Build the student signup form.
   *
   * This method defines the form elements and their properties, such as type,
   * title, and required status. It also retrieves the taxonomy terms for
   * the 'Stream' vocabulary to populate a dropdown list for the 'Stream'
   * field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The modified form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('Stream');
    $options = [];

    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['mobile_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Mobile Number'),
      '#required' => TRUE,
      '#attributes' => ['maxlength' => 10],
    ];

    $form['stream'] = [
      '#type' => 'select',
      '#title' => $this->t('Stream'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['joining_year'] = [
      '#type' => 'number',
      '#title' => $this->t('Joining Year'),
      '#required' => TRUE,
      '#min' => 2020,
      '#max' => 2024,
    ];

    $form['passing_year'] = [
      '#type' => 'number',
      '#title' => $this->t('Passing Year'),
      '#required' => TRUE,
      '#min' => 2024,
      '#max' => 2028,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Validates the student signup form.
   *
   * This method checks that the mobile number is exactly 10 digits long,
   * and that the passing year is within 4 years of the joining year. It
   * sets an error if these conditions are not met.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    if (strlen($values['mobile_number']) != 10) {
      $form_state->setErrorByName('mobile_number', $this->t('Mobile Number must be exactly 10 digits.'));
    }

    $joining_year = (int) $values['joining_year'];
    $passing_year = (int) $values['passing_year'];

    if ($passing_year > $joining_year + 4) {
      $form_state->setErrorByName('passing_year', $this->t('Passing Year must be within 4 years of Joining Year.'));
    }
  }

  /**
 * Submits the student signup form.
 *
 * This method creates a new user account with the submitted details, assigns
 * the 'student' role to the new user, and sends notifications to both the
 * student and the administrator.
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 */
public function submitForm(array &$form, FormStateInterface $form_state) {
  $values = $form_state->getValues();
  $student_id = uniqid('student_');

  $existing_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $values['email']]);
  
  if (!empty($existing_user)) {
    $this->messenger->addError($this->t('A user with the email @email already exists.', ['@email' => $values['email']]));
    return;
  }

  $user = User::create([
    'name' => $values['full_name'],
    'mail' => $values['email'],
    'pass' => $values['password'], 
    'status' => 1,
    'field_mobile_number' => $values['mobile_number'],
    'field_stream' => $values['stream'],
    'field_joining_year' => $values['joining_year'],
    'field_passing_year' => $values['passing_year'],
    'field_student_id' => $student_id, 
  ]);

  $user->save();

  $role = 'student';
  if ($user->hasRole($role)) {
    $user->removeRole($role);
  }
  $user->addRole($role);
  $user->save();

  $student_subject = 'Welcome to the Student Portal';
  $student_message = 'Dear ' . $values['full_name'] . ', your student ID is ' . $student_id . '. Your registration details are as follows: Email: ' . $values['email'] . ', Mobile Number: ' . $values['mobile_number'] . ', Stream: ' . $values['stream'] . ', Joining Year: ' . $values['joining_year'] . ', Passing Year: ' . $values['passing_year'] . '.';

  $this->mailManager->mail('custom_login', 'student_notification', $values['email'], \Drupal::currentUser()->getPreferredLangcode(), [
    'subject' => $student_subject,
    'message' => $student_message,
  ]);

  $admin_subject = 'New Student Registration';
  $admin_message = 'New student registered with details: Full Name: ' . $values['full_name'] . ', Email: ' . $values['email'] . ', Mobile Number: ' . $values['mobile_number'] . ', Stream: ' . $values['stream'] . ', Joining Year: ' . $values['joining_year'] . ', Passing Year: ' . $values['passing_year'] . '.';

  $admin_email = 'kulpratap98@gmail.com';
  $this->mailManager->mail('custom_login', 'admin_notification', $admin_email, \Drupal::currentUser()->getPreferredLangcode(), [
    'subject' => $admin_subject,
    'message' => $admin_message,
  ]);

  $this->messenger->addStatus($this->t('Your registration has been submitted successfully.'));
}
}
