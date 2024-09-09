<?php

/**
 * @file contains the UserLoginForm class.
 */

namespace Drupal\custom_login\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Provides the user login form with OTP verification.
 */
class UserLoginForm extends FormBase {

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
   * The user storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * The user authentication service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a UserLoginForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(MessengerInterface $messenger, MailManagerInterface $mail_manager, EntityTypeManagerInterface $entity_type_manager, UserAuthInterface $user_auth, StateInterface $state) {
    $this->messenger = $messenger;
    $this->mailManager = $mail_manager;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->userAuth = $user_auth;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager'),
      $container->get('user.auth'),
      $container->get('state')
    );
  }

  /**
   * This function build the formId.
   * 
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'combined_login_form';
  }

  /**
   * Builds the user login form.
   *
   * This method define form elementk and their properties.
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
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('username'),
      '#attributes' => ['readonly' => !empty($form_state->get('otp_sent'))],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('email'),
      '#attributes' => ['readonly' => !empty($form_state->get('otp_sent'))],
    ];

    if ($form_state->get('otp_sent')) {
      $form['otp'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter OTP'),
        '#required' => TRUE,
      ];

      $form['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#required' => TRUE,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $form_state->get('otp_sent') ? $this->t('Login') : $this->t('Send OTP'),
    ];

    return $form;
  }

  /**
   * Validate the user login form.
   *
   * This method performs validation based on whether the OTP has been sent.
   * If not, it checks the username and email against the user database.
   * If OTP has been sent, it validat the OTP with the stored OTP.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $username = $form_state->getValue('username');

    if (!$form_state->get('otp_sent')) {
      $user = $this->userStorage->loadByProperties(['name' => $username]);

      if (empty($user) || count($user) != 1) {
        $form_state->setErrorByName('username', $this->t('Invalid username.'));
        return;
      }

      $user = reset($user);
      if ($user->getEmail() !== $email) {
        $form_state->setErrorByName('email', $this->t('Email does not match.'));
        return;
      }
    } else {
      $entered_otp = $form_state->getValue('otp');
      $stored_otp = $this->state->get('otp_verification_code');

      if ($entered_otp != $stored_otp) {
        $form_state->setErrorByName('otp', $this->t('Invalid OTP.'));
      }
    }
  }

  /**
   * Handles the submission of the user login form.
   *
   * This method either sends an OTP to the provided email address or logs
   * the user in based on whether the OTP has been sent.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->get('otp_sent')) {
      $email = $form_state->getValue('email');
      $this->sendOtp($email);
      $this->messenger->addStatus($this->t('An OTP has been sent to your email address.'));
      $form_state->set('otp_sent', TRUE);
      $form_state->setRebuild();
    } else {
      $username = $form_state->getValue('username');
      $password = $form_state->getValue('password');
      $account = $this->userAuth->authenticate($username, $password);

      if ($account) {
        $user = User::load($account);
        user_login_finalize($user);
        $this->messenger->addStatus($this->t('You have been successfully logged in.'));
        $form_state->setRedirect('<front>');
      } else {
        $this->messenger->addError($this->t('Invalid password.'));
      }
    }
  }

  /**
   * Sends an OTP to the specified email address.
   *
   * This method generates a random OTP, stores it in the state service, and
   * sends it to the provided email address.
   *
   * @param string $email
   *   The email address to send the OTP to.
   */
  protected function sendOtp($email) {
    $otp = rand(100000, 999999);
    $this->state->set('otp_verification_code', $otp);

    $subject = $this->t('Your OTP for Login');
    $message = $this->t('Your OTP for login is: @otp', ['@otp' => $otp]);

    $params = [
      'subject' => $subject,
      'message' => $message,
    ];

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;

    $result = $this->mailManager->mail('custom_login', 'otp_verification', $email, $langcode, $params, NULL, $send);

    if ($result['result'] !== TRUE) {
      $this->messenger->addError($this->t('There was a problem sending the OTP email.'));
    }
  }
}
